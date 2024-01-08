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

/**
 * Helper for dealing with String values.
 *
 * @since 2.2.0
 */
class String_Utility {

	/**
	 * encoding used for mb_*() string functions
	 **/
	const MB_ENCODING = 'UTF-8';

	/**
	 * Truncates $string after a given $length if string is longer than
	 * $length.
	 *
	 * The last characters will be replaced with the $omission string
	 * for a total length not exceeding $length
	 *
	 * See Square_Helper::str_truncate()
	 *
	 * @since 2.2.0
	 * @param string $string text to truncate
	 * @param int $length total desired length of string, including omission
	 * @param string $omission omission text, defaults to '...'
	 * @return string
	 */
	public static function truncate( $string, $length, $omission = '...' ) {
		$string = self::to_ascii( $string );

		// bail if string doesn't need to be truncated
		if ( strlen( $string ) <= $length ) {
			return $string;
		}

		$length -= strlen( $omission );

		return substr( $string, 0, $length ) . $omission;
	}

	/**
	 * Returns a string with all non-ASCII characters removed. This is useful
	 * for any string functions that expect only ASCII chars and can't
	 * safely handle UTF-8. Note this only allows ASCII chars in the range
	 * 33-126 (newlines/carriage returns are stripped)
	 *
	 * See Square_Helper::to_ascii()
	 *
	 * @since 2.2.0
	 * @param string $string string to make ASCII
	 * @return string
	 */
	public static function to_ascii( $string ) {
		// strip ASCII chars 32 and under
		$string = filter_var( $string, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW );

		// strip ASCII chars 127 and higher
		return filter_var( $string, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH );
	}

	/**
	 * Returns true if the haystack string starts with needle
	 *
	 * Note: case-sensitive
	 * 
	 * See Square_Helper::str_starts_with()
	 *
	 * @since 2.2.0
	 * @param string $haystack
	 * @param string $needle
	 * @return bool
	 */
	public static function str_starts_with( $haystack, $needle ) {

		if ( self::multibyte_loaded() ) {

			if ( '' === $needle ) {
				return true;
			}

			return 0 === mb_strpos( $haystack, $needle, 0, self::MB_ENCODING );

		} else {

			$needle = self::to_ascii( $needle );

			if ( '' === $needle ) {
				return true;
			}

			return 0 === strpos( self::to_ascii( $haystack ), self::to_ascii( $needle ) );
		}
	}

	/**
	 * Returns true if the needle exists in haystack
	 *
	 * Note: case-sensitive
	 *
	 * See Square_Helper::str_exists()
	 *
	 * @since 2.2.0
	 * @param string $haystack
	 * @param string $needle
	 * @return bool
	 */
	public static function str_exists( $haystack, $needle ) {

		if ( self::multibyte_loaded() ) {

			if ( '' === $needle ) {
				return false;
			}

			return false !== mb_strpos( $haystack, $needle, 0, self::MB_ENCODING );

		} else {

			$needle = self::to_ascii( $needle );

			if ( '' === $needle ) {
				return false;
			}

			return false !== strpos( self::to_ascii( $haystack ), self::to_ascii( $needle ) );
		}
	}

	/**
	 * Helper method to check if the multibyte extension is loaded, which
	 * indicates it's safe to use the mb_*() string methods
	 *
	 * @since 2.2.0
	 * @return bool
	 */
	protected static function multibyte_loaded() {

		return extension_loaded( 'mbstring' );
	}
}
