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

namespace WooCommerce\Square\Gateway;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Square\Framework\PaymentGateway\Api\Payment_Gateway_API_Response;
use WooCommerce\Square\Framework\PaymentGateway\PaymentTokens\Payment_Gateway_Payment_Tokens_Handler;
use WooCommerce\Square\Framework\PaymentGateway\PaymentTokens\Square_Credit_Card_Payment_Token;

class Card_Handler extends Payment_Gateway_Payment_Tokens_Handler {


	/**
	 * Tokenizes the current payment method and adds the standard transaction
	 * data to the order post record.
	 *
	 * @since 2.1.0
	 *
	 * @param \WC_Order $order order object
	 * @param Payment_Gateway_API_Create_Payment_Token_Response|null $response payment token API response, or null if the request should be made
	 * @param string $environment_id optional environment ID, defaults to the current environment
	 * @return \WC_Order order object
	 * @throws \Exception on transaction failure
	 */
	public function create_token( \WC_Order $order, $response = null, $environment_id = null ) {

		$order = parent::create_token( $order, $response, $environment_id );

		// remove the verification token that was used to store the card so it's not also sent in the payment request
		$order->payment->verification_token = null;

		return $order;
	}


	/**
	 * Determines if a token should be deleted locally after a failed API attempt.
	 *
	 * Checks the response code, and if Square indicates the card ID was not found then it's probably safe to delete.
	 *
	 * @since 2.0.0
	 *
	 * @param Payment_Gateway_API_Response $response
	 * @return bool
	 */
	public function should_delete_token( Payment_Gateway_API_Response $response ) {

		return 'NOT_FOUND' === $response->get_status_code();
	}

	/**
	 * Gets the available payment tokens for a user as an associative array of
	 * payment token to Payment_Gateway_Payment_Token
	 *
	 * @since 2.2.0
	 * @param int $user_id WordPress user identifier, or 0 for guest
	 * @param array $args optional arguments, can include
	 *    `customer_id` - if not provided, this will be looked up based on $user_id
	 *    `environment_id` - defaults to plugin current environment
	 * @return array array of string token to Payment_Gateway_Payment_Token object
	 */
	public function get_tokens( $user_id, $args = array() ) {
		// default to current environment
		if ( ! isset( $args['environment_id'] ) ) {
			$args['environment_id'] = $this->get_environment_id();
		}

		if ( ! isset( $args['customer_id'] ) ) {
			$args['customer_id'] = $this->get_gateway()->get_customer_id( $user_id, array( 'environment_id' => $args['environment_id'] ) );
		}

		$transient_key = $this->get_transient_key( $user_id );

		$customer_id = $args['customer_id'];

		if ( $transient_key ) {
			$transient_tokens = get_transient( $transient_key );

			if ( false !== $transient_tokens ) {
				return $transient_tokens;
			}
		}

		$loaded_tokens = Square_Credit_Card_Payment_Token::get_square_customer_tokens(
			\WC_Payment_Tokens::get_customer_tokens( $user_id, \WooCommerce\Square\Plugin::GATEWAY_ID )
		);

		if ( $customer_id ) {
			try {
				// retrieve the payment method tokes from the remote API
				$response = $this->get_gateway()->get_api()->get_tokenized_payment_methods( $customer_id );

				// Only update local tokens when the response has no errors or the customer is not found in Square
				if ( ! $response->has_errors() || 'NOT_FOUND' === $response->get_status_code() ) {
					$remote_payment_tokens = $response->get_payment_tokens();

					$all_remote_payment_tokens = array_values(
						array_map(
							function( $remote_payment_token ) {
								return $remote_payment_token->get_id();
							},
							$remote_payment_tokens
						)
					);

					$all_loaded_tokens = array_map(
						function( $loaded_token ) {
							return $loaded_token->get_token();
						},
						$loaded_tokens
					);

					$unsynced_payment_token_ids = array_unique( array_diff( $all_remote_payment_tokens, $all_loaded_tokens ) );

					/**
					 * @var WooCommerce\Square\Framework\PaymentGateway\PaymentTokens\Payment_Gateway_Payment_Token $payment_token
					 */
					foreach ( $remote_payment_tokens as $payment_token => $remote_payment_token ) {
						if ( in_array( $remote_payment_token->get_id(), $unsynced_payment_token_ids, true ) ) {
							$token_obj = new Square_Credit_Card_Payment_Token();
							$token_obj->set_token( $remote_payment_token->get_id() );
							$token_obj->set_gateway_id( $this->get_gateway()->get_id() );
							$token_obj->set_last4( $remote_payment_token->get_last_four() );
							$token_obj->set_expiry_year( $remote_payment_token->get_exp_year() );
							$token_obj->set_expiry_month( $remote_payment_token->get_exp_month() );
							$token_obj->set_card_type( $remote_payment_token->get_card_type() );
							$token_obj->set_user_id( $user_id );
							$token_obj->save();
						}
					}
				}
			} catch ( \Exception $e ) {

				// communication or other error
				$this->get_gateway()->add_debug_message( $e->getMessage(), 'error' );
			}
		}

		/**
		 * Hook that fires once the Square Credit Card payment tokens are loaded.
		 *
		 * @since 3.8.0
		 */
		do_action( 'wc_payment_gateway_square_credit_card_payment_tokens_loaded', $loaded_tokens, $this );

		set_transient( $transient_key, $loaded_tokens, HOUR_IN_SECONDS );

		return $loaded_tokens;
	}
}
