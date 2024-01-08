<?php

namespace WooCommerce\Square\Gateway\API\Responses;

use WooCommerce\Square\Framework\PaymentGateway\Payment_Gateway_Helper;
use WooCommerce\Square\Framework\PaymentGateway\Api\Payment_Gateway_API_Get_Tokenized_Payment_Methods_Response;
use WooCommerce\Square\Framework\PaymentGateway\PaymentTokens\Payment_Gateway_Payment_Token;

defined( 'ABSPATH' ) || exit;

/**
 * Get customer response.
 *
 * @since 2.0.0
 *
 * @method \Square\Models\RetrieveCustomerResponse|array get_data()
 */
class Get_Customer extends \WooCommerce\Square\Gateway\API\Response implements Payment_Gateway_API_Get_Tokenized_Payment_Methods_Response {
	/**
	 * Returns any payment tokens.
	 *
	 * @since 1.0.0
	 *
	 * @return Payment_Gateway_Payment_Token[]
	 */
	public function get_payment_tokens() {

		$cards  = $this->get_data() instanceof \Square\Models\RetrieveCustomerResponse ? $this->get_data()->getCustomer()->getCards() : array();
		$tokens = array();

		if ( is_array( $cards ) ) {

			foreach ( $cards as $card ) {

				if ( 'SQUARE_GIFT_CARD' === $card->getCardBrand() ) {
					continue;
				}

				$token_id  = $card->getId();
				$card_type = 'AMERICAN_EXPRESS' === $card->getCardBrand() ? Payment_Gateway_Helper::CARD_TYPE_AMEX : $card->getCardBrand();

				$tokens[ $token_id ] = new Payment_Gateway_Payment_Token(
					$token_id,
					array(
						'type'      => 'credit_card',
						'card_type' => $card_type,
						'last_four' => $card->getLast4(),
						'exp_month' => $card->getExpMonth(),
						'exp_year'  => $card->getExpYear(),
					)
				);
			}
		}

		return $tokens;
	}
}
