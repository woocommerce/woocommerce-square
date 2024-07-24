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
use Square\Models\SearchCatalogObjectsResponse;
use Square\Models\CatalogInfoResponse;
use \Square\ApiHelper;
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

	/** @var int the limit for how many inventory counts can be requested per batch
	 * Square paginates responses in page size of 100.
	 * Consider some items can have more than one object returned with different states. */
	const BATCH_INVENTORY_COUNTS_LIMIT = 125;

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

				// swap the square ID into the array key for quick lookup
				$mapped_category_audit = array();

				foreach ( $mapped_categories as $mapped_category_id => $mapped_category ) {
					$mapped_category_audit[ $mapped_category['square_id'] ] = $mapped_category_id;
				}

				if ( ! $response->get_data() instanceof BatchRetrieveCatalogObjectsResponse ) {
					throw new \Exception( 'Could not fetch category data from Square. Response data is missing' );
				}

				// handle response
				if ( is_array( $response->get_data()->getObjects() ) ) {
					foreach ( $response->get_data()->getObjects() as $category ) {

						// don't check for the name, it will get overwritten by the Woo value anyway
						if ( isset( $mapped_category_audit[ $category->getId() ] ) ) {

							$category_id = $mapped_category_audit[ $category->getId() ];

							$map[ $category_id ]['square_version'] = $category->getVersion();
							unset( $mapped_category_audit[ $category->getId() ] );
						}
					}
				}

				// any remaining categories were not found in Square and should have their local mapping data removed
				if ( ! empty( $mapped_category_audit ) ) {

					$outdated_category_ids = array_values( $mapped_category_audit );

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

		foreach ( array_chunk( $batches, $this->get_max_objects_per_upsert() ) as $batch ) {
			$idempotency_key = wc_square()->get_idempotency_key( md5( serialize( $batch ) . $this->get_attr( 'id' ) ) . '_upsert_categories' );
			$result          = wc_square()->get_api()->batch_upsert_catalog_objects( $idempotency_key, $batch );

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

			foreach ( $result->get_data()->getObjects() as $upserted_category ) {
				$id      = $upserted_category->getId();
				$version = $upserted_category->getVersion();

				if ( isset( $reverse_map[ $id ] ) ) {
					Category::update_mapping( $reverse_map[ $id ], $id, $version );
					unset( $reverse_map[ $id ] );
				}
			}
		}

		$this->complete_step( 'upsert_categories' );
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

		if ( ! empty( $in_progress['unprocessed_search_response'] ) ) {
			$search_response = ApiHelper::deserialize( $in_progress['unprocessed_search_response'], new SearchCatalogObjectsResponse() );
		} else {

			$response = wc_square()->get_api()->search_catalog_objects(
				array(
					'cursor'       => $this->get_attr( 'search_products_cursor' ),
					'object_types' => array( 'ITEM' ),
					'limit'        => $this->get_max_objects_to_retrieve(),
				)
			);

			$search_response = $response->get_data();

			$in_progress['unprocessed_search_response'] = wp_json_encode( $search_response, JSON_PRETTY_PRINT );
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

						$product_id = wc_get_product_id_by_sku( $catalog_variation->getItemVariationData()->getSku() );

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
	 * @throws \Exception
	 */
	protected function upsert_new_products() {

		$product_ids           = $this->get_attr( 'upsert_new_product_ids', $this->get_attr( 'validated_product_ids', array() ) );
		$processed_product_ids = $this->get_attr( 'processed_product_ids', array() );

		// remove IDs that have already been processed
		$product_ids = array_diff( $product_ids, $processed_product_ids );

		if ( empty( $product_ids ) ) {

			$this->complete_step( 'upsert_new_products' );
			return;
		}

		$catalog_objects = array();

		foreach ( $product_ids as $product_id ) {

			$catalog_item   = new \Square\Models\CatalogItem();
			$catalog_object = new CatalogObject( 'ITEM', Product::get_square_item_id( $product_id ) );
			$catalog_object->setItemData( $catalog_item );
			$catalog_objects[ $product_id ] = $catalog_object;
		}

		$result = $this->upsert_catalog_objects( $catalog_objects );

		// newly upserted IDs should get their inventory pushed
		$this->set_attr( 'inventory_push_product_ids', $result['processed'] );

		$processed_product_ids = array_merge( $result['processed'], $processed_product_ids );

		$this->set_attr( 'processed_product_ids', $processed_product_ids );

		if ( ! empty( $result['unprocessed'] ) ) {

			$this->set_attr( 'upsert_new_product_ids', $result['unprocessed'] );

		} else {

			// at this point, log a failure for any products that weren't processed
			foreach ( array_diff( $product_ids, $processed_product_ids ) as $product_id ) {
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
	 * @param array $objects list of catalog objects to update, as $product_id => CatalogItem
	 * @return array
	 * @throws \Exception
	 */
	protected function upsert_catalog_objects( array $objects ) {

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
		);

		$in_progress = $this->get_attr(
			'in_progress_upsert_catalog_objects',
			array(
				'batches'                           => array(),
				'staged_product_ids'                => array(),
				'total_object_count'                => null,
				'unprocessed_upsert_response'       => null,
				'mapped_client_item_ids'            => array(),
				'processed_remote_catalog_item_ids' => array(),
			)
		);

		if ( null === $in_progress['unprocessed_upsert_response'] ) {

			// need all three items to restore from in-progress
			if ( ! empty( $in_progress['batches'] ) && ! empty( $in_progress['staged_product_ids'] ) && ! empty( $in_progress['total_object_count'] ) ) {

				$staged_product_ids = $in_progress['staged_product_ids'];
				$total_object_count = $in_progress['total_object_count'];
				$batches            = array_map(
					static function ( $batch_data ) {
						return ApiHelper::deserialize( $batch_data );
					},
					$in_progress['batches']
				);
			}

			foreach ( $objects as $product_id => $object ) {

				if ( in_array( $product_id, $staged_product_ids, true ) ) {
					continue;
				}

				if ( ! $object instanceof CatalogObject ) {
					$object = $this->convert_to_catalog_object( $object );
				}

				$product                                  = wc_get_product( $product_id );
				$original_square_image_ids[ $product_id ] = $product->get_meta( '_square_item_image_id' );

				$catalog_item = new Catalog_Item( $product, $is_delete_action );
				$batch        = $catalog_item->get_batch( $object );
				$object_count = $catalog_item->get_batch_object_count();

				if ( $this->get_max_objects_total() >= $object_count + $total_object_count ) {
					$batches[]            = $batch;
					$total_object_count  += $object_count;
					$staged_product_ids[] = $product_id;
				} else {
					break;
				}
			}
		}

		$upsert_response = null;

		if ( null !== $in_progress['unprocessed_upsert_response'] ) {
			$upsert_response = ApiHelper::deserialize( $in_progress['unprocessed_upsert_response'], new BatchUpsertCatalogObjectsResponse() );
		}

		if ( ! $upsert_response instanceof BatchUpsertCatalogObjectsResponse ) {

			$start = microtime( true );

			$idempotency_key = wc_square()->get_idempotency_key( md5( serialize( $batches ) ) . time() . '_upsert_products' );
			$response        = wc_square()->get_api()->batch_upsert_catalog_objects( $idempotency_key, $batches );
			$upsert_response = $response->get_data();

			if ( ! $upsert_response instanceof BatchUpsertCatalogObjectsResponse ) {
				throw new \Exception( 'API response data is missing' );
			}

			$duration = number_format( microtime( true ) - $start, 2 );

			wc_square()->log( 'Upserted ' . count( $upsert_response->getObjects() ) . ' objects in ' . $duration . 's' );

			$in_progress['unprocessed_upsert_response'] = wp_json_encode( $response, JSON_PRETTY_PRINT );
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
		}

		$pull_inventory_variation_ids = $this->get_attr( 'pull_inventory_variation_ids', array() );

		wc_square()->log( 'Storing Square item data to WooCommerce products' );

		$start = microtime( true );

		// loop through all returned objects and store their IDs to Woo products
		foreach ( $upsert_response->getObjects() as $remote_catalog_item ) {

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
				( ! ( $original_square_image_ids[ $product_id ] && $original_square_image_ids[ $product_id ] === $product->get_meta( '_square_item_image_id' ) ) ) ) {
				// there is no batch image endpoint
				$this->push_product_image( $product );

			}

			$in_progress['processed_remote_catalog_item_ids'][] = $remote_item_id;

			$result['processed'][] = $product->get_id();
			$result['unprocessed'] = array_diff( $product_ids, $result['processed'] );
		}

		$this->set_attr( 'pull_inventory_variation_ids', $pull_inventory_variation_ids );

		$duration = number_format( microtime( true ) - $start, 2 );

		wc_square()->log( 'Stored Square data to ' . count( $result['processed'] ) . ' products in ' . $duration . 's' );

		// log any failed products
		foreach ( array_diff( $staged_product_ids, $successful_product_ids ) as $product_id ) {

			Records::set_record(
				array(
					'type'       => 'alert',
					'product_id' => $product_id,
					'message'    => sprintf(
						/* translators: Placeholder: %s - product ID */
						esc_html__( 'Product %s could not be updated in Square.', 'woocommerce-square' ),
						'<a href="' . esc_url( get_edit_post_link( $product_id ) ) . '">' . $product_id . '</a>'
					),
				)
			);
		}

		$this->set_attr( 'in_progress_upsert_catalog_objects', null );

		$result['processed']   = $staged_product_ids;
		$result['unprocessed'] = array_diff( $product_ids, $staged_product_ids );

		return $result;
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
		$object_data_string = is_string( $object_data ) ? $object_data : wp_json_encode( $object_data );
		$object_data_obj    = is_string( $object_data ) ? json_decode( $object_data ) : $object_data;

		$catalog_object = new CatalogObject( $object_data_obj->type, $object_data_obj->id );
		$object         = ApiHelper::deserialize( $object_data_string, $catalog_object );

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
	 * Pushes WooCommerce inventory to Square for synced items.
	 *
	 * @since 2.0.0
	 *
	 * @throws \Exception
	 */
	protected function push_inventory() {

		$product_ids            = $this->get_attr( 'inventory_push_product_ids', array() );
		$count                  = $this->get_attr( 'push_inventory_count', 0 );
		$inventory_changes      = array();
		$inventory_change_count = 0;

		foreach ( $product_ids as $key => $product_id ) {

			$product             = wc_get_product( $product_id );
			$square_variation_id = Product::get_square_item_variation_id( $product_id, false );

			if ( $product instanceof \WC_Product ) {

				$product_inventory_changes = array();

				if ( $product->is_type( 'variable' ) && $product->has_child() ) {

					foreach ( $product->get_children() as $child_id ) {

						$child            = wc_get_product( $child_id );
						$inventory_change = Product::get_inventory_change_physical_count_type( $child );

						if ( $child instanceof \WC_Product && $child->get_manage_stock() && $inventory_change ) {

							$product_inventory_changes[] = $inventory_change;
						}
					}
				} elseif ( $square_variation_id ) {

					$inventory_change = Product::get_inventory_change_physical_count_type( $product );

					if ( $inventory_change && $product->get_manage_stock() ) {

						$product_inventory_changes[] = $inventory_change;
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
			$idempotency_key   = wc_square()->get_idempotency_key( md5( serialize( $inventory_changes ) ) . '_change_inventory' );
			$result            = wc_square()->get_api()->batch_change_inventory( $idempotency_key, $inventory_changes );
		}

		$this->set_attr( 'inventory_push_product_ids', $product_ids );
		$this->set_attr( 'push_inventory_count', $count + count( $inventory_changes ) );

		if ( empty( $product_ids ) ) {

			$this->complete_step( 'push_inventory' );
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

			} else {

				$response_data = ApiHelper::deserialize( $response_data, new SearchCatalogObjectsResponse() );
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
				$square_variation_ids      = array_map(
					function( $square_product_variation ) {
						return wc_get_product_id_by_sku( $square_product_variation->getItemVariationData()->getSku() );
					},
					$square_product_variations
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

				$found_product_id = wc_get_product_id_by_sku( $variation->getItemVariationData()->getSku() );

				// bail if this product has already been processed
				if ( in_array( $found_product_id, $processed_product_ids, false ) ) { // phpcs:ignore WordPress.PHP.StrictInArray.FoundNonStrictFalse
					break;
				}

				$found_product = wc_get_product( $found_product_id );

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

				Product::update_from_square( $product, $square_object->getItemData(), false );

				$image_id = Product::get_catalog_item_thumbnail_id( $square_object );
				Product::update_image_from_square( $product, $image_id );

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
			$response_data = ApiHelper::deserialize( $in_progress['response_data'], new BatchRetrieveInventoryCountsResponse() );
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

		foreach ( $catalog_objects_tracking_stats as $catalog_object_id => $is_tracking_inventory ) {

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
					$product->set_stock_status( 'instock' );
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
	 * Gets the maximum number of objects to retrieve in a single sync job.
	 *
	 * @since 2.0.0
	 *
	 * @return int
	 */
	protected function get_max_objects_to_retrieve() {
		$max = $this->get_attr( 'max_objects_to_retrieve', 100 );

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

		$max = $this->get_attr( 'max_objects_per_upsert', 500 );

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
