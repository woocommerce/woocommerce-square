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

namespace WooCommerce\Square\Handlers\Product;

use Square\Models\CatalogObject;

class Woo_SOR extends \WooCommerce\Square\Handlers\Product {

	/**
	 * Updates a Square catalog item with a WooCommerce product's data.
	 *
	 * @since 2.0.0
	 *
	 * @param CatalogObject $catalog_object Square SDK catalog object
	 * @param \WC_Product $product WooCommerce product
	 * @return CatalogObject
	 * @throws \Exception
	 */
	public static function update_catalog_item( CatalogObject $catalog_object, \WC_Product $product ) {

		if ( 'ITEM' !== $catalog_object->getType() || ! $catalog_object->getItemData() ) {
			throw new \Exception( 'Type of $catalog_object must be an ITEM' );
		}

		// ensure the product meta is persisted
		self::update_product( $product, $catalog_object );

		if ( ! $catalog_object->getId() ) {
			$catalog_object->setId( self::get_square_item_id( $product ) );
		}

		$is_delete = 'trash' === $product->get_status();

		$catalog_object = self::set_catalog_object_location_ids( $catalog_object, $is_delete );

		$item_data = $catalog_object->getItemData();

		$item_data->setName( $product->get_name() );
		$item_data->setDescriptionHtml( $product->get_description() );

		$square_category_id = 0;

		foreach ( $product->get_category_ids() as $category_id ) {

			$map = \WooCommerce\Square\Handlers\Category::get_mapping( $category_id );

			if ( ! empty( $map['square_id'] ) ) {

				$square_category_id = $map['square_id'];
				break;
			}
		}

		// if a category with a Square ID was found
		if ( $square_category_id ) {
			$square_category = new \Square\Models\CatalogObjectCategory();
			$square_category->setId( $square_category_id );
			$item_data->setCategories( array( $square_category ) );
			// Set the reporting category.
			$item_data->setReportingCategory( $square_category );
		}

		$catalog_variations = $item_data->getVariations() ?: array();

		// if dealing with a variable product, try and match the variations
		if ( $product->is_type( 'variable' ) ) {

			$product_variation_ids = $product->get_children();

			if ( is_array( $catalog_variations ) ) {

				foreach ( $catalog_variations as $object_key => $variation_object ) {

					$product_variation_id = self::get_product_id_by_square_variation_id( $variation_object->getId() );

					// ID might not be set, so try the SKU
					if ( ! $product_variation_id ) {
						$product_variation_id = wc_get_product_id_by_sku( $variation_object->getItemVariationData()->getSku() );
					}

					// if a product was found and belongs to the parent, use it
					if ( false !== ( $key = array_search( $product_variation_id, $product_variation_ids, false ) ) ) {

						$product_variation = wc_get_product( $product_variation_id );

						if ( $product_variation instanceof \WC_Product ) {

							$catalog_variations[ $object_key ] = self::update_catalog_variation( $variation_object, $product_variation );

							// consider this variation taken care of
							unset( $product_variation_ids[ $key ] );
						}
					} else {

						unset( $catalog_variations[ $object_key ] );
					}
				}
			}

			// all that's left are variations that didn't have a match, so create new variations
			foreach ( $product_variation_ids as $product_variation_id ) {

				$product_variation = wc_get_product( $product_variation_id );

				if ( ! $product_variation instanceof \WC_Product ) {
					continue;
				}

				$variation_object = new CatalogObject(
					'ITEM_VARIATION',
					''
				);

				$catalog_item_variation = new \Square\Models\CatalogItemVariation();
				$catalog_item_variation->setItemId( $catalog_object->getId() );
				$variation_object->setItemVariationData( $catalog_item_variation );

				$catalog_variations[] = self::update_catalog_variation( $variation_object, $product_variation );
			}
		} else { // otherwise, we have a simple product

			if ( ! empty( $catalog_variations ) ) {

				$variation_object = $catalog_variations[0];

			} else {

				$variation_object = new CatalogObject(
					'ITEM_VARIATION',
					''
				);

				$catalog_item_variation = new \Square\Models\CatalogItemVariation();
				$catalog_item_variation->setItemId( $catalog_object->getId() );
				$variation_object->setItemVariationData( $catalog_item_variation );
			}

			$catalog_variations = array( self::update_catalog_variation( $variation_object, $product ) );
		}

		$item_data->setVariations( array_values( $catalog_variations ) );

		$catalog_object->setItemData( $item_data );

		/**
		 * Fires when updating  a Square catalog item with WooCommerce product data.
		 *
		 * @since 2.0.0
		 *
		 * @param CatalogObject $catalog_object Square SDK catalog object
		 * @param \WC_Product $product WooCommerce product
		 */
		$catalog_object = apply_filters( 'wc_square_update_catalog_item', $catalog_object, $product );

		return $catalog_object;
	}


