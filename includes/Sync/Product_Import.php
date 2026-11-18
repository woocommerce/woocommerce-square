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

use Square\Models\SearchCatalogObjectsResponse;
use WooCommerce\Square\Handlers\Category;
use WooCommerce\Square\Handlers\Product;
use WooCommerce\Square\Utilities\Money_Utility;

defined( 'ABSPATH' ) || exit;

/**
 * Class to represent a synchronization job to import products from Square.
 *
 * @since 2.0.0
 */
class Product_Import extends Stepped_Job {

	/**
	 * Product's existing attributes at Woo.
	 */
	protected $woo_attributes = array();


	protected function assign_next_steps() {

		$this->set_attr(
			'next_steps',
			array(
				'fetch_options_data',
				'import_products',
				'import_inventory',
			)
		);
	}


	/**
	 * Gets the limit for how many items to import per request.
	 *
	 * Square has a hard maximum for this at 1000, but 100 seems to be a sane
	 * default to allow for creating products without timing out.
	 *
	 * @since 2.0.0
	 *
	 * @return int
	 */
	protected function get_import_api_limit() {

		/**
		 * Filters the number of items to import from the Square API per request.
		 *
		 * @since 2.0.0
		 *
		 * @param int limit
		 */
		$limit = (int) apply_filters( 'wc_square_import_api_limit', 100 );

		return max( 1, min( 1000, $limit ) );
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

		// Logged per page, not just at the end, so a read that stops short is visible here as a
		// count that never reaches the number of options Square holds. Without it a truncated read
		// only surfaces much later, as Square rejecting an option it already has.
		if ( isset( $result[1] ) && is_array( $result[1] ) ) {
			$option_count = count( $result[1] );

			wc_square()->log(
				sprintf(
					'Fetched item options from Square: %1$d accumulated, %2$s.',
					$option_count,
					empty( $new_cursor ) ? 'read complete' : 'more pages to follow'
				)
			);
		}

		if ( ! empty( $new_cursor ) ) {
			$this->set_attr( 'fetch_options_data_cursor', $new_cursor );
		} else {
			$this->set_attr( 'fetch_options_data_cursor', null );
			$this->complete_step( 'fetch_options_data' );
		}
	}

	/**
	 * Performs a product import.
	 *
	 * @since 2.0.0
	 *
	 * @throws \Exception
	 */
	protected function import_products() {

		$cursor                        = $this->get_attr( 'fetch_products_cursor' );
		$imported_product_ids          = $this->get_attr( 'processed_product_ids', array() );
		$updated_product_ids           = $this->get_attr( 'updated_product_ids', array() );
		$skipped_products              = $this->get_attr( 'skipped_products', array() );
		$update_products_during_import = $this->get_attr( 'update_products_during_import', false );

		$response = wc_square()->get_api()->search_catalog_objects(
			array(
				'cursor'                  => $cursor,
				'object_types'            => array( 'ITEM' ),
				'include_related_objects' => true,
				'limit'                   => $this->get_import_api_limit(),
			)
		);

		if ( ! $response->get_data() instanceof SearchCatalogObjectsResponse ) {
			throw new \Exception( 'API response data is invalid' );
		}

		$related_objects = $response->get_data()->getRelatedObjects();
		$categories      = array();

		if ( $related_objects && is_array( $related_objects ) ) {
			foreach ( $related_objects as $related_object ) {

				if ( 'CATEGORY' === $related_object->getType() ) {
					$categories[ $related_object->getId() ] = $related_object;
				}
			}
		}

		$catalog_objects = $response->get_data()->getObjects() ? $response->get_data()->getObjects() : array();

		foreach ( $catalog_objects as $catalog_object_index => $catalog_object ) {

			// validate permissions
			if ( ! current_user_can( 'publish_products' ) ) {
				$this->record_error( 'You do not have permission to create products' );
				break; // use a break so we don't continue trying to import products without permissions
			}

			// validate Square Catalog object (API data)
			if ( ! $catalog_object instanceof \Square\Models\CatalogObject || ! $catalog_object->getItemData() instanceof \Square\Models\CatalogItem ) {
				$this->record_error( 'Invalid data' );
				continue;
			}

			$item_id = $catalog_object->getId();

			// Ignore items that are available at all locations, but absent at ours.
			if ( is_array( $catalog_object->getAbsentAtLocationIds() ) && in_array( wc_square()->get_settings_handler()->get_location_id(), $catalog_object->getAbsentAtLocationIds(), true ) ) {
				$skipped_products[ $item_id ] = null;
				continue;
			}

			// Ignore items that are not available at our location.
			if ( ! $catalog_object->getPresentAtAllLocations() && ( ! is_array( $catalog_object->getPresentAtLocationIds() ) || ! in_array( wc_square()->get_settings_handler()->get_location_id(), $catalog_object->getPresentAtLocationIds(), true ) ) ) {
				$skipped_products[ $item_id ] = null;
				continue;
			}

			$product_id = (int) Product::get_product_id_by_square_id( $item_id );
			$product    = ! empty( $product_id ) ? wc_get_product( $product_id ) : null;

			if ( in_array( $product_id, array_merge( $imported_product_ids, $updated_product_ids ), true ) ) {
				continue; // don't import/update the same product twice.

			} elseif ( $product_id && ! $update_products_during_import ) {
				$skipped_products[ $item_id ] = null;
				continue;

			} elseif ( $product_id && ! $product ) {
				$this->record_error( 'Product not found', $catalog_object, 'update' );
				continue;
			}

			// Import or update categories related to the products that are being imported.
			$item_data = $catalog_object->getItemData();
			if ( $item_data->getCategories() && is_array( $item_data->getCategories() ) ) {
				foreach ( $item_data->getCategories() as $category ) {
					if ( $category instanceof \Square\Models\CatalogObjectCategory ) {
						$catalog_category_id = $category->getId();
						if ( $catalog_category_id && isset( $categories[ $catalog_category_id ] ) ) {
							Category::import_or_update( $categories[ $catalog_category_id ] );
							unset( $categories[ $catalog_category_id ] ); // don't import/update the same category multiple times per batch.
						}
					}
				}
			}

			$data = $this->extract_product_data( $catalog_object, $product );

			if ( ! $data ) {
				$skipped_products[ $item_id ] = null;
				continue;
			}

			/**
			 * Filters the data that is used to create update a WooCommerce product during import.
			 *
			 * @since 2.0.0
			 *
			 * @param array $data product data
			 * @param \Square\Models\CatalogObject $catalog_object the catalog object from the Square API
			 * @param Product_Import $this import class instance
			 */
			$data = apply_filters( 'woocommerce_square_create_product_data', $data, $catalog_object, $this );

			// set default type
			$data['type'] = ! empty( $data['type'] ) ? $data['type'] : 'simple';

			// if an item matches an existing product, update the product using data from Square
			if ( $product ) {
				$product_id = $this->update_product( $product, $data );

				if ( $product_id ) {
					$updated_product_ids[] = $product_id;
				}
			} elseif ( $this->item_variation_has_matching_sku( $data ) ) {
				// look in variation SKUs for a match - if so, skip the parent item, a normal sync should link it automatically
				continue;
			} else {
				$product_id = $this->import_product( $data );

				if ( $product_id ) {
					Product::set_synced_with_square( $product_id );
					$imported_product_ids[] = $product_id;
				}
			}
		}

		wc_square()->log( 'Imported New Products Count: ' . count( $imported_product_ids ) );
		wc_square()->log( 'Updated Products Count: ' . count( $updated_product_ids ) );

		$cursor = $response->get_data()->getCursor();
		$this->set_attr( 'fetch_products_cursor', $cursor );

		$this->set_attr( 'skipped_products', $skipped_products );
		$this->set_attr( 'updated_product_ids', $updated_product_ids );
		$this->set_attr( 'processed_product_ids', $imported_product_ids );

		if ( ! $cursor ) {
			$this->complete_step( 'import_products' );
		}
	}


