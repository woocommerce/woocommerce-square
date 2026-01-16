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

namespace WooCommerce\Square\Utilities;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Square\API;
use Square\Models;
use Square\Models\CatalogObjectType;
use Square\Models\CatalogDiscountType;

/**
 * Map a Square Coupon/Discount to a WooCommerce Coupon.
 *
 * @since x.x.x
 */
class Coupon_Utility {

	/**
	 * The Square API URL.
	 *
	 * @var string
	 */
	protected static $api_url = '';

	/**
	 * The Square API bearer token.
	 *
	 * @var string
	 */
	protected static $bearer_token = '';

	/**
	 * Whether to use the Square sandbox environment.
	 *
	 * @var bool
	 */
	protected static $is_sandbox = false;

	/**
	 * The Square Discount object.
	 *
	 * @var Models\CatalogObject
	 */
	protected static $discount_object;

	/**
	 * The Square Pricing Rule and Product Set objects.
	 *
	 * @var Models\CatalogObject
	 */
	protected static $pricing_rule_object;

	/**
	 * The Square Product Set object.
	 *
	 * @var Models\CatalogObject
	 */
	protected static $product_set_object;

	/**
	 * Map a Square Discount Code to a WooCommerce Coupon array.
	 *
	 * This allows for the creation of manual WC_Coupon object mapped from the
	 * Square Discount code data provides.
	 *
	 * @since x.x.x
	 *
	 * @param array $square_discount_code The Square discount code array.
	 * @return array|false The mapped WooCommerce coupon data. False if mapping fails.
	 */
	public static function map_square_discount_code_to_woocommerce_coupon( $square_discount_code ) {
		$wc_coupon = array();

		$pricing_rule_id      = $square_discount_code['pricing_rule_id'];
		$pricing_rule_version = $square_discount_code['pricing_rule_version'];

		// Configure the API for use.
		self::$bearer_token = wc_square()->get_settings_handler()->get_access_token();
		self::$is_sandbox   = wc_square()->get_settings_handler()->is_sandbox();
		self::$api_url      = 'https://connect.squareup' . ( self::$is_sandbox ? 'sandbox' : '' ) . '.com/v2/discount-codes/search';

		// Retrieve the pricing rule object.
		$pricing_rule_objects = self::request_pricing_rule_objects( $pricing_rule_id, $pricing_rule_version );

		if ( ! $pricing_rule_objects ) {
			// Unable to retrieve pricing rule details/objects. Coupon invalid/incomplete.
			return false;
		}

		// Map the Square coupon format to the WC coupon format.
		$wc_coupon = array(
			'code'          => $square_discount_code['code'],
			'discount_type' => self::map_discount_type(),
			'amount'        => 0,
			'product_ids'   => self::map_product_ids(),
		);

		return $wc_coupon;
	}

	/**
	 * Request the pricing rule and related objects from Square.
	 *
	 * @since x.x.x
	 *
	 * @param string $pricing_rule_id The Square pricing rule ID.
	 * @param int $pricing_rule_version The Square pricing rule version.
	 */
	protected static function request_pricing_rule_objects( $pricing_rule_id, $pricing_rule_version ) {
		// Retrieve the pricing rule object.
		$request  = new API( self::$bearer_token, self::$is_sandbox );
		$response = $request->retrieve_catalog_object( $pricing_rule_id, true, $pricing_rule_version );

		if ( ! $response->get_data() instanceof \Square\Models\RetrieveCatalogObjectResponse ) {
			// Unable to retrieve pricing rule details.
			return false;
		}

		$pricing_rule = $response->get_data()->getObject();
		if ( ! $pricing_rule instanceof Models\CatalogObject
			|| $pricing_rule->getType() !== CatalogObjectType::PRICING_RULE ) {
			// Invalid pricing rule object.
			return false;
		}

		$related_objects = $response->get_data()->getRelatedObjects();
		if ( empty( $related_objects ) || ! is_array( $related_objects ) ) {
			// No related objects found.
			return false;
		}

		$discount_object = self::get_related_objects_by_type( $related_objects, CatalogObjectType::DISCOUNT );
		if ( empty( $discount_object ) || ! $discount_object[0] instanceof Models\CatalogObject ) {
			// No discount object found.
			return false;
		}

		$product_set_object = self::get_related_objects_by_type( $related_objects, CatalogObjectType::PRODUCT_SET );
		if ( empty( $product_set_object ) || ! $product_set_object[0] instanceof Models\CatalogObject ) {
			/*
			 * No product set object found.
			 *
			 * Note: A pricing rule is always associated with a product set. If the
			 * pricing rule applies to all products. The product set will include the
			 * `allProducts` field set to true.
			 */

			return false;
		}

		self::$pricing_rule_object = $pricing_rule;
		self::$discount_object     = $discount_object[0];
		self::$product_set_object  = $product_set_object[0];

		return true;
	}

