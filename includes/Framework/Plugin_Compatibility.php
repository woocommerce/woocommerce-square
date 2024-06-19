<?php
/**
 * WooCommerce Plugin Framework
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is bundled with this package in the file license.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0 or later
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@skyverge.com so we can send you a copy immediately.
 *
 * @since     3.0.0
 * @author    WooCommerce / SkyVerge
 * @copyright Copyright (c) 2013-2019, SkyVerge, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0 or later
 *
 * Modified by WooCommerce on 01 December 2021.
 */

namespace WooCommerce\Square\Framework;

defined( 'ABSPATH' ) || exit;

class Plugin_Compatibility {

	/**
	 * Logs a doing_it_wrong message.
	 *
	 * Backports wc_doing_it_wrong() to WC 2.6.
	 *
	 * @since 3.0.0
	 *
	 * @param string $func    function used
	 * @param string $message message to log
	 * @param string $version version the message was added in
	 */
	public static function wc_doing_it_wrong( $func, $message, $version ) {
		wc_doing_it_wrong( $func, $message, $version );
	}

	/**
	 * Helper method to get the version of the currently installed WooCommerce
	 *
	 * @since 3.0.0
	 * @return string woocommerce version number or null
	 */
	public static function get_wc_version() {

		return defined( 'WC_VERSION' ) && WC_VERSION ? WC_VERSION : null;
	}

	/**
	 * Determines if the installed version of WooCommerce is lower than the
	 * passed version.
	 *
	 * @since 3.0.0
	 *
	 * @param string $version version number to compare
	 * @return bool
	 */
	public static function is_wc_version_lt( $version ) {
		return self::get_wc_version() && version_compare( self::get_wc_version(), $version, '<' );
	}

	/**
	 * Normalizes a WooCommerce page screen ID.
	 *
	 * Needed because WordPress uses a menu title (which is translatable), not slug, to generate screen ID.
	 * See details in: https://core.trac.wordpress.org/ticket/21454
	 *
	 * @since 3.0.0
	 * @param string $slug slug for the screen ID to normalize (minus `woocommerce_page_`)
	 * @return string normalized screen ID
	 */
	public static function normalize_wc_screen_id( $slug = 'wc-settings' ) {

		// The textdomain usage is intentional here, we need to match the menu title.
		$prefix = sanitize_title( __( 'WooCommerce', 'woocommerce-square' ) );

		return $prefix . '_page_' . $slug;
	}


	/**
	 * Converts a shorthand byte value to an integer byte value.
	 *
	 * Wrapper for wp_convert_hr_to_bytes(), moved to load.php in WordPress 4.6 from media.php
	 *
	 * Based on ActionScheduler's compat wrapper for the same function:
	 * ActionScheduler_Compatibility::convert_hr_to_bytes()
	 *
	 * @link https://secure.php.net/manual/en/function.ini-get.php
	 * @link https://secure.php.net/manual/en/faq.using.php#faq.using.shorthandbytes
	 *
	 * @since 3.0.0
	 *
	 * @param string $value A (PHP ini) byte value, either shorthand or ordinary.
	 * @return int An integer byte value.
	 */
	public static function convert_hr_to_bytes( $value ) {

		if ( function_exists( 'wp_convert_hr_to_bytes' ) ) {

			return wp_convert_hr_to_bytes( $value );
		}

		$value = strtolower( trim( $value ) );
		$bytes = (int) $value;

		if ( false !== strpos( $value, 'g' ) ) {

			$bytes *= GB_IN_BYTES;

		} elseif ( false !== strpos( $value, 'm' ) ) {

			$bytes *= MB_IN_BYTES;

		} elseif ( false !== strpos( $value, 'k' ) ) {

			$bytes *= KB_IN_BYTES;
		}

		// deal with large (float) values which run into the maximum integer size
		return min( $bytes, PHP_INT_MAX );
	}
}