	/**
	 * Imports inventory counts for all the tracked Square products.
	 *
	 * @since 2.0.0
	 *
	 * @throws \Exception
	 */
	protected function import_inventory() {

		$search_result = wc_square()->get_api()->search_catalog_objects(
			array(
				'object_types' => array( 'ITEM_VARIATION' ),
				'limit'        => 100,
				'cursor'       => $this->get_attr( 'import_inventory_cursor', null ),
			)
		);

		if ( ! $search_result->get_data() instanceof SearchCatalogObjectsResponse ) {
			throw new \Exception( 'API response data is invalid' );
		}

		$count         = $this->get_attr( 'import_inventory_count', 0 );
		$cursor        = $search_result->get_data()->getCursor();
		$objects       = $search_result->get_data()->getObjects() ? $search_result->get_data()->getObjects() : array();
		$variation_ids = array_map(
			static function ( \Square\Models\CatalogObject $catalog_object ) {
				return $catalog_object->getId();
			},
			$objects
		);

		$catalog_objects_hash = Helper::get_catalog_inventory_tracking( $objects );

		$result = wc_square()->get_api()->batch_retrieve_inventory_counts(
			array(
				'catalog_object_ids' => $variation_ids,
				'location_ids'       => array( wc_square()->get_settings_handler()->get_location_id() ),
				'states'             => array( 'IN_STOCK' ),
			)
		);

		/* We maintain this hash because batch_retrieve_inventory_counts doesn't return any catalog objects if they
		 * are not tracked.
		 *
		 * This is why, we instead iterate on $objects in the next steps and use $inventory_hash to set inventory.
		 */
		$inventory_hash = array();

		foreach ( $result->get_counts() as $inventory_count ) {
			$inventory_hash[ $inventory_count->getCatalogObjectId() ] = $inventory_count->getQuantity();
		}

		// Verify zero counts against Square's inventory change history: a never-counted item
		// reports IN_STOCK 0 exactly like a real sellout, and only real zeros may be written
		// (SQUARE-145).
		$zero_object_ids   = Helper::zero_count_object_ids( $inventory_hash );
		$verified_zero_ids = $this->resolve_zero_count_verification( 'import_inventory', $zero_object_ids );

		if ( null === $verified_zero_ids ) {
			// Verification unavailable and retries remain: return WITHOUT advancing the import cursor
			// so the step runs again; throwing would fail the whole import for one transient error.
			// Once the retries are spent the import proceeds writing no zeros and records an alert.
			return;
		}

		foreach ( $objects as $catalog_object ) {

			// all inventory should be tied to a variation, but check here just in case
			if ( 'ITEM_VARIATION' === $catalog_object->getType() ) {

				$product = Product::get_product_by_square_variation_id( $catalog_object->getId() );

				if ( $product && $product instanceof \WC_Product ) {

					// No "Sync with Square" gate here on purpose: an import is started by the
					// merchant, so it belongs with the explicit fetch action rather than with the
					// automatic pulls SQUARE-359 gates. Products matched and updated during an
					// import also never receive the synced flag (it is only set for newly imported
					// products), so gating here would silently skip their inventory.
					$inventory_data        = $catalog_objects_hash[ $catalog_object->getId() ] ?? array();
					$is_tracking_inventory = $inventory_data['track_inventory'] ?? false;
					$sold_out              = $inventory_data['sold_out'] ?? false;

					if ( $is_tracking_inventory && isset( $inventory_hash[ $catalog_object->getId() ] ) ) {

						$changed = Helper::apply_square_inventory_count(
							$product,
							(float) $inventory_hash[ $catalog_object->getId() ],
							(bool) $sold_out,
							in_array( $catalog_object->getId(), $verified_zero_ids, true )
						);

						if ( ! $changed ) {
							continue;
						}
					} elseif ( ! $is_tracking_inventory ) {

						// Not tracked in Square: reflect availability. Stock management follows only
						// when Square owns the setting; under WooCommerce SOR it is merchant intent
						// and is left alone, so a Square side toggle cannot invert the system of
						// record.
						$product->set_stock_status( $sold_out ? 'outofstock' : 'instock' );

						if ( wc_square()->get_settings_handler()->is_system_of_record_square() ) {
							$product->set_manage_stock( false );
						}
					} else {

						// Tracked but no IN_STOCK count returned: "no information", never a zero.
						continue;
					}

					$product->save();
				}
			}
		}

		$this->set_attr( 'import_inventory_count', $count + count( $objects ) );
		$this->set_attr( 'import_inventory_cursor', $cursor );

		if ( ! $cursor ) {

			$this->complete_step( 'import_inventory' );
		}
	}


	/**
	 * Determines whether any catalog item variation is missing a SKU
	 *
	 * @since 2.2.0
	 *
	 * @param \Square\Models\CatalogObject $catalog_object the catalog object
	 * @return bool
	 */
	private function item_variation_has_missing_sku( $catalog_object ) {
		$missing_sku = true;

		foreach ( $catalog_object->getItemData()->getVariations() as $variation ) {
			if ( in_array( trim( $variation->getItemVariationData()->getSku() ), array( '', null ), true ) ) { // can't use empty() because a valid SKU could be '0' which returns true
				$missing_sku = true;
				break;
			}

			$missing_sku = false;
		}

		return $missing_sku;
	}


	/**
	 * Determines whether a SKU within a catalog item is found in WooCommerce.
	 *
	 * @since 2.0.0
	 *
	 * @param array $data the catalog object data
	 * @return bool
	 */
	private function item_variation_has_matching_sku( $data ) {

		if ( 'simple' === $data['type'] ) {
			return (bool) wc_get_product_id_by_sku( $data['sku'] );
		} else {
			foreach ( $data['variations'] as $variation ) {
				$variation_id = wc_get_product_id_by_sku( $variation['sku'] );

				if ( $variation_id ) {
					// found variation with matching SKU, check if parent still exists and return that result
					return (bool) Product::get_parent_product_id_by_variation_id( $variation_id );
				}
			}
		}
		return false;
	}


