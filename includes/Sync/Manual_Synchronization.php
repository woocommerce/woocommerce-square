<?php
/**
 * WooCommerce Square
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is bundled with this package in the file license.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0 or later
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@woocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade WooCommerce Square to newer
 * versions in the future. If you wish to customize WooCommerce Square for your
 * needs please refer to https://docs.woocommerce.com/document/woocommerce-square/
 *
 * @author    WooCommerce
 * @copyright Copyright: (c) 2019, Automattic, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0 or later
 */

namespace WooCommerce\Square\Sync;

use Square\Models\BatchRetrieveInventoryCountsResponse;
use Square\Models\BatchUpsertCatalogObjectsResponse;
use Square\Models\BatchRetrieveCatalogObjectsResponse;
use Square\Models\CatalogObject;
use Square\Models\CatalogObjectType;
use Square\Models\CatalogQuery;
use Square\Models\CatalogQuerySet;
use Square\Models\SearchCatalogObjectsResponse;
use Square\Models\CatalogInfoResponse;
use Square\ApiHelper;
use WooCommerce\Square\Handlers\Category;
use WooCommerce\Square\Handlers\Product;

defined( 'ABSPATH' ) || exit;

/**
 * Class to represent a single synchronization job triggered manually.
 *
 * @since 2.0.0
 */
class Manual_Synchronization extends Stepped_Job {


	/** @var int the limit for how many objects can be upserted in a batch upsert request */
	const BATCH_UPSERT_OBJECT_LIMIT = 600;

	/** @var int the limit for how many inventory changes can be made in a single request */
	const BATCH_CHANGE_INVENTORY_LIMIT = 100;

	/** @var int maximum per product fallback upserts attempted within one step cycle */
	const MAX_ISOLATED_UPSERTS_PER_CYCLE = 5;

	/** @var int maximum rounds of dropping named objects from one inventory chunk before giving up */
	const MAX_INVENTORY_ISOLATION_ROUNDS = 5;

	/**
	 * Square error codes that mean "this object's own data is invalid", and nothing else.
	 *
	 * Skipping an object has to be opted into by a known data error, never assumed: anything not
	 * listed here fails the job instead, because a server error, timeout or permission problem may
	 * have applied the write already and re-sending would duplicate it in Square. Codes come from
	 * \Square\Models\ErrorCode. Filterable via wc_square_isolatable_error_codes.
	 *
	 * Before removing a code from this list because it produced a bad skip: some codes are
	 * deliberately listed here and narrowed by a second gate at the call site, because Square
	 * reuses one code for both an object level problem and a job level one. Removing the code
	 * disables the object level handling and does not fix the job level case.
	 *
	 * - NOT_FOUND is both a stale catalog mapping and a location that does not belong to the
	 *   account. push_inventory_changes_isolated() drops nothing unless Square named an object
	 *   present in the chunk.
	 * - INVALID_VALUE is both bad product data and an item option name collision, which
	 *   API::create_options_and_values() turns into a whole job replay. The staging catch in
	 *   upsert_catalog_objects() snapshots woocommerce_square_refresh_sync_cycle and rethrows
	 *   when it changed.
	 *
	 * @var string[]
	 */
	const ISOLATABLE_ERROR_CODES = array(
		'BAD_REQUEST',
		'MISSING_REQUIRED_PARAMETER',
		'INCORRECT_TYPE',
		'INVALID_VALUE',
		'INVALID_ENUM_VALUE',
		'INVALID_ARRAY_VALUE',
		'INVALID_TIME',
		'VALUE_EMPTY',
		'VALUE_TOO_LONG',
		'VALUE_TOO_SHORT',
		'VALUE_TOO_LOW',
		'VALUE_TOO_HIGH',
		'VALUE_REGEX_MISMATCH',
		'ARRAY_EMPTY',
		'ARRAY_LENGTH_TOO_LONG',
		'ARRAY_LENGTH_TOO_SHORT',
		'UNPROCESSABLE_ENTITY',
		'REQUEST_ENTITY_TOO_LARGE', // the combined request was too big; one object per request is the fix.
		'NOT_FOUND',
		'CONFLICT',
	);

	/** @var int max SKU-based lookups per push_inventory step to avoid rate limits */
	const MAX_SKU_LOOKUPS_PER_PUSH_STEP = 20;

	/** @var int the limit for how many inventory counts can be requested per batch
	 * Square paginates responses in page size of 100.
	 * Consider some items can have more than one object returned with different states. */
	const BATCH_INVENTORY_COUNTS_LIMIT = 125;

	/**
	 * Executes the next step of this job.
	 *
	 * @since 2.0.0
	 *
	 * @return \stdClass the job object
	 */
	public function run() {
		// If the option is set to refresh the sync cycle, clear the next steps and completed steps.
		// The refresh is requested when we do not have Square's Dynamic options data ready.
		$refresh_sync_cycle = get_option( 'woocommerce_square_refresh_sync_cycle', false );
		if ( $refresh_sync_cycle && $refresh_sync_cycle < 3 ) {
			$this->set_attr( 'next_steps', array() );
			$this->set_attr( 'completed_steps', array() );

			update_option( 'woocommerce_square_refresh_sync_cycle', intval( $refresh_sync_cycle ) + 1 );
		} else {
			// Stop retrying after 3 attempts.
			delete_option( 'woocommerce_square_refresh_sync_cycle' );
		}

		return parent::run();
	}

	/**
	 * Validates the products attached to this job.
	 *
	 * @since 2.0.0
	 */
	protected function validate_products() {
		$product_ids             = $this->get_attr( 'product_ids' );
		$unsupported_product_ids = array();

		if ( is_array( $product_ids ) ) {
			$matched_product_ids = wc_get_products(
				array(
					'include' => $product_ids,
					'return'  => 'ids',
					'type'    => wc_square()->get_sync_handler()->supported_product_types(),
					'limit'   => -1,
				)
			);

			$matched_product_ids     = is_array( $matched_product_ids ) ? $matched_product_ids : array();
			$unsupported_product_ids = array_diff( $product_ids, $matched_product_ids );

			foreach ( $unsupported_product_ids as $matched_product_id ) {
				$product = wc_get_product( $matched_product_id );
				$type    = $product->get_type();

				Records::set_record(
					array(
						'type'    => 'alert',
						'message' => sprintf(
							/* translators: %1$s - product edit page URL, %2$s - Product ID, %3$s - Product type. */
							__( 'Product <a href="%1$s">#%2$s</a> is excluded from sync as the product type "%3$s" is unsupported.', 'woocommerce-square' ),
							get_edit_post_link( $matched_product_id ),
							$matched_product_id,
							$type
						),
					)
				);
			}
		}

		$products_query = array(
			'include' => $product_ids,
			'limit'   => -1,
			'status'  => array( 'private', 'publish' ),
			'return'  => 'ids',
		);

		if ( 'delete' === $this->get_attr( 'action' ) ) {

			$products_query['status'] = array( 'trash', 'draft', 'pending', 'private', 'publish' );
		}

		$validated_products = wc_get_products( $products_query );

		$this->set_attr( 'validated_product_ids', $validated_products );

		$this->complete_step( 'validate_products' );
	}


	/**
	 * Updates the catalog API limits.
	 *
	 * @since 2.0.0
	 */
	protected function update_limits() {

		try {

			$catalog_info = wc_square()->get_api()->catalog_info();

			if ( $catalog_info->get_data() instanceof CatalogInfoResponse && $catalog_info->get_data()->getLimits() ) {

				$limits = $catalog_info->get_data()->getLimits();

				$this->set_attr( 'max_objects_to_retrieve', $limits->getBatchRetrieveMaxObjectIds() );
				$this->set_attr( 'max_objects_per_batch', $limits->getBatchUpsertMaxObjectsPerBatch() );
				$this->set_attr( 'max_objects_total', $limits->getBatchUpsertMaxTotalObjects() );
			}
		} catch ( \Exception $exception ) { // no need to handle errors here
		}

		$this->complete_step( 'update_limits' );
	}


	/**
	 * Extracts the category IDs from the list of product IDs in this job, and saves them.
	 *
	 * @since 2.0.0
	 */
	protected function extract_category_ids() {

		$category_ids = $this->get_shared_category_ids( $this->get_attr( 'validated_product_ids' ) );

		$this->set_attr( 'category_ids', $category_ids );

		$this->complete_step( 'extract_category_ids' );
	}


	/**
	 * Refreshes mappings for categories with known Square IDs.
	 *
	 * @since 2.0.0
	 *
	 * @throws \Exception
	 */
	protected function refresh_category_mappings() {

		$map                   = Category::get_map();
		$category_ids          = $this->get_attr( 'refresh_mappings_category_ids', $this->get_attr( 'category_ids' ) );
		$mapped_categories     = array();
		$unmapped_categories   = $this->get_attr( 'unmapped_categories', array() );
		$unmapped_category_ids = array();

		if ( empty( $category_ids ) ) {
			$this->complete_step( 'refresh_category_mappings' );
			return;
		}

		if ( count( $category_ids ) > $this->get_max_objects_to_retrieve() ) {

			$category_ids_batch = array_slice( $category_ids, 0, $this->get_max_objects_to_retrieve() );

			$this->set_attr( 'refresh_mappings_category_ids', array_diff( $category_ids, $category_ids_batch ) );

			$category_ids = $category_ids_batch;

		} else {

			$this->set_attr( 'refresh_mappings_category_ids', array() );
		}

		foreach ( $category_ids as $category_id ) {

			if ( isset( $map[ $category_id ] ) ) {

				$mapped_categories[ $category_id ] = $map[ $category_id ];

			} else {

				$unmapped_category_ids[] = $category_id;
			}
		}

		if ( ! empty( $mapped_categories ) ) {

			$square_ids = array_values(
				array_filter(
					array_map(
						function ( $mapped_category ) {
							return isset( $mapped_category['square_id'] ) ? $mapped_category['square_id'] : null;
						},
						$mapped_categories
					)
				)
			);

			if ( ! empty( $square_ids ) ) {

				$response = wc_square()->get_api()->batch_retrieve_catalog_objects( $square_ids );

				// Key by Square ID for lookup; handling duplicate mapping issue by storing all WC term IDs for each Square ID.
				$mapped_category_audit = array();

				foreach ( $mapped_categories as $mapped_category_id => $mapped_category ) {
					$mapped_category_audit[ $mapped_category['square_id'] ][] = $mapped_category_id;
				}

				if ( ! $response->get_data() instanceof BatchRetrieveCatalogObjectsResponse ) {
					throw new \Exception( 'Could not fetch category data from Square. Response data is missing' );
				}

				// handle response
				if ( is_array( $response->get_data()->getObjects() ) ) {
					foreach ( $response->get_data()->getObjects() as $category ) {

						// don't check for the name, it will get overwritten by the Woo value anyway
						if ( isset( $mapped_category_audit[ $category->getId() ] ) ) {

							$category_ids = $mapped_category_audit[ $category->getId() ];
							foreach ( (array) $category_ids as $category_id ) {
								$map[ $category_id ]['square_version'] = $category->getVersion();
							}
							unset( $mapped_category_audit[ $category->getId() ] );
						}
					}
				}

				// any remaining categories were not found in Square and should have their local mapping data removed
				if ( ! empty( $mapped_category_audit ) ) {

					$outdated_category_ids = array_merge( ...array_values( $mapped_category_audit ) );

					foreach ( $outdated_category_ids as $outdated_category_id ) {

						unset( $map[ $outdated_category_id ], $mapped_categories[ $outdated_category_id ] );

						$unmapped_category_ids[] = $outdated_category_id;
					}

					$unmapped_category_ids = array_unique( $unmapped_category_ids );
				}
			}
			// update unmapped list
		}

		if ( ! empty( $unmapped_category_ids ) ) {

			$unmapped_category_terms = get_terms(
				array(
					'taxonomy' => 'product_cat',
					'include'  => $unmapped_category_ids,
				)
			);

			// make the 'name' attribute the array key, for more efficient searching later.
			foreach ( $unmapped_category_terms as $unmapped_category_term ) {
				$unmapped_categories[ strtolower( wp_specialchars_decode( $unmapped_category_term->name ) ) ] = $unmapped_category_term;
			}
		}

		// save category lists
		$this->set_attr( 'mapped_categories', $mapped_categories );
		$this->set_attr( 'unmapped_categories', $unmapped_categories );

		Category::update_map( $map );
	}


	/**
	 * Checks the Square API for any unmapped categories we may have.
	 *
	 * @since 2.0.0
	 *
	 * @throws \Exception
	 */
	protected function query_unmapped_categories() {

		$unmapped_categories = $this->get_attr( 'unmapped_categories', array() );
		$mapped_categories   = $this->get_attr( 'mapped_categories', array() );

		if ( empty( $unmapped_categories ) ) {

			$this->complete_step( 'query_unmapped_categories' );

		} else {

			$response = wc_square()->get_api()->search_catalog_objects(
				array(
					'object_types' => array( 'CATEGORY' ),
					'cursor'       => $this->get_attr( 'unmapped_categories_cursor' ),
				)
			);

			$category_map = Category::get_map();
			$categories   = $response->get_data() instanceof SearchCatalogObjectsResponse ? $response->get_data()->getObjects() : null;

			if ( is_array( $categories ) ) {

				foreach ( $categories as $category_object ) {

					$unmapped_category_key = strtolower( $category_object->getCategoryData()->getName() );

					if ( isset( $unmapped_categories[ $unmapped_category_key ] ) ) {

						$category_id = $unmapped_categories[ $unmapped_category_key ]['term_id'];

						$category_map[ $category_id ] = array(
							'square_id'      => $category_object->getId(),
							'square_version' => $category_object->getVersion(),
						);

						$mapped_categories[] = $category_id;
						unset( $unmapped_categories[ $unmapped_category_key ] );
					}
				}
			}

			Category::update_map( $category_map );
			$this->set_attr( 'mapped_categories', $mapped_categories );
			$this->set_attr( 'unmapped_categories', $unmapped_categories );

			$cursor = $response->get_data() instanceof SearchCatalogObjectsResponse ? $response->get_data()->getCursor() : null;
			$this->set_attr( 'unmapped_categories_cursor', $cursor );

			if ( empty( $cursor ) ) {

				$this->complete_step( 'query_unmapped_categories' );
			}
		}
	}


