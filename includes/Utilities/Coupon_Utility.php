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
	 * Check if a coupon code is in the cart's applied coupons.
	 *
	 * @since x.x.x
	 *
	 * @param string $coupon_code The coupon code to check.
	 * @return bool True if the coupon is applied to the cart.
	 */
	public static function is_coupon_in_applied_cart( $coupon_code ) {
		$cart = WC()->cart;
		if ( ! $cart ) {
			return false;
		}
		foreach ( $cart->get_applied_coupons() as $applied_code ) {
			if ( wc_is_same_coupon( $applied_code, $coupon_code ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Get Square API credentials for direct HTTP requests.
	 * Used for endpoints not available in the Square SDK (e.g., discount codes search).
	 * Credentials are cached in static properties to avoid redundant fetches during a request.
	 *
	 * @since x.x.x
	 *
	 * @return array|null Array with 'access_token', 'is_sandbox', and 'base_url', or null on error.
	 */
	public static function get_square_api_credentials() {
		// Return cached credentials if already set (credentials don't change during a request).
		if ( ! empty( self::$bearer_token ) ) {
			$base_url = 'https://connect.squareup' . ( self::$is_sandbox ? 'sandbox' : '' ) . '.com/v2';
			return array(
				'access_token' => self::$bearer_token,
				'is_sandbox'   => self::$is_sandbox,
				'base_url'     => $base_url,
			);
		}

		if ( ! function_exists( 'wc_square' ) ) {
			return null;
		}

		$settings_handler = wc_square()->get_settings_handler();
		if ( ! $settings_handler ) {
			return null;
		}

		$bearer_token = $settings_handler->get_access_token();
		$is_sandbox   = $settings_handler->is_sandbox();

		if ( empty( $bearer_token ) ) {
			return null;
		}

		// Cache credentials in static properties for subsequent calls.
		self::$bearer_token = $bearer_token;
		self::$is_sandbox   = $is_sandbox;

		$base_url = 'https://connect.squareup' . ( $is_sandbox ? 'sandbox' : '' ) . '.com/v2';

		return array(
			'access_token' => $bearer_token,
			'is_sandbox'   => $is_sandbox,
			'base_url'     => $base_url,
		);
	}

	/**
	 * Make a POST request to the Square API.
	 * Wrapper for direct HTTP calls to avoid duplicating credential and request logic.
	 *
	 * @since x.x.x
	 *
	 * @param string $path    API path relative to base URL (e.g. 'orders/calculate' or 'discount-codes/search').
	 * @param array  $body    Request body as array (will be JSON-encoded).
	 * @param int    $timeout Request timeout in seconds. Default 30.
	 * @return array|WP_Error Decoded response body and response code on success, WP_Error on failure.
	 */
	public static function square_api_post( $path, $body, $timeout = 30 ) {
		$credentials = self::get_square_api_credentials();
		if ( null === $credentials ) {
			return new \WP_Error( 'missing_credentials', __( 'Square API credentials are not configured.', 'woocommerce-square' ) );
		}

		$api_url = $credentials['base_url'] . '/' . ltrim( $path, '/' );

		$response = wp_remote_post(
			$api_url,
			array(
				'headers' => array(
					'Authorization'  => 'Bearer ' . $credentials['access_token'],
					'Content-Type'   => 'application/json',
					'Square-Version' => '2025-01-23',
				),
				'body'    => wp_json_encode( $body ),
				'timeout' => $timeout,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( 200 !== $response_code ) {
			$error_data    = json_decode( $response_body, true );
			$error_message = isset( $error_data['errors'] ) && is_array( $error_data['errors'] ) && ! empty( $error_data['errors'][0]['detail'] )
				? $error_data['errors'][0]['detail']
				: wp_remote_retrieve_response_message( $response );

			return new \WP_Error(
				'api_error',
				$error_message,
				array(
					'status'   => $response_code,
					'response' => $error_data,
				)
			);
		}

		$data = json_decode( $response_body, true );

		return array(
			'body'          => $data,
			'response_code' => $response_code,
		);
	}

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
		// Create cache key based on pricing rule ID and version.
		$pricing_rule_id      = $square_discount_code['pricing_rule_id'];
		$pricing_rule_version = $square_discount_code['pricing_rule_version'];
		$cache_key            = 'square_coupon_mapping_' . md5( $pricing_rule_id . '_' . $pricing_rule_version );

		// Check cache first.
		$cached = get_transient( $cache_key );
		if ( false !== $cached && is_array( $cached ) ) {
			// Update code in case it's different (though it should be the same for same pricing rule).
			$cached['code'] = $square_discount_code['code'];
			return $cached;
		}

		// Configure the API for use.
		// get_square_api_credentials() will cache credentials in static properties.
		$credentials = self::get_square_api_credentials();
		if ( null === $credentials ) {
			return false;
		}

		// Credentials are already cached in static properties by get_square_api_credentials().
		// Set API URL for this specific use case.
		self::$api_url = $credentials['base_url'] . '/discount-codes/search';

		// Retrieve the pricing rule object.
		$pricing_rule_objects = self::request_pricing_rule_objects( $pricing_rule_id, $pricing_rule_version );

		if ( ! $pricing_rule_objects ) {
			// Unable to retrieve pricing rule details/objects. Coupon invalid/incomplete.
			return false;
		}

		// Map the Square coupon format to the WC coupon format.
		// product_ids is empty: eligibility is determined by Square (CalculateOrder);
		// we override amounts per line later in Coupons::override_discount_amount_with_square().
		$wc_coupon = array(
			'code'          => $square_discount_code['code'],
			'discount_type' => self::map_discount_type(),
			'amount'        => 0,
			'product_ids'   => array(),
		);

		// Cache the result for 1 hour (pricing rules don't change frequently).
		set_transient( $cache_key, $wc_coupon, HOUR_IN_SECONDS );

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
		// Ensure credentials are available.
		$credentials = self::get_square_api_credentials();
		if ( null === $credentials ) {
			return false;
		}

		// Retrieve the pricing rule object.
		$request  = new API( $credentials['access_token'], $credentials['is_sandbox'] );
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
}
