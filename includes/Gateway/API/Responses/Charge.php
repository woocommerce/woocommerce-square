<?php

namespace WooCommerce\Square\Gateway\API\Responses;

use WooCommerce\Square\Framework\PaymentGateway\Api\Payment_Gateway_API_Authorization_Response;
use WooCommerce\Square\Framework\PaymentGateway\Api\Payment_Gateway_API_Response_Message_Helper;

defined( 'ABSPATH' ) || exit;

/**
 * The Charge API response object.
 *
 * @since 2.0.0
 *
 * @method \Square\Models\ChargeResponse get_data()
 */
class Charge extends \WooCommerce\Square\Gateway\API\Response implements Payment_Gateway_API_Authorization_Response {


	/**
	 * Determines if the charge was held.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function transaction_held() {

		$held = parent::transaction_held();

		// ensure the tender is CAPTURED
		if ( $this->get_tender() ) {
			$held = 'AUTHORIZED' === $this->get_tender()->getCardDetails()->getStatus();
		}

		return $held;
	}


	/** Getter methods ************************************************************************************************/


	/**
	 * Gets the authorization code.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_authorization_code() {

		return $this->get_tender() ? $this->get_tender()->getId() : '';
	}


	/**
	 * Gets the transaction ID.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_transaction_id() {

		return $this->get_transaction() ? $this->get_transaction()->getId() : '';
	}


	/**
	 * Gets the location ID.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_location_id() {

		return $this->get_transaction() ? $this->get_transaction()->getLocationId() : '';
	}


	/**
	 * Gets the Square order ID, if any.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_square_order_id() {

		return $this->get_transaction() ? $this->get_transaction()->getOrderId() : '';
	}


	/**
	 * Gets the Square tender (auth) object.
	 *
	 * @since 2.0.0
	 *
	 * @return \Square\Models\Tender|null
	 */
	public function get_tender() {

		return $this->get_transaction() ? current( $this->get_transaction()->getTenders() ) : null;
	}


	/**
	 * Gets the Square transaction object.
	 *
	 * @since 2.0.0
	 *
	 * @return \Square\Models\Transaction|null
	 */
	public function get_transaction() {

		return ! $this->has_errors() && $this->get_data()->getTransaction() ? $this->get_data()->getTransaction() : null;
	}


	/**
	 * Gets the message to display to the user.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_user_message() {

		$message_id = $this->get_status_code();

		$helper = new \WooCommerce\Square\Gateway\API\Response_Message_Helper();

		return $helper->get_user_message( $message_id );
	}


	/** No-op methods *************************************************************************************************/


	public function get_avs_result() { }

	public function get_csc_result() { }

	public function csc_match() { }


}