	/**
	 * Updates a Square catalog item variation with a WooCommerce product's data.
	 *
	 * @since 2.0.0
	 *
	 * @param CatalogObject $catalog_object Square SDK catalog object
	 * @param \WC_Product $product WooCommerce product
	 * @return CatalogObject
	 * @throws \Exception
	 */
	public static function update_catalog_variation( CatalogObject $catalog_object, \WC_Product $product ) {

		if ( 'ITEM_VARIATION' !== $catalog_object->getType() || ! $catalog_object->getItemVariationData() ) {
			throw new \Exception( 'Type of $catalog_object must be an ITEM_VARIATION' );
		}

		// ensure the variation meta is persisted
		self::update_variation( $product, $catalog_object );

		if ( ! $catalog_object->getId() ) {
			$catalog_object->setId( self::get_square_item_variation_id( $product ) );
		}

		if ( ! $catalog_object->getVersion() ) {
			$catalog_object->setVersion( self::get_square_variation_version( $product ) );
		}

		$catalog_object = self::set_catalog_object_location_ids( $catalog_object, 'trash' === $product->get_status() );

		$variation_data = $catalog_object->getItemVariationData();

		if ( $product->get_regular_price() || $product->get_sale_price() ) {
			$variation_data->setPriceMoney( self::price_to_money( $product->get_sale_price() ?: $product->get_regular_price() ) );
		} else {
			$variation_data->setPriceMoney( self::price_to_money( 0 ) );
		}

		$variation_data->setPricingType( 'FIXED_PRICING' );

		/**
		 * Simple products have only 1 variation and the name of the variation
		 * is derived from CatalogItem::name. For variable products, each variation
		 * can have its own name, so we put a condition to only set the name for
		 * variation.
		 *
		 * @see https://github.com/woocommerce/woocommerce-square/issues/570
		 */
		if ( 'variation' === $product->get_type() ) {
			$variation_data->setName( $product->get_name() );
		}

		if ( wc_square()->get_settings_handler()->is_inventory_sync_enabled() ) {
			/*
			 * Only update track_inventory if it's not set.
			 * This will only update inventory tracking on new variations.
			 * inventory tracking will remain the same for existing variations.
			 */
			$track_inventory = $variation_data->getTrackInventory();
			if ( is_null( $track_inventory ) ) {
				$variation_data->setTrackInventory( $product->get_manage_stock() );
			}
		}

		$variation_data->setSku( $product->get_sku() );

		if ( ! $variation_data->getItemId() ) {

			$parent_product = $product->get_parent_id() ? wc_get_product( $product->get_parent_id() ) : $product;

			if ( ! $parent_product instanceof \WC_Product ) {
				$variation_data->setItemId( self::get_square_item_id( $parent_product ) );
			}
		}

		$catalog_object->setItemVariationData( $variation_data );

		/**
		 * Fires when updating  a Square catalog item variation with WooCommerce product data.
		 *
		 * @since 2.0.0
		 *
		 * @param CatalogObject $catalog_object Square SDK catalog object
		 * @param \WC_Product $product WooCommerce product
		 */
		$catalog_object = apply_filters( 'wc_square_update_catalog_item_variation', $catalog_object, $product );

		return $catalog_object;
	}


	/**
	 * Sets the present/absent location IDs to a catalog object.
	 *
	 * @since 2.0.0
	 *
	 * @param CatalogObject $catalog_object Square SDK catalog object
	 * @param bool $is_delete whether the product is being deleted
	 * @return CatalogObject
	 */
	public static function set_catalog_object_location_ids( CatalogObject $catalog_object, $is_delete = false ) {

		$location_id = wc_square()->get_settings_handler()->get_location_id();

		$present_location_ids = $catalog_object->getPresentAtLocationIds() ?: array();
		$absent_location_ids  = $catalog_object->getAbsentAtLocationIds() ?: array();

		// if trashed, set as absent at our location
		if ( $is_delete ) {

			$absent_location_ids[] = $location_id;

			if ( false !== ( $key = array_search( $location_id, $present_location_ids, true ) ) ) {
				unset( $present_location_ids[ $key ] );
			}
		} else { // otherwise, it's present

			$present_location_ids[] = $location_id;

			if ( false !== ( $key = array_search( $location_id, $absent_location_ids, true ) ) ) {
				unset( $absent_location_ids[ $key ] );
			}
		}

		$catalog_object->setAbsentAtLocationIds( array_unique( array_values( $absent_location_ids ) ) );
		$catalog_object->setPresentAtLocationIds( array_unique( array_values( $present_location_ids ) ) );

		$catalog_object->setPresentAtAllLocations( false );

		return $catalog_object;
	}


}