	/**
	 * Creates a product from catalog object data.
	 *
	 * @since 2.0.0
	 *
	 * @param array $data the catalog object data
	 * @return int|null
	 */
	private function import_product( $data ) {
		try {
			$product_id = $this->create_product_from_square_data( $data );

			// save product meta fields
			$this->save_product_meta( $product_id, $data );

			// save the image, if included
			if ( ! empty( $data['image_id'] ) ) {
				Product::update_image_from_square( $product_id, $data['image_id'], true );
			}

			// save variations
			if ( 'variable' === $data['type'] && is_array( $data['variations'] ) && isset( $data['type'], $data['variations'] ) ) {

				$this->save_variations( $product_id, $data );
			}

			/**
			 * Fired when a product is created from a square product import.
			 *
			 * @since 2.0.0
			 *
			 * @param int $product_id the product ID that was created
			 * @param array $data the data used to create the product
			 */
			do_action( 'woocommerce_square_create_product', $product_id, $data );

			// clear cache/transients
			wc_delete_product_transients( $product_id );
		} catch ( \Exception $e ) {
			// remove the product when creation fails
			if ( ! empty( $product_id ) ) {
				$this->clear_product( $product_id );
			}
			$product_id = 0;

			$this->record_error( $e->getMessage(), $data );
		}

		return $product_id;
	}

	/**
	 * Updates a product from catalog object data
	 *
	 * @since 2.2.0
	 *
	 * @param \WC_Product $product existing product in Woo that is being updated
	 * @param array $data the Square catalog object data
	 * @return int|null
	 */
	public function update_product( $product, $data ) {
		$product_id = $product->get_id();

		try {
			wc_transaction_query( 'start' );

			// update an existing product from a simple to a variable product if it has at least two variations
			if ( ! $product instanceof \WC_Product_Variable && 'variable' === $data['type'] ) {
				$product_id = $this->update_simple_product_to_variable( $product_id, $data );
				$product    = wc_get_product( $product_id );

				if ( ! $product instanceof \WC_Product ) {
					throw new \Exception( esc_html__( 'Could not convert the product to a variable product.', 'woocommerce-square' ) );
				}
			}

			$product->set_name( $data['title'] );
			$product->set_description( $data['description'] );
			$product->save();

			// save product meta fields
			$this->save_product_meta( $product_id, $data );

			// save the image, if included
			Product::update_image_from_square( $product_id, $data['image_id'], false );

			// save/update variations
			if ( isset( $data['type'], $data['variations'] ) && 'variable' === $data['type'] && is_array( $data['variations'] ) ) {
				$this->save_variations( $product_id, $data );
			}

			wc_transaction_query( 'commit' );

			wc_delete_product_transients( $product_id );
		} catch ( \Exception $e ) {
			// undo any updated data when updating fails
			wc_transaction_query( 'rollback' );
			$product_id = 0;

			$this->record_error( $e->getMessage(), $data, 'update' );
		}

		return $product_id;
	}

	/**
	 * Convert an existing simple product to a variable when new variations are found.
	 * This function
	 *
	 * @since 2.2.0
	 *
	 * @param int $variation_id simple product ID being updated to a variation with new parent product
	 * @param array $data
	 * @return int|null
	 */
	protected function update_simple_product_to_variable( $variation_id, $data = array() ) {
		$simple_product = wc_get_product( $variation_id );
		$total_sales    = $simple_product instanceof \WC_Product ? $simple_product->get_total_sales( 'edit' ) : 0;

		// create a new parent product
		$parent_product_id = $this->create_product_from_square_data( $data );

		// WooCommerce has no CRUD method to change a post type, so move the post over before loading it as a variation
		wp_update_post(
			array(
				'ID'           => $variation_id,
				'post_content' => '',
				'post_author'  => get_current_user_id(),
				'post_parent'  => $parent_product_id,
				'post_type'    => 'product_variation',
			)
		);

		\WC_Cache_Helper::invalidate_cache_group( 'product_' . $variation_id );

		// saving the variation also removes the simple product type term
		$variation = new \WC_Product_Variation( $variation_id );
		$variation->set_parent_id( $parent_product_id );

		// remove simple product meta that doesn't exist or needs be updated on the variation
		$variation->delete_meta_data( Product::SQUARE_ID_META_KEY );
		$variation->delete_meta_data( Product::SQUARE_VERSION_META_KEY );
		$variation->delete_meta_data( Product::SQUARE_IMAGE_ID_META_KEY );
		$variation->save();

		// legacy pre-WC 3.0 key written by earlier versions of this importer, not managed by the CRUD layer
		delete_post_meta( $variation_id, '_visibility' );

		// copy total sales from previous simple product over to new parent variable product
		if ( $total_sales ) {
			$parent_product = wc_get_product( $parent_product_id );

			if ( $parent_product instanceof \WC_Product ) {
				$parent_product->set_total_sales( $total_sales );
				$parent_product->save();
			}
		}

		return $parent_product_id;
	}

