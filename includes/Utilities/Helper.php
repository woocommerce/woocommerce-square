<?php
namespace WooCommerce\Square\Utilities;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce Square Helper Class
 *
 * The purpose of this class is to centralize common utility functions.
 *
 * @since 2.2.0
 */
class Helper {
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
	 * Safely get and trim data from $_POST
	 *
	 * @since 3.0.0
	 * @param string $key array key to get from $_POST array
	 * @return string value from $_POST or blank string if $_POST[ $key ] is not set
	 */
	public static function get_post( $key ) {

		if ( isset( $_POST[ $key ] ) ) {
			return trim( $_POST[ $key ] );
		}

		return '';
	}

	/**
	 * Add and store a notice.
	 *
	 * WC notice functions are not available in the admin
	 *
	 * @since 3.0.2
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
	 * Helper method to get the version of the currently installed WooCommerce
	 *
	 * @since 3.0.0
	 * @return string woocommerce version number or null
	 */
	public static function get_wc_version() {

		return defined( 'WC_VERSION' ) && WC_VERSION ? WC_VERSION : null;
	}
}