	/**
	 * Filter related objects by type.
	 *
	 * @since x.x.x
	 *
	 * @param Models\CatalogObject[] $related_objects The related objects.
	 * @param string $type The desired object type.
	 * @return Models\CatalogObject[] The filtered related objects.
	 */
	protected static function get_related_objects_by_type( $related_objects, $type ) {
		$filtered_objects = array();

		foreach ( $related_objects as $object ) {
			if (
				$object instanceof Models\CatalogObject
				&& $object->getType() === $type
				&& $object->getIsDeleted() === false
			) {
				$filtered_objects[] = $object;
			}
		}

		return $filtered_objects;
	}

	/**
	 * Map the Square discount type to the WooCommerce coupon discount type.
	 *
	 * @since x.x.x
	 *
	 * @return string The mapped discount type.
	 * @throws \Exception If the discount type is unsupported.
	 */
	protected static function map_discount_type() {
		$sq_discount_type   = self::$discount_object->getDiscountData()->getDiscountType();
		$sq_is_all_products = self::$product_set_object->getProductSetData()->getAllProducts();

		switch ( $sq_discount_type ) {
			case CatalogDiscountType::FIXED_AMOUNT:
				if ( $sq_is_all_products ) {
					return 'fixed_cart';
				}
				return 'fixed_product';
			case CatalogDiscountType::FIXED_PERCENTAGE:
				return 'percent';
			default:
				throw new \Exception(
					sprintf(
						/* translators: 1: Square discount type. */
						esc_html__( 'Unsupported discount type: %s', 'woocommerce-square' ),
						esc_html( $sq_discount_type )
					)
				);
		}
	}

	/**
	 * Maps the Square discount amount to the WooCommerce discount amount.
	 *
	 * For percentage discounts this value is unchanged as the numbers are represented
	 * as a percentage in both systems.
	 *
	 * For fixed amounts, the value is stored in Square in the lowest currency amount
	 * (ie, cents for USD) whereas WooCommerce stores the number in the base currency
	 * amount (ie, dollars for USD).
	 *
	 * @return float Discount amount.
	 */
	protected static function map_discount_amount() {
		if ( 'percent' === self::map_discount_type() ) {
			return (float) self::$discount_object->getDiscountData()->getPercentage();
		}

		// Fixed amount, convert from Square format (raw cents) to WC format (float).
		$sq_money = self::$discount_object->getDiscountData()->getAmountMoney();

		return (float) Helper::number_format( Money_Utility::cents_to_float( $sq_money->getAmount() ) );
	}

	/**
	 * Map the Square product IDs to WooCommerce product IDs.
	 *
	 * Takes the product IDs stored in the Square Product Set object and maps them
	 * to WooCommerce product IDs via the `_square_item_id` and `_square_item_variation_id`
	 * meta data fields.
	 *
	 * An empty array is used to indicate the discount applies to all products.
	 *
	 * @since x.x.x
	 *
	 * @return int[] The mapped WooCommerce product IDs.
	 */
	protected static function map_product_ids() {
		global $wpdb;
		$sq_is_all_products = self::$product_set_object->getProductSetData()->getAllProducts();

		if ( $sq_is_all_products ) {
			return array();
		}

		$sq_product_ids = self::$product_set_object->getProductSetData()->getProductIdsAny();

		// Remove dupes and sort for cache key consistency.
		$sq_product_ids = array_unique( $sq_product_ids );
		sort( $sq_product_ids );

		$cache_key   = 'woocommerce_square::sq_product_ids::' . md5( wp_json_encode( $sq_product_ids ) );
		$cache_group = 'post-queries';
		$cache_salt  = wp_cache_get_last_changed( 'posts' );

		if ( is_wp_version_compatible( '6.9' ) ) {
			$results = wp_cache_get_salted( $cache_key, $cache_group, $cache_salt );
		} else {
			$results = wp_cache_get( "{$cache_salt}::{$cache_key}", $cache_group );
		}

		if ( false === $results ) {
			// Query the product IDs via the `_square_item_id` meta field.
			$results = $wpdb->get_col(
				$wpdb->prepare(
					sprintf(
						"SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ( '_square_item_id', '_square_item_variation_id') AND meta_value IN (%s)",
						implode( ',', array_fill( 0, count( $sq_product_ids ), '%s' ) )
					),
					$sq_product_ids
				)
			);

			if ( is_wp_version_compatible( '6.9' ) ) {
				wp_cache_set_salted( $cache_key, $results, $cache_group, DAY_IN_SECONDS, $cache_salt );
			} else {
				wp_cache_set( "{$cache_salt}::{$cache_key}", $results, $cache_group, DAY_IN_SECONDS );
			}
		}

		if ( empty( $results ) ) {
			return array();
		}

		// Prime post cache to avoid DB calls.
		_prime_post_caches( $results );
		return array_values( $results );
	}
}
