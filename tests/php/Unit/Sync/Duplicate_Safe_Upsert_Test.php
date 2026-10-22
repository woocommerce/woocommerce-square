<?php
/**
 * Tests that a split catalog upsert does not create Square objects twice, and that placeholder IDs are never stored.
 *
 * @package WooCommerce\Square
 */

namespace WooCommerce\Square\Tests\Unit\Sync;

use Square\Models\CatalogCategory;
use Square\Models\CatalogItem;
use Square\Models\CatalogItemVariation;
use Square\Models\CatalogObject;
use Square\Models\SearchCatalogObjectsResponse;
use WP_UnitTestCase;
use WooCommerce\Square\API\Response;
use WooCommerce\Square\Handlers\Category;
use WooCommerce\Square\Handlers\Product;
use WooCommerce\Square\Sync\Manual_Synchronization;
use WooCommerce\Square\Tests\Unit\Sync\Fixtures\Scripted_Api_Plugin;
use WooCommerce\Square\Tests\Unit\Sync\Fixtures\Scripted_Catalog_Api;

require_once __DIR__ . '/Fixtures/Scripted_Catalog_Api.php';
require_once __DIR__ . '/Fixtures/Scripted_Api_Plugin.php';

/**
 * Square keeps the batches of a request before one that fails while processing, without
 * returning their IDs, and a placeholder ID stored before Square confirms it reads as unmapped.
 * Each test covers one way the sync used to send such objects to Square a second time.
 */
class Duplicate_Safe_Upsert_Test extends WP_UnitTestCase {

	/** @var Scripted_Catalog_Api */
	private $api;

	public function setUp(): void {
		parent::setUp();

		if ( ! function_exists( 'wc_square' ) || ! wc_square() || ! function_exists( 'wc_get_product' ) ) {
			$this->markTestSkipped( 'The plugin and WooCommerce are required for the upsert tests.' );
		}

		// The SKU lookups read WooCommerce's product lookup table.
		\WC_Install::create_tables();

		add_filter( 'wc_square_inventory_sync_enabled', '__return_false' );
		delete_option( Category::CATEGORY_MAP_META_KEY );

		$this->api = new Scripted_Catalog_Api();
		Scripted_Api_Plugin::install( $this->api );
	}

	public function tearDown(): void {
		Scripted_Api_Plugin::restore();
		remove_filter( 'wc_square_inventory_sync_enabled', '__return_false' );
		delete_option( Category::CATEGORY_MAP_META_KEY );
		parent::tearDown();
	}

	/**
	 * Builds a sync job backed by a real background job row, so attribute writes persist as in
	 * production and a second instance can resume it.
	 *
	 * @param array $attrs job attributes.
	 * @return Manual_Synchronization
	 */
	private function make_job( array $attrs ) {
		$job = wc_square()->get_background_job_handler()->create_job( array_merge( array( 'action' => 'sync' ), $attrs ) );

		return new Manual_Synchronization( $job );
	}

	/**
	 * Builds a category sync job. Empty categories are not synced, so both are given a product.
	 *
	 * @param int ...$category_ids product category IDs.
	 * @return Manual_Synchronization
	 */
	private function make_categorised_job( ...$category_ids ) {
		$product = $this->make_product();
		$product->set_category_ids( $category_ids );
		$product->save();

		return $this->make_job( array( 'category_ids' => $category_ids ) );
	}

	/**
	 * Calls a protected method on a job.
	 *
	 * @param Manual_Synchronization $job    the job.
	 * @param string                 $method method name.
	 * @param mixed                  ...$args arguments.
	 * @return mixed
	 */
	private function call( $job, $method, ...$args ) {
		$reflection = new \ReflectionMethod( Manual_Synchronization::class, $method );
		$reflection->setAccessible( true );
		return $reflection->invokeArgs( $job, $args );
	}

	/**
	 * @param string $sku optional SKU.
	 * @return \WC_Product_Simple
	 */
	private function make_product( $sku = '' ) {
		$product = new \WC_Product_Simple();
		$product->set_name( 'Duplicate safe ' . wp_rand() );
		$product->set_regular_price( '10' );
		if ( $sku ) {
			$product->set_sku( $sku );
		}
		$product->save();

		return $product;
	}

