<?php

namespace WooCommerce\Square\Framework;

use WooCommerce\Square\Framework\Plugin_Compatibility;

defined( 'ABSPATH' ) || exit;

/**
 * Square Helper Class
 *
 * The purpose of this class is to centralize common utility functions.
 *
 * @since 3.0.0
 */
class Square_Helper {


	/** encoding used for mb_*() string functions */
	const MB_ENCODING = 'UTF-8';


	/** String manipulation functions (all multi-byte safe) ***************/

	/**
	 * Returns true if the haystack string starts with needle
	 *
	 * Note: case-sensitive
	 *
	 * @since 3.0.0
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

			$needle = self::str_to_ascii( $needle );

			if ( '' === $needle ) {
				return true;
			}

			return 0 === strpos( self::str_to_ascii( $haystack ), self::str_to_ascii( $needle ) );
		}
	}

	/**
	 * Returns true if the needle exists in haystack
	 *
	 * Note: case-sensitive
	 *
	 * @since 3.0.0
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

			$needle = self::str_to_ascii( $needle );

			if ( '' === $needle ) {
				return false;
			}

			return false !== strpos( self::str_to_ascii( $haystack ), self::str_to_ascii( $needle ) );
		}
	}

	/**
	 * Returns a string with all non-ASCII characters removed. This is useful
	 * for any string functions that expect only ASCII chars and can't
	 * safely handle UTF-8. Note this only allows ASCII chars in the range
	 * 33-126 (newlines/carriage returns are stripped)
	 *
	 * @since 3.0.0
	 * @param string $string string to make ASCII
	 * @return string
	 */
	public static function str_to_ascii( $string ) {

		// strip ASCII chars 32 and under
		$string = filter_var( $string, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW );

		// strip ASCII chars 127 and higher
		return filter_var( $string, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH );
	}

	/**
	 * Truncates a given $string after a given $length if string is longer than
	 * $length. The last characters will be replaced with the $omission string
	 * for a total length not exceeding $length
	 *
	 * @since 3.0.0
	 * @param string $string text to truncate
	 * @param int $length total desired length of string, including omission
	 * @param string $omission omission text, defaults to '...'
	 * @return string
	 */
	public static function str_truncate( $string, $length, $omission = '...' ) {

		if ( self::multibyte_loaded() ) {

			// bail if string doesn't need to be truncated
			if ( mb_strlen( $string, self::MB_ENCODING ) <= $length ) {
				return $string;
			}

			$length -= mb_strlen( $omission, self::MB_ENCODING );

			return mb_substr( $string, 0, $length, self::MB_ENCODING ) . $omission;

		} else {

			$string = self::str_to_ascii( $string );

			// bail if string doesn't need to be truncated
			if ( strlen( $string ) <= $length ) {
				return $string;
			}

			$length -= strlen( $omission );

			return substr( $string, 0, $length ) . $omission;
		}
	}

	/**
	 * Helper method to check if the multibyte extension is loaded, which
	 * indicates it's safe to use the mb_*() string methods
	 *
	 * @since 3.0.0
	 * @return bool
	 */
	protected static function multibyte_loaded() {

		return extension_loaded( 'mbstring' );
	}


	/** Array functions ***************************************************/


	/**
	 * Insert the given element after the given key in the array
	 *
	 * Sample usage:
	 *
	 * given
	 *
	 * array( 'item_1' => 'foo', 'item_2' => 'bar' )
	 *
	 * array_insert_after( $array, 'item_1', array( 'item_1.5' => 'w00t' ) )
	 *
	 * becomes
	 *
	 * array( 'item_1' => 'foo', 'item_1.5' => 'w00t', 'item_2' => 'bar' )
	 *
	 * @since 3.0.0
	 * @param array $array array to insert the given element into
	 * @param string $insert_key key to insert given element after
	 * @param array $element element to insert into array
	 * @return array
	 */
	public static function array_insert_after( array $array, $insert_key, array $element ) {

		$new_array = array();

		foreach ( $array as $key => $value ) {

			$new_array[ $key ] = $value;

			if ( $insert_key === $key ) {

				foreach ( $element as $k => $v ) {
					$new_array[ $k ] = $v;
				}
			}
		}

		return $new_array;
	}

	/**
	 * Lists an array as text.
	 *
	 * Takes an array and returns a list like "one, two, three, and four"
	 * with a (mandatory) oxford comma.
	 *
	 * @since 3.0.0
	 *
	 * @param array $items items to list
	 * @param string|null $conjunction coordinating conjunction, like "or" or "and"
	 * @param string $separator list separator, like a comma
	 * @return string
	 */
	public static function list_array_items( array $items, $conjunction = null, $separator = '' ) {

		if ( ! is_string( $conjunction ) ) {
			$conjunction = _x( 'and', 'coordinating conjunction for a list of items: a, b, and c', 'woocommerce-square' );
		}

		// append the conjunction to the last item
		if ( count( $items ) > 1 ) {

			$last_item = array_pop( $items );

			array_push( $items, trim( "{$conjunction} {$last_item}" ) );

			// only use a comma if needed and no separator was passed
			if ( count( $items ) < 3 ) {
				$separator = ' ';
			} elseif ( ! is_string( $separator ) || '' === $separator ) {
				$separator = ', ';
			}
		}

		return implode( $separator, $items );
	}