	/**
	 * Upserts the categories for the selected products to Square.
	 *
	 * @since 2.0.0
	 *
	 * @throws \Exception
	 */
	protected function upsert_categories() {

		$category_ids = $this->get_attr( 'category_ids' );
		$categories   = get_terms(
			array(
				'taxonomy' => 'product_cat',
				'include'  => $category_ids,
			)
		);

		$batches     = array();
		$reverse_map = array();

		// For now, keep it to one category per batch. Since we can still send 1000 batches per request, it's efficient,
		// and insulates errors per category rather than a single category error breaking the entire batch it is in.
		// TODO: Performance - Consider sending larger-sized batches to reduce total requests for shops with thousands of categories.
		// This will require the ability to handle a failed batch, pulling out the error-causing category, and retrying the batch.
		foreach ( $categories as $category ) {

			$category_id    = $category->term_id;
			$square_id      = Category::get_square_id( $category_id );
			$square_version = Category::get_square_version( $category_id );

			$reverse_map[ $square_id ] = $category_id;

			$catalog_category = new \Square\Models\CatalogCategory();
			$catalog_category->setName( wp_specialchars_decode( $category->name ) );

			$catalog_object = new \Square\Models\CatalogObject( 'CATEGORY', $square_id );
			$catalog_object->setCategoryData( $catalog_category );

			if ( 0 < $square_version ) {
				$catalog_object->setVersion( $square_version );
			}

			$batches[] = new \Square\Models\CatalogObjectBatch( array( $catalog_object ) );
		}

		foreach ( array_chunk( $batches, $this->get_max_objects_per_upsert() ) as $chunk ) {

			try {
				$this->upsert_category_batches( $chunk, $reverse_map );
				continue;
			} catch ( \Exception $chunk_exception ) {

				if ( 'isolatable' !== $this->classify_sync_error( $chunk_exception ) ) {
					throw $chunk_exception;
				}

				// Each batch holds exactly one category, so a failing chunk can be isolated by
				// retrying every batch on its own: the broken category is recorded and skipped,
				// the rest sync normally (SQUARE-143 / SQUARE-31).
				wc_square()->log( 'Category chunk upsert failed (' . $chunk_exception->getMessage() . '); retrying each category individually.' );
			}

			foreach ( $chunk as $single_batch ) {
				try {
					$this->upsert_category_batches( array( $single_batch ), $reverse_map );
				} catch ( \Exception $category_exception ) {

					// Only a data level error is the category's own fault; auth fails the job and
					// a rate limit bubbles to the existing job level retry and backoff.
					if ( 'isolatable' !== $this->classify_sync_error( $category_exception ) ) {
						throw $category_exception;
					}

					$term_name = __( 'unknown category', 'woocommerce-square' );
					$objects   = $single_batch->getObjects();
					if ( is_array( $objects ) && isset( $objects[0] ) && $objects[0]->getCategoryData() ) {
						$term_name = $objects[0]->getCategoryData()->getName();
					}

					Records::set_record(
						array(
							'type'    => 'alert',
							'message' => sprintf(
								/* translators: Placeholders: %1$s - category name, %2$s - failure reason */
								esc_html__( 'Category "%1$s" was skipped so the sync could continue. Reason: %2$s', 'woocommerce-square' ),
								esc_html( $term_name ),
								esc_html( $category_exception->getMessage() )
							),
						)
					);
					wc_square()->log( 'Skipped category "' . $term_name . '": ' . $category_exception->getMessage() );

					// Deferred like record_skipped_product(); complete_step() below persists it.
					$this->set_attr( 'sync_error_count', (int) $this->get_attr( 'sync_error_count', 0 ) + 1, false );
				}
			}
		}

		$this->complete_step( 'upsert_categories' );
	}

	/**
	 * Sends a set of category batches to Square and applies the returned mappings.
	 *
	 * @since x.x.x
	 *
	 * @param \Square\Models\CatalogObjectBatch[] $category_batches batches to send (one category each)
	 * @param array $reverse_map square id keyed map to local term ids, updated in place
	 * @throws \Exception on API failure or invalid response
	 */
	protected function upsert_category_batches( array $category_batches, array &$reverse_map ) {

		$idempotency_key = wc_square()->get_idempotency_key( md5( serialize( $category_batches ) . $this->get_attr( 'id' ) ) . '_upsert_categories' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$result          = wc_square()->get_api()->batch_upsert_catalog_objects( $idempotency_key, $category_batches );

		if ( ! $result->get_data() instanceof BatchUpsertCatalogObjectsResponse ) {
			throw new \Exception( 'Response data is invalid' );
		}

		$id_mappings = $result->get_data()->getIdMappings(); // new entries to Square will return in the ID Mapping.

		if ( ! empty( $id_mappings ) ) {
			foreach ( $id_mappings as $id_mapping ) {
				$client_object_id = $id_mapping->getClientObjectId();
				$remote_object_id = $id_mapping->getObjectId();

				if ( isset( $reverse_map[ $client_object_id ] ) ) {
					$reverse_map[ $remote_object_id ] = $reverse_map[ $client_object_id ];
					unset( $reverse_map[ $client_object_id ] );
				}
			}
		}

		// null when the request produced no objects; never fatal on it
		foreach ( is_array( $result->get_data()->getObjects() ) ? $result->get_data()->getObjects() : array() as $upserted_category ) {
			$id      = $upserted_category->getId();
			$version = $upserted_category->getVersion();

			if ( isset( $reverse_map[ $id ] ) ) {
				Category::update_mapping( $reverse_map[ $id ], $id, $version );
				unset( $reverse_map[ $id ] );
			}
		}
	}

	/**
	 * Updates a set of products that already have a Square ID set and are found in the catalog.
	 *
	 * @since 2.0.0
	 *
	 * @throws \Exception
	 */
	protected function update_matched_products() {

		$product_ids           = $this->get_attr( 'matched_product_ids', $this->get_attr( 'validated_product_ids', array() ) );
		$processed_product_ids = $this->get_attr( 'processed_product_ids', array() );

		// remove IDs that have already been processed
		$product_ids = array_diff( $product_ids, $processed_product_ids );

		if ( empty( $product_ids ) ) {

			$this->complete_step( 'update_matched_products' );
			return;
		}

		if ( count( $product_ids ) > $this->get_max_objects_to_retrieve() ) {

			$product_ids_batch = array_slice( $product_ids, 0, $this->get_max_objects_to_retrieve() );

			$this->set_attr( 'matched_product_ids', array_diff( $product_ids, $product_ids_batch ) );

			$product_ids = $product_ids_batch;

		} else {

			$this->set_attr( 'matched_product_ids', array() );
		}

		$products_map = Product::get_square_meta( $product_ids, 'square_item_id' );
		$square_ids   = array_keys( $products_map );

		if ( empty( $square_ids ) ) {
			return;
		}

		$response = wc_square()->get_api()->batch_retrieve_catalog_objects( $square_ids );

		if ( ! $response->get_data() instanceof BatchRetrieveCatalogObjectsResponse ) {
			throw new \Exception( 'Response data is missing' );
		}

		$catalog_objects = array();

		if ( $response->get_data()->getObjects() ) {

			foreach ( $response->get_data()->getObjects() as $catalog_object ) {

				if ( ! empty( $products_map[ $catalog_object->getId() ]['product_id'] ) ) {

					$product_id = $products_map[ $catalog_object->getId() ]['product_id'];

					$catalog_objects[ $product_id ] = $catalog_object;
				}
			}
		}

		if ( ! empty( $catalog_objects ) ) {

			$result = $this->upsert_catalog_objects( $catalog_objects );

			$this->set_attr( 'processed_product_ids', array_merge( $result['processed'], $processed_product_ids ) );

			// any products that were staged but not processed, push to the matched array to try next time
			$matched_product_ids = $this->get_attr( 'matched_product_ids', array() );
			$this->set_attr( 'matched_product_ids', array_merge( $result['unprocessed'], $matched_product_ids ) );
		}
	}


	/**
	 * Searches the full Square catalog to find matches and updates them.
	 *
	 * @since 2.0.0
	 *
	 * @throws \Exception
	 */
	protected function search_matched_products() {

		$product_ids           = $this->get_attr( 'search_product_ids', $this->get_attr( 'validated_product_ids', array() ) );
		$processed_product_ids = $this->get_attr( 'processed_product_ids', array() );
		$in_progress           = $this->get_attr(
			'in_progress_search_matched_products',
			array(
				'unprocessed_search_response' => null,
				'processed_remote_object_ids' => array(),
				'catalog_objects_to_update'   => array(),
				'upserting'                   => false,
			)
		);

		// remove IDs that have already been processed
		$product_ids = array_diff( $product_ids, $processed_product_ids );

		if ( empty( $product_ids ) ) {

			$this->complete_step( 'search_matched_products' );
			return;
		}

		$products_map = Product::get_square_meta( $product_ids, 'square_item_id' );

		$search_response = null;
		if ( ! empty( $in_progress['unprocessed_search_response'] ) ) {
			$search_response = ApiHelper::getJsonHelper()->mapClass( json_decode( $in_progress['unprocessed_search_response'] ), 'Square\\Models\\SearchCatalogObjectsResponse' );
		}

		if ( ! $search_response || ! $search_response instanceof SearchCatalogObjectsResponse ) {
			$response = wc_square()->get_api()->search_catalog_objects(
				array(
					'cursor'       => $this->get_attr( 'search_products_cursor' ),
					'object_types' => array( 'ITEM' ),
					'limit'        => $this->get_max_objects_to_retrieve(),
				)
			);

			$search_response = $response->get_data();

			$in_progress['unprocessed_search_response'] = wp_json_encode( $search_response, JSON_PRETTY_PRINT );
			$this->set_attr( 'in_progress_search_matched_products', $in_progress );
		}

		if ( ! $search_response instanceof SearchCatalogObjectsResponse ) {
			throw new \Exception( 'Response data is missing' );
		}

		$catalog_objects           = $search_response->getObjects() ? $search_response->getObjects() : array();
		$cursor                    = $search_response->getCursor();
		$catalog_objects_to_update = $in_progress['catalog_objects_to_update'];

		if ( true !== $in_progress['upserting'] ) {

			wc_square()->log( 'Searching through ' . count( $catalog_objects ) . ' catalog objects' );

			foreach ( $catalog_objects as $catalog_object ) {

				$remote_object_id = $catalog_object->getId();

				if ( in_array( $remote_object_id, $in_progress['processed_remote_object_ids'], true ) ) {
					continue;
				}

				if ( isset( $products_map[ $remote_object_id ]['product_id'] ) ) {

					$product_id = $products_map[ $remote_object_id ]['product_id'];

					$product = wc_get_product( $product_id );

					// update the product's meta
					if ( $product ) {
						Product\Woo_SOR::update_product( $product, $catalog_object );
					}

					foreach ( $catalog_object->getItemData()->getVariations() as $catalog_variation ) {

						$variation_product_id = Product::get_product_id_by_square_variation_id( $catalog_variation->getId() );

						if ( $variation_product_id ) {

							$variation = wc_get_product( $variation_product_id );

							if ( $variation ) {
								Product\Woo_SOR::update_variation( $variation, $catalog_variation );
							}
						}
					}

					$catalog_objects_to_update[ $product_id ] = $catalog_object;

				} else {

					// no variations? no sku
					if ( ! is_array( $catalog_object->getItemData()->getVariations() ) ) {
						continue;
					}

					$product_id     = 0;
					$matched_object = null;

					foreach ( $catalog_object->getItemData()->getVariations() as $catalog_variation ) {

						$sku = $catalog_variation->getItemVariationData()->getSku();

						if ( empty( $sku ) ) {
							continue;
						}

						$product_id = wc_get_product_id_by_sku( $sku );

						$product = wc_get_product( $product_id );

						if ( ! $product ) {
							continue;
						}

						$parent_product = wc_get_product( $product->get_parent_id() );

						if ( $product->get_parent_id() && $parent_product ) {
							$product = $parent_product;
						}

						if ( ! in_array( $product->get_id(), $product_ids, false ) ) { // phpcs:ignore WordPress.PHP.StrictInArray.FoundNonStrictFalse
							continue;
						}

						$product_id     = $product->get_id();
						$matched_object = $catalog_object;

						break;
					}

					if ( $product_id && $matched_object ) {
						$catalog_objects_to_update[ $product_id ] = $matched_object;
					}
				}

				$in_progress['processed_remote_object_ids'][] = $remote_object_id;
				$in_progress['catalog_objects_to_update']     = $catalog_objects_to_update;
			}
		}

		$in_progress['upserting'] = true;

		$catalog_processed = ! $cursor;

		$remaining_product_ids = array_diff( $product_ids, array_keys( $catalog_objects_to_update ) );

		if ( ! empty( $catalog_objects_to_update ) ) {

			$result = $this->upsert_catalog_objects( $catalog_objects_to_update );

			$processed_product_ids = array_merge( $result['processed'], $processed_product_ids );
			$this->set_attr( 'processed_product_ids', $processed_product_ids );

			if ( ! empty( $result['unprocessed'] ) ) {

				$catalog_processed                        = false;
				$remaining_product_ids                    = array_merge( $result['unprocessed'], $remaining_product_ids );
				$in_progress['catalog_objects_to_update'] = array_diff_key( $catalog_objects_to_update, array_flip( $processed_product_ids ) );

			} else {

				$in_progress = null;
			}

			$this->set_attr( 'in_progress_search_matched_products', $in_progress );
		} else {
			// No products to update, clear the in progress data.
			$this->set_attr( 'in_progress_search_matched_products', null );
		}

		if ( ! $catalog_processed && ! empty( $remaining_product_ids ) ) {

			$this->set_attr( 'search_products_cursor', $cursor );
			$this->set_attr( 'search_product_ids', $remaining_product_ids );

		} else {

			Product::clear_square_meta( $remaining_product_ids );
			$this->complete_step( 'search_matched_products' );
		}
	}

	/**
	 * Links products to existing Square items by SKUs.
	 *
	 * @since 5.3.3
	 *
	 * @param array $product_ids The IDs of the WooCommerce products to link.
	 * @return array The IDs of the linked products.
	 */
	protected function link_products_to_existing_square_items( $product_ids ) {
		$linked_product_ids       = array();
		$product_skus             = array();
		$existing_catalog_objects = array();

		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				continue;
			}

			if ( ! empty( $product->get_sku() ) ) {
				$product_skus[] = $product->get_sku();
			}

			if ( $product->is_type( 'variable' ) && $product->has_child() ) {
				foreach ( $product->get_children() as $variation_id ) {
					$variation = wc_get_product( $variation_id );

					if ( $variation instanceof \WC_Product && ! empty( $variation->get_sku() ) ) {
						$product_skus[] = $variation->get_sku();
					}
				}
			}
		}

