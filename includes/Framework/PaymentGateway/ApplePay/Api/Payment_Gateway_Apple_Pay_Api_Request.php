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

use WooCommerce\Square\Framework\Api\API_JSON_Request;
use WooCommerce\Square\Framework\PaymentGateway\Payment_Gateway;

defined( 'ABSPATH' ) or exit;

/**
 * The Apple Pay API request object.
 *
 * @since 3.0.0
 */
class Payment_Gateway_Apple_Pay_API_Request extends API_JSON_Request {


	/** @var Payment_Gateway $gateway the gateway instance */
	protected $gateway;


	/**
	 * Constructs the request.
	 *
	 * @since 3.0.0
	 *
	 * @param Payment_Gateway $gateway the gateway instance
	 */
	public function __construct( Payment_Gateway $gateway ) {

		$this->gateway = $gateway;
	}


	/**
	 * Sets the data for merchant validation.
	 *
	 * @since 3.0.0
	 *
	 * @param string $merchant_id the merchant ID to validate
	 * @param string $domain_name the verified domain name
	 * @param string $display_name the merchant display name
	 */
	public function set_merchant_data( $merchant_id, $domain_name, $display_name ) {

		$data = array(
			'merchantIdentifier' => $merchant_id,
			'domainName'         => str_replace( array( 'http://', 'https://' ), '', $domain_name ),
			'displayName'        => $display_name,
		);

		/**
		 * Filters the data for merchant validation.
		 *
		 * @since 3.0.0
		 *
		 * @param array $data {
		 *     The merchant data.
		 *
		 *     @var string $merchantIdentifier the merchant ID
		 *     @var string $domainName         the verified domain name
		 *     @var string $displayName        the merchant display name
		 * }
		 * @param Payment_Gateway_Apple_Pay_API_Request the request object
		 */
		$this->data = apply_filters( 'sv_wc_apple_pay_api_merchant_data', $data, $this );
	}


	/**
	 * Get the string representation of this response with any and all sensitive
	 * elements masked or removed.
	 *
	 * @since 3.0.0
	 * @see API_Response::to_string_safe()
	 *
	 * @return string
	 */
	public function to_string_safe() {

		// mask the merchant ID
		$string = str_replace( $this->data['merchantIdentifier'], str_repeat( '*', strlen( $this->data['merchantIdentifier'] ) ), $this->to_string() );

		return $string;
	}
}
