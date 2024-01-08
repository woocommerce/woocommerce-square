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
use WooCommerce\Square\Framework as SquareFramework;
use WooCommerce\Square\Framework\PaymentGateway\Payment_Gateway;

defined( 'ABSPATH' ) or exit;

/**
 * Sets up the Apple Pay API.
 *
 * @since 3.0.0
 */
class Payment_Gateway_Apple_Pay_API extends SquareFramework\Api\Base {


	/** @var SquareFramework\PaymentGateway\Payment_Gateway the gateway instance */
	protected $gateway;


	/**
	 * Constructs the class.
	 *
	 * @since 3.0.0
	 *
	 * @param SquareFramework\PaymentGateway\Payment_Gateway the gateway instance
	 */
	public function __construct( SquareFramework\PaymentGateway\Payment_Gateway $gateway ) {

		$this->gateway = $gateway;

		$this->request_uri = 'https://apple-pay-gateway-cert.apple.com/paymentservices/startSession';

		$this->set_request_content_type_header( 'application/json' );
		$this->set_request_accept_header( 'application/json' );
		$this->set_response_handler( '\\WooCommerce\\Square\\Framework\\PaymentGateway\\ApplePay\\Api\\Payment_Gateway_Apple_Pay_API_Response' );
	}


	/**
	 * Validates the Apple Pay merchant.
	 *
	 * @since 3.0.0
	 *
	 * @param string $url the validation URL
	 * @param string $merchant_id the merchant ID to validate
	 * @param string $domain_name the verified domain name
	 * @param string $display_name the merchant display name
	 * @return Payment_Gateway_Apple_Pay_API_Response the response object
	 * @throws \Exception
	 */
	public function validate_merchant( $url, $merchant_id, $domain_name, $display_name ) {

		$this->request_uri = $url;

		$request = $this->get_new_request();

		$request->set_merchant_data( $merchant_id, $domain_name, $display_name );

		return $this->perform_request( $request );
	}


	/**
	 * Performs the request and return the parsed response.
	 *
	 * @since 3.0.0
	 *
	 * @param SquareFramework\Api\API_Request|object
	 * @return SquareFramework\Api\API_Response|object
	 * @throws \Exception
	 */
	protected function perform_request( $request ) {

		// set PEM file cert for requests
		add_action( 'http_api_curl', array( $this, 'set_cert_file' ) );

		return parent::perform_request( $request );
	}


	/**
	 * Sets the PEM file required for authentication.
	 *
	 * @internal
	 *
	 * @since 3.0.0
	 *
	 * @param resource $curl_handle
	 */
	public function set_cert_file( $curl_handle ) {

		if ( ! $curl_handle ) {
			return;
		}

		curl_setopt( $curl_handle, CURLOPT_SSLCERT, get_option( 'sv_wc_apple_pay_cert_path' ) );
	}


	/** Validation methods ****************************************************/


	/**
	 * Validates the post-parsed response.
	 *
	 * @since 3.0.0
	 *
	 * @return bool
	 *
	 * @throws \Exception
	 */
	protected function do_post_parse_response_validation() {

		$response = $this->get_response();

		if ( $response->get_status_code() && 200 !== $response->get_status_code() ) {
			throw new \Exception( $response->get_status_message() );
		}

		return true;
	}


	/** Helper methods ********************************************************/


	/**
	 * Gets a new request object.
	 *
	 * @since 3.0.0
	 *
	 * @param array $type Optional. The desired request type
	 * @return Payment_Gateway_Apple_Pay_API_Request the request object
	 */
	protected function get_new_request( $type = array() ) {

		return new Payment_Gateway_Apple_Pay_API_Request( $this->get_gateway() );
	}


	/**
	 * Gets the gateway instance.
	 *
	 * @since 3.0.0
	 *
	 * @return Payment_Gateway
	 */
	protected function get_gateway() {

		return $this->gateway;
	}


	/**
	 * Gets the plugin instance.
	 *
	 * @since 3.0.0
	 *
	 * @return SquareFramework\PaymentGateway\Payment_Gateway_Plugin
	 */
	protected function get_plugin() {

		return $this->get_gateway()->get_plugin();
	}
}
