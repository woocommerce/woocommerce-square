<?php

namespace WooCommerce\Square\Gateway\API\Responses;
use WooCommerce\Square\Framework\PaymentGateway\Payment_Gateway_Helper;
use WooCommerce\Square\Framework\PaymentGateway\Api\Payment_Gateway_API_Create_Payment_Token_Response;
use WooCommerce\Square\Framework\PaymentGateway\PaymentTokens\Payment_Gateway_Payment_Token;

defined( 'ABSPATH' ) || exit;

/**
 * Create customer response class.
 *
 * @since 2.0.0
 *
 * @method \Square\Models\CreateCustomerCardResponse get_data()
 */
class Create_Customer_Card extends \WooCommerce\Square\Gateway\API\Response implements Payment_Gateway_API_Create_Payment_Token_Response {

	/**
	 * Gets the created payment token.
	 *
	 * @since 2.0.0
	 *
	 * @return Payment_Gateway_Payment_Token|null
	 */
	public function get_payment_token() {

		$card  = $this->get_data() instanceof \Square\Models\CreateCardResponse ? $this->get_data()->getCard() : null;
		$token = null;

		if ( $card ) {

			$card_type = 'AMERICAN_EXPRESS' === $card->getCardBrand() ? Payment_Gateway_Helper::CARD_TYPE_AMEX : $card->getCardBrand();

			$token = new Payment_Gateway_Payment_Token(
				$card->getId(),
				array(
					'type'      => 'credit_card',
					'card_type' => $card_type,
					'last_four' => $card->getLast4(),
					'exp_month' => $card->getExpMonth(),
					'exp_year'  => $card->getExpYear(),
				)
			);
		}

		return $token;
	}
}