	/**
	 * Extracts product data from a CatalogObject to an array of data.
	 *
	 * @since 2.0.0
	 *
	 * @param \Square\Models\CatalogObject $catalog_object the catalog object
	 * @param WC_Product|null $product
	 * @return array|null
	 * @throws \Exception
	 */
	public function extract_product_data( $catalog_object, $product = null ) {

		$variations = $catalog_object->getItemData()->getVariations() ? $catalog_object->getItemData()->getVariations() : array();

		// if there are no variations, something is wrong - every catalog item has at least one
		if ( 0 >= count( $variations ) ) {
			return null;
		}

		$product_name        = $catalog_object->getItemData()->getName();
		$product_description = Product::get_catalog_item_description( $catalog_object->getItemData() );

		if ( $product ) {
			/**
			 * Allow overriding product name during import from Square
			 *
			 * @since 3.3.0
			 *
			 * @param string                           $product_name Product name to update.
			 * @param \SquareConnect\Model\CatalogItem $catalog_object Catalog item being imported.
			 * @param \WC_Product                      $product Product being updated.
			 * @return false|string String to override the product name, false to disable updating
			 *                      and keep existing name.
			 * @since 3.3.0
			 */
			$product_name = apply_filters( 'wc_square_update_product_set_name', $product_name, $catalog_object, $product );

			/**
			 * Allow overriding product description during import from Square
			 *
			 * @since 3.3.0
			 *
			 * @param string                           $product_description Product description to update.
			 * @param \SquareConnect\Model\CatalogItem $catalog_object Catalog item being imported.
			 * @param \WC_Product                      $product Product being updated.
			 *
			 * @return false|string String to override the product description, false to disable updating
			 *                      and keep existing description.
			 * @since 3.3.0
			 */
			$product_description = apply_filters( 'wc_square_update_product_set_description', $product_description, $catalog_object, $product );
		}

		$data = array(
			'title'       => $product_name,
			'type'        => ( 1 === count( $variations ) && ! ( $product && $product instanceof \WC_Product_Variable ) ) ? 'simple' : 'variable',
			'sku'         => '', // make sure to reset SKU when simple product is updated to variable.
			'description' => $product_description,
			'image_id'    => Product::get_catalog_item_thumbnail_id( $catalog_object ),
			'categories'  => array(), // Will be populated below.
			'square_meta' => array(
				'item_id'      => $catalog_object->getId(),
				'item_version' => $catalog_object->getVersion(),
			),
			'custom_meta' => array(),
		);

		// Process multiple categories from Square.
		$data['categories'] = Category::get_category_ids_from_catalog_item( $catalog_object->getItemData() );

		// variable product
		if ( 'variable' === $data['type'] ) {
			$data['attributes'] = array();
			$data['variations'] = array();

			// Get Woo product's existing attributes.
			$this->woo_attributes = $product ? $product->get_attributes() : array();

			foreach ( $variations as $variation ) {

				// sanity check for valid API data
				if ( ! $variation instanceof \Square\Models\CatalogObject || ! $variation->getItemVariationData() instanceof \Square\Models\CatalogItemVariation ) {
					continue;
				}

				// Ignore variations that are available at all locations, but absent at ours.
				if ( is_array( $variation->getAbsentAtLocationIds() ) && in_array( wc_square()->get_settings_handler()->get_location_id(), $variation->getAbsentAtLocationIds(), true ) ) {
					continue;
				}

				// Ignore variations that are not available at our location.
				if ( ! $variation->getPresentAtAllLocations() && ( ! is_array( $variation->getPresentAtLocationIds() ) || ! in_array( wc_square()->get_settings_handler()->get_location_id(), $variation->getPresentAtLocationIds(), true ) ) ) {
					continue;
				}

				try {
					$data['variations'][] = $this->extract_square_item_variation_data( $variation );

				} catch ( \Exception $exception ) {

					// alert for failed variation imports
					Records::set_record(
						array(
							'type'    => 'alert',
							'message' => sprintf(
								/* translators: Placeholders: %1$s - Square item name, %2$s - Square item variation name, %3$s - failure reason */
								__( 'Could not import "%1$s - %2$s" from Square. %3$s', 'woocommerce-square' ),
								$catalog_object->getItemData()->getName(),
								$variation->getItemVariationData()->getName(),
								$exception->getMessage()
							),
						)
					);
				}
			}

			if ( ! count( $data['variations'] ) ) {
				return null;
			}

			$options = $catalog_object->getItemData()->getItemOptions() ? $catalog_object->getItemData()->getItemOptions() : array();

			if ( count( $options ) ) {
				$data['attributes']                      = $this->extract_attributes_from_square_options( $options, $data['variations'] );
				$data['custom_meta']['_dynamic_options'] = true;
			} else {
				$data['attributes']                      = $this->extract_attributes_from_square_variations( $data['variations'] );
				$data['custom_meta']['_dynamic_options'] = false;
			}
		} else { // simple product
			try {

				$variation = $this->extract_square_item_variation_data( $variations[0] );

				$data['type']           = 'simple';
				$data['sku']            = ! empty( $variation['sku'] ) ? $variation['sku'] : null;
				$data['regular_price']  = ! empty( $variation['regular_price'] ) ? $variation['regular_price'] : null;
				$data['stock_quantity'] = ! empty( $variation['stock_quantity'] ) ? $variation['stock_quantity'] : null;
				$data['managing_stock'] = ! empty( $variation['managing_stock'] ) ? $variation['managing_stock'] : null;

				$data['square_meta']['item_variation_id']      = ! empty( $variation['square_meta']['item_variation_id'] ) ? $variation['square_meta']['item_variation_id'] : null;
				$data['square_meta']['item_variation_version'] = ! empty( $variation['square_meta']['item_variation_version'] ) ? $variation['square_meta']['item_variation_version'] : null;

			} catch ( \Exception $exception ) {

				Records::set_record(
					array(
						'type'    => 'alert',
						'message' => sprintf(
						/* translators: Placeholders: %1$s - Square item name, %2$s - failure reason */
							__( 'Could not import "%1$s" from Square. %2$s', 'woocommerce-square' ),
							$catalog_object->getItemData()->getName(),
							$exception->getMessage()
						),
					)
				);

				return null;
			}
		}

		return $data;
	}

	/**
	 * Extracts data from a CatalogItemVariation for insertion into a WC product.
	 *
	 * @since 2.0.0
	 *
	 * @param \Square\Models\CatalogObject $variation the variation object
	 * @return array
	 * @throws \Exception
	 */
	protected function extract_square_item_variation_data( $variation ) {

		$variation_data = $variation->getItemVariationData();

		if ( 'VARIABLE_PRICING' === $variation_data->getPricingType() ) {
			throw new \Exception( esc_html__( 'Items with variable pricing cannot be imported.', 'woocommerce-square' ) );
		}

		if ( in_array( $variation_data->getSku(), array( '', null ), true ) ) {
			throw new \Exception( esc_html__( 'Variations with missing SKUs cannot be imported.', 'woocommerce-square' ) );
		}

		$variation_options = $variation_data->getItemOptionValues();

		$attributes = array();

		foreach ( $variation_options as $variation_option ) {
			$option_id       = $variation_option->getItemOptionId();
			$option_value_id = $variation_option->getItemOptionValueId();
			$result          = wc_square()->get_api()->retrieve_options_data();
			$options_data    = isset( $result[1] ) ? $result[1] : array();

			if ( isset( $options_data[ $option_id ] ) && isset( $options_data[ $option_id ]['value_ids'][ $option_value_id ] ) ) {
				$option_name    = $options_data[ $option_id ]['name'];
				$option_matched = $options_data[ $option_id ]['value_ids'][ $option_value_id ];
			} else {
				// Fetch option data from Square.
				$response      = wc_square()->get_api()->retrieve_catalog_object( $option_id );
				$option_object = $response->get_data()->getObject();
				$option_name   = wc_square()->get_api()->get_item_option_name_from_catalog_object( $option_object );

				$option_values_object = $option_object && $option_object->getItemOptionData() ? $option_object->getItemOptionData()->getValues() : array();
				$option_matched       = '';
				$option_values        = array();
				$option_value_ids     = array();

				foreach ( $option_values_object as $option_value ) {
					$option_value_name = $option_value->getItemOptionValueData()->getName();
					$option_values[]   = $option_value_name;

					$option_value_ids[ $option_value->getId() ] = $option_value_name;

					if ( $option_value_id === $option_value->getId() ) {
						$option_matched = $option_value_name;
					}
				}

				$options_data[ $option_id ] = array(
					'name'      => $option_name,
					'values'    => $option_values,
					'value_ids' => $option_value_ids,
				);

				// Handed to the API layer rather than written straight to the finished cache: the
				// read above never walks the cursor, so its set can be the first page of a
				// paginated catalogue and must not be published as the whole of it.
				wc_square()->get_api()->cache_option_data( $option_id, $options_data[ $option_id ] );
			}

			$attributes[] = array(
				'name'         => str_replace( 'pa_', '', $option_name ),
				'slug'         => str_replace( 'pa_', '', sanitize_title( $option_name ) ),
				'is_variation' => true,
				'option'       => $option_matched,
				'pa_prefix'    => strpos( $option_name, 'pa_' ) !== false,
			);
		}

		if ( ! $variation_options ) {
			$attribute_name = ! empty( reset( $this->woo_attributes ) ) ? reset( $this->woo_attributes )->get_name() : 'Attribute';
			$attributes[]   = array(
				'name'         => str_replace( 'pa_', '', $attribute_name ),
				'slug'         => str_replace( 'pa_', '', sanitize_title( $attribute_name ) ),
				'is_variation' => true,
				'option'       => $variation_data->getName(),
				'pa_prefix'    => strpos( $attribute_name, 'pa_' ) !== false,
			);
		}

		$data = array(
			'name'           => $variation_data->getName(),
			'sku'            => $variation_data->getSku(),
			'regular_price'  => $variation_data->getPriceMoney() && $variation_data->getPriceMoney()->getAmount() ? Money_Utility::cents_to_float( $variation->getItemVariationData()->getPriceMoney()->getAmount() ) : null,
			'stock_quantity' => null,
			'managing_stock' => true,
			'square_meta'    => array(
				'item_variation_id'      => $variation->getId(),
				'item_variation_version' => $variation->getVersion(),
			),
			'attributes'     => $attributes,
		);

		return $data;
	}

