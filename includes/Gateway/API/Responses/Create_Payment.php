<?php

namespace WooCommerce\Square\Gateway\API\Responses;

use WooCommerce\Square\Framework\PaymentGateway\Api\Payment_Gateway_API_Authorization_Response;

defined( 'ABSPATH' ) || exit;

/**
 * The Create Payment API response object.
 *
 * @since 2.2.0
 *
 * @method \Square\Models\CreatePaymentResponse get_data()
 */
class Create_Payment extends \WooCommerce\Square\Gateway\API\Response implements Payment_Gateway_API_Authorization_Response {

	/**
	 * Determines if the charge was held.
	 *
	 * @since 2.2.0
	 *
	 * @return bool
	 */
	public function transaction_held() {

		$held = parent::transaction_held();

		// ensure the tender is CAPTURED
		if ( $this->get_payment() ) {
			// Check if the card or wallet is AUTHORIZED (WALLET is for Cash App payments).
			$card_details   = $this->get_payment()->getCardDetails();
			$wallet_details = $this->get_payment()->getWalletDetails();
			if ( ! empty( $card_details ) ) {
				$held = self::STATUS_AUTHORIZED === $card_details->getStatus();
			} elseif ( ! empty( $wallet_details ) ) {
				$held = self::STATUS_AUTHORIZED === $wallet_details->getStatus();
			}
		}

		return $held;
	}


	/** Getter methods ************************************************************************************************/


	/**
	 * Gets the authorization code.
	 *
	 * @since 2.2.0
	 *
	 * @return string
	 */
	public function get_authorization_code() {

		return $this->get_payment() ? $this->get_payment()->getId() : '';
	}


	/**
	 * Gets the transaction (payment) ID.
	 *
	 * @since 2.2.0
	 *
	 * @return string
	 */
	public function get_transaction_id() {

		return $this->get_payment() ? $this->get_payment()->getId() : '';
	}



	/**
	 * Gets the location ID.
	 *
	 * @since 2.2.0
	 *
	 * @return string
	 */
	public function get_location_id() {

		return $this->get_payment() ? $this->get_payment()->getLocationId() : '';
	}


	/**
	 * Gets the Square order ID, if any.
	 *
	 * @since 2.2.0
	 *
	 * @return string
	 */
	public function get_square_order_id() {

		return $this->get_payment() ? $this->get_payment()->getOrderId() : '';
	}


	/**
	 * Gets the Square payment object.
	 *
	 * @since 2.2.0
	 *
	 * @return \Square\Models\Payment|null
	 */
	public function get_payment() {

		return ! $this->has_errors() && ! is_null( $this->get_data() ) && $this->get_data()->getPayment() ? $this->get_data()->getPayment() : null;
	}


	/**
	 * Gets the message to display to the user.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_user_message() {

		$error_codes = $this->get_status_codes();

		$helper = new \WooCommerce\Square\Gateway\API\Response_Message_Helper();

		return $helper->get_user_messages( $error_codes );
	}

	/**
	 * Returns if the card used is a Square Gift Card.
	 *
	 * @since 3.9.0
	 * @return boolean
	 */
	public function is_gift_card_payment() {
		$payment      = $this->get_payment();
		$card_details = $payment->getCardDetails();

		// If the card details are not available, we can't determine if it's a gift card.
		if ( ! $card_details ) {
			return false;
		}

		$card = $card_details->getCard();

		return 'SQUARE_GIFT_CARD' === $card->getCardBrand();
	}

	/**
	 * Returns true if the payment status is completed.
	 *
	 * @since 4.5.0
	 * @return boolean
	 */
	public function is_cash_app_payment_completed() {
		return $this->get_payment() && self::STATUS_COMPLETED === $this->get_payment()->getStatus();
	}

	/**
	 * Returns true if the payment status is approved.
	 *
	 * @since 4.6.0
	 * @return boolean
	 */
	public function is_cash_app_payment_approved() {
		return $this->get_payment() && self::STATUS_APPROVED === $this->get_payment()->getStatus();
	}

	/** No-op methods *************************************************************************************************/

	public function get_avs_result() { }

	public function get_csc_result() { }

	public function csc_match() { }

}
