<?php
/**
 * Tests for WooCommerce\Square\API::create_options_and_values().
 *
 * @package WooCommerce\Square
 */

namespace WooCommerce\Square\Tests\Unit\API;

use Square\Models\CatalogItem;
use Square\Models\CatalogObject;
use WooCommerce\Square\API;
use WP_UnitTestCase;

require_once __DIR__ . '/Scripted_API.php';

class Create_Options_And_Values_Test extends WP_UnitTestCase {

	/**
	 * Scripted API under test.
	 *
	 * @var Scripted_API
	 */
	private $api;

	public function setUp(): void {
		parent::setUp();

		delete_transient( 'wc_square_options_data' );
		delete_transient( API::OPTIONS_DATA_PARTIAL_TRANSIENT );
		delete_option( 'woocommerce_square_refresh_sync_cycle' );

		$this->api = new Scripted_API();

		// A complete cache keeps the tail of create_options_and_values() off the wire, so each
		// test only exercises the option matching it is about.
		set_transient(
			'wc_square_options_data',
			array(
				'OPT_UNRELATED' => array(
					'name'      => 'Unrelated',
					'values'    => array(),
					'value_ids' => array(),
				),
			),
			DAY_IN_SECONDS
		);
	}

	public function tearDown(): void {
		delete_transient( 'wc_square_options_data' );
		delete_transient( API::OPTIONS_DATA_PARTIAL_TRANSIENT );
		delete_option( 'woocommerce_square_refresh_sync_cycle' );

		parent::tearDown();
	}

	/**
	 * Returns the value names on the object handed to the upsert call.
	 *
	 * @param CatalogObject $catalog_object Upserted option object.
	 * @return array
	 */
	private function upserted_value_names( CatalogObject $catalog_object ) {
		$names = array();

		foreach ( $catalog_object->getItemOptionData()->getValues() as $value ) {
			$names[] = $value->getItemOptionValueData()->getName();
		}

		return $names;
	}

	/**
	 * Square rejects a value whose name differs from an existing one only by case, so a
	 * case sensitive diff would keep asking for a value that can never be created.
	 */
	public function test_existing_values_are_matched_without_regard_to_case() {
		$existing = Scripted_API::make_option(
			'OPT_COLOUR',
			'Colour',
			array(
				'VAL_RED'  => 'Red',
				'VAL_BLUE' => 'Blue',
			)
		);

		$this->api->register_catalog_object( 'OPT_COLOUR', $existing )->set_upsert_object_id( null );

		$this->api->create_options_and_values( 'OPT_COLOUR', 'Colour', array( 'red', 'BLUE', 'Green' ) );

		$this->assertCount( 1, $this->api->upserted_objects );

		$upserted = $this->api->upserted_objects[0];

		$this->assertSame( 'OPT_COLOUR', $upserted->getId(), 'The existing option must be updated, not recreated.' );
		$this->assertSame( array( 'Red', 'Blue', 'Green' ), $this->upserted_value_names( $upserted ) );
	}

	/**
	 * Nothing new to add: the upsert must carry only what Square already has.
	 */
	public function test_values_differing_only_by_case_add_nothing() {
		$existing = Scripted_API::make_option( 'OPT_SIZE', 'Size', array( 'VAL_SMALL' => 'Small' ) );

		$this->api->register_catalog_object( 'OPT_SIZE', $existing )->set_upsert_object_id( null );

		$this->api->create_options_and_values( 'OPT_SIZE', 'Size', array( 'SMALL', 'small' ) );

		$this->assertSame( array( 'Small' ), $this->upserted_value_names( $this->api->upserted_objects[0] ) );
	}

	/**
	 * A cached ID naming an object Square no longer holds used to call setValues() on a null
	 * item option and take the whole sync down with a fatal.
	 */
	public function test_unresolvable_option_id_falls_through_to_the_create_path() {
		$created = Scripted_API::make_option( 'NEW_OPT', 'Colour', array( 'VAL_NEW' => 'Green' ) );

		$this->api->register_catalog_object( 'NEW_OPT', $created )->set_upsert_object_id( 'NEW_OPT' );

		$option = $this->api->create_options_and_values( 'DELETED_OPT', 'Colour', array( 'Green' ) );

		$this->assertCount( 1, $this->api->upserted_objects );
		$this->assertSame( '#Colour', $this->api->upserted_objects[0]->getId(), 'A missing option must be created under a temp ID.' );
		$this->assertSame( array( 'Green' ), $this->upserted_value_names( $this->api->upserted_objects[0] ) );
		$this->assertSame( 'NEW_OPT', $option->getId() );
		$this->assertSame( array( 'DELETED_OPT', 'NEW_OPT' ), $this->api->retrieved_object_ids );
	}

	/**
	 * Same protection, for an ID that resolves to something that is not an item option.
	 */
	public function test_option_id_resolving_to_another_object_type_falls_through_to_the_create_path() {
		$not_an_option = new CatalogObject( 'ITEM', 'SOME_ITEM' );
		$not_an_option->setItemData( new CatalogItem() );

		$created = Scripted_API::make_option( 'NEW_OPT', 'Colour', array( 'VAL_NEW' => 'Green' ) );

		$this->api->register_catalog_object( 'SOME_ITEM', $not_an_option )
			->register_catalog_object( 'NEW_OPT', $created )
			->set_upsert_object_id( 'NEW_OPT' );

		$this->api->create_options_and_values( 'SOME_ITEM', 'Colour', array( 'Green' ) );

		$this->assertSame( '#Colour', $this->api->upserted_objects[0]->getId() );
		$this->assertSame( array( 'Green' ), $this->upserted_value_names( $this->api->upserted_objects[0] ) );
	}

