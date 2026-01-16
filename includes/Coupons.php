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
	 * Initialize Coupons class.
	 */
	public static function init() {
		// Hooks for handling coupon application and removal.
		add_action( 'woocommerce_applied_coupon', array( self::$instance, 'handle_coupon_applied' ), 10, 1 );
		add_action( 'woocommerce_removed_coupon', array( self::$instance, 'handle_coupon_removed' ), 10, 1 );

		// Hooks for handling coupon data and discount amount.
		add_filter( 'woocommerce_get_shop_coupon_data', array( self::$instance, 'filter_woocommerce_get_shop_coupon_data' ), 10, 3 );
	}

	/**
	 * Preflight the construction of a WC_Coupon object.
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

		$bearer_token = wc_square()->get_settings_handler()->get_access_token();
		$is_sandbox   = wc_square()->get_settings_handler()->is_sandbox();
		$api_url      = 'https://connect.squareup' . ( $is_sandbox ? 'sandbox' : '' ) . '.com/v2/discount-codes/search';

		$query = array(
			'query' => array(
				'filter' => array(
					'code' => $coupon_data,
				),
			),
		);

		$response = wp_remote_post(
			$api_url,
			array(
				'headers' => array(
					'Authorization'  => 'Bearer ' . $bearer_token,
					'Content-Type'   => 'application/json',
					'Square-Version' => '2025-01-23',
				),
				'body'    => wp_json_encode( $query ),
			)
		);

		// Do not pre-flight the coupon if there was an error or non-200 response.
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return $coupon;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( empty( $data['discount_codes'] ) ) {
			// No codes found.
			return $coupon;
		}

		foreach ( $data['discount_codes'] as $code ) {
			if ( $code['code'] === $coupon_data ) {
				$code_data = $code;
				break;
			}
		}

		$wc_coupon = Coupon_Utility::map_square_discount_code_to_woocommerce_coupon( $code_data );

		return $wc_coupon;
	}

	/**
	 * Retrieve discount code from the Square API.
	 *
	 * @param string $discount_code The discount code to retrieve.
	 * @return array|null Discount code details, or null if not found.
	 */
	public static function get_discount_code( $discount_code ) {}

	/**
	 * Cache discount code details.
	 *
	 * @param string     $discount_code The discount code.
	 * @param array|null $code_details  The discount code details to cache. Null if not found.
	 */
	public static function set_cache_discount_code( $discount_code, $code_details ) {}

	/**
	 * Retrieve cached discount code details.
	 *
	 * @param string $discount_code The discount code.
	 * @return array|null Cached discount code details, or null if not found (known unknown).
	 */
	public static function get_cache_discount_code( $discount_code ) {}

	/**
	 * Clear cached discount code details.
	 *
	 * @param string $discount_code The discount code.
	 */
	public static function clear_cache_discount_code( $discount_code ) {}

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

		// Shipping is selected (or not required), calculate now.
		try {
			self::calculate_square_discount_from_cart( $coupon_code );
		} catch ( \Exception $e ) {
			// Remove coupon and show error.
			$cart->remove_coupon( $coupon_code );
			wc_add_notice( sprintf( __( 'Unable to apply coupon "%s": %s', 'woocommerce-square' ), $coupon_code, $e->getMessage() ), 'error' );
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

		$bearer_token = $settings_handler->get_access_token();
		$is_sandbox   = $settings_handler->is_sandbox();

		if ( empty( $bearer_token ) ) {
			return null;
		}

		$api_url = 'https://connect.squareup' . ( $is_sandbox ? 'sandbox' : '' ) . '.com/v2/discount-codes/search';

		$query = array(
			'query' => array(
				'filter' => array(
					'code' => $coupon_code,
				),
			),
		);

		$response = wp_remote_post(
			$api_url,
			array(
				'headers' => array(
					'Authorization'  => 'Bearer ' . $bearer_token,
					'Content-Type'   => 'application/json',
					'Square-Version' => '2025-01-23',
				),
				'body'    => wp_json_encode( $query ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				$error_message = is_wp_error( $response ) ? $response->get_error_message() : wp_remote_retrieve_response_message( $response );
				error_log( sprintf( 'Square: Error searching for discount code %s: %s', $coupon_code, $error_message ) );
			}
			return null;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( empty( $data['discount_codes'] ) ) {
			return null;
		}

		// Find matching code.
		foreach ( $data['discount_codes'] as $code ) {
			if ( isset( $code['code'] ) && strtoupper( $code['code'] ) === strtoupper( $coupon_code ) ) {
				return isset( $code['id'] ) ? $code['id'] : null;
			}
		}

		return null;
	}
}
