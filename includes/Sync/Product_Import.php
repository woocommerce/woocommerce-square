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


	protected function assign_next_steps() {

		$this->set_attr(
			'next_steps',
			array(
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

			// import or update categories related to the products that are being imported
			$catalog_category_id = Category::get_square_category_id( $catalog_object->getItemData() );

			if ( $catalog_category_id && isset( $categories[ $catalog_category_id ] ) ) {
				Category::import_or_update( $categories[ $catalog_category_id ] );
				unset( $categories[ $catalog_category_id ] ); // don't import/update the same category multiple times per batch
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
			static function( \Square\Models\CatalogObject $catalog_object ) {
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

		foreach ( $objects as $catalog_object ) {

			// all inventory should be tied to a variation, but check here just in case
			if ( 'ITEM_VARIATION' === $catalog_object->getType() ) {

				$product = Product::get_product_by_square_variation_id( $catalog_object->getId() );

				if ( $product && $product instanceof \WC_Product ) {
					$is_tracking_inventory = isset( $catalog_objects_hash[ $catalog_object->getId() ] ) ?
						$catalog_objects_hash[ $catalog_object->getId() ] :
						false;

					/* If catalog object is tracked and has a quantity > 0 set in Square. */
					if ( $is_tracking_inventory && isset( $inventory_hash[ $catalog_object->getId() ] ) ) {
						$product->set_stock_quantity( (float) $inventory_hash[ $catalog_object->getId() ] );
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
	private function update_product( $product, $data ) {
		global $wpdb;
		$product_id = $product->get_id();

		try {
			$wpdb->query( 'START TRANSACTION' );

			// update an existing product from a simple to a variable product if it has at least two variations
			if ( ! $product instanceof \WC_Product_Variable && 'variable' === $data['type'] ) {
				$product_id = $this->update_simple_product_to_variable( $product_id, $data );
			}

			$product->set_name( $data['title'] );
			$product->set_description( $data['description'] );
			$product->save();

			// save product meta fields
			$this->save_product_meta( $product_id, $data );

			// save the image, if included
			Product::update_image_from_square( $product_id, $data['image_id'], true );

			// save/update variations
			if ( isset( $data['type'], $data['variations'] ) && 'variable' === $data['type'] && is_array( $data['variations'] ) ) {
				$this->save_variations( $product_id, $data );
			}

			$wpdb->query( 'COMMIT' );

			wc_delete_product_transients( $product_id );
		} catch ( \Exception $e ) {
			// undo any updated data when updating fails
			$wpdb->query( 'ROLLBACK' );
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
		// create a new parent product
		$parent_product_id = $this->create_product_from_square_data( $data );

		// convert the simple product to a variation
		wp_set_object_terms( $variation_id, 'variation', 'product_type' );
		wp_update_post(
			array(
				'ID'           => $variation_id,
				'post_content' => '',
				'post_author'  => get_current_user_id(),
				'post_parent'  => $parent_product_id,
				'post_type'    => 'product_variation',
			)
		);

		// remove simple product meta that doesn't exist or needs be updated on the variation
		delete_post_meta( $variation_id, Product::SQUARE_ID_META_KEY );
		delete_post_meta( $variation_id, Product::SQUARE_VERSION_META_KEY );
		delete_post_meta( $variation_id, Product::SQUARE_IMAGE_ID_META_KEY );
		delete_post_meta( $variation_id, '_visibility' );

		// copy total sales from previous simple product over to new parent variable product
		$total_sales = get_post_meta( $variation_id, 'total_sales', true );

		if ( $total_sales ) {
			update_post_meta( $parent_product_id, 'total_sales', $total_sales );
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
	protected function extract_product_data( $catalog_object, $product = null ) {

		$variations = $catalog_object->getItemData()->getVariations() ? $catalog_object->getItemData()->getVariations() : array();

		// if there are no variations, something is wrong - every catalog item has at least one
		if ( 0 >= count( $variations ) ) {
			return null;
		}

		$square_category_id = Category::get_square_category_id( $catalog_object->getItemData() );
		$category_id        = Category::get_category_id_by_square_id( $square_category_id );

		$data = array(
			'title'       => $catalog_object->getItemData()->getName(),
			'type'        => ( 1 === count( $variations ) && ! ( $product && $product instanceof \WC_Product_Variable ) ) ? 'simple' : 'variable',
			'sku'         => '', // make sure to reset SKU when simple product is updated to variable.
			'description' => Product::get_catalog_item_description( $catalog_object->getItemData() ),
			'image_id'    => Product::get_catalog_item_thumbnail_id( $catalog_object ),
			'categories'  => array( $category_id ),
			'square_meta' => array(
				'item_id'      => $catalog_object->getId(),
				'item_version' => $catalog_object->getVersion(),
			),
		);

		// variable product
		if ( 'variable' === $data['type'] ) {
			$data['variations'] = array();

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

			$data['attributes'] = array(
				array(
					'name'      => 'Attribute',
					'slug'      => 'attribute',
					'position'  => 0,
					'visible'   => true,
					'variation' => true,
					'options'   => str_replace( '|', ' - ', wp_list_pluck( $data['variations'], 'name' ) ),
				),
			);
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
			'attributes'     => array(
				array(
					'name'         => 'Attribute',
					'is_variation' => true,
					'option'       => str_replace( '|', ' - ', $variation_data->getName() ),
				),
			),
		);

		return $data;
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

		// product type
		$product_type = null;

		if ( isset( $data['type'] ) ) {

			$product_type = wc_clean( $data['type'] );

			wp_set_object_terms( $product_id, $product_type, 'product_type' );
		} else {
			$_product_type = get_the_terms( $product_id, 'product_type' );

			if ( is_array( $_product_type ) ) {

				$_product_type = current( $_product_type );
				$product_type  = $_product_type->slug;
			}
		}

		// default total sales
		add_post_meta( $product_id, 'total_sales', '0', true );

		// catalog visibility
		update_post_meta( $product_id, '_visibility', ! empty( $data['catalog_visibility'] ) ? wc_clean( $data['catalog_visibility'] ) : 'visible' );

		// sku
		if ( isset( $data['sku'] ) ) {

			$sku     = get_post_meta( $product_id, '_sku', true );
			$new_sku = wc_clean( $data['sku'] );

			if ( '' === $new_sku ) {

				update_post_meta( $product_id, '_sku', '' );

			} elseif ( $new_sku !== $sku ) {

				if ( ! empty( $new_sku ) ) {

					$unique_sku = wc_product_has_unique_sku( $product_id, $new_sku );

					if ( $unique_sku ) {

						update_post_meta( $product_id, '_sku', $new_sku );

					} else {

						throw new \Exception( esc_html__( 'The SKU already exists on another product', 'woocommerce-square' ) );
					}
				} else {
					update_post_meta( $product_id, '_sku', '' );
				}
			}
		}

		// attributes
		if ( isset( $data['attributes'] ) ) {

			$attributes = array();

			foreach ( $data['attributes'] as $attribute ) {

				$is_taxonomy = 0;
				$taxonomy    = 0;

				if ( ! isset( $attribute['name'] ) ) {
					continue;
				}

				$attribute_slug = sanitize_title( $attribute['name'] );

				if ( isset( $attribute['slug'] ) ) {

					$taxonomy       = $this->get_attribute_taxonomy_by_slug( $attribute['slug'] );
					$attribute_slug = sanitize_title( $attribute['slug'] );
				}

				if ( $taxonomy ) {

					$is_taxonomy = 1;
				}

				if ( $is_taxonomy ) {

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

					// update post terms
					if ( taxonomy_exists( $taxonomy ) ) {

						wp_set_object_terms( $product_id, $values, $taxonomy );
					}

					if ( ! empty( $values ) ) {

						// add attribute to array, but don't set values
						$attributes[ $taxonomy ] = array(
							'name'         => $taxonomy,
							'value'        => '',
							'position'     => isset( $attribute['position'] ) ? (string) absint( $attribute['position'] ) : '0',
							'is_visible'   => ( isset( $attribute['visible'] ) && $attribute['visible'] ) ? 1 : 0,
							'is_variation' => ( isset( $attribute['variation'] ) && $attribute['variation'] ) ? 1 : 0,
							'is_taxonomy'  => $is_taxonomy,
						);
					}
				} elseif ( isset( $attribute['options'] ) ) {
					// array based
					if ( is_array( $attribute['options'] ) ) {

						$values = implode( ' ' . WC_DELIMITER . ' ', array_map( 'wc_clean', $attribute['options'] ) );

					} else {

						$values = implode( ' ' . WC_DELIMITER . ' ', array_map( 'wc_clean', explode( WC_DELIMITER, $attribute['options'] ) ) );
					}

					// custom attribute - add attribute to array and set the values
					$attributes[ $attribute_slug ] = array(
						'name'         => wc_clean( $attribute['name'] ),
						'value'        => $values,
						'position'     => isset( $attribute['position'] ) ? (string) absint( $attribute['position'] ) : '0',
						'is_visible'   => ( isset( $attribute['visible'] ) && $attribute['visible'] ) ? 1 : 0,
						'is_variation' => ( isset( $attribute['variation'] ) && $attribute['variation'] ) ? 1 : 0,
						'is_taxonomy'  => $is_taxonomy,
					);
				}
			}

			uasort( $attributes, 'wc_product_attribute_uasort_comparison' );

			update_post_meta( $product_id, '_product_attributes', $attributes );
		}

		// sales and prices
		if ( in_array( $product_type, array( 'variable', 'grouped' ), true ) ) {

			// variable and grouped products have no prices
			update_post_meta( $product_id, '_regular_price', '' );
			update_post_meta( $product_id, '_sale_price', '' );
			update_post_meta( $product_id, '_sale_price_dates_from', '' );
			update_post_meta( $product_id, '_sale_price_dates_to', '' );
			update_post_meta( $product_id, '_price', '' );

		} else {

			$this->wc_save_product_price( $product_id, $data['regular_price'] );
		}

		// product categories
		if ( isset( $data['categories'] ) && is_array( $data['categories'] ) ) {

			$term_ids = array_unique( array_map( 'intval', $data['categories'] ) );

			wp_set_object_terms( $product_id, $term_ids, 'product_cat' );
		}

		// clear/invalidate cache before calling WooCommerce\Square\Handlers\Product functions (these functions call wc_get_product() and save(), overriding our changes)
		if ( is_callable( '\WC_Cache_Helper::invalidate_cache_group' ) ) {
			\WC_Cache_Helper::invalidate_cache_group( 'product_' . $product_id );
		}

		// square item id
		if ( isset( $data['square_meta']['item_id'] ) ) {
			Product::set_square_item_id( $product_id, $data['square_meta']['item_id'] );
		}

		// square item version
		if ( isset( $data['square_meta']['item_version'] ) ) {
			Product::set_square_version( $product_id, $data['square_meta']['item_version'] );
		}

		// square item variation id
		if ( isset( $data['square_meta']['item_variation_id'] ) ) {
			Product::set_square_item_variation_id( $product_id, $data['square_meta']['item_variation_id'] );
		}

		// square item variation version
		if ( isset( $data['square_meta']['item_variation_version'] ) ) {
			Product::set_square_variation_version( $product_id, $data['square_meta']['item_variation_version'] );
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
		global $wpdb;

		$variations           = $data['variations'];
		$attributes           = (array) maybe_unserialize( get_post_meta( $product_id, '_product_attributes', true ) );
		$variable_product     = wc_get_product( $product_id );
		$variations_to_remove = $variable_product ? $variable_product->get_children() : array();

		foreach ( $variations as $menu_order => $variation ) {

			$variation_id = isset( $variation['id'] ) ? absint( $variation['id'] ) : 0;

			if ( ! $variation_id && isset( $variation['sku'] ) ) {

				$variation_sku = wc_clean( $variation['sku'] );
				$variation_id  = wc_get_product_id_by_sku( $variation_sku );
			}

			/* translators: Placeholders: %1$s - variation ID, %2$s - product name */
			$variation_post_title = sprintf( __( 'Variation #%1$s of %2$s', 'woocommerce-square' ), $variation_id, esc_html( get_the_title( $product_id ) ) );

			// update or add post
			if ( ! $variation_id ) {

				$post_status   = ( isset( $variation['visible'] ) && false === $variation['visible'] ) ? 'private' : 'publish';
				$new_variation = array(
					'post_title'   => $variation_post_title,
					'post_content' => '',
					'post_status'  => $post_status,
					'post_author'  => get_current_user_id(),
					'post_parent'  => $product_id,
					'post_type'    => 'product_variation',
					'menu_order'   => $menu_order,
				);

				$variation_id = wp_insert_post( $new_variation );

				/**
				 * Fired after creating a product variation during an import from Square.
				 *
				 * @since 2.0.0
				 *
				 * @param int $variation_id the new variation ID
				 */
				do_action( 'woocommerce_square_create_product_variation', $variation_id );

			} else {

				$update_variation = array(
					'post_title'  => $variation_post_title,
					'menu_order'  => $menu_order,
					'post_parent' => $product_id,
				);

				if ( isset( $variation['visible'] ) ) {

					$post_status = ( false === $variation['visible'] ) ? 'private' : 'publish';

					$update_variation['post_status'] = $post_status;
				}

				$wpdb->update( $wpdb->posts, $update_variation, array( 'ID' => $variation_id ) );

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

			// stop if we don't have a variation ID
			if ( is_wp_error( $variation_id ) ) {

				throw new \Exception( esc_html( $variation_id->get_error_message() ) );
			}

			// SKU
			if ( isset( $variation['sku'] ) ) {

				$sku     = get_post_meta( $variation_id, '_sku', true );
				$new_sku = wc_clean( $variation['sku'] );

				if ( '' === $new_sku ) {

					update_post_meta( $variation_id, '_sku', '' );

				} elseif ( $new_sku !== $sku ) {

					if ( ! empty( $new_sku ) ) {

						if ( wc_product_has_unique_sku( $variation_id, $new_sku ) ) {

							update_post_meta( $variation_id, '_sku', $new_sku );

						} else {

							throw new \Exception( esc_html__( 'The SKU already exists on another product', 'woocommerce-square' ) );
						}
					} else {

						update_post_meta( $variation_id, '_sku', '' );
					}
				}
			}

			update_post_meta( $variation_id, '_manage_stock', 'yes' );
			update_post_meta( $variation_id, '_backorders', 'no' );

			$this->wc_save_product_price( $variation_id, $variation['regular_price'] );

			update_post_meta( $variation_id, '_download_limit', '' );
			update_post_meta( $variation_id, '_download_expiry', '' );
			update_post_meta( $variation_id, '_downloadable_files', '' );

			// description
			if ( isset( $variation['description'] ) ) {
				update_post_meta( $variation_id, '_variation_description', wp_kses_post( $variation['description'] ) );
			}

			// update taxonomies
			if ( isset( $variation['attributes'] ) ) {

				$updated_attribute_keys = array();

				foreach ( $variation['attributes'] as $attribute_key => $attribute ) {

					if ( ! isset( $attribute['name'] ) ) {
						continue;
					}

					$taxonomy   = 0;
					$_attribute = array();

					if ( isset( $attribute['slug'] ) ) {

						$taxonomy = $this->get_attribute_taxonomy_by_slug( $attribute['slug'] );
					}

					if ( ! $taxonomy ) {

						$taxonomy = sanitize_title( $attribute['name'] );
					}

					if ( isset( $attributes[ $taxonomy ] ) ) {

						$_attribute = $attributes[ $taxonomy ];
					}

					if ( isset( $_attribute['is_variation'] ) && $_attribute['is_variation'] ) {

						$_attribute_key           = 'attribute_' . sanitize_title( $_attribute['name'] );
						$updated_attribute_keys[] = $_attribute_key;

						if ( isset( $_attribute['is_taxonomy'] ) && $_attribute['is_taxonomy'] ) {

							// Don't use wc_clean as it destroys sanitized characters
							$_attribute_value = isset( $attribute['option'] ) ? sanitize_title( stripslashes( $attribute['option'] ) ) : '';

						} else {

							$_attribute_value = isset( $attribute['option'] ) ? wc_clean( stripslashes( $attribute['option'] ) ) : '';
						}

						update_post_meta( $variation_id, $_attribute_key, $_attribute_value );
					}
				}

				// remove old taxonomies attributes so data is kept up to date - first get attribute key names
				$delete_attribute_keys = $wpdb->get_col( $wpdb->prepare( "SELECT meta_key FROM {$wpdb->postmeta} WHERE meta_key LIKE 'attribute_%%' AND meta_key NOT IN ( '" . implode( "','", $updated_attribute_keys ) . "' ) AND post_id = %d;", $variation_id ) ); //phpcs:ignore WordPress.DB.PreparedSQLPlaceholders, WordPress.DB.PreparedSQL.NotPrepared

				foreach ( $delete_attribute_keys as $key ) {

					delete_post_meta( $variation_id, $key );
				}
			}

			// square item variation id
			if ( isset( $variation['square_meta']['item_variation_id'] ) ) {
				Product::set_square_item_variation_id( $variation_id, $variation['square_meta']['item_variation_id'] );
			}

			// square item variation version
			if ( isset( $variation['square_meta']['item_variation_version'] ) ) {
				Product::set_square_variation_version( $variation_id, $variation['square_meta']['item_variation_version'] );
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
			wp_delete_post( $variation_id, true );
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

					if ( $_attribute['is_variation'] ) {

						$value = '';

						if ( isset( $default_attr['option'] ) ) {

							if ( $_attribute['is_taxonomy'] ) {

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

			update_post_meta( $product_id, '_default_attributes', $default_attributes );
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

		$product_id    = absint( $product_id );
		$regular_price = wc_format_decimal( $regular_price );
		$sale_price    = '' === $sale_price ? '' : wc_format_decimal( $sale_price );
		$date_from     = wc_clean( $date_from );
		$date_to       = wc_clean( $date_to );

		update_post_meta( $product_id, '_regular_price', $regular_price );
		update_post_meta( $product_id, '_sale_price', $sale_price );

		// Save Dates
		update_post_meta( $product_id, '_sale_price_dates_from', $date_from ? strtotime( $date_from ) : '' );
		update_post_meta( $product_id, '_sale_price_dates_to', $date_to ? strtotime( $date_to ) : '' );

		if ( $date_to && ! $date_from ) {

			$date_from = strtotime( 'NOW', current_time( 'timestamp' ) ); //phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested

			update_post_meta( $product_id, '_sale_price_dates_from', $date_from );
		}

		// Update price if on sale
		if ( '' !== $sale_price && '' === $date_to && '' === $date_from ) {

			update_post_meta( $product_id, '_price', $sale_price );

		} else {

			update_post_meta( $product_id, '_price', $regular_price );
		}

		if ( '' !== $sale_price && $date_from && strtotime( $date_from ) < strtotime( 'NOW', current_time( 'timestamp' ) ) ) { //phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested

			update_post_meta( $product_id, '_price', $sale_price );
		}

		if ( $date_to && strtotime( $date_to ) < strtotime( 'NOW', current_time( 'timestamp' ) ) ) { //phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested

			update_post_meta( $product_id, '_price', $regular_price );
			update_post_meta( $product_id, '_sale_price_dates_from', '' );
			update_post_meta( $product_id, '_sale_price_dates_to', '' );
		}
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
		wp_delete_post( $product_id, true );
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

		// validate type
		if ( ! array_key_exists( wc_clean( $data['type'] ), wc_get_product_types() ) ) {
			/* translators: Placeholders: %s - comma separated list of valid product types */
			throw new \Exception( sprintf( esc_html__( 'Invalid product type - the product type must be any of these: %s', 'woocommerce-square' ), esc_html( implode( ', ', array_keys( wc_get_product_types() ) ) ) ) );
		}

		$new_product = array(
			'post_title'   => wc_clean( $data['title'] ),
			'post_status'  => isset( $data['status'] ) ? wc_clean( $data['status'] ) : 'publish',
			'post_type'    => 'product',
			'post_content' => isset( $data['description'] ) ? $data['description'] : '',
			'post_author'  => get_current_user_id(),
			'menu_order'   => isset( $data['menu_order'] ) ? (int) $data['menu_order'] : 0,
		);

		if ( ! empty( $data['name'] ) ) {
			$new_product = array_merge( $new_product, array( 'post_name' => sanitize_title( $data['name'] ) ) );
		}

		// attempt to create the new product
		$product_id = wp_insert_post( $new_product, true );

		if ( is_wp_error( $product_id ) ) {
			throw new \Exception( esc_html( $product_id->get_error_message() ) );
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