	/**
	 * Extracts attributes from Square options.
	 *
	 * @since 4.9.0
	 *
	 * @param array $options    the Square options
	 * @param array $variations the product variations to determine which option values are actually used
	 * @return array
	 * @throws \Exception
	 */
	protected function extract_attributes_from_square_options( $options, $variations = array() ) {

		$data_attributes = array();

		// Collect all option values that are actually used by variations.
		$used_option_values = array();
		foreach ( $variations as $variation ) {
			if ( isset( $variation['attributes'] ) && is_array( $variation['attributes'] ) ) {
				foreach ( $variation['attributes'] as $attribute ) {
					if ( isset( $attribute['name'] ) && isset( $attribute['option'] ) ) {
						$attribute_name = $attribute['name'];
						if ( ! isset( $used_option_values[ $attribute_name ] ) ) {
							$used_option_values[ $attribute_name ] = array();
						}
						$used_option_values[ $attribute_name ][] = $attribute['option'];
					}
				}
			}
		}

		foreach ( $options as $option ) {
			$option_id = $option->getItemOptionId();

			$result       = wc_square()->get_api()->retrieve_options_data();
			$options_data = isset( $result[1] ) ? $result[1] : array();

			if ( isset( $options_data[ $option_id ] ) && isset( $options_data[ $option_id ]['values'] ) ) {
				$option_name   = $options_data[ $option_id ]['name'];
				$option_values = $options_data[ $option_id ]['values'];
			} else {
				// Fetch option name from Square.
				$response             = wc_square()->get_api()->retrieve_catalog_object( $option_id );
				$option_object        = $response->get_data()->getObject();
				$option_name          = wc_square()->get_api()->get_item_option_name_from_catalog_object( $option_object );
				$option_values_object = $option_object && $option_object->getItemOptionData() ? $option_object->getItemOptionData()->getValues() : array();
				$option_value_ids     = array();
				$option_values        = array();

				foreach ( $option_values_object as $option_value ) {
					$option_values[]    = $option_value->getItemOptionValueData()->getName();
					$option_value_ids[] = $option_value->getId();
				}

				$options_data[ $option_id ] = array(
					'name'      => $option_name,
					'values'    => $option_values,
					'value_ids' => array_combine( $option_value_ids, $option_values ),
				);

				// Same reason as above: an unlooped read must not publish under the finished cache.
				wc_square()->get_api()->cache_option_data( $option_id, $options_data[ $option_id ] );
			}

			// Filter option values to only include those actually used by variations.
			$clean_option_name = str_replace( 'pa_', '', $option_name );
			$filtered_values   = $option_values;

			if ( isset( $used_option_values[ $clean_option_name ] ) ) {
				$filtered_values = array_intersect( $option_values, array_unique( $used_option_values[ $clean_option_name ] ) );
			}

			$data_attributes[] = array(
				'name'      => $clean_option_name,
				'slug'      => str_replace( 'pa_', '', sanitize_title( $option_name ) ),
				'visible'   => true,
				'variation' => true,
				'options'   => $filtered_values,
				'pa_prefix' => strpos( $option_name, 'pa_' ) !== false,
			);
		}

		return $data_attributes;
	}

	/**
	 * Extracts attributes from Square variations.
	 *
	 * @since 4.9.0
	 *
	 * @param array $variations the variations
	 * @return array
	 */
	protected function extract_attributes_from_square_variations( $variations ) {

		$attribute_name = ! empty( reset( $this->woo_attributes ) ) ? reset( $this->woo_attributes )->get_name() : 'Attribute';
		$attributes[]   = array(
			'name'      => str_replace( 'pa_', '', $attribute_name ),
			'slug'      => str_replace( 'pa_', '', sanitize_title( $attribute_name ) ),
			'visible'   => true,
			'variation' => true,
			'options'   => wp_list_pluck( $variations, 'name' ),
			'pa_prefix' => strpos( $attribute_name, 'pa_' ) !== false,
		);

		return $attributes;
	}


	protected function save_product_images( $product_id, $images ) {}


	protected function upload_product_image( $src ) {}


	protected function set_product_image_as_attachment( $upload, $product_id ) {}


