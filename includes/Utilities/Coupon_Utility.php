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
use WooCommerce\Square\Handlers\Product;
use Square\Models;
use Square\Models\CatalogObjectType;
use Square\Models\CatalogDiscountType;

/**
 * Map a Square Coupon/Discount to a WooCommerce Coupon.
 *
 * @since 5.3.0
 */
class Coupon_Utility {

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
	 * Check if Square discount codes are enabled (setting + filter).
	 * Scope is not checked here; use Token_Scope_Utility in admin for merchant notices only.
	 *
	 * @since 5.3.0
	 *
	 * @return bool True if Square discount codes should be processed.
	 */
	public static function is_square_discount_codes_enabled() {
		$square_settings = get_option( 'wc_square_settings', array() );
		$from_setting    = ! array_key_exists( 'enable_square_discount_codes', $square_settings ) || 'yes' === $square_settings['enable_square_discount_codes'];

		/**
		 * Filters whether Square discount codes should be processed.
		 *
		 * @since 5.3.0
		 *
		 * @param bool $enable_square_discount_codes Whether Square discount codes should be processed. Default follows Square settings.
		 */
		return (bool) apply_filters( 'woocommerce_square_enable_discount_codes', $from_setting );
	}

	/**
	 * Remove a coupon from the cart by code (finds the applied code that matches and removes it).
	 *
	 * @since 5.3.0
	 *
	 * @param string $coupon_code The coupon code to remove (matched with wc_is_same_coupon).
	 * @return bool True if the coupon was found and removed, false otherwise.
	 */
	public static function remove_coupon_from_cart( $coupon_code ) {
		$cart = WC()->cart;
		if ( ! $cart ) {
			return false;
		}
		foreach ( $cart->get_applied_coupons() as $applied_code ) {
			if ( wc_is_same_coupon( $applied_code, $coupon_code ) ) {
				$cart->remove_coupon( $applied_code );
				return true;
			}
		}
		return false;
	}

