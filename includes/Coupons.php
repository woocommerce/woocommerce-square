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
		return $coupon;
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