	/** Number helper functions *******************************************/


	/**
	 * Format a number with 2 decimal points, using a period for the decimal
	 * separator and no thousands separator.
	 *
	 * Commonly used for payment gateways which require amounts in this format.
	 *
	 * @since 3.0.0
	 * @param float $number
	 * @return string
	 */
	public static function number_format( $number ) {

		return number_format( (float) $number, 2, '.', '' );
	}

	/**
	 * Determines if an order contains only virtual products.
	 *
	 * @since 3.0.0
	 * @param \WC_Order $order the order object
	 * @return bool
	 */
	public static function is_order_virtual( \WC_Order $order ) {

		$is_virtual = true;

		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();

			// once we've found one non-virtual product we know we're done, break out of the loop
			if ( $product && ! $product->is_virtual() ) {
				$is_virtual = false;
				break;
			}
		}

		return $is_virtual;
	}


	/**
	 * Safely get sanitized data from $_POST
	 *
	 * @since 3.0.0
	 * @param string $key               Array key to get from $_POST array.
	 * @param string $sanitize_callback Name of the sanitization callback function.
	 *
	 * @return string value from $_POST or blank string if $_POST[ $key ] is not set
	 */
	public static function get_post( $key = '', $sanitize_callback = 'sanitize_text_field' ) {
		if ( ! is_callable( $sanitize_callback ) ) {
			$sanitize_callback = 'sanitize_text_field';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		return isset( $_POST[ $key ] ) ? call_user_func( $sanitize_callback, wp_unslash( $_POST[ $key ] ) ) : '';
	}


	/**
	 * Safely get and trim data from $_REQUEST
	 *
	 * @since 3.0.0
	 * @param string $key               Array key to get from $_REQUEST array.
	 * @param string $sanitize_callback Name of the sanitization callback function.
	 *
	 * @return string value from $_REQUEST or blank string if $_REQUEST[ $key ] is not set
	 */
	public static function get_request( $key, $sanitize_callback = 'sanitize_text_field' ) {

		if ( ! is_callable( $sanitize_callback ) ) {
			$sanitize_callback = 'sanitize_text_field';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		return isset( $_REQUEST[ $key ] ) ? call_user_func( $sanitize_callback, wp_unslash( $_REQUEST[ $key ] ) ) : '';
	}

	/**
	 * Add and store a notice.
	 *
	 * WC notice functions are not available in the admin
	 *
	 * @since 3.0.0
	 * @param string $message The text to display in the notice.
	 * @param string $notice_type The singular name of the notice type - either error, success or notice. [optional]
	 */
	public static function wc_add_notice( $message, $notice_type = 'success' ) {

		if ( function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( $message, $notice_type );
		}
	}

	/**
	 * Gets the full URL to the log file for a given $handle
	 *
	 * @since 3.0.0
	 * @param string $handle log handle
	 * @return string URL to the WC log file identified by $handle
	 */
	public static function get_wc_log_file_url( $handle ) {
		return admin_url( sprintf( 'admin.php?page=wc-status&tab=logs&log_file=%s-%s-log', $handle, sanitize_file_name( wp_hash( $handle ) ) ) );
	}


	/**
	 * Gets the current WordPress site name.
	 *
	 * This is helpful for retrieving the actual site name instead of the
	 * network name on multisite installations.
	 *
	 * @since 3.0.0
	 * @return string
	 */
	public static function get_site_name() {
		return ( is_multisite() ) ? get_blog_details()->blogname : get_bloginfo( 'name' );
	}

	/**
	 * Displays a notice if the provided hook has not yet run.
	 *
	 * @since 3.0.0
	 *
	 * @param string $hook action hook to check
	 * @param string $method method/function name
	 * @param string $version version the notice was added
	 */
	public static function maybe_doing_it_early( $hook, $method, $version ) {

		if ( ! did_action( $hook ) ) {
			Plugin_Compatibility::wc_doing_it_wrong( $method, "This should only be called after '{$hook}'", $version );
		}
	}

	/**
	 * Triggers a PHP error.
	 *
	 * This wrapper method ensures AJAX isn't broken in the process.
	 *
	 * @since 3.0.0
	 * @param string $message the error message
	 * @param int $type Optional. The error type. Defaults to E_USER_NOTICE
	 */
	public static function trigger_error( $message, $type = E_USER_NOTICE ) { // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		wc_deprecated_function( __METHOD__, '3.9.0' );

		if ( wp_doing_ajax() ) {

			switch ( $type ) {

				case E_USER_NOTICE:
					$prefix = 'Notice: ';
					break;

				case E_USER_WARNING:
					$prefix = 'Warning: ';
					break;

				default:
					$prefix = '';
			}

			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( $prefix . $message );

		} else {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
			trigger_error( $message, $type );
		}
	}
}
