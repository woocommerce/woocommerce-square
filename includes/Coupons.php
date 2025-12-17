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
}