	/**
	 * Saves product meta data for a given product.
	 *
	 * @since 2.0.0
	 *
	 * @param int $product_id the product ID
	 * @param array $data the product data
	 * @return bool
	 * @throws \Exception
	 */
	protected function save_product_meta( $product_id, $data ) {

		$product = wc_get_product( $product_id );

		if ( ! $product instanceof \WC_Product ) {
			/* translators: Placeholders: %d - product ID */
			throw new \Exception( sprintf( esc_html__( 'Invalid product ID %d', 'woocommerce-square' ), absint( $product_id ) ) );
		}

		// product type
		$product_type = $product->get_type();

		if ( isset( $data['type'] ) ) {

			$product_type = wc_clean( $data['type'] );

			// load the product with the class for its new type, the type term is updated on save
			if ( ! $product->is_type( $product_type ) ) {
				$classname = \WC_Product_Factory::get_product_classname( $product_id, $product_type );
				$product   = new $classname( $product_id );
			}
		}

		// catalog visibility
		if ( ! empty( $data['catalog_visibility'] ) ) {
			$product->set_catalog_visibility( wc_clean( $data['catalog_visibility'] ) );
		}

		// sku
		if ( isset( $data['sku'] ) ) {
			$this->set_product_sku( $product, $data['sku'] );
		}

		// attributes
		if ( isset( $data['attributes'] ) ) {

			$attributes = array();

			foreach ( $data['attributes'] as $attribute ) {

				$attribute_id = 0;
				$taxonomy     = null;

				if ( ! isset( $attribute['name'] ) ) {
					continue;
				}

				$attribute_slug = sanitize_title( $attribute['name'] );

				if ( isset( $attribute['slug'] ) ) {
					$taxonomy       = $this->get_attribute_taxonomy_by_slug( $attribute['slug'] );
					$attribute_slug = sanitize_title( $attribute['slug'] );
				}

				if ( $taxonomy ) {
					$attribute_id = wc_attribute_taxonomy_id_by_name( $taxonomy );

				} elseif ( isset( $attribute['pa_prefix'] ) && $attribute['pa_prefix'] ) {
					// Create new taxonomy attribute.
					$taxonomy = wc_attribute_taxonomy_name( $attribute_slug );

					if ( ! taxonomy_exists( $taxonomy ) ) {
						$attribute_name = ucfirst( wc_clean( $attribute['name'] ) );
						$attribute_args = array(
							'label' => $attribute_name,
							'name'  => $attribute_name,
							'slug'  => $attribute_slug,
						);

						$attribute_id = wc_create_attribute( $attribute_args );

						if ( is_wp_error( $attribute_id ) ) {
							throw new \Exception( esc_html( $attribute_id->get_error_message() ) );
						}

						// Register the taxonomy.
						register_taxonomy(
							$taxonomy,
							'product',
							array(
								'hierarchical' => true,
								'show_ui'      => false,
								'query_var'    => true,
								'rewrite'      => false,
							)
						);
					} else {
						$attribute_id = wc_attribute_taxonomy_id_by_name( $taxonomy );
					}
				}

				// Remove 'Any' from options.
				if ( isset( $attribute['options'] ) && is_array( $attribute['options'] ) ) {
					$attribute['options'] = array_diff( $attribute['options'], array( WC_SQUARE_OPTION_ANY ) );
				}

				if ( $attribute_id ) {

					if ( isset( $attribute['options'] ) ) {

						$options = $attribute['options'];

						if ( ! is_array( $attribute['options'] ) ) {

							// text based attributes - Posted values are term names
							$options = explode( WC_DELIMITER, $options );
						}

						$values = array_map( 'wc_sanitize_term_text_based', $options );
						$values = array_filter( $values, 'strlen' );

					} else {

						$values = array();
					}

					// attributes left out of the product are removed and their terms cleared on save
					if ( empty( $values ) ) {
						continue;
					}

					$attribute_name = $taxonomy;

				} elseif ( isset( $attribute['options'] ) ) {
					// array based
					$options = is_array( $attribute['options'] ) ? $attribute['options'] : explode( WC_DELIMITER, $attribute['options'] );

					$values         = array_map( 'wc_clean', $options );
					$attribute_name = wc_clean( $attribute['name'] );

				} else {
					continue;
				}

				$product_attribute = new \WC_Product_Attribute();
				$product_attribute->set_id( $attribute_id );
				$product_attribute->set_name( $attribute_name );
				$product_attribute->set_options( array_values( $values ) );
				$product_attribute->set_position( isset( $attribute['position'] ) ? absint( $attribute['position'] ) : 0 );
				$product_attribute->set_visible( isset( $attribute['visible'] ) && $attribute['visible'] );
				$product_attribute->set_variation( isset( $attribute['variation'] ) && $attribute['variation'] );

				$attributes[] = $product_attribute;
			}

			$product->set_attributes( $attributes );
		}

		// sales and prices
		if ( in_array( $product_type, array( 'variable', 'grouped' ), true ) ) {

			// variable and grouped products have no prices
			$product->set_regular_price( '' );
			$product->set_sale_price( '' );
			$product->set_date_on_sale_from( null );
			$product->set_date_on_sale_to( null );
			$product->set_price( '' );

		} else {

			$this->set_product_price_props( $product, $data['regular_price'] );
		}

		// product categories
		if ( isset( $data['categories'] ) && is_array( $data['categories'] ) ) {
			$product->set_category_ids( array_unique( array_map( 'intval', $data['categories'] ) ) );
		}

		// Update custom meta.
		if ( $data['custom_meta'] ) {
			foreach ( $data['custom_meta'] as $meta_key => $meta_value ) {
				$product->update_meta_data( $meta_key, $meta_value );
			}
		}

		$product->save();

		// square item id
		if ( isset( $data['square_meta']['item_id'] ) ) {
			Product::set_square_item_id( $product, $data['square_meta']['item_id'] );
		}

		// square item version
		if ( isset( $data['square_meta']['item_version'] ) ) {
			Product::set_square_version( $product, $data['square_meta']['item_version'] );
		}

		// square item variation id
		if ( isset( $data['square_meta']['item_variation_id'] ) ) {
			Product::set_square_item_variation_id( $product, $data['square_meta']['item_variation_id'] );
		}

		// square item variation version
		if ( isset( $data['square_meta']['item_variation_version'] ) ) {
			Product::set_square_variation_version( $product, $data['square_meta']['item_variation_version'] );
		}

		/**
		 * Fires after processing product meta for a product imported from Square.
		 *
		 * @since 2.0.0
		 *
		 * @param int $product_id the product ID
		 * @param array $data the product data
		 */
		do_action( 'woocommerce_square_process_product_meta_' . $product_type, $product_id, $data );

		return true;
	}


	/**
	 * Sets the SKU on a product, making sure it is unique.
	 *
	 * @since x.x.x
	 *
	 * @param \WC_Product $product the product or variation object
	 * @param string $sku the SKU from Square
	 * @throws \Exception
	 */
	private function set_product_sku( $product, $sku ) {

		$new_sku = wc_clean( $sku );

		if ( $new_sku === $product->get_sku( 'edit' ) ) {
			return;
		}

		if ( ! empty( $new_sku ) && ! wc_product_has_unique_sku( $product->get_id(), $new_sku ) ) {
			throw new \Exception( esc_html__( 'The SKU already exists on another product', 'woocommerce-square' ) );
		}

		$product->set_sku( empty( $new_sku ) ? '' : $new_sku );
	}