	/**
	 * @param string $id Square ID.
	 * @return CatalogObject
	 */
	private function item_object( $id ) {
		$object = new CatalogObject( 'ITEM', $id );
		$object->setItemData( new CatalogItem() );

		return $object;
	}

	/** A placeholder is never stored as the product's Square ID. */
	public function test_update_product_does_not_store_a_placeholder_item_id() {
		$product = $this->make_product();

		Product::update_product( $product, $this->item_object( '#item_' . $product->get_id() ) );

		$this->assertSame( '', get_post_meta( $product->get_id(), Product::SQUARE_ID_META_KEY, true ) );
		$this->assertFalse( Product::is_mapped( $product->get_id() ) );
	}

	/** A placeholder left behind by an earlier sync is removed when the product is synced again. */
	public function test_update_product_removes_a_stored_placeholder() {
		$product = $this->make_product();
		update_post_meta( $product->get_id(), Product::SQUARE_ID_META_KEY, '#item_' . $product->get_id() );
		$product = wc_get_product( $product->get_id() );

		Product::update_product( $product, $this->item_object( '#item_' . $product->get_id() ) );

		$this->assertSame( '', get_post_meta( $product->get_id(), Product::SQUARE_ID_META_KEY, true ) );
	}

	/** A real Square ID is still stored exactly as before. */
	public function test_update_product_stores_a_real_item_id() {
		$product = $this->make_product();

		Product::update_product( $product, $this->item_object( 'REALITEMID000000000000000' ) );

		$this->assertSame( 'REALITEMID000000000000000', get_post_meta( $product->get_id(), Product::SQUARE_ID_META_KEY, true ) );
	}

	/** The same rule applies to variations. */
	public function test_update_variation_does_not_store_a_placeholder_variation_id() {
		$product = $this->make_product();
		update_post_meta( $product->get_id(), Product::SQUARE_VARIATION_ID_META_KEY, '#item_variation_' . $product->get_id() );
		$product = wc_get_product( $product->get_id() );

		$variation = new CatalogObject( 'ITEM_VARIATION', '#item_variation_' . $product->get_id() );
		$variation->setItemVariationData( new CatalogItemVariation() );

		Product::update_variation( $product, $variation );

		$this->assertSame( '', get_post_meta( $product->get_id(), Product::SQUARE_VARIATION_ID_META_KEY, true ) );
	}

	/**
	 * A batch fails while processing after Square already created the first product. The product
	 * it created is found by SKU and linked, and only the other one is sent on its own.
	 */
	public function test_a_product_the_failed_batch_created_is_linked_not_sent_again() {
		$created = $this->make_product( 'SQ394-CREATED' );
		$other   = $this->make_product();
		$job     = $this->make_job( array( 'validated_product_ids' => array( $created->get_id(), $other->get_id() ) ) );

		$this->api->upsert_script[] = function () {
			throw new \Exception( '[INVALID_VALUE] Object ID must be blank, start with \'#\', or match an existing object' );
		};

		// The guard before the request finds nothing; after the failure the created item exists.
		$this->api->search_script[] = function () {
			return new Response( new SearchCatalogObjectsResponse() );
		};
		$this->api->search_script[] = function () {
			$variation_data = new CatalogItemVariation();
			$variation_data->setSku( 'SQ394-CREATED' );
			$variation = new CatalogObject( 'ITEM_VARIATION', 'CREATEDVARIATION00000000' );
			$variation->setVersion( 3 );
			$variation->setItemVariationData( $variation_data );

			$item_data = new CatalogItem();
			$item_data->setVariations( array( $variation ) );
			$item = new CatalogObject( 'ITEM', 'CREATEDITEM0000000000000' );
			$item->setVersion( 3 );
			$item->setItemData( $item_data );

			$data = new SearchCatalogObjectsResponse();
			$data->setObjects( array( $variation ) );
			$data->setRelatedObjects( array( $item ) );

			return new Response( $data );
		};

		$this->call( $job, 'upsert_new_products' );

		$this->assertCount( 2, $this->api->upserts, 'only the combined request and one retry for the product Square did not create' );

		$retried_ids = array();
		foreach ( $this->api->upserts[1]['batches'] as $batch ) {
			$retried_ids[] = $batch->getObjects()[0]->getId();
		}
		$this->assertSame( array( '#item_' . $other->get_id() ), $retried_ids );

		$this->assertSame( 'CREATEDITEM0000000000000', get_post_meta( $created->get_id(), Product::SQUARE_ID_META_KEY, true ) );
		$this->assertNotContains( $created->get_id(), (array) $this->call( $job, 'get_attr', 'failed_product_ids', array() ), 'a linked product is not a skip' );
		$this->assertContains( $created->get_id(), (array) $this->call( $job, 'get_attr', 'processed_product_ids', array() ) );
		$this->assertContains( $other->get_id(), (array) $this->call( $job, 'get_attr', 'processed_product_ids', array() ) );
	}

