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

		// Only use attributes that are used for variations.
		$attributes = self::get_used_variation_attributes( $product );

		$product_variation_ids = $product->get_children();
		$catalog_variations    = $item_data->getVariations() ?? array();

		// if dealing with a variable product, try and match the variations
		if ( $product->is_type( 'variable' ) ) {

			$options_ids = array();

			/**
			 * If there are multiple variations, it must be a considered as Dynamic Options supported product.
			 * Create/Update and Assign Dynamic Options only if a product
			 * has multiple attributes OR options already exists in Square.
			 */
			if (
				count( $attributes ) > 1
			) {
				$result       = wc_square()->get_api()->retrieve_options_data();
				$options_data = $result[1] ?? array();

				// Set the product as a dynamic options product.
				update_post_meta( $product->get_id(), '_dynamic_options', true );

				// Loop through the attributes to create options and values at Square.
				foreach ( $attributes as $attribute_id => $attribute ) {

					$attribute_name = $attribute->get_name();
					// Check if its a taxonomy-based attribute.
					$attribute_option_values = array();
					if ( taxonomy_exists( $attribute_id ) ) {
						$terms                   = get_terms( $attribute_id );
						$attribute_option_values = wp_list_pluck( $terms, 'name' );
					} else {
						$attribute_option_values = $attribute->get_options();
					}

					// Check if Square already has the option created with the same name.
					// To do so, we can check if we already have the name in options/transient,
					// if yes, use the relative Square ID.
					$option_id = false;
					foreach ( $options_data as $transient_option_id => $option_data_transient ) {
						if ( $option_data_transient['name'] === $attribute_name ) {
							$option_id = $transient_option_id;
							break;
						}
					}

					// If name does not exist, create a new option in Square.
					// If name exists, check if all values are present in Square.
					// If not, create the missing values.
					$option        = wc_square()->get_api()->create_options_and_values( $option_id, $attribute_name, $attribute_option_values );
					$options_ids[] = $option->getId();
				}

				// Set the item_option_id for each option to the product.
				$product_options = array();

				foreach ( $options_ids as $option_id ) {
					$item_option = new \Square\Models\CatalogItemOptionForItem();
					$item_option->setItemOptionId( $option_id );
					$product_options[] = $item_option;
				}

				$catalog_object->getItemData()->setItemOptions( $product_options );
			} else {
				// If the product has only one attribute, it's not a dynamic options product.
				// So, remove the dynamic options meta.
				delete_post_meta( $product->get_id(), '_dynamic_options' );
				$catalog_object->getItemData()->setItemOptions( null );
			}

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

							$catalog_variations[ $object_key ] = self::update_catalog_variation( $variation_object, $product_variation, $options_ids );

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

				$catalog_variations[] = self::update_catalog_variation( $variation_object, $product_variation, $options_ids );
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

			$catalog_object->getItemData()->setItemOptions( null );
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
	 * @param \WC_Product   $product        WooCommerce product
	 * @param array         $options_ids    Array of options IDs
	 *
	 * @return CatalogObject
	 * @throws \Exception
	 */
	public static function update_catalog_variation( CatalogObject $catalog_object, \WC_Product $product, $options_ids = array() ) {

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
			$result                = wc_square()->get_api()->retrieve_options_data();
			$options_data          = isset( $result[1] ) ? $result[1] : array();
			$parent_product        = wc_get_product( $product->get_parent_id() );
			$attributes            = self::get_used_variation_attributes( $parent_product );
			$variation_items       = $product->get_attributes();
			$variation_item_values = array();

			if ( 1 === count( $attributes ) ) {
				// Set the name of the variation if it's a single variation.
				$variation_data->setName( reset( $variation_items ) );
				$variation_data->setItemOptionValues( null );
			} else {
				// If there are multiple attributes, the name of the variation is the combination of all attribute values.
				$variation_name  = array();
				$variation_index = 0;

				/**
				 * Set the `item_option_values` for the variation.
				 *
				 * Retrieve the options data from the transient. At this point, the options data
				 * should already be available, as we have already created the necessary options
				 * and values in the parent product above.
				 */
				foreach ( $variation_items as $attribute_id => $attribute_value ) {
					// If the attribute value is empty, set it to 'Any'.
					$attribute_value = empty( $attribute_value ) ? WC_SQUARE_OPTION_ANY : $attribute_value;

					// Check if it's a global attribute (taxonomy-based, e.g., "pa_color")
					$taxonomy_exists = false;
					if ( taxonomy_exists( $attribute_id ) ) {
						// Use wc_attribute_label for global attributes
						$attribute_name   = $attribute_id;
						$variation_name[] = $attribute_value = WC_SQUARE_OPTION_ANY === $attribute_value ? WC_SQUARE_OPTION_ANY : get_term_by( 'slug', $attribute_value, $attribute_id )->name;
						$taxonomy_exists  = true;
					} else {
						// For custom attributes, simply use the cleaned-up attribute ID
						$attribute_name   = str_replace( '-', ' ', $attribute_id );
						$attribute_id     = $attribute_name;
						$variation_name[] = $attribute_value;
					}

					$option_id       = '';
					$option_value_id = '';
					if ( isset( $options_data[ $options_ids[ $variation_index ] ] ) ) {
						$option_id = $options_ids[ $variation_index ];

						foreach ( $options_data[ $options_ids[ $variation_index ] ]['value_ids'] as $value_id => $value_name ) {
							if ( $value_name === $attribute_value ) {
								$option_value_id = $value_id;
								break;
							}
						}
					}

					if ( $option_id && $option_value_id ) {
						$option_value_object = new \Square\Models\CatalogItemOptionValueForItemVariation();
						$option_value_object->setItemOptionId( $option_id );
						$option_value_object->setItemOptionValueId( $option_value_id );

						$variation_item_values[] = $option_value_object;
					} else {

						if ( $taxonomy_exists ) {
							// Get all attribute terms from Woo taxonomy.
							$attribute_option_values = get_terms( $attribute_id );
							$attribute_option_values = wp_list_pluck( $attribute_option_values, 'name' );
						} else {
							// Get all attribute values from the parent product.
							$attribute_option_values = $parent_product->get_attribute( $attribute_id );
							$attribute_option_values = array_map( 'trim', explode( '|', $attribute_option_values ) );
						}

						// If the attribute value is 'Any', add it to the attribute option values.
						if ( WC_SQUARE_OPTION_ANY === $attribute_value && ! in_array( WC_SQUARE_OPTION_ANY, $attribute_option_values, true ) ) {
							$attribute_option_values[] = WC_SQUARE_OPTION_ANY;
						}

						$option    = wc_square()->get_api()->create_options_and_values( $option_id, $attribute_name, $attribute_option_values );
						$option_id = $option->getId();

						// Get the Square ID of the attribute value.
						$updated_option_values = $option->getItemOptionData()->getValues();
						foreach ( $updated_option_values as $option_value ) {
							if ( $option_value->getItemOptionValueData()->getName() === $attribute_value ) {
								$option_value_id = $option_value->getId();
								break;
							}
						}

						$option_value_object = new \Square\Models\CatalogItemOptionValueForItemVariation();
						$option_value_object->setItemOptionId( $option_id );
						$option_value_object->setItemOptionValueId( $option_value_id );

						$variation_item_values[] = $option_value_object;
					}

					++$variation_index;
				}

				// Set the name of the variation as the combination of all attribute values.
				$variation_data->setName( implode( ', ', $variation_name ) );
				$variation_data->setItemOptionValues( $variation_item_values );
			}
		}

		if ( wc_square()->get_settings_handler()->is_inventory_sync_enabled() ) {
			$track_inventory    = $variation_data->getTrackInventory();
			$location_overrides = $variation_data->getLocationOverrides();

			/*
			 * Only update track_inventory if it's not set.
			 * This will only update inventory tracking on new variations.
			 * inventory tracking will remain the same for existing variations.
			 */
			if ( is_null( $track_inventory ) && is_null( $location_overrides ) ) {
				$variation_data->setTrackInventory( $product->get_manage_stock() );

				// If the product is not managing stock and is out of stock, set it as sold out.
				if ( ! $product->get_manage_stock() && 'outofstock' === $product->get_stock_status() ) {
					$configured_location = wc_square()->get_settings_handler()->get_location_id();
					$location_override   = new \Square\Models\ItemVariationLocationOverrides();
					$location_override->setLocationId( $configured_location );
					// We need to set track_inventory to true to be able to set sold_out to true, without it will be ignored.
					$location_override->setTrackInventory( true );
					$location_override->setSoldOut( true );
					$location_overrides = array( $location_override );

					// Set the location overrides.
					$variation_data->setLocationOverrides( $location_overrides );
				}
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

	/**
	 * Helper to get only attributes used for variations.
	 *
	 * @since 4.9.3
	 *
	 * @param WC_Product $product
	 * @return array
	 */
	public static function get_used_variation_attributes( $product ) {
		return array_filter(
			$product->get_attributes(),
			function ( $attribute ) {
				return $attribute->get_variation();
			}
		);
	}
}