	/**
	 * Check if a coupon code is in the cart's applied coupons.
	 *
	 * @since 5.3.0
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
	 * Build a map of cart item key => catalog_object_id and name for matching Square line items to cart.
	 *
	 * @since 5.3.0
	 *
	 * @param \WC_Cart $cart WooCommerce cart.
	 * @return array<string, array{catalog_object_id: string, name: string}> Cart key => lookup data.
	 */
	public static function get_cart_items_by_key( $cart ) {
		$map = array();
		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
			$product               = $cart_item['data'];
			$map[ $cart_item_key ] = array(
				'catalog_object_id' => $product->get_meta( Product::SQUARE_VARIATION_ID_META_KEY ),
				'name'              => $product->get_name(),
			);
		}
		return $map;
	}

	/**
	 * Find the WooCommerce cart item key that corresponds to a Square order line item.
	 * Matches by catalog object ID first, then by product name.
	 *
	 * @since 5.3.0
	 *
	 * @param array $square_line_item  Line item from Square API response.
	 * @param array $cart_items_by_key Map from get_cart_items_by_key().
	 * @return string|null Cart item key or null if no match.
	 */
	public static function match_square_line_to_cart_key( $square_line_item, $cart_items_by_key ) {
		if ( ! empty( $square_line_item['catalog_object_id'] ) ) {
			foreach ( $cart_items_by_key as $cart_key => $info ) {
				if ( $info['catalog_object_id'] === $square_line_item['catalog_object_id'] ) {
					return $cart_key;
				}
			}
		}

		if ( ! empty( $square_line_item['name'] ) ) {
			foreach ( $cart_items_by_key as $cart_key => $info ) {
				if ( $info['name'] === $square_line_item['name'] ) {
					return $cart_key;
				}
			}
		}

		return null;
	}

	/**
	 * Get the transient key for caching discount code data.
	 *
	 * @since 5.3.0
	 *
	 * @param string $discount_code The discount code.
	 * @return string Transient key.
	 */
	public static function get_discount_code_transient_key( $discount_code ) {
		return 'square_discount_code_' . md5( strtolower( $discount_code ) );
	}

	/**
	 * Sentinel key in transient value when the discount code was looked up and not found.
	 * get_transient() returns false when the key is missing, so we store an array to distinguish "not found" from "not cached".
	 *
	 * @var string
	 */
	const CACHE_SENTINEL_NOT_FOUND = '__not_found';

	/**
	 * Cache discount code details.
	 * Uses WordPress transients to cache discount code data for 15 minutes.
	 *
	 * @since 5.3.0
	 *
	 * @param string     $discount_code The discount code.
	 * @param array|null $code_details  The discount code details to cache. Null if not found.
	 */
	public static function set_cache_discount_code( $discount_code, $code_details ) {
		$transient_key = self::get_discount_code_transient_key( $discount_code );

		// Cache for 15 minutes. Store a sentinel array when not found so we can tell "not found" from "transient missing".
		$cache_value = null === $code_details ? array( self::CACHE_SENTINEL_NOT_FOUND => true ) : $code_details;
		set_transient( $transient_key, $cache_value, 15 * MINUTE_IN_SECONDS );
	}

	/**
	 * Retrieve cached discount code details.
	 *
	 * @since 5.3.0
	 *
	 * @param string $discount_code The discount code.
	 * @return array|null|false Cached discount code details, false if cached as "not found", or null if not cached.
	 */
	public static function get_cache_discount_code( $discount_code ) {
		$transient_key = self::get_discount_code_transient_key( $discount_code );
		$cached        = get_transient( $transient_key );

		// Transient missing (expired or never set) → not cached.
		if ( false === $cached ) {
			return null;
		}

		// Explicitly cached as "not found" (sentinel array).
		if ( is_array( $cached ) && isset( $cached[ self::CACHE_SENTINEL_NOT_FOUND ] ) ) {
			return false;
		}

		return $cached;
	}

	/**
	 * Clear cached discount code details.
	 *
	 * @since 5.3.0
	 *
	 * @param string $discount_code The discount code.
	 */
	public static function clear_cache_discount_code( $discount_code ) {
		$transient_key = self::get_discount_code_transient_key( $discount_code );
		delete_transient( $transient_key );
	}

	/**
	 * Retrieve discount code from the Square API.
	 * Uses caching to avoid repeated API calls for the same code.
	 *
	 * @since 5.3.0
	 *
	 * @param string $discount_code The discount code to retrieve.
	 * @return array|null Discount code details, or null if not found.
	 */
	public static function get_discount_code( $discount_code ) {
		$cached = self::get_cache_discount_code( $discount_code );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		if ( false === $cached ) {
			// Cached as "not found"; avoid redundant API call.
			return null;
		}

		$data = self::search_discount_codes_via_api( $discount_code );

		if ( null === $data || empty( $data['discount_codes'] ) ) {
			self::set_cache_discount_code( $discount_code, null );
			return null;
		}

		$code = self::find_valid_discount_code( $data['discount_codes'], $discount_code );
		if ( null === $code ) {
			self::set_cache_discount_code( $discount_code, null );
			return null;
		}

		self::set_cache_discount_code( $discount_code, $code );
		return $code;
	}

	/**
	 * Get Square discount code ID by coupon code.
	 *
	 * @since 5.3.0
	 *
	 * @param string $coupon_code The coupon code to search for.
	 * @return string|null The discount code ID or null if not found.
	 */
	public static function get_square_discount_code_id_by_code( $coupon_code ) {
		$code = self::get_discount_code( $coupon_code );
		return ( $code && isset( $code['id'] ) ) ? $code['id'] : null;
	}

	/**
	 * Find and validate a matching discount code from API response.
	 *
	 * @since 5.3.0
	 *
	 * @param array  $discount_codes Array of discount codes from API response.
	 * @param string $coupon_code    The coupon code to find and validate.
	 * @return array|null The matching and valid discount code, or null if not found or invalid.
	 */
	private static function find_valid_discount_code( $discount_codes, $coupon_code ) {
		if ( empty( $discount_codes ) || ! is_array( $discount_codes ) ) {
			return null;
		}

		$current_time = time();

		foreach ( $discount_codes as $code ) {
			if ( ! isset( $code['code'] ) || strtoupper( $code['code'] ) !== strtoupper( $coupon_code ) ) {
				continue;
			}

			$valid_from = isset( $code['valid_from'] ) ? strtotime( $code['valid_from'] ) : null;
			$expires_at = isset( $code['expires_at'] ) ? strtotime( $code['expires_at'] ) : null;

			if ( null !== $valid_from && $valid_from > $current_time ) {
				continue;
			}
			if ( null !== $expires_at && $expires_at < $current_time ) {
				continue;
			}

			return $code;
		}

		return null;
	}

	/**
	 * Search for discount codes via Square API.
	 *
	 * @since 5.3.0
	 *
	 * @param string $coupon_code The coupon code to search for.
	 * @param int    $timeout     Request timeout in seconds. Default 30.
	 * @return array|null Response data with 'discount_codes' key, or null on error.
	 */
	private static function search_discount_codes_via_api( $coupon_code, $timeout = 30 ) {
		$query = array(
			'query' => array(
				'filter' => array(
					'code' => $coupon_code,
				),
			),
		);

		$result = self::square_api_post( 'discount-codes/search', $query, $timeout );

		if ( is_wp_error( $result ) ) {
			if ( function_exists( 'wc_square' ) ) {
				wc_square()->log( sprintf( 'Error searching for discount code %s: %s', $coupon_code, $result->get_error_message() ), 'square-coupons' );
			}
			return null;
		}

		return isset( $result['body'] ) ? $result['body'] : null;
	}

	/**
	 * Get Square API credentials for direct HTTP requests.
	 * Used for endpoints not available in the Square SDK (e.g., discount codes search).
	 * Credentials are cached in static properties to avoid redundant fetches during a request.
	 *
	 * @since 5.3.0
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
	 * @since 5.3.0
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
	 * @since 5.3.0
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

		// Retrieve the pricing rule object.
		$pricing_rule_objects = self::request_pricing_rule_objects( $pricing_rule_id, $pricing_rule_version );

		if ( ! $pricing_rule_objects ) {
			// Unable to retrieve pricing rule details/objects. Coupon invalid/incomplete.
			return false;
		}

		// Map the Square coupon format to the WC coupon format.
		// product_ids is empty: eligibility is determined by Square (CalculateOrder);
		// amount is 0: we override amounts per line later in Coupons::override_discount_amount_with_square().
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
	 * Get the Square CatalogDiscount object ID for a pricing rule (used to match order.discounts in API responses).
	 * Results are cached by pricing_rule_id and version to avoid repeated API calls.
	 *
	 * @since 5.3.0
	 *
	 * @param string $pricing_rule_id      The Square pricing rule ID.
	 * @param int    $pricing_rule_version The Square pricing rule version.
	 * @return string|null The discount catalog object ID, or null if not found.
	 */
	public static function get_discount_catalog_id_for_pricing_rule( $pricing_rule_id, $pricing_rule_version ) {
		if ( empty( $pricing_rule_id ) ) {
			return null;
		}
		$cache_key = 'square_discount_catalog_id_' . md5( $pricing_rule_id . '_' . (int) $pricing_rule_version );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached && is_string( $cached ) ) {
			return $cached;
		}
		if ( ! self::request_pricing_rule_objects( $pricing_rule_id, $pricing_rule_version ) ) {
			return null;
		}
		$discount_id = null;
		if ( self::$discount_object instanceof Models\CatalogObject ) {
			$discount_id = self::$discount_object->getId();
		}
		if ( $discount_id ) {
			set_transient( $cache_key, $discount_id, HOUR_IN_SECONDS );
		}

		return $discount_id;
	}

	/**
	 * Request the pricing rule and related objects from Square.
	 *
	 * @since 5.3.0
	 *
	 * @param string $pricing_rule_id      The Square pricing rule ID.
	 * @param int    $pricing_rule_version The Square pricing rule version.
	 *
	 * @return bool True on success, false on failure.
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
	 * @since 5.3.0
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
	 * @since 5.3.0
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