	/**
	 * A category chunk fails while processing after Square already created the first category.
	 * That category is found by name and mapped, and only the other one is sent on its own.
	 */
	public function test_a_category_the_failed_chunk_created_is_mapped_not_sent_again() {
		$alpha = self::factory()->term->create(
			array(
				'taxonomy' => 'product_cat',
				'name'     => 'SQ394 Alpha',
			)
		);
		$bravo = self::factory()->term->create(
			array(
				'taxonomy' => 'product_cat',
				'name'     => 'SQ394 Bravo',
			)
		);
		$job   = $this->make_categorised_job( $alpha, $bravo );

		$this->api->upsert_script[] = function () {
			throw new \Exception( '[INVALID_VALUE] Object ID must be blank, start with \'#\', or match an existing object' );
		};

		$this->api->search_script[] = function () {
			$category_data = new CatalogCategory();
			$category_data->setName( 'sq394 alpha' );
			$category = new CatalogObject( 'CATEGORY', 'EXISTINGALPHA00000000000' );
			$category->setVersion( 5 );
			$category->setCategoryData( $category_data );

			$data = new SearchCatalogObjectsResponse();
			$data->setObjects( array( $category ) );

			return new Response( $data );
		};

		$this->call( $job, 'upsert_categories' );

		$this->assertCount( 2, $this->api->upserts, 'only the chunk and one retry for the category Square did not create' );
		$this->assertCount( 1, $this->api->upserts[1]['batches'] );
		$this->assertSame( '#category_' . $bravo, $this->api->upserts[1]['batches'][0]->getObjects()[0]->getId() );

		$this->assertSame( 'EXISTINGALPHA00000000000', Category::get_square_id( $alpha ) );
		$this->assertSame( Scripted_Catalog_Api::permanent_id( '#category_' . $bravo ), Category::get_square_id( $bravo ) );
	}

	/** A failed lookup falls back to retrying every category on its own, as before. */
	public function test_a_failed_category_lookup_retries_every_category() {
		$alpha = self::factory()->term->create(
			array(
				'taxonomy' => 'product_cat',
				'name'     => 'SQ394 Alpha',
			)
		);
		$bravo = self::factory()->term->create(
			array(
				'taxonomy' => 'product_cat',
				'name'     => 'SQ394 Bravo',
			)
		);
		$job   = $this->make_categorised_job( $alpha, $bravo );

		$this->api->upsert_script[] = function () {
			throw new \Exception( '[INVALID_VALUE] Object ID must be blank, start with \'#\', or match an existing object' );
		};
		$this->api->search_script[] = function () {
			throw new \Exception( '[INTERNAL_SERVER_ERROR] Something went wrong' );
		};

		$this->call( $job, 'upsert_categories' );

		$this->assertCount( 3, $this->api->upserts, 'the chunk and one retry per category' );
		$this->assertSame( Scripted_Catalog_Api::permanent_id( '#category_' . $alpha ), Category::get_square_id( $alpha ) );
		$this->assertSame( Scripted_Catalog_Api::permanent_id( '#category_' . $bravo ), Category::get_square_id( $bravo ) );
	}
}