		// Remove duplicates if any.
		$product_skus = array_unique( $product_skus );

		if ( empty( $product_skus ) ) {
			return $linked_product_ids;
		}

		// Set query has limit of 250 items.
		$sku_to_object_id_map = array();
		foreach ( array_chunk( $product_skus, 250 ) as $batched_skus ) {
			foreach ( $this->find_existing_square_items_by_skus( $batched_skus ) as $existing_catalog_object ) {
				if ( ! $existing_catalog_object instanceof CatalogObject ) {
					continue;
				}
				$remote_catalog_object_id = $existing_catalog_object->getId();
				if ( empty( $remote_catalog_object_id ) ) {
					continue;
				}
				$existing_catalog_objects[ $remote_catalog_object_id ] = $existing_catalog_object;

				// Check for Multiple Square items with the same SKU.
				$item_data = $existing_catalog_object->getItemData();
				if ( $item_data && is_array( $item_data->getVariations() ) ) {
					foreach ( $item_data->getVariations() as $variation ) {
						$variation_data = $variation->getItemVariationData();
						if ( ! $variation_data || empty( $variation_data->getSku() ) ) {
							continue;
						}

						$sku = $variation_data->getSku();
						if ( isset( $sku_to_object_id_map[ $sku ] ) && ! empty( $sku_to_object_id_map[ $sku ] ) ) {
							unset( $existing_catalog_objects[ $sku_to_object_id_map[ $sku ] ] );
							unset( $existing_catalog_objects[ $remote_catalog_object_id ] );
							Records::set_record(
								array(
									'type'    => 'alert',
									'message' => sprintf(
										/* translators: %1$s - SKU, %2$s - Square Item 1, %3$s - Square Item 2. */
										__( 'Multiple Square items share the same SKU: %1$s. Square Item IDs: %2$s, %3$s', 'woocommerce-square' ),
										$sku,
										$sku_to_object_id_map[ $sku ],
										$remote_catalog_object_id
									),
								)
							);
							continue;
						}

						$sku_to_object_id_map[ $sku ] = $remote_catalog_object_id;
					}
				}
			}
		}

		// If no existing catalog objects found, return.
		if ( empty( $existing_catalog_objects ) ) {
			return $linked_product_ids;
		}

		// Link products to existing catalog objects.
		foreach ( $existing_catalog_objects as $remote_catalog_item ) {
			$item_data = $remote_catalog_item->getItemData();
			if ( $item_data && is_array( $item_data->getVariations() ) ) {
				$product_id = null;
				foreach ( $remote_catalog_item->getItemData()->getVariations() as $catalog_item_variation ) {
					$variation_data = $catalog_item_variation->getItemVariationData();
					if ( ! $variation_data || empty( $variation_data->getSku() ) ) {
						continue;
					}

					$local_product_id = wc_get_product_id_by_sku( $variation_data->getSku() );
					if ( ! $local_product_id ) {
						continue;
					}

					$local_product = wc_get_product( $local_product_id );
					if ( ! $local_product ) {
						continue;
					}

					Product::update_square_meta(
						$local_product,
						array(
							'item_variation_id'      => $catalog_item_variation->getId(),
							'item_variation_version' => $catalog_item_variation->getVersion(),
						)
					);

					$product_id = $local_product->is_type( 'variation' ) ? $local_product->get_parent_id() : $local_product->get_id();
				}

				// Update the parent product if it exists.
				if ( $product_id ) {
					$product = wc_get_product( $product_id );
					if ( $product ) {
						Product::update_square_meta(
							$product,
							array(
								'item_id'       => $remote_catalog_item->getId(),
								'item_version'  => $remote_catalog_item->getVersion(),
								'item_image_id' => Product::get_catalog_item_thumbnail_id( $remote_catalog_item ),
							)
						);
					}

					$linked_product_ids[] = $product_id;
				}
			}
		}

