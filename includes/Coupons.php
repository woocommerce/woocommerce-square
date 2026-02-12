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

namespace WooCommerce\Square;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Square\Utilities\Coupon_Utility;
use WooCommerce\Square\API;
use WooCommerce\Square\Handlers\Product;
use WooCommerce\Square\Utilities\Money_Utility;
use WooCommerce\Square\Framework\Square_Helper;

/**
 * Class Coupons
 *
 * Handles coupon (WooCommerce term) and discount code (Square term)
 * use during the checkout process.
 *
 * @package WooCommerce\Square
 */
class Coupons {

	/**
	 * The singleton instance.
	 *
	 * @var Coupons|null
	 */
	private static $instance = null;

	/**
	 * Stores the last Square discount validation error message for woocommerce_coupon_error filter.
	 * Used when we reject a Square coupon in woocommerce_coupon_is_valid (e.g. no discount applies).
	 *
	 * @var array|null Array with 'code' and 'message' keys, or null.
	 */
	private static $last_square_validation_error = null;

	/**
	 * Gets the singleton instance.
	 *
	 * @since x.x.x
	 *
	 * @return Coupons The singleton instance.
	 */
	public static function instance() {

		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance::init();
		}

		return self::$instance;
	}

	/**
	 * Check if Square discount codes are enabled.
	 *
	 * Use the woocommerce_square_enable_discount_codes filter to opt out of Square discount code
	 * handling. When disabled, only WooCommerce coupons will be processed on cart/checkout.
	 *
	 * @since x.x.x
	 *
	 * @return bool True if Square discount codes should be processed, false to use only WooCommerce coupons.
	 */
	public static function is_square_discount_codes_enabled() {
		/**
		 * Filters whether Square discount codes should be processed.
		 *
		 * @since x.x.x
		 *
		 * @param bool $enable_square_discount_codes Whether Square discount codes should be processed. Default true.
		 */
		return apply_filters( 'woocommerce_square_enable_discount_codes', true );
	}

	/**
	 * Initialize Coupons class.
	 *
	 * @since x.x.x
	 */
	public static function init() {
		if ( ! self::is_square_discount_codes_enabled() ) {
			return;
		}

		// Detect Square coupon > calculate the discount from API > Store in the cart session for later use.
		add_action( 'woocommerce_applied_coupon', array( self::$instance, 'handle_coupon_applied' ), 10, 1 );

		// Clear the discount data from the cart session when the coupon is removed.
		add_action( 'woocommerce_removed_coupon', array( self::$instance, 'handle_coupon_removed' ), 10, 1 );

		// Store the discount code data in the order meta data.
		add_action( 'woocommerce_checkout_create_order', array( self::$instance, 'populate_order_square_discount_meta' ), 10, 1 );

		// Ensure Square discount meta is always set (backup in case create_order didn't run or metadata was missed).
		add_action( 'woocommerce_checkout_update_order_meta', array( self::$instance, 'ensure_order_square_discount_meta' ), 10, 1 );

		// Fetch Square discount full data (pricing rule, version, code) > Convert to a WooCommerce Coupon array.
		// Keeping discount amount and product_ids empty as we will override it with the Square's CalculateOrder response.
		add_filter( 'woocommerce_get_shop_coupon_data', array( self::$instance, 'filter_woocommerce_get_shop_coupon_data' ), 10, 3 );

		// Override the discount amount (which is set to 0 by default) with the Square calculated discount amount.
		add_filter( 'woocommerce_coupon_get_discount_amount', array( self::$instance, 'override_discount_amount_with_square' ), 10, 5 );

		// Validate Square coupon provides a discount BEFORE WooCommerce adds it (avoids success + error double message).
		add_filter( 'woocommerce_coupon_is_valid', array( self::$instance, 'validate_square_coupon_has_discount' ), 5, 2 );

		// Prevent non-Square coupons from being used with Square coupons.
		add_filter( 'woocommerce_coupon_is_valid', array( self::$instance, 'prevent_non_square_coupons_with_square_coupons' ), 10, 2 );

		// Show our custom error message when we reject a Square coupon in validate_square_coupon_has_discount.
		add_filter( 'woocommerce_coupon_error', array( self::$instance, 'filter_square_coupon_validation_error_message' ), 10, 3 );

		// Trigger recalculation before WooCommerce calculates totals (covers add, remove, quantity update).
		add_action( 'woocommerce_before_calculate_totals', array( self::$instance, 'handle_cart_contents_changed' ), 5 );
	}

	/**
	 * Prevent non-Square coupons from being used with Square coupons.
	 *
	 * @since x.x.x
	 *
	 * @param bool       $is_valid Whether the coupon is valid.
	 * @param \WC_Coupon $coupon   Coupon object.
	 *
	 * @return bool|WP_Error True if valid, false or WP_Error if invalid.
	 */
	public static function prevent_non_square_coupons_with_square_coupons( $is_valid, $coupon ) {
		// If coupon is already invalid, don't override.
		if ( ! $is_valid ) {
			return $is_valid;
		}

		$cart = WC()->cart;
		if ( ! $cart ) {
			return $is_valid;
		}

		$coupon_code     = $coupon->get_code();
		$applied_coupons = $cart->get_applied_coupons();

		// Check if this is a Square discount code.
		$is_square_coupon = ! empty( self::get_square_discount_code_id_by_code( $coupon_code ) );

		// Check if there are any applied coupons.
		if ( ! empty( $applied_coupons ) ) {
			$has_square_coupon = ! empty( self::get_applied_square_coupon_codes() );

			// If trying to apply Square coupon but WooCommerce coupon exists.
			if ( $is_square_coupon && ! $has_square_coupon ) {
				$coupon->set_error_message(
					sprintf(
						/* translators: %s: coupon code */
						__( 'Sorry, the Square discount code "%s" cannot be used in combination with WooCommerce coupons. Please remove the WooCommerce coupon and try again.', 'woocommerce-square' ),
						esc_html( $coupon_code )
					)
				);
				return false;
			}

			// If trying to apply WooCommerce coupon but Square coupon exists.
			if ( ! $is_square_coupon && $has_square_coupon ) {
				$coupon->set_error_message(
					sprintf(
						/* translators: %s: coupon code */
						__( 'Sorry, the WooCommerce coupon "%s" cannot be used in combination with Square discount codes. Please remove the Square discount code and try again.', 'woocommerce-square' ),
						esc_html( $coupon_code )
					)
				);
				return false;
			}
		}

		return $is_valid;
	}

	/**
	 * Validate Square coupon provides a discount before WooCommerce applies it.
	 * Runs at priority 5 so we reject before the success message is shown.
	 *
	 * @since x.x.x
	 *
	 * @param bool       $is_valid Whether the coupon is valid.
	 * @param \WC_Coupon $coupon   Coupon object.
	 *
	 * @return bool True if valid, false if Square coupon provides no discount.
	 */
	public static function validate_square_coupon_has_discount( $is_valid, $coupon ) {
		if ( ! $is_valid ) {
			return $is_valid;
		}

		$coupon_code = $coupon->get_code();
		if ( empty( self::get_square_discount_code_id_by_code( $coupon_code ) ) ) {
			return $is_valid;
		}

		$cart = WC()->cart;
		if ( ! $cart || $cart->is_empty() ) {
			return $is_valid;
		}

		try {
			self::calculate_square_discount_from_cart( $coupon_code );
		} catch ( \Exception $e ) {
			self::$last_square_validation_error = array(
				'code'    => $coupon_code,
				'message' => $e->getMessage(),
			);
			return false;
		}

		return $is_valid;
	}

	/**
	 * Replace the generic coupon error message with our Square-specific messages when we rejected.
	 * Handles: (1) Square discount validation (no discount, zero total), (2) mixing Square and WooCommerce coupons.
	 *
	 * @since x.x.x
	 *
	 * @param string $error_message The error message.
	 * @param int    $error_code    The error code.
	 * @param \WC_Coupon|null $coupon The coupon object.
	 *
	 * @return string The error message.
	 */
	public static function filter_square_coupon_validation_error_message( $error_message, $error_code, $coupon ) {
		if ( ! $coupon ) {
			return $error_message;
		}

		// Use custom message from Square validation (e.g. no discount, zero total).
		if ( null !== self::$last_square_validation_error ) {
			$coupon_code = $coupon->get_code();
			if ( wc_is_same_coupon( $coupon_code, self::$last_square_validation_error['code'] ) ) {
				$message = self::$last_square_validation_error['message'];

				self::$last_square_validation_error = null;
				return $message;
			}
		}

		// Use custom message from prevent_non_square_coupons (e.g. cannot mix Square and WooCommerce coupons).
		if ( \WC_Coupon::E_WC_COUPON_INVALID_FILTERED === $error_code ) {
			$custom_message = $coupon->get_error_message();
			if ( ! empty( $custom_message ) ) {
				return $custom_message;
			}
		}

		return $error_message;
	}

	/**
	 * Preflight the construction of a WC_Coupon object.
	 *
	 * @since x.x.x
	 *
	 * @param false|array $coupon      Coupon data. False indicates that the coupon has not yet
	 *                                 been found/replaced via the preflight process.
	 * @param mixed       $coupon_data Coupon data, object, ID or code as passed to the \WC_Coupon constructor.
	 * @param \WC_Coupon  $wc_coupon   The WC_Coupon instance being constructed
	 * @return false|array Modified coupon data.
	 */
	public static function filter_woocommerce_get_shop_coupon_data( $coupon, $coupon_data, \WC_Coupon $wc_coupon ) {
		// Coupon has already been found via preflight.
		if ( false !== $coupon ) {
			return $coupon;
		}

		// Only handle requests for string coupon codes.
		if ( ! is_string( $coupon_data ) ) {
			return $coupon;
		}

		// Use cached discount code lookup.
		$code_data = self::get_discount_code( $coupon_data );
		if ( null === $code_data ) {
			return $coupon;
		}

		$wc_coupon = Coupon_Utility::map_square_discount_code_to_woocommerce_coupon( $code_data );
		if ( false === $wc_coupon ) {
			return $coupon;
		}

		return $wc_coupon;
	}

	/**
	 * Find and validate a matching discount code from API response.
	 *
	 * @since x.x.x
	 *
	 * @param array  $discount_codes Array of discount codes from API response.
	 * @param string $coupon_code    The coupon code to find and validate.
	 * @return array|null The matching and valid discount code, or null if not found or invalid.
	 */
	private static function find_valid_discount_code( $discount_codes, $coupon_code ) {
		if ( empty( $discount_codes ) || ! is_array( $discount_codes ) ) {
			return null;
		}

		$current_time = current_time( 'timestamp' ); // phpcs:disable WordPress.DateTime.CurrentTimeTimestamp

		foreach ( $discount_codes as $code ) {
			// Match by code (case-insensitive).
			if ( ! isset( $code['code'] ) || strtoupper( $code['code'] ) !== strtoupper( $coupon_code ) ) {
				continue;
			}

			// Validate expiration dates if present.
			$valid_from = isset( $code['valid_from'] ) ? strtotime( $code['valid_from'] ) : null;
			$expires_at = isset( $code['expires_at'] ) ? strtotime( $code['expires_at'] ) : null;

			// Check if code is valid from date (skip if valid_from is set and in the future).
			if ( null !== $valid_from && $valid_from > $current_time ) {
				continue;
			}

			// Check if code is expired.
			if ( null !== $expires_at && $expires_at < $current_time ) {
				continue;
			}

			// Found a valid matching code.
			return $code;
		}

		return null;
	}

	/**
	 * Search for discount codes via Square API.
	 *
	 * @since x.x.x
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

		$result = Coupon_Utility::square_api_post( 'discount-codes/search', $query, $timeout );

		if ( is_wp_error( $result ) ) {
			if ( function_exists( 'wc_square' ) ) {
				wc_square()->log( sprintf( 'Error searching for discount code %s: %s', $coupon_code, $result->get_error_message() ), 'square-coupons' );
			}
			return null;
		}

		return isset( $result['body'] ) ? $result['body'] : null;
	}

	/**
	 * Retrieve discount code from the Square API.
	 * Uses caching to avoid repeated API calls for the same code.
	 *
	 * @since x.x.x
	 *
	 * @param string $discount_code The discount code to retrieve.
	 * @return array|null Discount code details, or null if not found.
	 */
	public static function get_discount_code( $discount_code ) {
		// First check cache.
		$cached = self::get_cache_discount_code( $discount_code );
		if ( $cached ) {
			// Return cached data.
			return $cached;
		}

		// Not in cache, fetch from API.
		$data = self::search_discount_codes_via_api( $discount_code );

		if ( null === $data || empty( $data['discount_codes'] ) ) {
			// Cache "not found" as false to avoid repeated API calls for invalid codes.
			self::set_cache_discount_code( $discount_code, null );
			return null;
		}

		// Find and validate the matching code.
		$code = self::find_valid_discount_code( $data['discount_codes'], $discount_code );
		if ( null === $code ) {
			// Cache "not found" as false.
			self::set_cache_discount_code( $discount_code, null );
			return null;
		}

		// Cache the found code details.
		self::set_cache_discount_code( $discount_code, $code );

		return $code;
	}

	/**
	 * Get the transient key for caching discount code data.
	 *
	 * @since x.x.x
	 *
	 * @param string $discount_code The discount code.
	 * @return string Transient key.
	 */
	private static function get_discount_code_transient_key( $discount_code ) {
		return 'square_discount_code_' . md5( strtolower( $discount_code ) );
	}

	/**
	 * Cache discount code details.
	 * Uses WordPress transients to cache discount code data for 1 hour.
	 *
	 * @since x.x.x
	 *
	 * @param string     $discount_code The discount code.
	 * @param array|null $code_details  The discount code details to cache. Null if not found.
	 */
	public static function set_cache_discount_code( $discount_code, $code_details ) {
		$transient_key = self::get_discount_code_transient_key( $discount_code );

		// Cache for 1 hour. Store false if not found to distinguish from "not cached yet".
		$cache_value = null === $code_details ? false : $code_details;
		set_transient( $transient_key, $cache_value, HOUR_IN_SECONDS * 1 );
	}

	/**
	 * Retrieve cached discount code details.
	 *
	 * @since x.x.x
	 *
	 * @param string $discount_code The discount code.
	 * @return array|null|false Cached discount code details, false if cached as "not found", or null if not cached.
	 */
	public static function get_cache_discount_code( $discount_code ) {
		$transient_key = self::get_discount_code_transient_key( $discount_code );
		$cached        = get_transient( $transient_key );

		// Return false if explicitly cached as "not found", null if not cached, or the cached array.
		return false === $cached ? false : ( $cached ? $cached : null );
	}

	/**
	 * Clear cached discount code details.
	 *
	 * @since x.x.x
	 *
	 * @param string $discount_code The discount code.
	 */
	public static function clear_cache_discount_code( $discount_code ) {
		$transient_key = self::get_discount_code_transient_key( $discount_code );
		delete_transient( $transient_key );

		// Also clear the old ID-only cache for backward compatibility.
		$old_transient_key = 'square_discount_code_id_' . $discount_code;
		delete_transient( $old_transient_key );
	}

	/**
	 * Handle coupon application - trigger Square discount calculation if conditions are met.
	 *
	 * @since x.x.x
	 *
	 * @param string $coupon_code The coupon code that was applied.
	 */
	public static function handle_coupon_applied( $coupon_code ) {
		// Check if this is a Square discount code.
		$square_discount_code_id = self::get_square_discount_code_id_by_code( $coupon_code );

		if ( empty( $square_discount_code_id ) ) {
			// Not a Square discount code, skip.
			return;
		}

		// Store discount code ID in cart session for later use.
		WC()->session->set( '_square_discount_code_id_' . $coupon_code, $square_discount_code_id );

		// Check if we can calculate now (shipping must be selected if required).
		$cart = WC()->cart;

		if ( ! $cart ) {
			return;
		}

		// Check if shipping is required and selected.
		if ( $cart->needs_shipping() ) {
			$chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );
			if ( empty( $chosen_shipping_methods ) ) {
				// Shipping not selected yet, mark for later calculation.
				WC()->session->set( '_square_discount_pending_recalc_' . $coupon_code, true );
				return;
			}
		}

		// Skip API call if we already calculated in woocommerce_coupon_is_valid (avoids duplicate CalculateOrder).
		$existing_amount = WC()->session->get( '_square_discount_amount_' . $coupon_code );
		if ( null !== $existing_amount && (float) $existing_amount > 0 ) {
			return;
		}

		// Shipping is selected (or not required), calculate now.
		try {
			self::calculate_square_discount_from_cart( $coupon_code );
		} catch ( \Exception $e ) {
			// Remove coupon (use exact code from cart so removal always matches) and show error.
			$applied_coupons = $cart->get_applied_coupons();
			foreach ( $applied_coupons as $applied_code ) {
				if ( wc_is_same_coupon( $applied_code, $coupon_code ) ) {
					$cart->remove_coupon( $applied_code );
					break;
				}
			}
			// Clear our Square session data for this coupon in case removal didn't fire woocommerce_removed_coupon in this context.
			self::handle_coupon_removed( $coupon_code );
			/* translators: %1$s: coupon code, %2$s: error message */
			wc_add_notice( sprintf( __( 'Unable to apply coupon "%1$s": %2$s', 'woocommerce-square' ), esc_html( $coupon_code ), esc_html( $e->getMessage() ) ), 'error' );
		}
	}

	/**
	 * Populate order with Square discount meta from cart session.
	 *
	 * @since x.x.x
	 *
	 * @param \WC_Order $order The order object.
	 * @return bool True if meta was set, false otherwise.
	 */
	public static function populate_order_square_discount_meta( $order ) {
		if ( ! $order instanceof \WC_Order || ! function_exists( 'wc_square' ) ) {
			return false;
		}

		$square_discount_code_ids = array();
		$first_code               = null;
		$first_id                 = null;

		// Prefer cart/session so we have amounts; works for shortcode checkout.
		$cart = WC()->cart;
		if ( $cart && WC()->session ) {
			$applied_coupons = $cart->get_applied_coupons();
			if ( ! empty( $applied_coupons ) ) {
				foreach ( $applied_coupons as $coupon_code ) {
					$square_discount_code_id = WC()->session->get( '_square_discount_code_id_' . $coupon_code );
					if ( empty( $square_discount_code_id ) ) {
						$square_discount_code_id = self::get_square_discount_code_id_by_code( $coupon_code );
					}
					if ( empty( $square_discount_code_id ) ) {
						continue;
					}
					$square_discount_code_ids[] = $square_discount_code_id;
					if ( null === $first_code ) {
						$first_code = $coupon_code;
						$first_id   = $square_discount_code_id;
					}
				}
			}
		}

		// Fallback: get Square discount code IDs from order coupon items (e.g. block/Store API checkout where session may be empty).
		if ( empty( $square_discount_code_ids ) ) {
			$coupon_items = $order->get_coupons();
			if ( ! empty( $coupon_items ) ) {
				foreach ( $coupon_items as $coupon_item ) {
					$coupon_code = $coupon_item->get_code();
					if ( empty( $coupon_code ) ) {
						continue;
					}
					$square_discount_code_id = self::get_square_discount_code_id_by_code( $coupon_code );
					if ( empty( $square_discount_code_id ) ) {
						continue;
					}
					$square_discount_code_ids[] = $square_discount_code_id;
					if ( null === $first_code ) {
						$first_code = $coupon_code;
						$first_id   = $square_discount_code_id;
					}
				}
			}
		}

		if ( empty( $square_discount_code_ids ) ) {
			return false;
		}

		$order->update_meta_data( '_square_discount_code_ids', $square_discount_code_ids );
		$order->update_meta_data( '_square_discount_code_id', $first_id );

		if ( null !== $first_code ) {
			$square_discount_amount = WC()->session->get( '_square_discount_amount_' . $first_code );
			if ( ! empty( $square_discount_amount ) ) {
				$order->update_meta_data( '_square_discount_amount', $square_discount_amount );
			}
			$square_discount_per_item = WC()->session->get( '_square_discount_per_item_' . $first_code );
			if ( ! empty( $square_discount_per_item ) ) {
				$order->update_meta_data( '_square_discount_per_item', $square_discount_per_item );
			}
		}

		return true;
	}

	/**
	 * Ensure order has Square discount code meta (backup for edge cases).
	 * Runs on woocommerce_checkout_update_order_meta so metadata is always available.
	 *
	 * @since x.x.x
	 *
	 * @param int $order_id The order ID.
	 */
	public static function ensure_order_square_discount_meta( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$existing_ids = self::get_order_square_discount_code_ids( $order );
		if ( ! empty( $existing_ids ) ) {
			return;
		}

		if ( self::populate_order_square_discount_meta( $order ) ) {
			$order->save_meta_data();
		}
	}

	/**
	 * Get Square discount code ID by coupon code.
	 *
	 * @since x.x.x
	 *
	 * @param string $coupon_code The coupon code to search for.
	 * @return string|null The discount code ID or null if not found.
	 */
	public static function get_square_discount_code_id_by_code( $coupon_code ) {
		// Only proceed if WooCommerce Square is active.
		if ( ! function_exists( 'wc_square' ) ) {
			return null;
		}

		$settings_handler = wc_square()->get_settings_handler();
		if ( ! $settings_handler ) {
			return null;
		}

		// Use cached discount code lookup.
		$code = self::get_discount_code( $coupon_code );
		if ( null === $code ) {
			return null;
		}

		// Extract and return the discount code ID.
		return isset( $code['id'] ) ? $code['id'] : null;
	}

	/**
	 * Last-chance: ensure order has Square discount code IDs from order coupon items before payment.
	 * Call this from the payment gateway when meta may be missing (e.g. block checkout). If the order
	 * has coupon items and they are Square codes, populates _square_discount_code_ids and saves.
	 *
	 * @since x.x.x
	 *
	 * @param \WC_Order $order The order object.
	 */
	public static function ensure_order_square_discount_code_ids_before_payment( $order ) {
		if ( ! $order instanceof \WC_Order || ! function_exists( 'wc_square' ) ) {
			return;
		}
		if ( ! empty( self::get_order_square_discount_code_ids( $order ) ) ) {
			return;
		}
		$coupon_items = $order->get_coupons();
		if ( empty( $coupon_items ) ) {
			return;
		}
		$square_discount_code_ids = array();
		$first_id                 = null;
		foreach ( $coupon_items as $coupon_item ) {
			$coupon_code = $coupon_item->get_code();
			if ( empty( $coupon_code ) ) {
				continue;
			}
			$square_discount_code_id = self::get_square_discount_code_id_by_code( $coupon_code );
			if ( empty( $square_discount_code_id ) ) {
				continue;
			}
			$square_discount_code_ids[] = $square_discount_code_id;
			if ( null === $first_id ) {
				$first_id = $square_discount_code_id;
			}
		}
		if ( empty( $square_discount_code_ids ) ) {
			return;
		}
		$order->update_meta_data( '_square_discount_code_ids', $square_discount_code_ids );
		$order->update_meta_data( '_square_discount_code_id', $first_id );
		$order->save_meta_data();
	}

	/**
	 * Get all Square discount code IDs stored on an order (for creating redemptions).
	 * Supports both legacy single meta and new array meta.
	 *
	 * @since x.x.x
	 *
	 * @param \WC_Order $order The order object.
	 * @return string[] Array of Square discount code IDs (empty if none).
	 */
	public static function get_order_square_discount_code_ids( $order ) {
		if ( ! $order instanceof \WC_Order ) {
			return array();
		}
		$ids = $order->get_meta( '_square_discount_code_ids' );
		if ( is_array( $ids ) && ! empty( $ids ) ) {
			return array_values( array_filter( $ids, 'is_string' ) );
		}
		$singular = $order->get_meta( '_square_discount_code_id' );
		if ( ! empty( $singular ) && is_string( $singular ) ) {
			return array( $singular );
		}
		return array();
	}

	/**
	 * Handle coupon removal - clear Square discount session data.
	 *
	 * @since x.x.x
	 *
	 * @param string $coupon_code The coupon code that was removed.
	 */
	public static function handle_coupon_removed( $coupon_code ) {
		// Clear all Square-related session data for this coupon.
		WC()->session->__unset( '_square_discount_code_id_' . $coupon_code );
		WC()->session->__unset( '_square_discount_amount_' . $coupon_code );
		WC()->session->__unset( '_square_discount_per_item_' . $coupon_code );
		WC()->session->__unset( '_square_discount_pending_recalc_' . $coupon_code );

		// Clear cached discount code data.
		self::clear_cache_discount_code( $coupon_code );
	}

	/**
	 * Get applied coupon codes that are Square discount codes.
	 *
	 * @since x.x.x
	 *
	 * @return string[] Array of applied Square coupon codes.
	 */
	private static function get_applied_square_coupon_codes() {
		$cart = WC()->cart;
		if ( ! $cart || $cart->is_empty() ) {
			return array();
		}

		$square_codes = array();
		foreach ( $cart->get_applied_coupons() as $applied_code ) {
			if ( ! empty( self::get_square_discount_code_id_by_code( $applied_code ) ) ) {
				$square_codes[] = $applied_code;
			}
		}
		return $square_codes;
	}

	/**
	 * Check if the cart has any Square discount code applied.
	 *
	 * Square discount codes can only be redeemed when paying with Square.
	 * Used to restrict available payment gateways to Square when a Square coupon is applied.
	 *
	 * @since x.x.x
	 *
	 * @return bool True if cart has an applied Square discount code.
	 */
	public static function cart_has_square_coupon() {
		if ( ! self::is_square_discount_codes_enabled() ) {
			return false;
		}

		return ! empty( self::get_applied_square_coupon_codes() );
	}

	/**
	 * Calculate Square discount from cart using CalculateOrder API.
	 *
	 * @since x.x.x
	 *
	 * @param string $coupon_code The coupon code to calculate discount for.
	 * @return float The calculated discount amount.
	 * @throws \Exception If calculation fails.
	 */
	private static function calculate_square_discount_from_cart( $coupon_code ) {
		// Only proceed if WooCommerce Square is active
		if ( ! function_exists( 'wc_square' ) ) {
			throw new \Exception( 'WooCommerce Square plugin is not available.' );
		}

		$cart = WC()->cart;

		if ( ! $cart || $cart->is_empty() ) {
			throw new \Exception( 'Cart is empty.' );
		}

		// Get Square discount code ID for the coupon we're calculating.
		$square_discount_code_id = self::get_square_discount_code_id_by_code( $coupon_code );
		if ( empty( $square_discount_code_id ) ) {
			throw new \Exception( 'Square discount code not found.' );
		}

		// Build array of ALL Square discount codes that should apply (existing + new).
		// When validating, the new coupon isn't in applied_coupons yet, so we include it explicitly.
		// This ensures we reject only the coupon that would make the combined total zero.
		$proposed_discount_codes = array();
		$applied_coupons         = $cart->get_applied_coupons();
		$current_is_applied      = false;

		foreach ( $applied_coupons as $applied_code ) {
			$id = self::get_square_discount_code_id_by_code( $applied_code );
			if ( ! empty( $id ) ) {
				$proposed_discount_codes[ $id ] = true;
				if ( wc_is_same_coupon( $applied_code, $coupon_code ) ) {
					$current_is_applied = true;
				}
			}
		}

		if ( ! $current_is_applied ) {
			$proposed_discount_codes[ $square_discount_code_id ] = true;
		}
		$proposed_discount_codes = array_keys( $proposed_discount_codes );

		// Get location ID.
		$settings_handler = wc_square()->get_settings_handler();
		if ( ! $settings_handler ) {
			throw new \Exception( 'Square settings handler is not available.' );
		}

		$location_id = $settings_handler->get_location_id();
		if ( empty( $location_id ) ) {
			throw new \Exception( 'Square location ID is not configured.' );
		}

		// Build Square Order from cart.
		$square_order = self::build_square_order_from_cart( $location_id );
		if ( ! $square_order ) {
			throw new \Exception( 'Failed to build Square order from cart.' );
		}

		// Get API instance.
		$api = wc_square()->get_gateway()->get_api();
		if ( ! $api ) {
			throw new \Exception( 'Square API is not available.' );
		}

		// Call CalculateOrder with ALL discount codes (existing + new) so we get the combined total.
		// Note: We pass null for WC_Order since we're calculating from cart, not an existing order.
		$calculate_result      = $api->calculate_order( null, $square_order, $proposed_discount_codes, true );
		$calculated_order      = $calculate_result['order'];
		$calculated_order_data = $calculate_result['raw_response'];

		// Extract per-line-item discounts from Square's response.
		$line_item_discounts   = array();
		$total_discount_amount = 0;

		if ( isset( $calculated_order_data['line_items'] ) && is_array( $calculated_order_data['line_items'] ) ) {
			$cart_items_by_key = self::get_cart_items_by_key( $cart );

			// Sum combined discount from each line and map to cart item keys (for fallback and totals).
			foreach ( $calculated_order_data['line_items'] as $square_line_item ) {
				$line_item_discount_cents = isset( $square_line_item['total_discount_money']['amount'] ) ? (int) $square_line_item['total_discount_money']['amount'] : 0;
				if ( $line_item_discount_cents <= 0 ) {
					continue;
				}

				$line_item_discount     = Money_Utility::cents_to_float( $line_item_discount_cents );
				$total_discount_amount += $line_item_discount;

				$matched_key = self::match_square_line_to_cart_key( $square_line_item, $cart_items_by_key );
				if ( $matched_key ) {
					$line_item_discounts[ $matched_key ] = ( $line_item_discounts[ $matched_key ] ?? 0 ) + $line_item_discount;
				}
			}
		}

		// If we couldn't extract per-item discounts, fall back to total discount calculation.
		if ( empty( $line_item_discounts ) ) {
			// Order sent to Square has no shipping (merchandise + fees only). Compare like-for-like.
			$cart_subtotal  = $cart->get_subtotal();
			$cart_fee_total = 0;
			foreach ( $cart->get_fees() as $fee ) {
				$cart_fee_total += (float) $fee->amount;
			}
			$cart_tax_total = $cart->get_total_tax();

			// Total before discount (exclude shipping so it matches the order we sent to Square).
			$total_before_discount = $cart_subtotal + $cart_fee_total + $cart_tax_total;

			// Get calculated total from Square (in cents).
			$total_after_discount_cents = $calculated_order->getTotalMoney() ? $calculated_order->getTotalMoney()->getAmount() : 0;
			$total_after_discount       = Money_Utility::cents_to_float( $total_after_discount_cents );

			// Discount amount is the difference.
			$total_discount_amount = $total_before_discount - $total_after_discount;

			// Ensure discount is not negative.
			if ( $total_discount_amount < 0 ) {
				$total_discount_amount = 0;
			}
		}

		// Reject coupon when it provides no discount (e.g. product-specific with no matching items, free delivery only).
		if ( $total_discount_amount <= 0 ) {
			/* translators: error message when discount code applies but gives no discount */
			throw new \Exception( esc_html__( 'This discount code does not apply to your current cart. It may be for specific products or free delivery only—add qualifying items or check the code requirements.', 'woocommerce-square' ) );
		}

		// Reject coupon if it would make the order total zero (WooCommerce-Square cannot process zero-amount transactions).
		$order_total_cents = $calculated_order->getTotalMoney() ? $calculated_order->getTotalMoney()->getAmount() : 0;
		if ( $order_total_cents <= 0 ) {
			/* translators: error message when discount would make order total zero */
			throw new \Exception( esc_html__( 'This discount code cannot be used because it would make your order total zero. Square cannot process zero-amount transactions.', 'woocommerce-square' ) );
		}

		// Build list of coupon codes in this calculation (to avoid double-counting when multiple coupons applied).
		$coupon_codes_in_calc = array();
		foreach ( $applied_coupons as $applied_code ) {
			$id = self::get_square_discount_code_id_by_code( $applied_code );
			if ( ! empty( $id ) && in_array( $id, $proposed_discount_codes, true ) ) {
				$coupon_codes_in_calc[] = $applied_code;
			}
		}
		if ( ! $current_is_applied ) {
			$coupon_codes_in_calc[] = $coupon_code;
		}

		self::store_square_discount_amounts_per_coupon( $calculated_order_data, $total_discount_amount, $line_item_discounts, $coupon_codes_in_calc, $cart );

		// Remove coupons that received no discount (e.g. product-specific with no qualifying item in cart).
		$applied_coupons = $cart->get_applied_coupons();
		foreach ( $coupon_codes_in_calc as $code ) {
			$stored_amount = WC()->session->get( '_square_discount_amount_' . $code );
			if ( null === $stored_amount || (float) $stored_amount <= 0 ) {
				if ( wc_is_same_coupon( $code, $coupon_code ) ) {
					self::handle_coupon_removed( $code );
					/* translators: error when discount code applies to nothing in cart */
					throw new \Exception( esc_html__( 'This discount code does not apply to your current cart. It may be for specific products or free delivery only—add qualifying items or check the code requirements.', 'woocommerce-square' ) );
				}
				if ( in_array( $code, $applied_coupons, true ) ) {
					$cart->remove_coupon( $code );
					/* translators: notice when a coupon was removed because it no longer applies */
					wc_add_notice( sprintf( esc_html__( 'The coupon "%s" was removed because it does not apply to your current cart.', 'woocommerce-square' ), $code ), 'notice' );
					self::handle_coupon_removed( $code );
				}
			}
		}

		return $total_discount_amount;
	}

	/**
	 * Stores each coupon's discount amount and per-item breakdown in session for cart/checkout display.
	 *
	 * Uses Square's order.discounts[] when available so each coupon shows its real amount (e.g. $5 off
	 * vs 10% off). Coupons with no matching order.discount (e.g. product-specific with no qualifying item) stay at 0.
	 * When Square returns no order.discounts and there is exactly one coupon, that coupon gets the full total.
	 *
	 * @param array    $calculated_order_data  Raw CalculateOrder response (order.discounts, line_items).
	 * @param float    $total_discount_amount  Combined discount total (from line items or order total).
	 * @param array    $line_item_discounts    Cart item key => combined discount amount (for fallback).
	 * @param string[] $coupon_codes_in_calc   WooCommerce coupon codes included in this calculation.
	 * @param \WC_Cart $cart                   WooCommerce cart (for mapping Square lines to cart keys).
	 */
	private static function store_square_discount_amounts_per_coupon( $calculated_order_data, $total_discount_amount, $line_item_discounts, $coupon_codes_in_calc, $cart ) {
		$coupon_count    = count( $coupon_codes_in_calc );
		$order_discounts = isset( $calculated_order_data['discounts'] ) && is_array( $calculated_order_data['discounts'] ) ? $calculated_order_data['discounts'] : array();

		// Map Square's catalog_object_id (from order.discounts) to our coupon code so we don't rely on array order.
		$catalog_id_to_coupon = array();
		foreach ( $coupon_codes_in_calc as $code ) {
			$code_data = self::get_discount_code( $code );
			if ( ! empty( $code_data['pricing_rule_id'] ) ) {
				$pricing_rule_version = isset( $code_data['pricing_rule_version'] ) ? (int) $code_data['pricing_rule_version'] : ( isset( $code_data['version'] ) ? (int) $code_data['version'] : 0 );
				$discount_catalog_id  = Coupon_Utility::get_discount_catalog_id_for_pricing_rule( $code_data['pricing_rule_id'], $pricing_rule_version );
				if ( ! empty( $discount_catalog_id ) ) {
					$catalog_id_to_coupon[ $discount_catalog_id ] = $code;
				}
			}
		}

		$cart_items_by_key   = self::get_cart_items_by_key( $cart );
		$per_coupon_amount   = array();
		$per_coupon_per_item = array();
		foreach ( $coupon_codes_in_calc as $code ) {
			$per_coupon_amount[ $code ]   = 0;
			$per_coupon_per_item[ $code ] = array();
		}

		if ( empty( $order_discounts ) && 1 === $coupon_count ) {
			$only_code                         = $coupon_codes_in_calc[0];
			$per_coupon_amount[ $only_code ]   = $total_discount_amount;
			$per_coupon_per_item[ $only_code ] = $line_item_discounts;
		}

		foreach ( $order_discounts as $idx => $order_discount ) {
			$discount_uid = isset( $order_discount['uid'] ) ? $order_discount['uid'] : '';
			$catalog_id   = isset( $order_discount['catalog_object_id'] ) ? $order_discount['catalog_object_id'] : '';
			$code         = null;
			if ( ! empty( $catalog_id ) && isset( $catalog_id_to_coupon[ $catalog_id ] ) ) {
				$code = $catalog_id_to_coupon[ $catalog_id ];
			}
			if ( ! $code && $idx < $coupon_count ) {
				$code = $coupon_codes_in_calc[ $idx ];
			}
			if ( ! $code ) {
				continue;
			}

			if ( isset( $order_discount['applied_money']['amount'] ) ) {
				$per_coupon_amount[ $code ] = Money_Utility::cents_to_float( (int) $order_discount['applied_money']['amount'] );
			}

			if ( $discount_uid && isset( $calculated_order_data['line_items'] ) && is_array( $calculated_order_data['line_items'] ) ) {
				foreach ( $calculated_order_data['line_items'] as $square_line_item ) {
					$applied_list = isset( $square_line_item['applied_discounts'] ) && is_array( $square_line_item['applied_discounts'] ) ? $square_line_item['applied_discounts'] : array();
					foreach ( $applied_list as $applied_discount ) {
						if ( ( $applied_discount['discount_uid'] ?? '' ) !== $discount_uid || ! isset( $applied_discount['applied_money']['amount'] ) ) {
							continue;
						}
						$line_amount = Money_Utility::cents_to_float( (int) $applied_discount['applied_money']['amount'] );
						$cart_key    = self::match_square_line_to_cart_key( $square_line_item, $cart_items_by_key );
						if ( $cart_key ) {
							$per_coupon_per_item[ $code ][ $cart_key ] = ( $per_coupon_per_item[ $code ][ $cart_key ] ?? 0 ) + $line_amount;
						}
						break;
					}
				}
			}
		}

		foreach ( $coupon_codes_in_calc as $code ) {
			WC()->session->set( '_square_discount_code_id_' . $code, self::get_square_discount_code_id_by_code( $code ) );
			WC()->session->set( '_square_discount_amount_' . $code, $per_coupon_amount[ $code ] ?? 0 );
			WC()->session->set( '_square_discount_per_item_' . $code, $per_coupon_per_item[ $code ] ?? array() );
		}
	}

	/**
	 * Build a map of cart item key => catalog_object_id and name for matching Square line items to cart.
	 *
	 * @param \WC_Cart $cart WooCommerce cart.
	 * @return array<string, array{catalog_object_id: string, name: string}> Cart key => lookup data.
	 */
	private static function get_cart_items_by_key( $cart ) {
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
	 * @param array    $square_line_item   Line item from Square API response.
	 * @param array    $cart_items_by_key  Map from get_cart_items_by_key().
	 * @return string|null Cart item key or null if no match.
	 */
	private static function match_square_line_to_cart_key( $square_line_item, $cart_items_by_key ) {
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
	 * Build Square Order object from cart data (for calculation only, no order creation).
	 *
	 * @since x.x.x
	 *
	 * @param string $location_id Square location ID.
	 * @return \Square\Models\Order Square Order object ready for CalculateOrder.
	 */
	private static function build_square_order_from_cart( $location_id ) {
		$cart = WC()->cart;
		if ( ! $cart || $cart->is_empty() ) {
			return null;
		}

		$currency    = get_woocommerce_currency();
		$order_model = new \Square\Models\Order( $location_id );

		// Build line items from cart.
		$line_items = array();
		$tax_type   = wc_prices_include_tax() ? API::TAX_TYPE_INCLUSIVE : API::TAX_TYPE_ADDITIVE;

		// Get tax rates for cart. Use get_tax_totals() when available; otherwise build from
		// WC_Tax::get_rates() so Square always receives consistent tax definitions with
		// inclusive line amounts (avoids discount applied to wrong base when totals not yet calculated).
		$tax_rates = array();
		if ( wc_tax_enabled() ) {
			$tax_totals = $cart->get_tax_totals();
			if ( ! empty( $tax_totals ) ) {
				foreach ( $tax_totals as $rate_id => $tax ) {
					$tax_item = new \Square\Models\OrderLineItemTax();
					$tax_item->setUid( uniqid() );
					$tax_item->setName( $tax->label );
					$tax_item->setType( $tax_type );
					$tax_item->setScope( 'LINE_ITEM' );

					$rate_percentage = 0;
					$tax_rate        = \WC_Tax::_get_tax_rate( $rate_id );
					if ( $tax_rate && isset( $tax_rate['tax_rate'] ) ) {
						$rate_percentage = (float) $tax_rate['tax_rate'];
					}
					$tax_item->setPercentage( Square_Helper::number_format( $rate_percentage ) );
					$tax_rates[ $rate_id ] = $tax_item;
				}
			} else {
				// Fallback: build tax rates from WC_Tax so order has tax definitions when cart totals aren't ready.
				$customer      = $cart->get_customer();
				$seen_rate_ids = array();
				foreach ( $cart->get_cart() as $cart_item ) {
					$product = $cart_item['data'];
					if ( ! $product || ! $product->is_taxable() ) {
						continue;
					}
					$item_rates = \WC_Tax::get_rates( $product->get_tax_class(), $customer );
					foreach ( $item_rates as $rate_id => $rate_data ) {
						if ( isset( $seen_rate_ids[ $rate_id ] ) ) {
							continue;
						}
						$seen_rate_ids[ $rate_id ] = true;
						$tax_rate_row              = \WC_Tax::_get_tax_rate( $rate_id );
						$rate_percentage           = ( $tax_rate_row && isset( $tax_rate_row['tax_rate'] ) ) ? (float) $tax_rate_row['tax_rate'] : 0;
						$label                     = ( $tax_rate_row && isset( $tax_rate_row['tax_rate_name'] ) ) ? $tax_rate_row['tax_rate_name'] : __( 'Tax', 'woocommerce-square' );
						$tax_item                  = new \Square\Models\OrderLineItemTax();
						$tax_item->setUid( uniqid() );
						$tax_item->setName( $label );
						$tax_item->setType( $tax_type );
						$tax_item->setScope( 'LINE_ITEM' );
						$tax_item->setPercentage( Square_Helper::number_format( $rate_percentage ) );
						$tax_rates[ $rate_id ] = $tax_item;
					}
				}
			}
		}

		// Convert cart items to Square line items.
		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
			$product  = $cart_item['data'];
			$quantity = (float) $cart_item['quantity'];

			$line_item = new \Square\Models\OrderLineItem( (string) $quantity );

			// Check if gift card.
			if ( Product::is_gift_card( $product ) ) {
				$line_item->setItemType( 'GIFT_CARD' );
			}

			// Calculate amounts.
			$price_total         = (float) $product->get_price() * $quantity;
			$line_subtotal       = $price_total;
			$line_subtotal_tax   = 0.0;
			$line_tax_data_total = array();

			if ( wc_tax_enabled() && $product->is_taxable() ) {
				$customer           = $cart->get_customer();
				$is_vat_exempt      = $customer && $customer->get_is_vat_exempt();
				$item_tax_rates     = \WC_Tax::get_rates( $product->get_tax_class(), $customer );
				$price_includes_tax = wc_prices_include_tax();

				if ( ! $is_vat_exempt && ! empty( $item_tax_rates ) ) {
					$subtotal_taxes = \WC_Tax::calc_tax( $price_total, $item_tax_rates, $price_includes_tax );
					foreach ( $subtotal_taxes as $rate_id => $tax_amount ) {
						$rounded_tax                     = wc_round_tax_total( $tax_amount );
						$line_tax_data_total[ $rate_id ] = $rounded_tax;
					}
					$line_subtotal_tax = array_sum( $line_tax_data_total );

					if ( $price_includes_tax ) {
						$line_subtotal = $price_total - $line_subtotal_tax;
					}
				}
			}

			// Base price per unit (before any discounts).
			$subtotal_per_unit = $line_subtotal;
			if ( API::TAX_TYPE_INCLUSIVE === $tax_type ) {
				$subtotal_per_unit += $line_subtotal_tax;
			}

			if ( $quantity > 0 ) {
				$subtotal_per_unit = $subtotal_per_unit / $quantity;
			} else {
				$subtotal_per_unit = 0;
			}

			$line_item->setQuantity( (string) $quantity );
			$line_item->setBasePriceMoney( Money_Utility::amount_to_money( $subtotal_per_unit, $currency ) );

			// Set catalog object ID if available.
			$square_variation_id = $product->get_meta( Product::SQUARE_VARIATION_ID_META_KEY );
			if ( ! empty( $square_variation_id ) ) {
				$line_item->setCatalogObjectId( $square_variation_id );
			} else {
				$line_item->setName( $product->get_name() );
			}

			// Apply taxes (using computed rates for this product).
			$applied_taxes = array();
			if ( ! empty( $line_tax_data_total ) ) {
				foreach ( $line_tax_data_total as $rate_id => $tax_amount ) {
					if ( (float) $tax_amount > 0 && isset( $tax_rates[ $rate_id ] ) ) {
						$applied_taxes[] = new \Square\Models\OrderLineItemAppliedTax( $tax_rates[ $rate_id ]->getUid() );
					}
				}
			}

			if ( ! empty( $applied_taxes ) ) {
				$line_item->setAppliedTaxes( $applied_taxes );
			}

			// Note: We do NOT add WooCommerce discounts here - Square will calculate via discount codes.
			$line_items[] = $line_item;
		}

		// Intentionally do NOT add shipping to the order sent to CalculateOrder.
		// When shipping is included, Square prorates/allocates discounts across product + shipping,
		// so fixed/capped coupons show less on product (e.g. $5 fixed becomes $4.82). Excluding
		// shipping keeps discount amounts consistent regardless of shipping method. The cart/checkout
		// total still adds shipping via WooCommerce; at payment we send the full order (with shipping)
		// and use adjust_order so the charge matches the displayed total.

		// Add fees as line items.
		foreach ( $cart->get_fees() as $fee_key => $fee ) {
			$fee_amount = (float) $fee->amount;
			if ( 0 !== $fee_amount ) {
				$fee_line_item = new \Square\Models\OrderLineItem( '1' );
				$fee_line_item->setName( $fee->name );
				$fee_line_item->setBasePriceMoney( Money_Utility::amount_to_money( $fee_amount, $currency ) );
				$line_items[] = $fee_line_item;
			}
		}

		$order_model->setLineItems( $line_items );

		// Set taxes.
		if ( ! empty( $tax_rates ) ) {
			$order_model->setTaxes( array_values( $tax_rates ) );
		}

		return $order_model;
	}

	/**
	 * Override WooCommerce discount calculation with Square's calculated amount.
	 *
	 * @since x.x.x
	 *
	 * @param float      $discount      Discount amount.
	 * @param float      $discounting_amount Amount the coupon is being applied to.
	 * @param array|null $cart_item     Cart item being discounted.
	 * @param bool       $single        True if discounting a single qty item.
	 * @param \WC_Coupon $coupon        Coupon object.
	 * @return float Discount amount.
	 */
	public static function override_discount_amount_with_square( $discount, $discounting_amount, $cart_item, $single, $coupon ) {
		$coupon_code = $coupon->get_code();

		// Verify the coupon is actually applied to the cart before using session data.
		if ( ! Coupon_Utility::is_coupon_in_applied_cart( $coupon_code ) ) {
			return $discount;
		}

		// Check if we have Square discount data stored for this coupon.
		$square_discount_per_item = WC()->session->get( '_square_discount_per_item_' . $coupon_code );

		if ( null !== $square_discount_per_item && is_array( $square_discount_per_item ) ) {
			// This is a Square discount code - use Square's calculated per-item amounts.
			if ( $cart_item && is_array( $cart_item ) && isset( $cart_item['key'] ) ) {
				$cart_item_key = $cart_item['key'];

				if ( isset( $square_discount_per_item[ $cart_item_key ] ) && $square_discount_per_item[ $cart_item_key ] > 0 ) {
					$item_line_discount = (float) $square_discount_per_item[ $cart_item_key ];
					$quantity           = isset( $cart_item['quantity'] ) ? (float) $cart_item['quantity'] : 1;
					return $single ? ( $quantity > 0 ? $item_line_discount / $quantity : 0 ) : $item_line_discount;
				}
			}

			return 0;
		}

		// Fallback: if per-item discounts not available, check for total discount and distribute proportionally.
		$square_discount_amount = WC()->session->get( '_square_discount_amount_' . $coupon_code );
		if ( null !== $square_discount_amount && $square_discount_amount > 0 ) {
			$cart = WC()->cart;
			if ( $cart && $cart_item && is_array( $cart_item ) ) {
				$cart_subtotal = $cart->get_subtotal();
				if ( $cart_subtotal > 0 ) {
					$item_subtotal      = isset( $cart_item['line_subtotal'] ) ? (float) $cart_item['line_subtotal'] : 0;
					$proportion         = $item_subtotal / $cart_subtotal;
					$item_line_discount = $square_discount_amount * $proportion;
					$quantity           = isset( $cart_item['quantity'] ) ? (float) $cart_item['quantity'] : 1;
					return $single ? ( $quantity > 0 ? $item_line_discount / $quantity : 0 ) : $item_line_discount;
				}
			}
		}

		return $discount;
	}

	/**
	 * Handle cart contents changed - recalculate Square discounts if applied.
	 *
	 * @since x.x.x
	 */
	public static function handle_cart_contents_changed() {
		// Prevent infinite loops - check if we're already recalculating.
		static $recalculating = false;
		if ( $recalculating ) {
			return;
		}

		$cart = WC()->cart;
		if ( ! $cart || $cart->is_empty() ) {
			return;
		}

		$applied_coupons = $cart->get_applied_coupons();
		if ( empty( $applied_coupons ) ) {
			return;
		}

		// Check if any Square coupons are still applied and recalculate.
		$square_coupons_to_remove = array();
		foreach ( $applied_coupons as $coupon_code ) {
			$square_discount_code_id = self::get_square_discount_code_id_by_code( $coupon_code );

			if ( ! empty( $square_discount_code_id ) ) {
				// This is a Square coupon - recalculate the discount for the new cart contents.
				$recalculating = true;

				try {
					self::calculate_square_discount_from_cart( $coupon_code );
				} catch ( \Exception $e ) {
					// Recalculation failed (e.g. combined total would be zero). Remove ALL Square coupons
					// to clear the invalid state - we cannot determine which single coupon to remove.
					$square_coupons_to_remove = self::get_applied_square_coupon_codes();
					if ( function_exists( 'wc_square' ) ) {
						wc_square()->log( sprintf( 'Error recalculating discount after cart change for coupon %s: %s', $coupon_code, $e->getMessage() ), 'square-coupons' );
					}
					break;
				} finally {
					$recalculating = false;
				}
			}
		}

		// Remove invalid Square coupons and show error.
		if ( ! empty( $square_coupons_to_remove ) ) {
			foreach ( $square_coupons_to_remove as $code ) {
				$cart->remove_coupon( $code );
				self::handle_coupon_removed( $code );
			}
			wc_add_notice( __( 'One or more discount codes could not be applied because they would make your order total zero. Square cannot process zero-amount transactions.', 'woocommerce-square' ), 'error' );
		}
	}
}
