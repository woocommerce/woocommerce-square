<?php
/**
 * WooCommerce Payment Gateway Framework
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
 * @copyright Copyright (c) 2013-2016, SkyVerge, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0 or later
 *
 * Modified by WooCommerce on 19 December 2021.
 */

namespace WooCommerce\Square\Framework\PaymentGateway\ApplePay\Api;
use WooCommerce\Square\Framework\Api\API_JSON_Response;

defined( 'ABSPATH' ) or exit;

/**
 * The Apple Pay API response object.
 *
 * @since 3.0.0
 */
class Payment_Gateway_Apple_Pay_API_Response extends API_JSON_Response {


	/**
	 * Gets the status code.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public function get_status_code() {

		return $this->statusCode;
	}


	/**
	 * Gets the status message.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public function get_status_message() {

		return $this->statusMessage;
	}


	/**
	 * Gets the validated merchant session.
	 *
	 * @since 3.0.0
	 *
	 * @return string|array
	 */
	public function get_merchant_session() {

		return $this->raw_response_json;
	}


	/**
	 * Get the string representation of this response with any and all sensitive
	 * elements masked or removed.
	 *
	 * No strong indication from the Apple documentation that these _need_ to be
	 * masked, but they don't provide any useful info and only make the debug
	 * logs unnecessarily huge.
	 *
	 * @since 3.0.0
	 * @see SquareFramework\Api\API_Response::to_string_safe()
	 *
	 * @return string
	 */
	public function to_string_safe() {

		$string = $this->to_string();

		// mask the merchant session ID
		$string = str_replace( $this->merchantSessionIdentifier, str_repeat( '*', 10 ), $string );

		// mask the merchant ID
		$string = str_replace( $this->merchantIdentifier, str_repeat( '*', 10 ), $string );

		// mask the signature
		$string = str_replace( $this->signature, str_repeat( '*', 10 ), $string );

		return $string;
	}
}
