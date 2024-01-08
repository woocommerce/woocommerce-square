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

use WooCommerce\Square\Framework\Plugin_Compatibility;
use WooCommerce\Square\Framework\Square_Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Helper for converting money values.
 *
 * Square deals in cents, and Woo deals in floats so this methods help conversion between the two.
 *
 * @since 2.0.0
 */
class Money_Utility {


	/**
	 * Converts a WooCommerce amount to a Square money object.
	 *
	 * @since 2.0.0
	 *
	 * @param float $amount amount to convert
	 * @param string $currency currency code
	 *
	 * @return \Square\Models\Money
	 */
	public static function amount_to_money( $amount, $currency ) {
		$amount_money = new \Square\Models\Money();
		$amount_money->setAmount( self::amount_to_cents( $amount, $currency ) );
		$amount_money->setCurrency( $currency );

		return $amount_money;
	}


	/**
	 * Converts a float amount to cents.
	 *
	 * @since 2.0.0
	 *
	 * @param float $amount float amount to convert
	 * @param string $currency currency code for the amount
	 * @return int
	 */
	public static function amount_to_cents( $amount, $currency = '' ) {

		if ( ! $currency ) {
			$currency = get_woocommerce_currency();
		}

		$cents_factor = 10 ** self::get_currency_decimals( $currency );
		return (int) ( round( $cents_factor * $amount ) );
	}


	/**
	 * Converts an amount in cents to a float.
	 *
	 * @since 2.0.0
	 *
	 * @param int $cents amount in cents
	 * @param string $currency currency code for the amount
	 * @return float
	 */
	public static function cents_to_float( $cents, $currency = '' ) {

		if ( ! $currency ) {
			$currency = get_woocommerce_currency();
		}

		$cents_factor = 10 ** self::get_currency_decimals( $currency );
		return (float) ( $cents / $cents_factor );
	}


	/**
	 * Gets the standard number of decimals for the given currency.
	 *
	 * @since 2.0.2
	 *
	 * @param string $currency currency code
	 * @return int
	 */
	public static function get_currency_decimals( $currency ) {

		$other_currencies = array(
			'BIF' => 0,
			'CLP' => 0,
			'DJF' => 0,
			'GNF' => 0,
			'HUF' => 0,
			'JPY' => 0,
			'KMF' => 0,
			'KRW' => 0,
			'MGA' => 0,
			'PYG' => 0,
			'RWF' => 0,
			'VND' => 0,
			'VUV' => 0,
			'XAF' => 0,
			'XOF' => 0,
			'XPF' => 0,
		);

		$locale_info = include WC()->plugin_path() . '/i18n/locale-info.php';

		$currencies = wp_list_pluck( $locale_info, 'num_decimals', 'currency_code' );

		// ensure the values set in local-info.php always override the above
		$currencies = array_merge( $other_currencies, $currencies );

		/**
		 * Filters the number of decimals to use for a given currency when converting to or from its smallest denomination.
		 *
		 * @since 2.0.2
		 *
		 * @param int $decimals number of decimals
		 * @param string $currency currency code
		 */
		return apply_filters( 'wc_square_currency_decimals', isset( $currencies[ $currency ] ) ? $currencies[ $currency ] : 2, $currency );
	}


}