	/**
	 * Saves the variations for a given product.
	 *
	 * @since 2.0.0
	 *
	 * @param int $product_id the product ID
	 * @param array $data the product data, including a 'variations' key
	 * @return bool
	 * @throws \Exception
	 */
	protected function save_variations( $product_id, $data ) {

		$variations           = $data['variations'];
		$variable_product     = wc_get_product( $product_id );
		$attributes           = $variable_product ? $variable_product->get_attributes( 'edit' ) : array();
		$variations_to_remove = $variable_product ? $variable_product->get_children() : array();

		foreach ( $variations as $menu_order => $variation ) {

			$variation_id = isset( $variation['id'] ) ? absint( $variation['id'] ) : 0;

			if ( ! $variation_id && isset( $variation['sku'] ) ) {

				$variation_sku = wc_clean( $variation['sku'] );
				$variation_id  = wc_get_product_id_by_sku( $variation_sku );
			}

			$is_new_variation = ! $variation_id;
			$variation_object = $is_new_variation ? new \WC_Product_Variation() : wc_get_product( $variation_id );

			if ( ! $variation_object instanceof \WC_Product_Variation ) {
				/* translators: Placeholders: %d - product ID */
				throw new \Exception( sprintf( esc_html__( 'Product #%d is not a product variation', 'woocommerce-square' ), absint( $variation_id ) ) );
			}

			$variation_object->set_parent_id( $product_id );
			$variation_object->set_menu_order( $menu_order );

			if ( $is_new_variation || isset( $variation['visible'] ) ) {
				$variation_object->set_status( ( isset( $variation['visible'] ) && false === $variation['visible'] ) ? 'private' : 'publish' );
			}

			$variation_id = $variation_object->save();

			// stop if we don't have a variation ID
			if ( ! $variation_id ) {
				throw new \Exception( esc_html__( 'Could not save the product variation', 'woocommerce-square' ) );
			}

			if ( $is_new_variation ) {

				/**
				 * Fired after creating a product variation during an import from Square.
				 *
				 * @since 2.0.0
				 *
				 * @param int $variation_id the new variation ID
				 */
				do_action( 'woocommerce_square_create_product_variation', $variation_id );

			} else {

				// remove any variation from $variations_to_remove that is found in Sqaure and also matches a variation in Woo
				if ( ( $key = array_search( $variation_id, $variations_to_remove, true ) ) !== false ) { //phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.Found, Squiz.PHP.DisallowMultipleAssignments.FoundInControlStructure
					unset( $variations_to_remove[ $key ] );
				}

				/**
				 * Fired after updating a product variation during an import from Square.
				 *
				 * @since 2.0.0
				 *
				 * @param int $variation_id the updated variation ID
				 */
				do_action( 'woocommerce_square_update_product_variation', $variation_id );
			}

			// SKU
			if ( isset( $variation['sku'] ) ) {
				$this->set_product_sku( $variation_object, $variation['sku'] );
			}

			$variation_object->set_manage_stock( true );
			$variation_object->set_backorders( 'no' );

			$this->set_product_price_props( $variation_object, $variation['regular_price'] );

			$variation_object->set_download_limit( -1 );
			$variation_object->set_download_expiry( -1 );
			$variation_object->set_downloads( array() );

			// description
			if ( isset( $variation['description'] ) ) {
				$variation_object->set_description( wp_kses_post( $variation['description'] ) );
			}

			// update taxonomies, attributes left out are removed from the variation on save
			if ( isset( $variation['attributes'] ) ) {

				$variation_attributes = array();

				foreach ( $variation['attributes'] as $attribute_key => $attribute ) {
					// Set empty if attribute is to 'Any' to prevent it from being saved.
					$attribute['option'] = isset( $attribute['option'] ) && WC_SQUARE_OPTION_ANY !== $attribute['option'] ? $attribute['option'] : '';

					if ( ! isset( $attribute['name'] ) ) {
						continue;
					}

					$taxonomy = 0;

					if ( isset( $attribute['slug'] ) ) {

						$taxonomy = $this->get_attribute_taxonomy_by_slug( $attribute['slug'] );
					}

					if ( ! $taxonomy ) {

						$taxonomy = sanitize_title( $attribute['name'] );
					}

					$_attribute = isset( $attributes[ $taxonomy ] ) ? $attributes[ $taxonomy ] : null;

					if ( ! $_attribute instanceof \WC_Product_Attribute || ! $_attribute->get_variation() ) {
						continue;
					}

					if ( $_attribute->is_taxonomy() ) {

						// Don't use wc_clean as it destroys sanitized characters
						$_attribute_value = sanitize_title( stripslashes( $attribute['option'] ) );

					} else {

						$_attribute_value = wc_clean( stripslashes( $attribute['option'] ) );
					}

					$variation_attributes[ sanitize_title( $_attribute->get_name() ) ] = $_attribute_value;
				}

				$variation_object->set_attributes( $variation_attributes );
			}

			$variation_object->save();

			// square item variation id
			if ( isset( $variation['square_meta']['item_variation_id'] ) ) {
				Product::set_square_item_variation_id( $variation_object, $variation['square_meta']['item_variation_id'] );
			}

			// square item variation version
			if ( isset( $variation['square_meta']['item_variation_version'] ) ) {
				Product::set_square_variation_version( $variation_object, $variation['square_meta']['item_variation_version'] );
			}

			/**
			 * Fired after saving a product variation during a Square product import.
			 *
			 * @since 2.0.0
			 *
			 * @param int $variation_id the variation ID
			 * @param int $menu_order the menu order
			 * @param array $variation the variation data
			 */
			do_action( 'woocommerce_square_save_product_variation', $variation_id, $menu_order, $variation );
		}

		// remove any existing variations on the product that no longer exist in Square
		foreach ( $variations_to_remove as $variation_id ) {
			$variation_to_remove = wc_get_product( $variation_id );

			if ( $variation_to_remove instanceof \WC_Product ) {
				$variation_to_remove->delete( true );
			}
		}

		// update parent if variable so price sorting works and stays in sync with the cheapest child
		\WC_Product_Variable::sync( $product_id );

		// update default attributes options setting
		if ( isset( $data['default_attribute'] ) ) {

			$data['default_attributes'] = $data['default_attribute'];
		}

		if ( isset( $data['default_attributes'] ) && is_array( $data['default_attributes'] ) ) {

			$default_attributes = array();

			foreach ( $data['default_attributes'] as $default_attr_key => $default_attr ) {

				if ( ! isset( $default_attr['name'] ) ) {
					continue;
				}

				$taxonomy = sanitize_title( $default_attr['name'] );

				if ( isset( $default_attr['slug'] ) ) {
					$taxonomy = $this->get_attribute_taxonomy_by_slug( $default_attr['slug'] );
				}

				if ( isset( $attributes[ $taxonomy ] ) ) {

					$_attribute = $attributes[ $taxonomy ];

					if ( $_attribute->get_variation() ) {

						$value = '';

						if ( isset( $default_attr['option'] ) ) {

							if ( $_attribute->is_taxonomy() ) {

								// Don't use wc_clean as it destroys sanitized characters
								$value = sanitize_title( trim( stripslashes( $default_attr['option'] ) ) );

							} else {

								$value = wc_clean( trim( stripslashes( $default_attr['option'] ) ) );
							}
						}

						if ( $value ) {

							$default_attributes[ $taxonomy ] = $value;
						}
					}
				}
			}

			// reload the parent, sync() above saved its prices on a separate object
			$variable_product = wc_get_product( $product_id );

			if ( $variable_product instanceof \WC_Product ) {
				$variable_product->set_default_attributes( $default_attributes );
				$variable_product->save();
			}
		}

		return true;
	}