		return $linked_product_ids;
	}

	/**
	 * Finds existing Square items by SKUs.
	 *
	 * @since 5.3.3
	 *
	 * @param array $product_skus The SKUs of the WooCommerce products to find.
	 * @return CatalogObject[] The existing Square ITEM catalog objects.
	 */
	protected function find_existing_square_items_by_skus( $product_skus ) {
		$existing_items = array();

		$query     = new CatalogQuery();
		$set_query = new CatalogQuerySet( 'sku', $product_skus );
		$query->setSetQuery( $set_query );

		try {
			$response = wc_square()->get_api()->search_catalog_objects(
				array(
					'object_types'            => array( 'ITEM_VARIATION' ),
					'query'                   => $query,
					'include_related_objects' => true,
				)
			);

			$search_response = $response->get_data();

			if ( ! $search_response instanceof SearchCatalogObjectsResponse ) {
				return array();
			}

			$related_objects = $search_response->getRelatedObjects();
			if ( empty( $related_objects ) || ! is_array( $related_objects ) ) {
				// No related objects found.
				return array();
			}

			foreach ( $related_objects as $object ) {
				if (
					$object instanceof CatalogObject
					&& $object->getType() === CatalogObjectType::ITEM
					&& ! $object->getIsDeleted()
				) {
					$existing_items[] = $object;
				}
			}

			return $existing_items;
		} catch ( \Exception $exception ) {
			wc_square()->log(
				sprintf(
					'Failed to find existing Square items by SKUs: %s',
					$exception->getMessage()
				)
			);
		}

		// Return empty array to indicate no existing items found, even if an exception was thrown, log failure and continue.
		return array();
	}

	/**
	 * @throws \Exception
	 */
	protected function upsert_new_products() {
		$product_ids                = $this->get_attr( 'upsert_new_product_ids', $this->get_attr( 'validated_product_ids', array() ) );
		$processed_product_ids      = $this->get_attr( 'processed_product_ids', array() );
		$inventory_push_product_ids = $this->get_attr( 'inventory_push_product_ids', array() );

		// remove IDs that have already been processed
		$product_ids = array_diff( $product_ids, $processed_product_ids );
		if ( empty( $product_ids ) ) {
			$this->complete_step( 'upsert_new_products' );
			return;
		}

		// Use the previous idempotency key and product list to retry the upsert request, if previous request failed with rate limit error.
		$retry_idempotency_key    = $this->get_attr( 'upsert_retry_idempotency_key', null );
		$upsert_retry_product_ids = $this->get_attr( 'upsert_retry_product_ids', array() );
		if ( ! empty( $retry_idempotency_key ) && ! empty( $upsert_retry_product_ids ) ) {
			$product_ids = $upsert_retry_product_ids;
		} elseif ( count( $product_ids ) > $this->get_max_objects_per_upsert() ) {
			$product_ids_batch = array_slice( $product_ids, 0, $this->get_max_objects_per_upsert() );
			$this->set_attr( 'upsert_new_product_ids', array_diff( $product_ids, $product_ids_batch ) );
			$product_ids = $product_ids_batch;
		} else {
			$this->set_attr( 'upsert_new_product_ids', array() );
		}

		// SKU guard: check for existing Square items before creating new ones.
		// Link products to existing Square items by SKUs, to prevent creating duplicate items.
		$linked_product_ids = $this->link_products_to_existing_square_items( $product_ids );

		// Remove linked products from the upsert batch — they already exist in Square.
		if ( ! empty( $linked_product_ids ) ) {
			// Log the number of products linked to existing Square items.
			wc_square()->log( '[SKU Guard] Linked ' . count( $linked_product_ids ) . ' products to existing Square items - skipping upsert for these products.' );

			// Remove linked products from the upsert batch.
			$product_ids = array_values( array_diff( $product_ids, $linked_product_ids ) );

			// Linked products count as processed. Push their inventory inline when inventory sync
			// is enabled - Square IDs are fresh in postmeta at this point. Only failed IDs are
			// queued for the deferred step.
			$processed_product_ids = array_merge( $linked_product_ids, $processed_product_ids );
			if ( wc_square()->get_settings_handler()->is_inventory_sync_enabled() ) {
				$failed_linked_ids          = $this->push_inventory_for_products( $linked_product_ids );
				$inventory_push_product_ids = array_merge( $failed_linked_ids, $inventory_push_product_ids );
			} else {
				$inventory_push_product_ids = array_merge( $linked_product_ids, $inventory_push_product_ids );
			}
			$this->set_attr( 'processed_product_ids', $processed_product_ids );
			$this->set_attr( 'inventory_push_product_ids', $inventory_push_product_ids );

			// Clear retry idempotency key if the batch changed — the key is bound to the
			// original request body and would cause Square to reject a modified batch.
			if ( ! empty( $retry_idempotency_key ) ) {
				$this->set_attr( 'upsert_retry_idempotency_key', null );
				$this->set_attr( 'upsert_retry_product_ids', array() );
			}

			// If all products were linked to existing items, no upsert needed, return early.
			if ( empty( $product_ids ) ) {
				$upsert_new_product_ids = $this->get_attr( 'upsert_new_product_ids', array() );
				if ( empty( $upsert_new_product_ids ) ) {
					$this->complete_step( 'upsert_new_products' );
				}
				return;
			}
		}

		$catalog_objects = array();
		foreach ( $product_ids as $product_id ) {
			$catalog_item = new \Square\Models\CatalogItem();
			// Always use a Square temp ID (prefixed with '#') regardless of any stored Square ID.
			// Stored IDs may be stale (e.g. catalog wiped on the Square side) and would cause Square
			// to reject the entire batch with INVALID_VALUE. Square maps temp IDs to new permanent IDs
			// in the response, which are written back to WC postmeta by upsert_catalog_objects().
			$catalog_object = new CatalogObject( 'ITEM', '#item_' . $product_id );
			$catalog_object->setItemData( $catalog_item );
			$catalog_objects[ $product_id ] = $catalog_object;
		}

		$result = $this->upsert_catalog_objects( $catalog_objects, true );

		// Push inventory inline immediately after each upsert batch when inventory sync is enabled.
		// This ensures products don't sit in Square with zero inventory if the sync fails before
		// the deferred push_inventory step runs. Only IDs whose inline push failed are queued for
		// the deferred step - successful pushes are not re-queued to avoid double-counting.
		// Skipped products are consumed but were not upserted, so they are excluded here: pushing
		// inventory for one would write stock against a stale mapping for a product whose catalog
		// data Square just rejected, and would risk a second alert for the same product.
		$upserted_product_ids = array_values( array_diff( $result['processed'], $result['skipped'] ?? array() ) );

		if ( wc_square()->get_settings_handler()->is_inventory_sync_enabled() && ! empty( $upserted_product_ids ) ) {
			$failed_inventory_ids       = $this->push_inventory_for_products( $upserted_product_ids );
			$inventory_push_product_ids = array_merge( $failed_inventory_ids, $inventory_push_product_ids );
		} else {
			$inventory_push_product_ids = array_merge( $upserted_product_ids, $inventory_push_product_ids );
		}
		$this->set_attr( 'inventory_push_product_ids', $inventory_push_product_ids );

		// update the processed list
		$processed_product_ids = array_merge( $result['processed'], $processed_product_ids );
		$this->set_attr( 'processed_product_ids', $processed_product_ids );

		$upsert_new_product_ids = $this->get_attr( 'upsert_new_product_ids', array() );
		$updated_product_ids    = array_merge( $result['unprocessed'], $upsert_new_product_ids );
		$this->set_attr( 'upsert_new_product_ids', $updated_product_ids );

		// if all products were processed, move on.
		if ( empty( $updated_product_ids ) ) {
			$all_product_ids = $this->get_attr( 'validated_product_ids', array() );
			// at this point, log a failure for any products that weren't processed.
			foreach ( array_diff( $all_product_ids, $processed_product_ids ) as $product_id ) {
				Records::set_record(
					array(
						'type'       => 'info',
						'product_id' => $product_id,
						'message'    => sprintf(
							/* translators: Placeholder: %s - product ID */
							esc_html__( 'Product #%s could not be updated.', 'woocommerce-square' ),
							'<a href="' . esc_url( get_edit_post_link( $product_id ) ) . '">' . $product_id . '</a>'
						),
					)
				);
			}

			$this->complete_step( 'upsert_new_products' );
		}
	}

	/**
	 * Upserts a list of catalog objects and updates their cooresponding products.
	 *
	 * @since 2.0.0
	 *
	 * @param array $objects      list of catalog objects to update, as $product_id => CatalogItem
	 * @param bool  $new_products Whether these are new products or not.
	 * @return array
	 * @throws \Exception
	 */
	protected function upsert_catalog_objects( array $objects, $new_products = false ) {
		wc_square()->log( 'Upserting ' . count( $objects ) . ' catalog objects' );

		$is_delete_action          = 'delete' === $this->get_attr( 'action' );
		$product_ids               = array_keys( $objects );
		$original_square_image_ids = array();
		$staged_product_ids        = array();
		$successful_product_ids    = array();
		$total_object_count        = 0;
		$batches                   = array();
		$result                    = array(
			'processed'   => array(),
			'unprocessed' => $product_ids,
			'skipped'     => array(),
		);
		$isolated_fail_ids         = array();
		$partial_error_detail      = '';

		$in_progress = $this->get_attr(
			'in_progress_upsert_catalog_objects',
			array(
				'staged_product_ids'                => array(),
				'unprocessed_upsert_response'       => null,
				'mapped_client_item_ids'            => array(),
				'processed_remote_catalog_item_ids' => array(),
				'isolated_fail_ids'                 => array(),
				'partial_error_detail'              => '',
			)
		);

		$upsert_response = null;
		if ( ! empty( $in_progress['unprocessed_upsert_response'] ) ) {
			$staged_product_ids = $in_progress['staged_product_ids'] ?? array();
			$upsert_response    = ApiHelper::getJsonHelper()->mapClass( json_decode( $in_progress['unprocessed_upsert_response'] ), 'Square\\Models\\BatchUpsertCatalogObjectsResponse' );

			// Restored alongside staged_product_ids: without them a cycle resumed after the
			// response was persisted would no longer recognise the already recorded skips and
			// would raise a second alert and a second error count for every one of them.
			$isolated_fail_ids    = (array) ( $in_progress['isolated_fail_ids'] ?? array() );
			$partial_error_detail = (string) ( $in_progress['partial_error_detail'] ?? '' );
		}

		if ( empty( $upsert_response ) || ! $upsert_response instanceof BatchUpsertCatalogObjectsResponse ) {
			foreach ( $objects as $product_id => $object ) {

				if ( in_array( $product_id, $staged_product_ids, true ) ) {
					continue;
				}

				if ( ! $object instanceof CatalogObject ) {
					$object = $this->convert_to_catalog_object( $object );
				}

				$product = wc_get_product( $product_id );

				// Building the payload happens in two stages that differ in what a failure can mean,
				// so they are caught separately rather than distinguished after the fact.
				//
				// Catalog_Item's constructor only validates local product data and never reaches
				// Square, so a failure here is definitively this product's own problem. A product
				// that no longer resolves also lands here, which keeps a deleted product a skip
				// rather than a job failure.
				try {
					$catalog_item = new Catalog_Item( $product, $is_delete_action );
				} catch ( \Exception $local_exception ) {
					$this->record_skipped_product( $product_id, $local_exception->getMessage() );
					$isolated_fail_ids[]  = $product_id;
					$staged_product_ids[] = $product_id; // consumed, so the step queue advances past it
					continue;
				}

				$original_square_image_ids[ $product_id ] = $product->get_meta( '_square_item_image_id' );

				// get_batch() is not local work: for a variable product it reaches Square to look up
				// and create item options, so a failure here can just as easily be infrastructure.
				$refresh_requested_before = get_option( 'woocommerce_square_refresh_sync_cycle', false );

				try {
					$batch        = $catalog_item->get_batch( $object );
					$object_count = $catalog_item->get_batch_object_count();

				} catch ( \InvalidArgumentException $shape_exception ) {

					// The payload builders reject an object of the wrong type with this specific
					// exception, matching the guards in Handlers\Product. It is a local shape
					// problem for this one product and never an API failure, so it is safe to skip
					// even though it carries no Square error code.
					//
					// This is checked before the replay sentinel below on purpose: both shape guards
					// run at the top of their builder, before any Square call, so a shape failure
					// cannot coincide with a replay request. If a guard ever moves after an API call
					// that assumption breaks and the sentinel has to be checked here too.
					$this->record_skipped_product( $product_id, $shape_exception->getMessage() );
					$isolated_fail_ids[]  = $product_id;
					$staged_product_ids[] = $product_id; // consumed, so the step queue advances past it
					continue;

				} catch ( \Exception $staging_exception ) {

					// A failure that asked for the job to restart is never this product's fault.
					// API::create_options_and_values() sets woocommerce_square_refresh_sync_cycle
					// and clears the cached options data when Square rejects an item option, so
					// that run() refetches the options and replays the cycle. Recording a skip here
					// would strand a perfectly good product that the replay would have synced.
					if ( get_option( 'woocommerce_square_refresh_sync_cycle', false ) !== $refresh_requested_before ) {
						throw $staging_exception;
					}

					// Only a Square error naming this object's own data may become a skip. An
					// exception carrying no Square error code that reaches this point is a transport
					// failure from the option lookups above, not bad product data, so it must fail
					// the job loudly instead of stranding a valid product.
					if ( 'isolatable' !== $this->classify_sync_error( $staging_exception ) ) {
						throw $staging_exception;
					}

					$this->record_skipped_product( $product_id, $staging_exception->getMessage() );
					$isolated_fail_ids[]  = $product_id;
					$staged_product_ids[] = $product_id; // consumed, so the step queue advances past it
					continue;
				}

				if ( $this->get_max_objects_total() >= $object_count + $total_object_count ) {
					// Keyed by product so the per product fallback can reuse the batch built here
					// instead of building it a second time. Never rely on position: the skip
					// branches below advance $staged_product_ids without adding a batch.
					$batches[ $product_id ] = $batch;
					$total_object_count    += $object_count;
					$staged_product_ids[]   = $product_id;
				} elseif ( empty( $batches ) ) {

					// This single product alone exceeds the per request object limit, so no cycle
					// will ever be able to stage it. Without this it is retried forever: nothing is
					// staged, so nothing is reported as processed and the step repeats unchanged.
					$this->record_skipped_product(
						$product_id,
						sprintf(
							/* translators: Placeholders: %1$d - number of Square objects the product needs, %2$d - the per request limit */
							__( 'it needs %1$d Square objects, more than the %2$d a single request allows', 'woocommerce-square' ),
							$object_count,
							$this->get_max_objects_total()
						)
					);
					$isolated_fail_ids[]  = $product_id;
					$staged_product_ids[] = $product_id; // consumed, so the step queue advances past it
					continue;

				} else {
					break;
				}
			}

			// Every product in this cycle was skipped while staging, so there is nothing to send.
			// An empty request would be rejected and would turn a handled set of skips back into a
			// failed job.
			if ( empty( $batches ) ) {

				wc_square()->log( 'Nothing left to upsert this cycle: all ' . count( $staged_product_ids ) . ' staged products were skipped.' );

				// Persists this cycle's skip records counters along with clearing the resume state.
				$this->set_attr( 'in_progress_upsert_catalog_objects', null );

				$result['processed']   = $staged_product_ids;
				$result['unprocessed'] = array_diff( $product_ids, $staged_product_ids );
				$result['skipped']     = $isolated_fail_ids;

				return $result;
			}

			// The SDK takes a plain list. Hashing that list rather than the product keyed map keeps
			// the idempotency key identical to what this step has always produced.
			$batch_list = array_values( $batches );

			try {
				$start     = microtime( true );
				$body_hash = md5( serialize( $batches ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize

				// Reuse the key stored by a rate-limited attempt only while the request body is
				// unchanged; any body drift gets a fresh key or Square rejects the request with
				// IDEMPOTENCY_KEY_REUSED. The body legitimately changes between attempts when
				// temporary #category_* references resolve to real Square IDs after
				// upsert_categories completes.
				// Note: upsert_new_products() reads upsert_retry_product_ids before this method
				// runs to rebuild the same batch, which is what makes an unchanged body possible.
				$retry_idempotency_key = $this->get_attr( 'upsert_retry_idempotency_key', null );
				$idempotency_key       = wc_square()->get_reusable_idempotency_key( $retry_idempotency_key, $body_hash, '_upsert_products' );

				if ( ! empty( $retry_idempotency_key ) ) {
					// Consumed either way; a later rate limit stores the key actually used.
					$this->set_attr( 'upsert_retry_idempotency_key', null );
					$this->set_attr( 'upsert_retry_product_ids', null );
				}

				$response        = wc_square()->get_api()->batch_upsert_catalog_objects( $idempotency_key, $batch_list );
				$upsert_response = $response->get_data();
			} catch ( \Exception $e ) {
				$retry          = $this->get_attr( 'retry', 0 );
				$error_message  = $e->getMessage();
				$classification = $this->classify_sync_error( $e );

				// Store the key used for this attempt so a rate-limited retry can reuse it while
				// the request body is unchanged. Applies to every upsert path; the product ID
				// snapshot is only needed to rebuild the batch for new products. Retry up to 3 times.
				//
				// Detected through classify_sync_error() rather than by matching the message, so the
				// two branches that both needed to spot a rate limit agree on one definition of it.
				if ( 'rate_limited' === $classification && $retry < 3 ) {
					$this->set_attr( 'upsert_retry_idempotency_key', $idempotency_key );
					if ( $new_products ) {
						$this->set_attr( 'upsert_retry_product_ids', $product_ids );
					}
				}

				if ( 'isolatable' !== $classification ) {
					// Rate limiting keeps the existing job level retry; auth errors fail the job.
					throw $e;
				}

				// One bad product must not terminate the sync for every other product: retry the
				// staged set one request per product, record and skip the failing ones, and let the
				// step continue with whatever succeeded (SQUARE-143 / SQUARE-31). Products that
				// already failed at the staging stage are excluded so they are not recorded twice,
				// and only products the fallback actually attempted are consumed this cycle; the
				// rest are staged again on the next cycle.
				wc_square()->log( 'Batch upsert failed (' . $error_message . '); retrying the staged products individually.' );
				$isolation          = $this->upsert_products_individually( $batches, array_values( array_diff( $staged_product_ids, $isolated_fail_ids ) ) );
				$upsert_response    = $isolation['response'];
				$staged_product_ids = array_values( array_unique( array_merge( $isolated_fail_ids, $isolation['done_ids'], $isolation['failed_ids'] ) ) );
				$isolated_fail_ids  = array_values( array_unique( array_merge( $isolated_fail_ids, $isolation['failed_ids'] ) ) );
			}

			if ( ! $upsert_response instanceof BatchUpsertCatalogObjectsResponse ) {
				throw new \Exception( 'API response data is missing' );
			}

			// A response can be a 200 with partial failures: keep the error detail for the
			// skipped product records below (Square does not always name the failing object).
			// Every error is kept, not just the first: a single request carries several batches
			// and Square does not say which batch an error belongs to, so attributing one error
			// to every unreturned product would present a guess as the reason.
			if ( is_array( $upsert_response->getErrors() ) && ! empty( $upsert_response->getErrors() ) ) {
				$error_details = array();
				foreach ( $upsert_response->getErrors() as $response_error ) {
					$error_details[] = trim( ( $response_error->getCode() ? '[' . $response_error->getCode() . '] ' : '' ) . ( $response_error->getDetail() ?? '' ) );
				}
				$partial_error_detail = implode( ' | ', array_filter( array_unique( $error_details ) ) );
			}

			$in_progress['staged_product_ids']          = $staged_product_ids;
			$in_progress['unprocessed_upsert_response'] = wp_json_encode( $upsert_response, JSON_PRETTY_PRINT );
			$in_progress['isolated_fail_ids']           = $isolated_fail_ids;
			$in_progress['partial_error_detail']        = $partial_error_detail;
			$this->set_attr( 'in_progress_upsert_catalog_objects', $in_progress );

			$duration = number_format( microtime( true ) - $start, 2 );

			// getObjects() is null when every batch failed; that must not fatal the job.
			wc_square()->log( 'Upserted ' . ( is_array( $upsert_response->getObjects() ) ? count( $upsert_response->getObjects() ) : 0 ) . ' objects in ' . $duration . 's' );
		}

		// update local square meta for newly upserted objects
		if ( ! $is_delete_action && $upsert_response instanceof BatchUpsertCatalogObjectsResponse && is_array( $upsert_response->getIdMappings() ) ) {

			wc_square()->log( 'Mapping new Square item IDs to WooCommerce product IDs' );

			$start = microtime( true );

			foreach ( $upsert_response->getIdMappings() as $id_mapping ) {

				$client_item_id = $id_mapping->getClientObjectId();
				$remote_item_id = $id_mapping->getObjectId();

				if ( in_array( $client_item_id, $in_progress['mapped_client_item_ids'], true ) ) {
					continue;
				}

				if ( 0 === strpos( $client_item_id, '#item_variation_' ) ) {

					$product_id = substr( $client_item_id, strlen( '#item_variation_' ) );
					Product::set_square_item_variation_id( $product_id, $remote_item_id );

				} elseif ( 0 === strpos( $client_item_id, '#item_' ) ) {

					$product_id = substr( $client_item_id, strlen( '#item_' ) );
					Product::set_square_item_id( $product_id, $remote_item_id );
				}

				$in_progress['mapped_client_item_ids'][] = $client_item_id;
			}

			$duration = number_format( microtime( true ) - $start, 2 );

			wc_square()->log( 'Mapped ' . count( $in_progress['mapped_client_item_ids'] ) . ' Square IDs in ' . $duration . 's' );

			// Save the progress.
			$this->set_attr( 'in_progress_upsert_catalog_objects', $in_progress );
		}

		$pull_inventory_variation_ids = $this->get_attr( 'pull_inventory_variation_ids', array() );

		wc_square()->log( 'Storing Square item data to WooCommerce products' );

		$start = microtime( true );

		// loop through all returned objects and store their IDs to Woo products
		// (null when every batch failed; the skip records below still run)
		foreach ( is_array( $upsert_response->getObjects() ) ? $upsert_response->getObjects() : array() as $remote_catalog_item ) {

			$remote_item_id = $remote_catalog_item->getId();

			if ( in_array( $remote_item_id, $in_progress['processed_remote_catalog_item_ids'], true ) ) {
				continue;
			}

			$product = Product::get_product_by_square_id( $remote_item_id );

			if ( ! $product ) {
				$in_progress['processed_remote_catalog_item_ids'][] = $remote_item_id;
				continue;
			}

			Product::update_square_meta(
				$product,
				array(
					'item_id'       => $remote_item_id,
					'item_version'  => $remote_catalog_item->getVersion(),
					'item_image_id' => Product::get_catalog_item_thumbnail_id( $remote_catalog_item ),
				)
			);

			$successful_product_ids[] = $product->get_id();

			if ( is_array( $remote_catalog_item->getItemData()->getVariations() ) ) {

				foreach ( $remote_catalog_item->getItemData()->getVariations() as $catalog_item_variation ) {

					$product_variation = Product::get_product_by_square_variation_id( $catalog_item_variation->getId() );

					if ( $product_variation ) {

						$pull_inventory_variation_ids[] = $catalog_item_variation->getId();

						Product::update_square_meta(
							$product_variation,
							array(
								'item_variation_id'      => $catalog_item_variation->getId(),
								'item_variation_version' => $catalog_item_variation->getVersion(),
							)
						);
					}
				}
			}

			$local_image_id = $product->get_image_id();
			$product_id     = $product->get_id();

			// If there is a local image which is different from the last uploaded image
			// Or if the remote square image id has changed
			if ( ( $local_image_id && $local_image_id !== $product->get_meta( '_square_uploaded_image_id' ) ) ||
				( ! ( ( $original_square_image_ids[ $product_id ] ?? '' ) && ( $original_square_image_ids[ $product_id ] ?? '' ) === $product->get_meta( '_square_item_image_id' ) ) ) ) {
				// there is no batch image endpoint
				$this->push_product_image( $product );

			}

			$in_progress['processed_remote_catalog_item_ids'][] = $remote_item_id;
		}

		$this->set_attr( 'pull_inventory_variation_ids', $pull_inventory_variation_ids );

		$duration = number_format( microtime( true ) - $start, 2 );

		wc_square()->log( 'Stored Square data to ' . count( $staged_product_ids ) . ' products in ' . $duration . 's' );

		$unreturned_product_ids = array_values( array_diff( $staged_product_ids, $successful_product_ids, $isolated_fail_ids ) );

		// A 200 response can still carry errors, and they get the same treatment as a thrown one:
		// only a data error means these products are themselves at fault. On a server, auth or rate
		// limit error the write may yet succeed, so those products must not be reported as skipped.
		// They are left unconsumed instead, which retries them on the next cycle while the products
		// this response did return keep the mappings they just earned. Re-throwing here would
		// discard those mappings and re-send the successful products under a fresh key, creating
		// duplicates in Square for anything newly created.
		$partial_error_is_isolatable = '' === $partial_error_detail
			|| 'isolatable' === $this->classify_sync_error( new \Exception( $partial_error_detail ) );

		if ( ! empty( $unreturned_product_ids ) && ! $partial_error_is_isolatable ) {

			wc_square()->log(
				'Leaving ' . count( $unreturned_product_ids ) . ' products unprocessed for the next cycle: the upsert response'
				. ' carried an error that is not the products\' own data (' . $partial_error_detail . ').'
			);

			$staged_product_ids = array_values( array_diff( $staged_product_ids, $unreturned_product_ids ) );

		} else {

			// log any failed products (isolation failures were already recorded with their reason)
			foreach ( $unreturned_product_ids as $product_id ) {

				// Square names no product against these errors, so the reason is worded as the set of
				// errors the request returned rather than as this product's own confirmed cause.
				$this->record_skipped_product(
					$product_id,
					'' !== $partial_error_detail
						? sprintf(
							/* translators: Placeholder: %s - one or more error messages returned by Square */
							__( 'Square did not return the product in the upsert response. Errors returned by the request: %s', 'woocommerce-square' ),
							$partial_error_detail
						)
						: __( 'Square did not return the product in the upsert response', 'woocommerce-square' )
				);
			}
		}

		$this->set_attr( 'in_progress_upsert_catalog_objects', null );

		// processed means consumed by this cycle, which includes the skipped products so the step
		// queue advances past them. skipped is reported separately because a skipped product has no
		// usable Square mapping from this cycle and must not be treated as successfully upserted.
		$result['processed']   = $staged_product_ids;
		$result['unprocessed'] = array_diff( $product_ids, $staged_product_ids );
		$result['skipped']     = $isolated_fail_ids;

		return $result;
	}

	/**
	 * Completes the job, reporting a completed with errors outcome when products were skipped.
	 *
	 * @since x.x.x
	 *
	 * @return \stdClass the job object
	 */
	protected function complete() {

		$error_count = (int) $this->get_attr( 'sync_error_count', 0 );

		if ( $error_count > 0 ) {

			$this->set_attr( 'completed_with_errors', true, false );

			$failed_ids = (array) $this->get_attr( 'failed_product_ids', array() );

			// Only the most recent records are retained, so the notice must not promise an alert
			// for every skipped product: a large sync evicts the earliest ones.
			Records::set_record(
				array(
					'type'    => 'notice',
					'message' => sprintf(
						/* translators: Placeholders: %1$d - number of errors, %2$d - number of skipped products */
						esc_html__( 'Sync completed with %1$d errors. %2$d products were skipped; the most recent alerts above give the product and reason for each, and the full list is in the Square logs.', 'woocommerce-square' ),
						$error_count,
						count( $failed_ids )
					),
				)
			);
		}

		return parent::complete();
	}


	/**
	 * Classifies a sync exception to decide how the sync should react to it.
	 *
	 * - rate_limited: throttling, safe to retry the same request unchanged (existing behavior).
	 * - isolatable: Square understood the request and rejected this object because its data is
	 *   invalid. The same request will keep failing, so the object can be skipped and the sync can
	 *   continue without it (SQUARE-143 / SQUARE-31).
	 * - fatal: anything else. The write may or may not have been applied, so the job must fail
	 *   loudly instead of isolating: re-sending the staged objects under fresh temporary IDs would
	 *   create duplicate catalog items in Square.
	 *
	 * Skipping an object has to be opted into by a known data error, never assumed. A deny list
	 * would classify server errors, timeouts and permission problems as bad product data.
	 *
	 * @since x.x.x
	 *
	 * @param \Exception $exception the caught exception
	 * @return string rate_limited|isolatable|fatal
	 */
	protected function classify_sync_error( \Exception $exception ) {

		/**
		 * Filters the Square error codes that allow one object to be skipped so the sync continues.
		 *
		 * Adding a code here makes the sync skip the offending product or category on that error
		 * instead of failing the job. Only add codes that mean the object's own data is invalid:
		 * on anything else the write may already have been applied, and retrying the objects
		 * individually would create duplicates in Square.
		 *
		 * @since x.x.x
		 *
		 * @param string[] $codes Square error codes treated as an object level data problem
		 * @param \Exception $exception the exception being classified
		 */
		$isolatable_codes = (array) apply_filters( 'wc_square_isolatable_error_codes', self::ISOLATABLE_ERROR_CODES, $exception );

		// API::do_post_parse_response_validation() builds the message as "[CODE] detail" per error,
		// joined with " | ", so a code only ever appears at the start of a segment. Anchoring there
		// keeps bracketed text inside a detail out of the classification, whether that is a JSON
		// path Square wrote ("Value at `variations[0].sku`") or a merchant's own product name.
		$codes = array();
		foreach ( explode( ' | ', $exception->getMessage() ) as $segment ) {
			if ( preg_match( '/^\[([A-Z][A-Z0-9_]*)\]/', trim( $segment ), $matches ) ) {
				$codes[] = $matches[1];
			}
		}

		if ( in_array( 'RATE_LIMITED', $codes, true ) ) {
			return 'rate_limited';
		}

		// Every reported code must be a known data error before the object is skipped: a response
		// mixing a data error with a server error may still have been partly applied.
		if ( ! empty( $codes ) && ! array_diff( $codes, $isolatable_codes ) ) {
			return 'isolatable';
		}

		return 'fatal';
	}


	/**
	 * Records a product skipped by the sync, with the reason, and tracks the error counters.
	 *
	 * @since x.x.x
	 *
	 * @param int $product_id the skipped product ID
	 * @param string $reason the failure reason (Square error message or local validation detail)
	 */
	protected function record_skipped_product( $product_id, $reason ) {

		$product = $product_id ? wc_get_product( $product_id ) : false;
		$label   = (string) $product_id;

		if ( $product instanceof \WC_Product ) {
			$label = $product->get_name();
			if ( $product->get_sku() ) {
				$label .= ' (SKU ' . $product->get_sku() . ')';
			}
		}

		// A skip can be recorded with no product ID: push_inventory_changes_isolated() passes 0
		// when Square names a catalog object that no local product claims any more. There is no
		// subject to name in that case, so it gets its own sentence rather than being forced into
		// the product one, which would read "Product unknown product was skipped".
		if ( $product_id ) {

			$edit_link = get_edit_post_link( $product_id );
			$subject   = $edit_link
				? '<a href="' . esc_url( $edit_link ) . '">' . esc_html( $label ) . '</a>'
				: esc_html( $label );

			$message = sprintf(
				/* translators: Placeholders: %1$s - product name/SKU (possibly linked), %2$s - failure reason */
				esc_html__( 'Product %1$s was skipped so the sync could continue. Reason: %2$s', 'woocommerce-square' ),
				$subject,
				esc_html( $reason )
			);

		} else {

			$message = sprintf(
				/* translators: Placeholder: %s - failure reason */
				esc_html__( 'A Square item with no matching WooCommerce product was skipped so the sync could continue. Reason: %s', 'woocommerce-square' ),
				esc_html( $reason )
			);
		}

		Records::set_record(
			array(
				'type'       => 'alert',
				'product_id' => $product_id,
				'message'    => $message,
			)
		);

		// Records retain only the most recent entries, so the log is the complete list a merchant
		// or support can go back to after a sync that skipped more products than that.
		wc_square()->log(
			$product_id
				? 'Skipped product #' . $product_id . ' (' . $label . '): ' . $reason
				: 'Skipped a Square item with no matching WooCommerce product: ' . $reason
		);

		if ( $product_id ) {
			$failed_ids   = (array) $this->get_attr( 'failed_product_ids', array() );
			$failed_ids[] = (int) $product_id;
			$this->set_attr( 'failed_product_ids', array_values( array_unique( $failed_ids ) ), false );
		}

		// Deferred write: Records::set_record() already wrote an option for this skip, and a step
		// that skips many products would otherwise double that cost with a full job write per
		// product. Both counters reach the option on the next persisting set_attr() in the cycle.
		$this->set_attr( 'sync_error_count', (int) $this->get_attr( 'sync_error_count', 0 ) + 1, false );
	}


	/**
	 * Upserts the staged products one request per product after a combined batch request failed.
	 *
	 * Square rejects a whole upsert when any object in it is invalid and does not always identify
	 * the offender, so the reliable isolation is granularity: one request per product. Successful
	 * responses are merged into a single synthesized response so the caller's post processing works
	 * unchanged; failing products are recorded with their reason and skipped (SQUARE-143/31).
	 *
	 * The batches built by the staging loop are reused rather than rebuilt. Rebuilding would not be
	 * free: Catalog_Item::get_batch() reaches Square to create item options for variable products,
	 * so re-deriving a payload here would multiply the request count on the one path that only runs
	 * because something already failed.
	 *
	 * @since x.x.x
	 *
	 * At most MAX_ISOLATED_UPSERTS_PER_CYCLE products are attempted per invocation so a huge
	 * fallback cannot exhaust PHP's execution time inside one step cycle; unattempted products are
	 * simply not consumed and are staged again on the next cycle. A rate limit mid loop stops the
	 * slice: with partial progress the progress is kept, with none the exception bubbles so the
	 * existing job level retry and backoff take over.
	 *
	 * @param \Square\Models\CatalogObjectBatch[] $batches product ID keyed batches already built by the staging loop
	 * @param int[] $staged_product_ids the product IDs staged into the failed combined request
	 * @return array { response: BatchUpsertCatalogObjectsResponse, done_ids: int[], failed_ids: int[] }
	 */
	protected function upsert_products_individually( array $batches, array $staged_product_ids ) {

		$merged_objects  = array();
		$merged_mappings = array();
		$done_ids        = array();
		$failed_ids      = array();
		$attempted       = 0;

		foreach ( $staged_product_ids as $product_id ) {

			if ( self::MAX_ISOLATED_UPSERTS_PER_CYCLE <= $attempted ) {
				break;
			}

			// Products that never made it into a batch were already recorded while staging.
			if ( ! isset( $batches[ $product_id ] ) ) {
				continue;
			}

			$batch = $batches[ $product_id ];

			++$attempted;

			try {
				// Deterministic key (no timestamp): a retry with an unchanged body must reuse the
				// same key or Square treats the temp ids as a brand new upsert and creates
				// duplicate catalog items. The whole input is hashed so the key stays well inside
				// Square's documented length limits regardless of job and product ID length.
				$idempotency_key = wc_square()->get_idempotency_key( md5( $this->get_attr( 'id' ) . '_' . $product_id . '_' . serialize( $batch ) ) . '_isolated_upsert' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize

				$response      = wc_square()->get_api()->batch_upsert_catalog_objects( $idempotency_key, array( $batch ) );
				$response_data = $response->get_data();

				if ( ! $response_data instanceof BatchUpsertCatalogObjectsResponse ) {
					throw new \Exception( 'API response data is missing' );
				}

				// A single product request can also answer 200 while carrying errors. Rethrowing
				// them in the API layer's own "[CODE] detail" shape hands them to the catch below,
				// so one policy decides the outcome and the merchant gets Square's actual reason
				// rather than a generic "not returned in the response".
				if ( is_array( $response_data->getErrors() ) && ! empty( $response_data->getErrors() ) ) {

					$product_error_details = array();

					foreach ( $response_data->getErrors() as $response_error ) {
						$product_error_details[] = trim( ( $response_error->getCode() ? '[' . $response_error->getCode() . '] ' : '' ) . ( $response_error->getDetail() ?? '' ) );
					}

					throw new \Exception( esc_html( implode( ' | ', array_filter( array_unique( $product_error_details ) ) ) ) );
				}

				if ( is_array( $response_data->getObjects() ) ) {
					$merged_objects = array_merge( $merged_objects, $response_data->getObjects() );
				}
				if ( is_array( $response_data->getIdMappings() ) ) {
					$merged_mappings = array_merge( $merged_mappings, $response_data->getIdMappings() );
				}
				$done_ids[] = $product_id;
			} catch ( \Exception $product_exception ) {

				$classification = $this->classify_sync_error( $product_exception );

				// Anything that is not this product's own data ends the slice rather than marking a
				// product skipped: a rate limit, an auth failure or a server error says nothing about
				// the product, and on a server error the write may yet have landed.
				//
				// Progress already made is kept rather than discarded, so the products that did
				// succeed get their Square IDs mapped instead of being re-sent from scratch. That is
				// safe because each isolated request uses a key derived only from the job, the
				// product and the body, so re-sending an unattempted or half applied product next
				// cycle reuses the same key and Square deduplicates it. With no progress at all
				// there is nothing worth keeping, so the exception bubbles to the job level retry
				// and backoff, which is also what fails the job on an auth error.
				if ( 'isolatable' !== $classification ) {

					if ( empty( $done_ids ) && empty( $failed_ids ) ) {
						throw $product_exception;
					}

					wc_square()->log(
						'Stopping the isolated upsert fallback after a ' . $classification . ' error (' . $product_exception->getMessage() . '); '
						. count( $done_ids ) . ' done, the rest resume next cycle.'
					);
					break;
				}

				$failed_ids[] = $product_id;
				$this->record_skipped_product( $product_id, $product_exception->getMessage() );
				wc_square()->log( 'Isolated upsert failed for product #' . $product_id . ': ' . $product_exception->getMessage() );
			}
		}

		$synthesized = new BatchUpsertCatalogObjectsResponse();
		$synthesized->setObjects( $merged_objects );
		$synthesized->setIdMappings( $merged_mappings );

		return array(
			'response'   => $synthesized,
			'done_ids'   => $done_ids,
			'failed_ids' => $failed_ids,
		);
	}


	/**
	 * Converts object data to an instance of CatalogObject.
	 *
	 * @since 2.0.0
	 *
	 * @param array|string $object_data json string or array of object data
	 * @return CatalogObject
	 */
	protected function convert_to_catalog_object( $object_data ) {
		$object_data = ! is_string( $object_data ) ? wp_json_encode( $object_data ) : $object_data;
		$object      = ApiHelper::getJsonHelper()->mapClass( json_decode( $object_data ), 'Square\\Models\\CatalogObject' );

		return $object instanceof CatalogObject ? $object : null;
	}


	/**
	 * Pushes a product's image to Square.
	 *
	 * @since 2.0.0
	 *
	 * @param \WC_Product|int $product product object or ID
	 */
	protected function push_product_image( $product ) {

		$product = wc_get_product( $product );

		if ( ! $product instanceof \WC_Product || ! $product->get_image_id() ) {
			return;
		}

		$local_image_id = $product->get_image_id();
		$image_path     = get_attached_file( $local_image_id );

		if ( $image_path ) {

			try {

				$image_id = wc_square()->get_api()->create_image( $image_path, Product::get_square_item_id( $product ), $product->get_name() );

				Product::set_square_image_id( $product, $image_id );

				// record the WC image ID that was uploaded
				$product->update_meta_data( '_square_uploaded_image_id', $local_image_id );
				$product->save_meta_data();

			} catch ( \Exception $exception ) {

				if ( wc_square()->get_settings_handler()->is_debug_enabled() ) {
					wc_square()->log( 'Could not upload image for product #' . $product->get_id() . ': ' . $exception->getMessage() );
				}
			}
		}
	}


	/**
	 * Pushes WooCommerce inventory to Square for a specific set of product IDs.
	 *
	 * Called inline after each upsert_new_products batch so that newly created Square catalog
	 * objects receive correct stock quantities immediately, rather than waiting for the deferred
	 * push_inventory step. If the API call fails the exception is caught, the failure is logged,
	 * and the affected product IDs are returned so the caller can queue them for the deferred step.
	 *
	 * @since 5.4.1
	 *
	 * @param int[] $product_ids WooCommerce product IDs to push inventory for.
	 * @return int[] Product IDs for which the inventory push failed.
	 */
	private function push_inventory_for_products( array $product_ids ): array {

		$inventory_changes = array();

		foreach ( $product_ids as $product_id ) {

			$product = wc_get_product( $product_id );
			if ( ! $product instanceof \WC_Product ) {
				continue;
			}

			$product_inventory_changes = $this->get_product_inventory_changes( $product );

			if ( ! empty( $product_inventory_changes ) ) {
				$inventory_changes[ $product_id ] = $product_inventory_changes;
			}
		}

		if ( empty( $inventory_changes ) ) {
			return array();
		}

		$all_changes = array_merge( ...array_values( $inventory_changes ) );

		// Chunk by the batch limit in case the set of products has many variations.
		$chunks = array_chunk( $all_changes, self::BATCH_CHANGE_INVENTORY_LIMIT );

		$total_chunks = count( $chunks );

		foreach ( $chunks as $chunk_index => $chunk ) {
			try {
				$idempotency_key = wc_square()->get_idempotency_key( md5( serialize( $chunk ) ) . '_inline_inventory_' . $this->get_attr( 'id' ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
				wc_square()->get_api()->batch_change_inventory( $idempotency_key, $chunk );
			} catch ( \Exception $e ) {
				wc_square()->log(
					sprintf(
						'Inline inventory push failed on chunk %d of %d for %d products during upsert_new_products: %s. These will be retried by the push_inventory step.',
						$chunk_index + 1,
						$total_chunks,
						count( $inventory_changes ),
						$e->getMessage()
					)
				);
				// Return all product IDs so the deferred push_inventory step retries them.
				return array_keys( $inventory_changes );
			}
		}

		wc_square()->log(
			sprintf(
				'Pushed inventory inline for %d newly upserted products.',
				count( $inventory_changes )
			)
		);

		return array();
	}


	/**
	 * Builds inventory change objects for a single product.
	 *
	 * Handles both variable and simple products. For variable products it iterates
	 * each child variation; for simple products it acts on the product itself.
	 * Only products with stock management enabled produce inventory changes.
	 *
	 * Note: this method does not perform SKU-based Square ID lookups. It assumes
	 * Square variation IDs are already stored in WC postmeta, which is guaranteed
	 * for products that have just been upserted via upsert_catalog_objects().
	 * The deferred push_inventory() step handles its own SKU lookups separately.
	 *
	 * @since 5.4.1
	 *
	 * @param \WC_Product $product WooCommerce product object.
	 * @return \Square\Models\InventoryChange[] Inventory change objects for the product.
	 */
	private function get_product_inventory_changes( \WC_Product $product ): array {

		$changes = array();

		if ( $product->is_type( 'variable' ) && $product->has_child() ) {

			foreach ( $product->get_children() as $child_id ) {

				$child = wc_get_product( $child_id );
				if ( ! $child instanceof \WC_Product || ! $child->get_manage_stock() ) {
					continue;
				}

				$change = Product::get_inventory_change_physical_count_type( $child );
				if ( $change ) {
					$changes[] = $change;
				}
			}
		} elseif ( $product->get_manage_stock() ) {

			$change = Product::get_inventory_change_physical_count_type( $product );
			if ( $change ) {
				$changes[] = $change;
			}
		}

		return $changes;
	}


	/**
	 * Pushes WooCommerce inventory to Square for synced items.
	 *
	 * @since 2.0.0
	 *
	 * @throws \Exception
	 */
	protected function push_inventory() {

		$product_ids            = $this->get_attr( 'inventory_push_product_ids', array() );
		$count                  = $this->get_attr( 'push_inventory_count', 0 );
		$sku_lookups_this_step  = 0;
		$inventory_changes      = array();
		$inventory_change_count = 0;

		foreach ( $product_ids as $key => $product_id ) {

			$product             = wc_get_product( $product_id );
			$square_variation_id = Product::get_square_item_variation_id( $product_id, false );

			if ( $product instanceof \WC_Product ) {

				$product_inventory_changes = array();

				if ( $product->is_type( 'variable' ) && $product->has_child() ) {

					foreach ( $product->get_children() as $child_id ) {

						$child = wc_get_product( $child_id );
						if ( ! $child instanceof \WC_Product || ! $child->get_manage_stock() ) {
							continue;
						}

						$child_square_id = Product::get_square_item_variation_id( $child_id, false );
						if ( ! $child_square_id && $child->get_sku() && $sku_lookups_this_step < self::MAX_SKU_LOOKUPS_PER_PUSH_STEP ) {
							++$sku_lookups_this_step;
							$child_square_id = Product::get_square_variation_id_by_sku( $child->get_sku(), $child_id, true );
						}
						$inventory_change = Product::get_inventory_change_physical_count_type( $child );

						if ( $inventory_change ) {
							$product_inventory_changes[] = $inventory_change;
						}
					}
				} else {
					// Simple product: try SKU-based lookup if unmapped but synced (e.g. mapping lost after timeout).
					if ( ! $square_variation_id && $product->get_sku() && $product->get_manage_stock() && $sku_lookups_this_step < self::MAX_SKU_LOOKUPS_PER_PUSH_STEP ) {
						++$sku_lookups_this_step;
						$square_variation_id = Product::get_square_variation_id_by_sku( $product->get_sku(), $product_id, true );
					}

					if ( $square_variation_id ) {

						$inventory_change = Product::get_inventory_change_physical_count_type( $product );

						if ( $inventory_change && $product->get_manage_stock() ) {
							$product_inventory_changes[] = $inventory_change;
						}
					}
				}

				if ( self::BATCH_CHANGE_INVENTORY_LIMIT >= $inventory_change_count + count( $product_inventory_changes ) ) {
					if ( ! empty( $product_inventory_changes ) ) {
						$inventory_changes[]     = $product_inventory_changes;
						$inventory_change_count += count( $product_inventory_changes );
					}
					unset( $product_ids[ $key ] );

				} else {

					break;
				}
			} else {

				unset( $product_ids[ $key ] );
			}
		}

		if ( ! empty( $inventory_changes ) ) {

			$inventory_changes = array_merge( ...$inventory_changes );
			$this->push_inventory_changes_isolated( $inventory_changes );
		}

		$this->set_attr( 'inventory_push_product_ids', $product_ids );
		$this->set_attr( 'push_inventory_count', $count + count( $inventory_changes ) );

		if ( empty( $product_ids ) ) {

			$this->complete_step( 'push_inventory' );
		}
	}

	/**
	 * Splits a failed inventory chunk into the objects Square named in the error and the rest.
	 *
	 * The chunk's own catalog object IDs are tested against the error text rather than parsing an
	 * ID out of it. Square does not guarantee any particular formatting and frequently quotes the
	 * JSON field path instead of the value, so parsing either extracts a field name that matches
	 * nothing in the chunk, and discards up to a hundred good inventory updates, or extracts
	 * nothing at all.
	 *
	 * An empty named set means the failure cannot be attributed to anything in this chunk. The
	 * caller treats that as fatal, because the same code Square returns for a dead catalog object
	 * is also what it returns for a location the account does not own.
	 *
	 * @since x.x.x
	 *
	 * @param \Square\Models\InventoryChange[] $chunk changes sent in the request that failed
	 * @param string $error_message the message Square returned
	 * @return array {named: string[], remaining: \Square\Models\InventoryChange[]}
	 */
	protected function partition_inventory_changes_by_error( array $chunk, $error_message ) {

		$remaining = array();
		$named     = array();

		foreach ( $chunk as $change ) {

			$change_object_id = $change->getPhysicalCount() ? $change->getPhysicalCount()->getCatalogObjectId() : null;

			if ( $change_object_id && false !== strpos( $error_message, $change_object_id ) ) {
				$named[ $change_object_id ] = true;
				continue;
			}

			$remaining[] = $change;
		}

		return array(
			'named'     => array_keys( $named ),
			'remaining' => $remaining,
		);
	}


	/**
	 * Sends inventory changes to Square with per change isolation of dead catalog objects.
	 *
	 * A single change referencing a Square object that no longer exists (a stale local mapping
	 * after a Square side catalog wipe) rejects the whole batch with NOT_FOUND, silently dropping
	 * inventory for every other product in it. Square names the offending object in the error, so
	 * the changes it names are removed, recorded, and the remainder is retried. Unattributable
	 * failures skip only their own chunk and the sync continues (SQUARE-143 / SQUARE-31).
	 *
	 * The offending IDs are found by testing the chunk's own catalog object IDs against the error
	 * text rather than by parsing an ID out of it. Square does not guarantee any particular
	 * formatting and frequently quotes the JSON field path instead of the value, so parsing would
	 * either extract a field name that matches nothing in the chunk, and drop up to 100 good
	 * inventory updates, or extract nothing at all.
	 *
	 * @since x.x.x
	 *
	 * @param \Square\Models\InventoryChange[] $inventory_changes the changes to push
	 */
	protected function push_inventory_changes_isolated( array $inventory_changes ) {

		foreach ( array_chunk( $inventory_changes, self::BATCH_CHANGE_INVENTORY_LIMIT ) as $chunk ) {

			// Every round drops at least one change, so this terminates on its own. The round cap
			// bounds the request count regardless: Square normally names every offending object in
			// one response, so a chunk that needs more rounds than this is not behaving as expected
			// and must not spend a whole step cycle discovering one object at a time.
			$attempts_left = min( count( $chunk ) + 1, self::MAX_INVENTORY_ISOLATION_ROUNDS );

			while ( ! empty( $chunk ) && $attempts_left > 0 ) {

				--$attempts_left;

				try {
					$idempotency_key = wc_square()->get_idempotency_key( md5( serialize( $chunk ) ) . '_change_inventory' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
					wc_square()->get_api()->batch_change_inventory( $idempotency_key, $chunk );

					$chunk = array(); // sent, so nothing in this chunk is left unaccounted for
					break;
				} catch ( \Exception $exception ) {

					if ( 'isolatable' !== $this->classify_sync_error( $exception ) ) {
						throw $exception;
					}

					$error_message = $exception->getMessage();

					// Keep every change Square did not name, and drop all of the ones it did in a
					// single round rather than one per round.
					$partition = $this->partition_inventory_changes_by_error( $chunk, $error_message );
					$remaining = $partition['remaining'];
					$dropped   = $partition['named'];

					if ( empty( $dropped ) ) {
						// Square named none of this chunk's objects, so nothing here is known to be
						// at fault. NOT_FOUND for example is also what Square returns when the
						// configured location does not belong to the account, which no amount of
						// skipping fixes. Silently dropping up to a hundred inventory updates on a
						// failure we cannot attribute would hide that, so fail loudly instead.
						throw $exception;
					}

					foreach ( $dropped as $dead_object_id ) {

						$product    = Product::get_product_by_square_variation_id( $dead_object_id );
						$product_id = $product instanceof \WC_Product ? $product->get_id() : 0;

						$this->record_skipped_product(
							$product_id,
							sprintf(
								/* translators: Placeholder: %s - Square catalog object ID */
								__( 'its saved Square mapping (%s) is no longer usable in Square', 'woocommerce-square' ),
								$dead_object_id
							)
						);
					}

					$chunk = $remaining;
				}
			}

			// Rounds ran out with changes still unsent. Dropping them silently would lose real
			// inventory updates for products Square never named as the problem, so fail loudly.
			if ( ! empty( $chunk ) ) {
				throw new \Exception(
					esc_html(
						'Gave up isolating inventory changes after ' . self::MAX_INVENTORY_ISOLATION_ROUNDS
						. ' rounds with ' . count( $chunk ) . ' changes still unsent.'
					)
				);
			}
		}
	}


	/**
	 * Performs a sync when Square is the Sync setting.
	 *
	 * @since 2.0.0
	 */
	protected function square_sor_sync() {

		$synced_product_ids        = $this->get_attr( 'validated_product_ids', array() );
		$processed_product_ids     = $this->get_attr( 'processed_product_ids', array() );
		$deleted_square_variations = $this->get_attr( 'deleted_square_variations', array() );
		$unprocessed_product_ids   = array_diff( array_merge( $synced_product_ids, $deleted_square_variations ), $processed_product_ids );
		$catalog_processed         = $this->get_attr( 'catalog_processed', false );

		if ( $catalog_processed ) {

			wc_square()->log( 'Square catalog fully processed' );

			if ( ! empty( $unprocessed_product_ids ) ) {
				$this->mark_failed_products( $unprocessed_product_ids );
			}

			$this->complete_step( 'square_sor_sync' );
			return;
		}

		try {

			$response_data = $this->get_attr( 'catalog_objects_search_response_data', null );

			if ( ! empty( $response_data ) ) {
				$response_data = ApiHelper::getJsonHelper()->mapClass( json_decode( $response_data ), 'Square\\Models\\SearchCatalogObjectsResponse' );

				// If the response data is invalid, reset it.
				if ( ! $response_data instanceof SearchCatalogObjectsResponse ) {
					$response_data = null;
				}
			}

			if ( ! $response_data ) {

				wc_square()->log( 'Generating a new catalog search request' );

				$cursor = $this->get_attr( 'square_sor_cursor' );

				$response = wc_square()->get_api()->search_catalog_objects(
					array(
						'cursor'                  => $cursor,
						'object_types'            => array( 'ITEM' ),
						'include_related_objects' => true,
						'limit'                   => $this->get_max_objects_to_retrieve(),
					)
				);

				$response_data = $response->get_data();

				$this->set_attr( 'catalog_objects_search_response_data', wp_json_encode( $response_data ) );
			}

			if ( ! $response_data instanceof SearchCatalogObjectsResponse ) {
				throw new \Exception( 'API response data is missing' );
			}

			$cursor = $response_data->getCursor();
			$this->set_attr( 'square_sor_cursor', $cursor );

			$catalog_processed = ! $cursor;
			$this->set_attr( 'catalog_processed', $catalog_processed );

		} catch ( \Exception $exception ) { // bail early and fail for any API and plugin errors

			$this->fail( 'Product sync failed. ' . $exception->getMessage() );
			return;
		}

		$related_objects = $response_data->getRelatedObjects();

		if ( $related_objects && is_array( $related_objects ) ) {
			// first import any related categories
			foreach ( $related_objects as $related_object ) {
				if ( 'CATEGORY' === $related_object->getType() ) {
					Category::import_or_update( $related_object );
				}
			}
		}

		$pull_inventory_variation_ids = $this->get_attr( 'pull_inventory_variation_ids', array() );

		/** @var \Square\Models\CatalogObject[] */
		$catalog_objects = $products_to_update = array();

		$catalog_objects = $response_data->getObjects() ? $response_data->getObjects() : array();

		wc_square()->log( 'Searching for products in ' . count( $catalog_objects ) . ' Square objects' );

		foreach ( $catalog_objects as $object ) {

			$found_product = null;

			if ( ! $object instanceof CatalogObject ) {
				continue;
			}

			// filter out objects that aren't at our configured location
			if ( ! $object->getPresentAtAllLocations() && ( ! is_array( $object->getPresentAtLocationIds() ) || ! in_array( wc_square()->get_settings_handler()->get_location_id(), $object->getPresentAtLocationIds(), true ) ) ) {
				continue;
			}

			// even simple items have a single variation
			if ( ! is_array( $object->getItemData()->getVariations() ) ) {
				continue;
			}

			$maybe_parent_product = Product::get_product_by_square_id( $object->getId() );

			if ( $maybe_parent_product instanceof \WC_Product && $maybe_parent_product->is_type( 'variable' ) ) {
				$missing_variations        = array();
				$woo_product_variations    = $maybe_parent_product->get_children();
				$square_product_variations = $object->getItemData()->getVariations();
				$square_variation_ids      = array_values(
					array_filter(
						array_map(
							function ( $square_product_variation ) {
								$sku = $square_product_variation->getItemVariationData()->getSku();

								if ( empty( $sku ) ) {
									return null;
								}

								return wc_get_product_id_by_sku( $sku );
							},
							$square_product_variations
						)
					)
				);

				foreach ( $woo_product_variations as $woo_product_variation_id ) {
					if ( ! in_array( (int) $woo_product_variation_id, $square_variation_ids, true ) ) {
						$woo_product_variation = wc_get_product( $woo_product_variation_id );
						$woo_product_variation->set_status( 'private' );
						$woo_product_variation->save();
						$missing_variations[] = $woo_product_variation_id;
					}
				}

				$missing_variations = array_diff( $woo_product_variations, $square_variation_ids );
				$this->set_attr( 'deleted_square_variations', $missing_variations );
			}

			foreach ( $object->getItemData()->getVariations() as $variation ) {

				$sku = $variation->getItemVariationData()->getSku();

				if ( empty( $sku ) ) {
					continue;
				}

				$found_product_id = wc_get_product_id_by_sku( $sku );

				// bail if this product has already been processed
				if ( in_array( $found_product_id, $processed_product_ids, false ) ) { // phpcs:ignore WordPress.PHP.StrictInArray.FoundNonStrictFalse
					break;
				}

				$found_product = wc_get_product( $found_product_id );

				// The new Square variation which does not exist in WooCommerce,
				// would be skipped here but will be added to the WooCommerce later.
				if ( ! $found_product ) {
					continue;
				}

				if ( $found_product instanceof \WC_Product_Variation ) {

					$found_variation = $found_product;
					$found_parent_id = $found_product->get_parent_id() ? $found_product->get_parent_id() : 0;
					$found_product   = null;

					// bail if this parent product has already been processed
					if ( in_array( $found_parent_id, $processed_product_ids, false ) ) { // phpcs:ignore WordPress.PHP.StrictInArray.FoundNonStrictFalse
						break;
					}

					$found_parent = wc_get_product( $found_parent_id );

					if ( $found_parent ) {

						Product::set_square_item_variation_id( $found_variation, $variation->getId() );

						$found_product = $found_parent;
					}

					break;

				} else {

					Product::set_square_item_variation_id( $found_product, $variation->getId() );
				}
			}

			// if no variation was found, check if the parent product exists.
			if ( ! $found_product && $maybe_parent_product ) {
				$found_product = $maybe_parent_product;
			}

			if ( $found_product && in_array( $found_product->get_id(), $synced_product_ids, false ) ) { // phpcs:disable WordPress.PHP.StrictInArray.FoundNonStrictFalse

				Product::set_square_item_id( $found_product, $object->getId() );

				$products_to_update[] = $found_product;

				$catalog_objects[ $found_product->get_id() ] = $object;
			}
		}

		wc_square()->log( 'Found ' . count( $products_to_update ) . ' products with matching SKUs' );

		// Square SOR always gets the latest inventory
		// set this before processing so nothing is missed during processing
		wc_square()->get_sync_handler()->set_inventory_last_synced_at();

		$product_import = new Product_Import();

		foreach ( $products_to_update as $product ) {

			try {

				$square_object = ! empty( $catalog_objects[ $product->get_id() ] ) ? $catalog_objects[ $product->get_id() ] : null;

				// if no Square object was found
				if ( ! $square_object ) {
					$record = array(
						'type'       => 'alert',
						'product_id' => $product->get_id(),
						/* translators: Placeholder %s Product ID */
						'message'    => sprintf( esc_html__( '%s does not exist in the Square catalog.', 'woocommerce-square' ), '<a href="' . esc_url( get_edit_post_link( $product->get_id() ) ) . '">' . $product->get_formatted_name() . '</a>' ),
					);

					// if enabled, hide the product from the catalog
					if ( wc_square()->get_settings_handler()->is_system_of_record_square() && wc_square()->get_settings_handler()->hide_missing_square_products() ) {
						try {
							$product->set_catalog_visibility( 'hidden' );
							$product->save();

							$record['product_hidden'] = true;
						} catch ( \Exception $e ) {
							$record['message'] .= esc_html__( 'This product failed to be hidden.', 'woocommerce-square' );
						}
					}

					Records::set_record( $record );
					continue;
				}

				foreach ( $square_object->getItemData()->getVariations() as $variation ) {
					$pull_inventory_variation_ids[] = $variation->getId();
				}

				$data = $product_import->extract_product_data( $square_object, $product );

				/**
				 * Filters the data that is used to create update a WooCommerce product during import.
				 *
				 * @since 2.0.0
				 *
				 * @param array $data product data
				 * @param \Square\Models\CatalogObject $square_object the catalog object from the Square API
				 * @param Manual_Synchronization $this current class instance
				 */
				$data = apply_filters( 'woocommerce_square_create_product_data', $data, $square_object, $this );

				// Update the product, this will update/create the variations as well.
				$product_import->update_product( $product, $data );
				Product::update_from_square( $product, $square_object->getItemData(), false );

			} catch ( \Exception $exception ) {

				Records::set_record(
					array(
						'type'       => 'alert',
						'product_id' => $product->get_id(),
						/* translators: Placeholder %1$s Product Name, %2$s Exception message */
						'message'    => sprintf( esc_html__( 'Could not sync %1$s data from Square. %2$s.', 'woocommerce-square' ), '<a href="' . esc_url( get_edit_post_link( $product->get_id() ) ) . '">' . $product->get_formatted_name() . '</a>', $exception->getMessage() ),
					)
				);

			}

			$processed_product_ids[] = $product->get_id();
		}

		$this->set_attr( 'catalog_objects_search_response_data', null );

		$this->set_attr( 'pull_inventory_variation_ids', $pull_inventory_variation_ids );

		$this->set_attr( 'processed_product_ids', $processed_product_ids );
	}


	/**
	 * Pulls the latest inventory counts for the variation IDs in `pull_inventory_variation_ids`.
	 *
	 * @since 2.0.2
	 *
	 * @throws \Exception
	 */
	protected function pull_inventory() {

		$processed_ids = $this->get_attr( 'processed_square_variation_ids', array() );

		$in_progress = wp_parse_args(
			$this->get_attr(
				'in_progress_pull_inventory',
				array()
			),
			array(
				'response_data'           => null,
				'processed_variation_ids' => array(),
			)
		);

		$response_data = null;

		// if a response was never cleared, we likely had a timeout
		if ( null !== $in_progress['response_data'] ) {
			$response_data = ApiHelper::getJsonHelper()->mapClass( json_decode( $in_progress['response_data'] ), 'Square\\Models\\BatchRetrieveInventoryCountsResponse' );
		}

		// if the saved response was somehow corrupted, start over
		if ( ! $response_data instanceof BatchRetrieveInventoryCountsResponse ) {

			$square_variation_ids = $this->get_attr( 'pull_inventory_variation_ids', array() );

			// remove IDs that have already been processed
			$square_variation_ids = array_diff( $square_variation_ids, $processed_ids );

			if ( empty( $square_variation_ids ) ) {

				$this->complete_step( 'pull_inventory' );
				return;
			}

			if ( count( $square_variation_ids ) > self::BATCH_INVENTORY_COUNTS_LIMIT ) {

				$variation_ids_batch = array_slice( $square_variation_ids, 0, self::BATCH_INVENTORY_COUNTS_LIMIT );

				$this->set_attr( 'pull_inventory_variation_ids', array_diff( $square_variation_ids, $variation_ids_batch ) );

				$square_variation_ids = $variation_ids_batch;
			}

			$cursor             = '';
			$response_counts    = array();
			$location_ids       = array( wc_square()->get_settings_handler()->get_location_id() );
			$catalog_object_ids = array_values( $square_variation_ids );

			// Repeat fetching objects using the cursor when the results are paginated.
			do {
				$response = wc_square()->get_api()->batch_retrieve_inventory_counts(
					array(
						'catalog_object_ids' => $catalog_object_ids,
						'location_ids'       => $location_ids,
						'cursor'             => $cursor,
					)
				);

				if ( ! $response->get_data() instanceof BatchRetrieveInventoryCountsResponse ) {
					throw new \Exception( 'Response data missing or invalid' );
				}

				$response_data = $response->get_data();

				// if no counts were returned, there's nothing to process
				if ( ! is_array( $response_data->getCounts() ) ) {

					$this->set_attr( 'processed_square_variation_ids', array_merge( $processed_ids, $square_variation_ids ) );
					return;
				}

				$in_progress['response_data'] = wp_json_encode( $response_data, JSON_PRETTY_PRINT );

				// Store the response counts to be processed later.
				$response_counts = array_merge( $response_counts, $response_data->getCounts() );
				$cursor          = $response->get_data()->getCursor();

			} while ( ! empty( $cursor ) );
		}

		$catalog_objects_inventory_stats = array();

		foreach ( $response_counts as $count ) {
			// If catalog stats array already contains the catalog object marked as IN_STOCK, then continue.
			if ( isset( $catalog_objects_inventory_stats[ $count->getCatalogObjectId() ] ) && $catalog_objects_inventory_stats[ $count->getCatalogObjectId() ]['IN_STOCK'] ) {
				continue;
				// Else if the catalog object is IN_STOCK, then mark IN_STOCK as true and set the quantity for later use.
			} elseif ( 'IN_STOCK' === $count->getState() ) {
				$catalog_objects_inventory_stats[ $count->getCatalogObjectId() ] = array(
					'IN_STOCK' => true,
					'quantity' => $count->getQuantity(),
				);
				// Else if the catalog object doesn't have an IN_STOCK status, then mark IN_STOCK as false and set the quantity as 0 for later use.
			} else {
				$catalog_objects_inventory_stats[ $count->getCatalogObjectId() ] = array(
					'IN_STOCK' => false,
					'quantity' => 0,
				);
			}
		}

		$catalog_objects_tracking_stats = Helper::get_catalog_objects_tracking_stats( $catalog_object_ids );

		foreach ( $catalog_objects_tracking_stats as $catalog_object_id => $inventory_data ) {
			$is_tracking_inventory = $inventory_data['track_inventory'] ?? false;
			$sold_out              = $inventory_data['sold_out'] ?? false;

			if ( in_array( $catalog_object_id, $in_progress['processed_variation_ids'], false ) ) { // phpcs:disable WordPress.PHP.StrictInArray.FoundNonStrictFalse
				continue;
			}

			$product = Product::get_product_by_square_variation_id( $catalog_object_id );

			if ( $product instanceof \WC_Product ) {

				/* If catalog object is tracked and has a quantity > 0 set in Square. */
				if ( $is_tracking_inventory && isset( $catalog_objects_inventory_stats[ $catalog_object_id ] ) ) {
					$product->set_stock_quantity( (float) $catalog_objects_inventory_stats[ $catalog_object_id ]['quantity'] );
					$product->set_manage_stock( true );

					/* If the catalog object is tracked but the quantity in Square is set to 0. */
				} elseif ( $is_tracking_inventory ) {
					$product->set_stock_quantity( 0 );
					$product->set_manage_stock( true );

					/* If the catalog object is not tracked in Square at all. */
				} else {
					$product->set_stock_status( $sold_out ? 'outofstock' : 'instock' );
					$product->set_manage_stock( false );
				}

				$product->save();

				$in_progress['processed_variation_ids'][] = $catalog_object_id;
			} else {
				Records::set_record(
					array(
						'type'    => 'alert',
						'message' => sprintf(
							/* translators: %1$s - Item Variation ID */
							__( '[Pull Inventory] The product does not exist in the WooCommerce store for the item variation: %1$s.', 'woocommerce-square' ),
							$catalog_object_id
						),
					)
				);

				// Add the catalog object ID to the processed list to avoid processing it again.
				$in_progress['processed_variation_ids'][] = $catalog_object_id;
			}

			$this->set_attr( 'in_progress_pull_inventory', $in_progress );
		}

		$this->set_attr( 'processed_square_variation_ids', array_merge( $processed_ids, $in_progress['processed_variation_ids'] ) );

		// clear any in-progress data
		$this->set_attr( 'in_progress_pull_inventory', array() );
	}

	/**
	 * Marks a set of products as failed to sync.
	 *
	 * @since 2.0.0
	 *
	 * @param \WC_Product[]|int[] $products products to mark as failed
	 */
	protected function mark_failed_products( $products = array() ) {

		foreach ( $products as $product ) {

			$product = wc_get_product( $product );

			if ( ! $product instanceof \WC_Product ) {
				continue;
			}

			$record_data = array(
				'type'       => 'alert',
				'product_id' => $product->get_id(),
			);

			// optionally hide unmatched products from catalog
			if ( wc_square()->get_settings_handler()->is_system_of_record_square() && wc_square()->get_settings_handler()->hide_missing_square_products() ) {

				try {

					$product->set_catalog_visibility( 'hidden' );
					$product->save();

					$record_data['product_hidden'] = true;

				} catch ( \Exception $e ) {
					/* translators: Placeholder %1$s Product Name, %2$s Exception message */
					$record['message'] = sprintf( esc_html__( '%1$s was deleted in Square but could not be hidden in WooCommerce. %2$s.', 'woocommerce-square' ), '<a href="' . esc_url( get_edit_post_link( $product->get_id() ) ) . '">' . $product->get_formatted_name() . '</a>', $e->getMessage() );
				}
			}

			Records::set_record( $record_data );
		}
	}


	/**
	 * Gets a list of unique category IDs used by a group of product IDs.
	 *
	 * @since 2.0.0
	 *
	 * @param  int[] $product_ids array of product IDs.
	 * @return int[]
	 */
	protected function get_shared_category_ids( $product_ids ) {

		if ( ! empty( $product_ids ) ) {
			$category_ids = get_terms(
				array(
					'taxonomy'   => 'product_cat',
					'fields'     => 'ids',
					'object_ids' => $product_ids,
				)
			);
		}

		return ! empty( $category_ids ) && ! is_wp_error( $category_ids ) ? $category_ids : array();
	}


	/**
	 * Assigns the next steps needed for this sync job.
	 *
	 * @since 2.0.0
	 */
	protected function assign_next_steps() {

		$next_steps = array();

		if ( $this->is_system_of_record_woocommerce() ) {

			if ( 'delete' === $this->get_attr( 'action' ) ) {

				$next_steps = array(
					'validate_products',
					'update_matched_products',
					'search_matched_products',
				);

			} else {

				$next_steps = array(
					'validate_products',
					'extract_category_ids',
					'refresh_category_mappings',
					'query_unmapped_categories',
					'upsert_categories',
					'fetch_options_data',
					'update_matched_products',
					'search_matched_products',
					'upsert_new_products',
				);

				// only handle product inventory if enabled
				if ( wc_square()->get_settings_handler()->is_inventory_sync_enabled() ) {
					$next_steps[] = 'push_inventory';
					$next_steps[] = 'pull_inventory';
				}
			}
		} elseif ( $this->is_system_of_record_square() ) {

			$next_steps = array(
				'validate_products',
				'square_sor_sync',
			);

			// only pull product inventory if enabled
			if ( wc_square()->get_settings_handler()->is_inventory_sync_enabled() ) {
				$next_steps[] = 'pull_inventory';
			}
		}

		$this->set_attr( 'next_steps', $next_steps );
	}

	/**
	 * Fetch the option (attribute) names from Square.
	 *
	 * @since 4.9.0
	 *
	 * @throws \Exception
	 */
	protected function fetch_options_data() {
		$cursor     = $this->get_attr( 'fetch_options_data_cursor' ) ? $this->get_attr( 'fetch_options_data_cursor' ) : '';
		$result     = wc_square()->get_api()->retrieve_options_data( $cursor );
		$new_cursor = isset( $result[2] ) ? $result[2] : null;

		$this->set_attr( 'fetch_options_data_cursor', $new_cursor );

		if ( empty( $new_cursor ) ) {
			$this->complete_step( 'fetch_options_data' );
		}
	}

	/**
	 * Gets the maximum number of objects to retrieve in a single sync job.
	 *
	 * @since 2.0.0
	 *
	 * @return int
	 */
	protected function get_max_objects_to_retrieve() {
		$max = $this->get_attr( 'max_objects_to_retrieve', 50 );

		/**
		 * Filters the maximum number of objects to retrieve in a single sync job.
		 *
		 * @since 2.0.0
		 *
		 * $param int $max
		 */
		return max( 1, (int) apply_filters( 'wc_square_sync_max_objects_to_retrieve', $max ) );
	}


	/**
	 * Gets the maximum number of objects per batch in a single sync job.
	 *
	 * @deprecated 3.2
	 * @since 2.0.0
	 *
	 * @return int
	 */
	protected function get_max_objects_per_batch() {

		wc_deprecated_function( __METHOD__, '3.2' );

		$max = $this->get_attr( 'max_objects_per_batch', 1000 );

		/**
		 * Filters the maximum number of objects per batch in a single sync job.
		 *
		 * @since 2.0.0
		 *
		 * $param int $max
		 */
		return max( 10, (int) apply_filters( 'wc_square_sync_max_objects_per_batch', $max ) );
	}


	/**
	 * Gets the maximum number of objects per batch upsert in a single request.
	 *
	 * @since 2.0.0
	 *
	 * @return int
	 */
	protected function get_max_objects_per_upsert() {

		$max = $this->get_attr( 'max_objects_per_upsert', 25 );

		/**
		 * Filters the maximum number of objects per upsert in a single request.
		 *
		 * @since 2.0.0
		 *
		 * $param int $max
		 */
		return max( 1, (int) apply_filters( 'wc_square_sync_max_objects_per_upsert', $max ) );
	}


	/**
	 * Gets the maximum number of objects allowed in a single sync job.
	 *
	 * @since 2.0.0
	 *
	 * @return int
	 */
	protected function get_max_objects_total() {

		$max = $this->get_attr( 'max_objects_total', self::BATCH_UPSERT_OBJECT_LIMIT );

		/**
		 * Filters the maximum number of objects allowed in a single sync job.
		 *
		 * @since 2.0.0
		 *
		 * $param int $max
		 */
		return max( 1, (int) apply_filters( 'wc_square_sync_max_objects_total', $max ) );
	}
}