	/**
	 * An ITEM_OPTION carrying no item option data is just as unusable as a missing one.
	 */
	public function test_option_without_item_option_data_falls_through_to_the_create_path() {
		$hollow  = new CatalogObject( 'ITEM_OPTION', 'HOLLOW_OPT' );
		$created = Scripted_API::make_option( 'NEW_OPT', 'Colour', array( 'VAL_NEW' => 'Green' ) );

		$this->api->register_catalog_object( 'HOLLOW_OPT', $hollow )
			->register_catalog_object( 'NEW_OPT', $created )
			->set_upsert_object_id( 'NEW_OPT' );

		$this->api->create_options_and_values( 'HOLLOW_OPT', 'Colour', array( 'Green' ) );

		$this->assertSame( '#Colour', $this->api->upserted_objects[0]->getId() );
	}

	/**
	 * The freshly created option is written back into the options cache under its real ID.
	 */
	public function test_created_option_is_written_back_to_the_options_cache() {
		$created = Scripted_API::make_option(
			'NEW_OPT',
			'Colour',
			array(
				'VAL_GREEN' => 'Green',
				'VAL_TEAL'  => 'Teal',
			)
		);

		$this->api->register_catalog_object( 'NEW_OPT', $created )->set_upsert_object_id( 'NEW_OPT' );

		$this->api->create_options_and_values( false, 'Colour', array( 'Green', 'Teal' ) );

		$cached = get_transient( 'wc_square_options_data' );

		$this->assertArrayHasKey( 'NEW_OPT', $cached );
		$this->assertSame( 'Colour', $cached['NEW_OPT']['name'] );
		$this->assertSame( array( 'Green', 'Teal' ), $cached['NEW_OPT']['values'] );
		$this->assertSame(
			array(
				'VAL_GREEN' => 'Green',
				'VAL_TEAL'  => 'Teal',
			),
			$cached['NEW_OPT']['value_ids']
		);
		$this->assertArrayHasKey( 'OPT_UNRELATED', $cached, 'Writing one option back must not drop the rest of the cache.' );
	}

	/**
	 * The write back must not reach for the catalogue itself. The old shape called
	 * retrieve_options_data() here, which on a large account meant an unlooped read.
	 */
	public function test_write_back_does_not_read_the_catalogue() {
		$created = Scripted_API::make_option( 'NEW_OPT', 'Colour', array( 'VAL_GREEN' => 'Green' ) );

		$this->api->register_catalog_object( 'NEW_OPT', $created )->set_upsert_object_id( 'NEW_OPT' );

		$this->api->create_options_and_values( false, 'Colour', array( 'Green' ) );

		$this->assertSame( array(), $this->api->list_catalog_cursors );
	}

	/**
	 * A partial left behind by an unlooped read has no reader: every caller of the write back runs
	 * after the options step has emptied the cursor, so a walk can never be in flight here. The
	 * write belongs to the finished cache and the leftover is not to be touched.
	 */
	public function test_created_option_ignores_a_leftover_partial_read() {
		set_transient(
			API::OPTIONS_DATA_PARTIAL_TRANSIENT,
			array(
				'OPT_PAGE_ONE' => array(
					'name'      => 'Page one',
					'values'    => array(),
					'value_ids' => array(),
				),
			),
			DAY_IN_SECONDS
		);

		$created = Scripted_API::make_option( 'NEW_OPT', 'Colour', array( 'VAL_GREEN' => 'Green' ) );

		$this->api->register_catalog_object( 'NEW_OPT', $created )->set_upsert_object_id( 'NEW_OPT' );

		$this->api->create_options_and_values( false, 'Colour', array( 'Green' ) );

		$finished = get_transient( 'wc_square_options_data' );

		$this->assertSame( array( 'OPT_UNRELATED', 'NEW_OPT' ), array_keys( $finished ) );
		$this->assertSame( 'Colour', $finished['NEW_OPT']['name'] );
		$this->assertSame(
			array( 'OPT_PAGE_ONE' ),
			array_keys( get_transient( API::OPTIONS_DATA_PARTIAL_TRANSIENT ) ),
			'A leftover partial belongs to an abandoned walk, so it must be left alone.'
		);
	}

	/**
	 * With no cache to extend there is nothing to merge into, and a set built from one option
	 * must not be published as a finished catalogue.
	 */
	public function test_created_option_is_not_published_when_no_cache_exists() {
		delete_transient( 'wc_square_options_data' );

		$created = Scripted_API::make_option( 'NEW_OPT', 'Colour', array( 'VAL_GREEN' => 'Green' ) );

		$this->api->register_catalog_object( 'NEW_OPT', $created )->set_upsert_object_id( 'NEW_OPT' );

		$this->api->create_options_and_values( false, 'Colour', array( 'Green' ) );

		$this->assertFalse( get_transient( 'wc_square_options_data' ) );
		$this->assertFalse( get_transient( API::OPTIONS_DATA_PARTIAL_TRANSIENT ) );
	}
}