	/**
	 * Gets an attribute taxonomy by its slug.
	 *
	 * @since 2.0.0
	 *
	 * @param string $slug
	 * @return string|null
	 */
	protected function get_attribute_taxonomy_by_slug( $slug ) {

		$taxonomy             = null;
		$attribute_taxonomies = wc_get_attribute_taxonomies();

		foreach ( $attribute_taxonomies as $key => $tax ) {

			if ( $slug === $tax->attribute_name ) {

				$taxonomy = 'pa_' . $tax->attribute_name;
				break;
			}
		}

		return $taxonomy;
	}


	/**
	 * Saves the product price.
	 *
	 * @since 2.0.0
	 *
	 * @param int $product_id
	 * @param float $regular_price
	 * @param float $sale_price
	 * @param string $date_from
	 * @param string $date_to
	 */
	public function wc_save_product_price( $product_id, $regular_price, $sale_price = '', $date_from = '', $date_to = '' ) {

		$product = wc_get_product( absint( $product_id ) );

		if ( ! $product instanceof \WC_Product ) {
			return;
		}

		$this->set_product_price_props( $product, $regular_price, $sale_price, $date_from, $date_to );

		$product->save();
	}


	/**
	 * Sets the price props on a product object without saving it.
	 *
	 * @since x.x.x
	 *
	 * @param \WC_Product $product the product or variation object
	 * @param float $regular_price
	 * @param float $sale_price
	 * @param string $date_from
	 * @param string $date_to
	 */
	private function set_product_price_props( $product, $regular_price, $sale_price = '', $date_from = '', $date_to = '' ) {

		$regular_price = wc_format_decimal( $regular_price );
		$sale_price    = '' === $sale_price ? '' : wc_format_decimal( $sale_price );
		$date_from     = wc_clean( $date_from );
		$date_to       = wc_clean( $date_to );

		if ( $date_to && ! $date_from ) {
			$date_from = time();
		}

		// a sale that already ended has no dates
		if ( $date_to && strtotime( $date_to ) < time() ) {
			$date_from = '';
			$date_to   = '';
		}

		$product->set_regular_price( $regular_price );
		$product->set_sale_price( $sale_price );
		$product->set_date_on_sale_from( $date_from ? $date_from : null );
		$product->set_date_on_sale_to( $date_to ? $date_to : null );

		// WooCommerce recalculates the active price on save when any of the props above change, this covers the case where none did
		$product->set_price( $product->is_on_sale( 'edit' ) ? $sale_price : $regular_price );
	}


	/**
	 * Clears a product from WooCommerce - used when product creation fails partially through the creation process.
	 *
	 * @since 2.0.0
	 *
	 * @param int $product_id the product ID
	 */
	protected function clear_product( $product_id ) {

		if ( ! is_numeric( $product_id ) || 0 >= $product_id ) {
			return;
		}

		// Delete product attachments
		$attachments = get_children(
			array(
				'post_parent' => $product_id,
				'post_status' => 'any',
				'post_type'   => 'attachment',
			)
		);

		foreach ( $attachments as $attachment ) {
			wp_delete_attachment( $attachment->ID, true );
		}

		// Delete product
		$product = wc_get_product( $product_id );

		if ( $product instanceof \WC_Product ) {
			$product->delete( true );
		}
	}

	/**
	 * Creates a product in WooCommerce using the data from Square
	 *
	 * @since 2.2.0
	 *
	 * @param array $data New product data
	 * @return int
	 * @throws \Exception
	 */
	protected function create_product_from_square_data( $data = array() ) {
		// validate title field
		if ( ! isset( $data['title'] ) ) {
			/* translators: Placeholders: %s - missing parameter name */
			throw new \Exception( sprintf( esc_html__( 'Missing parameter %s', 'woocommerce-square' ), 'title' ) );
		}

		$product_type = wc_clean( $data['type'] );

		// validate type
		if ( ! array_key_exists( $product_type, wc_get_product_types() ) ) {
			/* translators: Placeholders: %s - comma separated list of valid product types */
			throw new \Exception( sprintf( esc_html__( 'Invalid product type - the product type must be any of these: %s', 'woocommerce-square' ), esc_html( implode( ', ', array_keys( wc_get_product_types() ) ) ) ) );
		}

		$classname = \WC_Product_Factory::get_product_classname( 0, $product_type );
		$product   = new $classname();

		$product->set_name( wc_clean( $data['title'] ) );
		$product->set_status( isset( $data['status'] ) ? wc_clean( $data['status'] ) : 'publish' );
		$product->set_description( isset( $data['description'] ) ? $data['description'] : '' );
		$product->set_menu_order( isset( $data['menu_order'] ) ? (int) $data['menu_order'] : 0 );

		if ( ! empty( $data['name'] ) ) {
			$product->set_slug( sanitize_title( $data['name'] ) );
		}

		// attempt to create the new product
		$product_id = $product->save();

		if ( ! $product_id ) {
			throw new \Exception( esc_html__( 'Could not create the product', 'woocommerce-square' ) );
		}

		return $product_id;
	}

	/**
	 * Record an error made during import or update by creating a new Sync Record and Square log
	 *
	 * @since 2.2.0
	 *
	 * @param string $error Error message to record
	 * @param \Square\Models\CatalogObject|array|null $catalog_item the catalog object or data
	 * @param string $context Context for whether the error occurred during import or update
	 */
	protected function record_error( $error, $catalog_item = null, $context = 'import' ) {
		if ( $catalog_item && $catalog_item instanceof \Square\Models\CatalogObject && $catalog_item->getItemData() instanceof \Square\Models\CatalogItem ) {
			$item_name = $catalog_item->getItemData()->getName();
		} elseif ( is_array( $catalog_item ) && ! empty( $catalog_item['title'] ) ) {
			$item_name = $catalog_item['title'];
		}

		if ( 'update' === $context ) {
			/* translators: Placeholders: %1$s - Square item name, %2$s - Failure reason  */
			$message = sprintf( __( 'Could not update %1$s from Square. %2$s', 'woocommerce-square' ), ! empty( $item_name ) ? '"' . $item_name . '"' : 'item', $error );
		} else {
			/* translators: Placeholders: %1$s - Square item name, %2$s - Failure reason  */
			$message = sprintf( __( 'Could not import %1$s from Square. %2$s', 'woocommerce-square' ), ! empty( $item_name ) ? '"' . $item_name . '"' : 'item', $error );
		}

		Records::set_record(
			array(
				'type'    => 'alert',
				'message' => $message,
			)
		);

		wc_square()->log( sprintf( 'Error %s product during import: %s', 'import' === $context ? 'creating' : 'updating', $error ) );
	}
}
