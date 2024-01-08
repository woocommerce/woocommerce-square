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
 * @copyright Copyright (c) 2013-2019, SkyVerge, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0 or later
 *
 * Modified by WooCommerce on 15 December 2021.
 */

namespace WooCommerce\Square\Framework\PaymentGateway;

use WooCommerce;
use WooCommerce\Square\Framework\PaymentGateway\Payment_Gateway_Helper;
use WooCommerce\Square\Framework\Square_Helper;
use WooCommerce\Square\Framework\Compatibility\Order_Compatibility;
use WooCommerce\Square\Framework\Addresses\Customer_Address;
use WooCommerce\Square\Framework\PaymentGateway\Api\Payment_Gateway_API_Response_Interface;
use \WooCommerce\Square\Framework\PaymentGateway\Api\Payment_Gateway_API_Response;
use WooCommerce\Square\Gateway\API\Responses\Create_Payment as Create_Payment;
use WooCommerce\Square\Utilities\Money_Utility;
use WooCommerce\Square\Handlers\Order;
use WooCommerce\Square\WC_Order_Square;


defined( 'ABSPATH' ) || exit;

/**
 * # WooCommerce Payment Gateway Framework Direct Gateway
 *
 * @since 3.0.0
 */
abstract class Payment_Gateway_Direct extends Payment_Gateway {

	/**
	 * Validate the payment fields when processing the checkout
	 *
	 * NOTE: if we want to bring billing field validation (ie length) into the
	 * fold, see the Elavon VM Payment Gateway for a sample implementation
	 *
	 * @since 3.0.0
	 * @see WC_Payment_Gateway::validate_fields()
	 * @return bool true if fields are valid, false otherwise
	 */
	public function validate_fields() {

		$is_valid = parent::validate_fields();

		if ( $this->supports_tokenization() ) {

			// tokenized transaction?
			if ( Square_Helper::get_post( 'wc-' . $this->get_id_dasherized() . '-payment-token' ) ) {

				// unknown token?
				if ( ! $this->get_payment_tokens_handler()->user_has_token( get_current_user_id(), Square_Helper::get_post( 'wc-' . $this->get_id_dasherized() . '-payment-token' ) ) ) {
					Square_Helper::wc_add_notice( esc_html__( 'Payment error, please try another payment method or contact us to complete your transaction.', 'woocommerce-square' ), 'error' );
					$is_valid = false;
				}

				// Check the CSC if enabled
				if ( $this->is_credit_card_gateway() && $this->csc_enabled_for_tokens() ) {
					$is_valid = $this->validate_csc( Square_Helper::get_post( 'wc-' . $this->get_id_dasherized() . '-csc' ) ) && $is_valid;
				}

				// no more validation to perform
				return $is_valid;
			}
		}

		// validate remaining payment fields
		if ( $this->is_credit_card_gateway() ) {
			return $this->validate_credit_card_fields( $is_valid );
		} else {
			$method_name = 'validate_' . str_replace( '-', '_', strtolower( $this->get_payment_type() ) ) . '_fields';
			if ( is_callable( array( $this, $method_name ) ) ) {
				return $this->$method_name( $is_valid );
			}
		}

		// no more validation to perform. Return the parent method's outcome.
		return $is_valid;
	}


	/**
	 * Returns true if the posted credit card fields are valid, false otherwise
	 *
	 * @since 3.0.0
	 * @param boolean $is_valid true if the fields are valid, false otherwise
	 * @return boolean true if the fields are valid, false otherwise
	 */
	protected function validate_credit_card_fields( $is_valid ) {

		$account_number   = Square_Helper::get_post( 'wc-' . $this->get_id_dasherized() . '-account-number' );
		$expiration_month = Square_Helper::get_post( 'wc-' . $this->get_id_dasherized() . '-exp-month' );
		$expiration_year  = Square_Helper::get_post( 'wc-' . $this->get_id_dasherized() . '-exp-year' );
		$expiry           = Square_Helper::get_post( 'wc-' . $this->get_id_dasherized() . '-expiry' );
		$csc              = Square_Helper::get_post( 'wc-' . $this->get_id_dasherized() . '-csc' );

		// handle single expiry field formatted like "MM / YY" or "MM / YYYY"
		if ( ! $expiration_month & ! $expiration_year && $expiry ) {
			list( $expiration_month, $expiration_year ) = array_map( 'trim', explode( '/', $expiry ) );
		}

		$is_valid = $this->validate_credit_card_account_number( $account_number ) && $is_valid;

		$is_valid = $this->validate_credit_card_expiration_date( $expiration_month, $expiration_year ) && $is_valid;

		// validate card security code
		if ( $this->csc_enabled() ) {
			$is_valid = $this->validate_csc( $csc ) && $is_valid;
		}

		/**
		 * Direct Payment Gateway Validate Credit Card Fields Filter.
		 *
		 * Allow actors to filter the credit card field validation.
		 *
		 * @since 3.0.0
		 * @param bool $is_valid true for validation to pass
		 * @param Payment_Gateway_Direct $this direct gateway class instance
		 */
		return apply_filters( 'wc_payment_gateway_' . $this->get_id() . '_validate_credit_card_fields', $is_valid, $this );
	}


	/**
	 * Validates the provided credit card expiration date
	 *
	 * @since 3.0.0
	 * @param string $expiration_month the credit card expiration month
	 * @param string $expiration_year the credit card expiration month
	 * @return boolean true if the card expiration date is valid, false otherwise
	 */
	protected function validate_credit_card_expiration_date( $expiration_month, $expiration_year ) {

		$is_valid = true;

		if ( 2 === strlen( $expiration_year ) ) {
			$expiration_year = '20' . $expiration_year;
		}

		// validate expiration data
		$current_year  = gmdate( 'Y' );
		$current_month = gmdate( 'n' );

		if ( ! ctype_digit( $expiration_month ) || ! ctype_digit( $expiration_year ) ||
			$expiration_month > 12 ||
			$expiration_month < 1 ||
			$expiration_year < $current_year ||
			( $expiration_year === $current_year && $expiration_month < $current_month ) ||
			$expiration_year > $current_year + 20
		) {
			Square_Helper::wc_add_notice( esc_html__( 'Card expiration date is invalid', 'woocommerce-square' ), 'error' );
			$is_valid = false;
		}

		return $is_valid;
	}


	/**
	 * Validates the provided credit card account number
	 *
	 * @since 3.0.0
	 * @param string $account_number the credit card account number
	 * @return boolean true if the card account number is valid, false otherwise
	 */
	protected function validate_credit_card_account_number( $account_number ) {

		$is_valid = true;

		// validate card number
		$account_number = str_replace( array( ' ', '-' ), '', $account_number );

		if ( empty( $account_number ) ) {

			Square_Helper::wc_add_notice( esc_html__( 'Card number is missing', 'woocommerce-square' ), 'error' );
			$is_valid = false;

		} else {

			if ( strlen( $account_number ) < 12 || strlen( $account_number ) > 19 ) {
				Square_Helper::wc_add_notice( esc_html__( 'Card number is invalid (wrong length)', 'woocommerce-square' ), 'error' );
				$is_valid = false;
			}

			if ( ! ctype_digit( $account_number ) ) {
				Square_Helper::wc_add_notice( esc_html__( 'Card number is invalid (only digits allowed)', 'woocommerce-square' ), 'error' );
				$is_valid = false;
			}

			if ( ! Payment_Gateway_Helper::luhn_check( $account_number ) ) {
				Square_Helper::wc_add_notice( esc_html__( 'Card number is invalid', 'woocommerce-square' ), 'error' );
				$is_valid = false;
			}
		}

		return $is_valid;
	}


	/**
	 * Validates the provided Card Security Code, adding user error messages as
	 * needed
	 *
	 * @since 3.0.0
	 * @param string $csc the customer-provided card security code
	 * @return boolean true if the card security code is valid, false otherwise
	 */
	protected function validate_csc( $csc ) {

		$is_valid = true;

		// validate security code
		if ( ! empty( $csc ) ) {

			// digit validation
			if ( ! ctype_digit( $csc ) ) {
				Square_Helper::wc_add_notice( esc_html__( 'Card security code is invalid (only digits are allowed)', 'woocommerce-square' ), 'error' );
				$is_valid = false;
			}

			// length validation
			if ( strlen( $csc ) < 3 || strlen( $csc ) > 4 ) {
				Square_Helper::wc_add_notice( esc_html__( 'Card security code is invalid (must be 3 or 4 digits)', 'woocommerce-square' ), 'error' );
				$is_valid = false;
			}
		} elseif ( $this->csc_required() ) {

			Square_Helper::wc_add_notice( esc_html__( 'Card security code is missing', 'woocommerce-square' ), 'error' );
			$is_valid = false;
		}

		return $is_valid;
	}

	/**
	 * Handles payment processing.
	 *
	 * @see WC_Payment_Gateway::process_payment()
	 *
	 * @since 3.0.0
	 *
	 * @param int|string $order_id
	 * @return array associative array with members 'result' and 'redirect'
	 */
	public function process_payment( $order_id ) {

		$default = parent::process_payment( $order_id );

		/**
		 * Direct Gateway Process Payment Filter.
		 *
		 * Allow actors to intercept and implement the process_payment() call for
		 * this transaction. Return an array value from this filter will return it
		 * directly to the checkout processing code and skip this method entirely.
		 *
		 * @since 3.0.0
		 * @param bool $result default true
		 * @param int|string $order_id order ID for the payment
		 * @param Payment_Gateway_Direct $this instance
		 */
		$result = apply_filters( 'wc_payment_gateway_' . $this->get_id() . '_process_payment', true, $order_id, $this );

		if ( is_array( $result ) ) {
			return $result;
		}

		// add payment information to order
		$order = $this->get_order( $order_id );

		try {

			// handle creating or updating a payment method for registered customers if tokenization is enabled
			if ( $this->supports_tokenization() && 0 !== (int) $order->get_user_id() ) {

				// if already paying with an existing method, try and updated it locally and remotely
				if ( ! empty( $order->payment->token ) ) {

					$this->update_transaction_payment_method( $order );

					// otherwise, create a new token if desired
				} elseif ( $this->get_payment_tokens_handler()->should_tokenize() && ( '0.00' === $order->payment_total || $this->tokenize_before_sale() ) ) {

					$order = $this->get_payment_tokens_handler()->create_token( $order );
				}
			}

			// payment failures are handled internally by do_transaction()
			// the order amount will be $0 if a WooCommerce Subscriptions free trial product is being processed
			// note that customer id & payment token are saved to order when create_token() is called
			if ( ( '0.00' === $order->payment_total && ! $this->transaction_forced() ) || $this->do_transaction( $order ) ) {

				// add transaction data for zero-dollar "orders"
				if ( '0.00' === $order->payment_total ) {
					$this->add_transaction_data( $order );
				}

				/**
				 * Filters the order status that's considered to be "held".
				 *
				 * @since 3.0.0
				 *
				 * @param string $status held order status
				 * @param \WC_Order $order order object
				 * @param Payment_Gateway_API_Response_Interface|null $response API response object, if any
				 */
				$held_order_status = apply_filters( 'wc_' . $this->get_id() . '_held_order_status', 'on-hold', $order, null );

				if ( $order->has_status( $held_order_status ) ) {

					/**
					 * Although `wc_reduce_stock_levels` accepts $order, it's necessary to pass
					 * the order ID instead as `wc_reduce_stock_levels` reloads the order from the DB.
					 *
					 * Refer to the following PR link for more details:
					 * @see https://github.com/woocommerce/woocommerce-square/pull/728
					 */
					wc_reduce_stock_levels( $order->get_id() ); // reduce stock for held orders, but don't complete payment
				} else {
					$order->payment_complete(); // mark order as having received payment
				}

				// process_payment() can sometimes be called in an admin-context
				if ( isset( WC()->cart ) ) {
					WC()->cart->empty_cart();
				}

				/**
				 * Payment Gateway Payment Processed Action.
				 *
				 * Fired when a payment is processed for an order.
				 *
				 * @since 3.0.0
				 * @param \WC_Order $order order object
				 * @param Payment_Gateway_Direct $this instance
				 */
				do_action( 'wc_payment_gateway_' . $this->get_id() . '_payment_processed', $order, $this );

				$gift_card_purchase_type = Order::get_gift_card_purchase_type( $order );

				// To create/activate/load a gift card, a payment must be in COMPLETE state.
				if ( self::TRANSACTION_TYPE_CHARGE === wc_square()->get_gateway()->get_option( 'transaction_type' ) ) {
					if ( 'new' === $gift_card_purchase_type ) {
						$this->create_gift_card( $order );
					} elseif ( 'load' === $gift_card_purchase_type ) {
						$gan = Order::get_gift_card_gan( $order );
						$this->load_gift_card( $gan, $order );
					}
				}

				return array(
					'result'   => 'success',
					'redirect' => $this->get_return_url( $order ),
				);
			}
		} catch ( \Exception $e ) {

			$this->mark_order_as_failed( $order, $e->getMessage() );

			return array(
				'result'  => 'failure',
				'message' => $e->getMessage(),
			);
		}

		return $default;
	}

	/**
	 * Creates a gift card and activates it.
	 *
	 * @since 4.2.0
	 *
	 * @param \WC_Order $order
	 */
	public function create_gift_card( $order ) {
		$gift_card_line_item_id = $this->get_order_meta( $order, 'gift_card_line_item_id' );

		/** @var API\Responses\Get_Gift_Card $response */
		$response = $this->get_api()->create_gift_card( $gift_card_line_item_id );

		if ( ! $response->get_data() instanceof \Square\Models\CreateGiftCardResponse ) {
			return false;
		}

		/**
		 * Fires after creation of the gift card.
		 *
		 * @since 4.2.0
		 *
		 * @param \WC_Order $order Woo Order.
		 */
		do_action( 'wc_square_gift_card_created', $order );

		$gift_card_id = $response->get_id();

		$response = $this->get_api()->activate_gift_card( $gift_card_id, $this->get_order_meta( $order, 'square_order_id' ), $gift_card_line_item_id );

		/** @var \Square\Models\GiftCardActivity $gift_card_activity */
		$gift_card_activity = $response->get_data();

		if ( ! $gift_card_activity instanceof \Square\Models\CreateGiftCardActivityResponse ) {
			return false;
		}

		$gan = $gift_card_activity->getGiftCardActivity()->getGiftCardGan();
		$this->update_order_meta( $order, 'gift_card_number', $gan );

		$order->add_order_note(
			sprintf(
				/* translators: %1$s - Gift Card Account Number */
				esc_html__( 'Gift card with number: %1$s created and activated.', 'woocommerce-square' ),
				esc_html( $gan )
			)
		);

		/**
		 * Fires after activation of the gift card.
		 *
		 * @since 4.2.0
		 *
		 * @param \WC_Order $order Woo Order.
		 */
		do_action( 'wc_square_gift_card_activated', $order );

		return true;
	}

	/**
	 * Loads an existing gift card with an amount.
	 *
	 * @since 4.2.0
	 *
	 * @param string    $gan   The gift card number.
	 * @param \WC_Order $order WooCommerce order.
	 * @return boolean
	 */
	public function load_gift_card( $gan, $order ) {
		/** @var API\Responses\Get_Gift_Card $response */
		$response = $this->get_api()->load_gift_card( $gan, $order );

		/** @var \Square\Models\GiftCardActivity $gift_card_activity */
		$gift_card_activity = $response->get_data();

		if ( ! $gift_card_activity instanceof \Square\Models\CreateGiftCardActivityResponse ) {
			return false;
		}

		return false;
	}

	/**
	 * Handles updating a user's payment method during payment.
	 *
	 * This allows us to check the billing address against the last used so we can determine if it needs an update.
	 *
	 * @since 3.0.0
	 *
	 * @param \WC_Order $order
	 * @return \WC_Order
	 */
	protected function update_transaction_payment_method( \WC_Order $order ) {

		$token   = $this->get_payment_tokens_handler()->get_token( $order->get_user_id(), $order->payment->token );
		$address = new Customer_Address();
		$address->set_from_order( $order );

		$new_billing_hash = $address->get_hash();

		// if the address & token hash don't match, update
		if ( $token->get_billing_hash() !== $new_billing_hash ) {

			// if the API supports it, update remotely
			if ( $this->get_api()->supports_update_tokenized_payment_method() ) {

				$response = null;

				try {

					$response = $this->get_api()->update_tokenized_payment_method( $order );

					// if an address was passed and the token was updated remotely, update the billing hash
					if ( $response->transaction_approved() ) {

						$token->set_billing_hash( $new_billing_hash );

					} else {

						if ( $response->get_status_message() ) {
							$message = $response->get_status_code() ? $response->get_status_code() . ' - ' . $response->get_status_message() : $response->get_status_message();
						} else {
							$message = __( 'Unknown error', 'woocommerce-square' );
						}

						throw new \Exception( $message );
					}
				} catch ( \Exception $exception ) {

					$message = sprintf(
						// translators: Placeholder: %s Error message.
						esc_html__( 'Payment method address could not be updated. %s', 'woocommerce-square' ),
						$exception->getMessage()
					);

					$order->add_order_note( $message );

					if ( $this->debug_log() ) {
						$this->get_plugin()->log( $message, $this->get_id() );
					}
				}
			} else {

				// updating remotely isn't supported, so just update the hash locally
				$token->set_billing_hash( $new_billing_hash );
			}
		}

		return $order;
	}


	/**
	 * Add payment and transaction information as class members of WC_Order
	 * instance.  The standard information that can be added includes:
	 *
	 * $order->payment_total           - the payment total
	 * $order->customer_id             - optional payment gateway customer id (useful for tokenized payments for certain gateways, etc)
	 * $order->payment->account_number - the credit card or checking account number
	 * $order->payment->last_four      - the last four digits of the account number
	 * $order->payment->card_type      - the card type (e.g. visa) derived from the account number
	 * $order->payment->routing_number - account routing number (check transactions only)
	 * $order->payment->account_type   - optional type of account one of 'checking' or 'savings' if type is 'check'
	 * $order->payment->card_type      - optional card type, ie one of 'visa', etc
	 * $order->payment->exp_month      - the 2 digit credit card expiration month (for credit card gateways), e.g. 07
	 * $order->payment->exp_year       - the 2 digit credit card expiration year (for credit card gateways), e.g. 17
	 * $order->payment->csc            - the card security code (for credit card gateways)
	 * $order->payment->check_number   - optional check number (check transactions only)
	 * $order->payment->drivers_license_number - optional driver license number (check transactions only)
	 * $order->payment->drivers_license_state  - optional driver license state code (check transactions only)
	 * $order->payment->token          - payment token (for tokenized transactions)
	 *
	 * Note that not all gateways will necessarily pass or require all of the
	 * above.  These represent the most common attributes used among a variety
	 * of gateways, it's up to the specific gateway implementation to make use
	 * of, or ignore them, or add custom ones by overridding this method.
	 *
	 * @since 3.0.0
	 * @see WooCommerce\Square\Framework\PaymentGateway\Payment_Gateway::get_order()
	 * @param int|\WC_Order $order_id order ID being processed
	 * @return WC_Order_Square object with payment and transaction information attached
	 */
	public function get_order( $order_id ) {
		$order = parent::get_order( $order_id );

		// paying with tokenized payment method (we've already verified that this token exists in the validate_fields method)
		$token_id = Square_Helper::get_post( 'wc-' . $this->get_id_dasherized() . '-payment-token' );

		// payment info
		if ( $token_id ) {
			$token = \WC_Payment_Tokens::get( $token_id );
			$token = $this->get_payment_tokens_handler()->get_token( $order->get_user_id(), $token->get_token() );

			$order->payment->token          = $token->get_token();
			$order->payment->account_number = $token->get_last4();
			$order->payment->last_four      = $token->get_last4();

			if ( $this->is_credit_card_gateway() ) {

				// credit card specific attributes
				$order->payment->card_type = $token->get_card_type();
				$order->payment->exp_month = $token->get_expiry_month();
				$order->payment->exp_year  = $token->get_expiry_year();

				if ( $this->csc_enabled_for_tokens() ) {
					$order->payment->csc = Square_Helper::get_post( 'wc-' . $this->get_id_dasherized() . '-csc' );
				}
			}
			// make this the new default payment token
			$this->get_payment_tokens_handler()->set_default_token( $order->get_user_id(), $token );
		}

		// standardize expiration date year to 2 digits
		if ( ! empty( $order->payment->exp_year ) && 4 === strlen( $order->payment->exp_year ) ) {
			$order->payment->exp_year = substr( $order->payment->exp_year, 2 );
		}

		/**
		 * Direct Gateway Get Order Filter.
		 *
		 * Allow actors to modify the order object.
		 *
		 * @since 3.0.0
		 * @param \WC_Order $order order object
		 * @param Payment_Gateway_Direct $this instance
		 */
		return apply_filters( 'wc_payment_gateway_' . $this->get_id() . '_get_order', $order, $this );
	}

	/**
	 * Performs a credit card transaction for the given order and returns the result.
	 *
	 * @since 3.0.0
	 *
	 * @param WC_Order_Square $order the order object
	 * @param Payment_Gateway_API_Response_Interface $response optional credit card transaction response
	 * @return Payment_Gateway_API_Response_Interface the response
	 * @throws \Exception network timeouts, etc
	 */
	protected function do_credit_card_transaction( $order, $response = null ) {
		// Generate a new transaction ref if the order payment is split using multiple payment methods.
		if ( isset( $order->payment->partial_total ) ) {
			$order->unique_transaction_ref = $this->get_order_with_unique_transaction_ref( $order );
		}

		if ( is_null( $response ) ) {
			// As per Square's docs, orders paid using multiple payment methods should always be authorised.
			// Once all payment methods are used, we use the payment IDs of all authorised transactions to charge.
			if ( $this->perform_credit_card_charge( $order ) && self::CHARGE_TYPE_PARTIAL !== $this->get_charge_type() ) {
				$response = $this->get_api()->credit_card_charge( $order );
			} else {
				$response = $this->get_api()->credit_card_authorization( $order );
			}
		}

		// success! update order record
		if ( $response->transaction_approved() ) {

			$last_four        = substr( $order->payment->account_number, -4 );
			$first_four       = substr( $order->payment->account_number, 0, 4 );
			$payment_response = $response->get_data();
			$payment          = $payment_response->getPayment();

			// use direct card type if set, or try to guess it from card number
			if ( ! empty( $order->payment->card_type ) ) {
				$card_type = $order->payment->card_type;
			} elseif ( $first_four ) {
				$card_type = Payment_Gateway_Helper::card_type_from_account_number( $first_four );
			} else {
				$card_type = 'card';
			}

			$what = Payment_Gateway_Helper::payment_type_to_name( $card_type );

			// credit card order note
			$message = sprintf(
				/* translators: Placeholders: %1$s - payment method title, %2$s - environment ("Test"), %3$s - transaction type (authorization/charge), %4$s - card type (mastercard, visa, ...), %5$s - last four digits of the card */
				esc_html__( '%1$s %2$s %3$s Approved for an amount of %4$s: %5$s ending in %6$s', 'woocommerce-square' ),
				$this->get_method_title(),
				wc_square()->get_settings_handler()->is_sandbox() ? esc_html_x( 'Test', 'noun, software environment', 'woocommerce-square' ) : '',
				'APPROVED' === $response->get_payment()->getStatus() ? esc_html_x( 'Authorization', 'credit card transaction type', 'woocommerce-square' ) : esc_html_x( 'Charge', 'noun, credit card transaction type', 'woocommerce-square' ),
				wc_price( Money_Utility::cents_to_float( $payment->getTotalMoney()->getAmount(), $order->get_currency() ) ),
				Payment_Gateway_Helper::payment_type_to_name( $card_type ),
				$last_four
			);

			// add the expiry date if it is available
			if ( ! empty( $order->payment->exp_month ) && ! empty( $order->payment->exp_year ) ) {

				$message .= ' ' . sprintf(
					/* translators: Placeholders: %s - credit card expiry date */
					__( '(expires %s)', 'woocommerce-square' ),
					esc_html( $order->payment->exp_month . '/' . substr( $order->payment->exp_year, -2 ) )
				);
			}

			// adds the transaction id (if any) to the order note
			if ( $response->get_transaction_id() ) {
				/* translators: Placeholders: %s - transaction ID */
				$message .= ' ' . sprintf( esc_html__( '(Transaction ID %s)', 'woocommerce-square' ), $response->get_transaction_id() );
			}

			/**
			 * Direct Gateway Credit Card Transaction Approved Order Note Filter.
			 *
			 * Allow actors to modify the order note added when a Credit Card transaction
			 * is approved.
			 *
			 * @since 3.0.0
			 *
			 * @param string $message order note
			 * @param \WC_Order $order order object
			 * @param Payment_Gateway_API_Response_Interface $response transaction response
			 * @param Payment_Gateway_Direct $this instance
			 */
			$message = apply_filters( 'wc_payment_gateway_' . $this->get_id() . '_credit_card_transaction_approved_order_note', $message, $order, $response, $this );

			$this->update_order_meta( $order, 'is_tender_type_card', true );

			$order->add_order_note( $message );

		}

		return $response;

	}

	/**
	 * Performs a gift card transaction for the given order and returns the result.
	 *
	 * @since 3.7.0
	 *
	 * @param WC_Order_Square $order WooCommerce order object.
	 *
	 * @return Create_Payment The payment response.
	 */
	public function do_gift_card_transaction( $order ) {
		// Generate a new transaction ref if the order payment is split using multiple payment methods.
		if ( isset( $order->payment->partial_total ) ) {
			$order->unique_transaction_ref = $this->get_order_with_unique_transaction_ref( $order );
		}

		$response = $this->get_api()->gift_card_charge( $order );

		if ( ! $response->transaction_approved() ) {
			return $response;
		}

		$payment_response = $response->get_data();

		if ( $payment_response instanceof \Square\Models\CreatePaymentResponse ) {
			$payment      = $payment_response->getPayment();
			$card_details = $payment->getCardDetails();

			$card      = $card_details->getCard();
			$exp_month = $card->getExpMonth();
			$exp_year  = $card->getExpYear();
			$last_four = $card->getLast4();

			// Gift card order note.
			$message = sprintf(
				/* translators: Placeholders: %1$s - payment method title, %2$s - environment ("Test"), %3$s - transaction type (authorization/charge), %4$s - last four digits of the card */
				esc_html__( '%1$s %2$s %3$s Approved for an amount of %4$s: %5$s ending in %6$s', 'woocommerce-square' ),
				$this->get_method_title(),
				wc_square()->get_settings_handler()->is_sandbox() ? esc_html_x( 'Test', 'noun, software environment', 'woocommerce-square' ) : '',
				'APPROVED' === $response->get_payment()->getStatus() ? esc_html_x( 'Authorization', 'credit card transaction type', 'woocommerce-square' ) : esc_html_x( 'Charge', 'noun, credit card transaction type', 'woocommerce-square' ),
				wc_price( Money_Utility::cents_to_float( $payment->getTotalMoney()->getAmount(), $order->get_currency() ) ),
				esc_html__( 'Gift Card', 'woocommerce-square' ),
				$last_four
			);

			// Add the expiry date.
			$message .= ' ' . sprintf(
				/* translators: Placeholders: %s - Gift card expiry date */
				__( '(expires %s)', 'woocommerce-square' ),
				esc_html( $exp_month . '/' . substr( $exp_year, -2 ) )
			);

			// Adds the transaction id (if any) to the order note.
			if ( $response->get_transaction_id() ) {
				/* translators: Placeholders: %s - transaction ID */
				$message .= ' ' . sprintf( esc_html__( '(Transaction ID %s)', 'woocommerce-square' ), $response->get_transaction_id() );
			}

			/**
			 * Direct Gateway Gift Card Transaction Approved Order Note Filter.
			 *
			 * Allow actors to modify the order note added when a Gift Card transaction
			 * is approved.
			 *
			 * @since 3.7.0
			 *
			 * @param string $message order note
			 * @param \WC_Order $order order object
			 * @param Payment_Gateway_API_Response_Interface $response transaction response
			 * @param Payment_Gateway_Direct $this instance
			 */
			$message = apply_filters( 'wc_payment_gateway_' . $this->get_id() . '_gift_card_transaction_approved_order_note', $message, $order, $response, $this );

			$this->update_order_meta( $order, 'is_tender_type_gift_card', true );

			$amount = $payment->getTotalMoney()->getAmount();

			Order::set_gift_card_total_charged_amount( $order, Square_Helper::number_format( Money_Utility::cents_to_float( $amount ) ) );
			Order::set_gift_card_last4( $order, $card->getLast4() );

			$order->add_order_note( $message );
		}

		return $response;
	}


	/**
	 * Create a transaction.
	 *
	 * @since 3.0.0
	 *
	 * @param \WC_Order $order the order object
	 * @return bool
	 * @throws \Exception
	 */
	protected function do_transaction( $order ) {

		// Check if a Gift card is applied.
		if ( $this->is_gift_card_applied() ) {
			$is_partial_payment = self::CHARGE_TYPE_PARTIAL === $this->get_charge_type();

			// If payment is partial, i.e.; Gift Card is used along with a Square Credit Card, then save the partial totals
			// in a `$order->payment->partial_total` property.
			if ( $is_partial_payment ) {
				$order->payment->partial_total              = new \stdClass();
				$order->payment->partial_total->gift_card   = $this->get_partial_total_on_gift_card();
				$order->payment->partial_total->credit_card = $this->get_partial_total_on_credit_card();

				// If the sum of partial totals is not equal to the order total, show an error.
				if ( ! $this->verify_order_total( $order ) ) {
					Square_Helper::wc_add_notice( esc_html__( 'The sum of partial totals does not match the order total.', 'woocommerce-square' ), 'error' );
					return false;
				};

				// Save the charge type as `PARTIAL`.
				$this->update_order_meta( $order, 'charge_type', self::CHARGE_TYPE_PARTIAL );
			}

			// Perform a Gift Card transaction.
			$gift_card_response = $this->do_gift_card_transaction( $order );

			// If the payment is partial, then perform a Square Credit Card transaction as well.
			if ( $is_partial_payment ) {
				$credit_card_response = $this->do_credit_card_transaction( $order );

				// Handle responses when partial payments are made using either Gift and Square Credit Card.
				return $this->handle_multi_payment_methods( $gift_card_response, $credit_card_response, $order );
			} else {
				// Handle response when payment is made using either Gift or Square Credit Card.
				return $this->handle_single_payment_method( $gift_card_response, $order );
			}
		} elseif ( $this->is_credit_card_gateway() ) {
			$response = $this->do_credit_card_transaction( $order );
		} else {
			$do_payment_type_transaction = 'do_' . $this->get_payment_type() . '_transaction';
			$response                    = $this->$do_payment_type_transaction( $order );
		}

		// Handle response when payment is made using a Square Credit Card.
		return $this->handle_single_payment_method( $response, $order );
	}

	/**
	 * Returns true if the order total equals to the sum of partial totals.
	 * False otherwise.
	 *
	 * @param \WC_Order $order WooCommerce order object.
	 * @return boolean
	 */
	protected function verify_order_total( $order ) {
		$order_total       = (float) $order->get_total();
		$gift_card_total   = $order->payment->partial_total->gift_card;
		$credit_card_total = $order->payment->partial_total->credit_card;

		return $order_total === $gift_card_total + $credit_card_total;
	}

	/**
	 * Handles responses when the order payment is made using a Square Gift or a Credit Card.
	 *
	 * @param Create_Payment $response       Square Gift Card or Credit Card payment response.
	 * @param \WC_Order      $order          WooCommerce Order object.
	 * @param string         $payment_method Describes whether payment was made using a Square Gift or a Credit Card.
	 */
	protected function handle_single_payment_method( $response, $order ) {
		if ( $response->transaction_approved() || $response->transaction_held() ) {
			if ( $response->is_gift_card_payment() ) {
				$this->maybe_tokenize( $response, $order );
			}

			// add the standard transaction data
			$this->add_transaction_data( $order, $response );

			// allow the concrete class to add any gateway-specific transaction data to the order
			$this->add_payment_gateway_transaction_data( $order, $response );

			// if the transaction was held (ie fraud validation failure) mark it as such
			// TODO: consider checking whether the response *was* an authorization, rather than blanket-assuming it was because of the settings.  There are times when an auth will be used rather than charge, ie when performing in-plugin AVS handling (moneris)
			if ( $response->transaction_held() || ( $this->supports( self::FEATURE_CREDIT_CARD_AUTHORIZATION ) && $this->perform_credit_card_authorization( $order ) ) ) {
				// TODO: need to make this more flexible, and not force the message to 'Authorization only transaction' for auth transactions (re moneris efraud handling)
				/* translators: This is a message describing that the transaction in question only performed a credit card authorization and did not capture any funds. */
				$this->mark_order_as_held( $order, $this->supports( self::FEATURE_CREDIT_CARD_AUTHORIZATION ) && $this->perform_credit_card_authorization( $order ) ? esc_html__( 'Authorization only transaction', 'woocommerce-square' ) : $response->get_status_message(), $response );
			}

			return true;

		} else {
			return $this->do_transaction_failed_result( $order, $response );
		}
	}

	/**
	 * Handles responses when the order total payment is split between a Gift and Square Credit Card.
	 *
	 * @param Create_Payment $gift_card_response   Gift Card payment response.
	 * @param Create_Payment $credit_card_response Credit Card payment response.
	 * @param \WC_Order      $order                WooCommerce Order object.
	 */
	protected function handle_multi_payment_methods( $gift_card_response, $credit_card_response, $order ) {
		if ( $credit_card_response->transaction_approved() || $credit_card_response->transaction_held() ) {
			$this->maybe_tokenize( $credit_card_response, $order );

			// add the standard transaction data
			$this->add_transaction_data( $order, $credit_card_response, 'credit_card', true );

			// allow the concrete class to add any gateway-specific transaction data to the order
			$this->add_payment_gateway_transaction_data( $order, $credit_card_response );
		} else {
			return $this->do_transaction_failed_result( $order, $credit_card_response );
		}

		if ( $gift_card_response->transaction_approved() || $gift_card_response->transaction_held() ) {
			// add the standard transaction data
			$this->add_transaction_data( $order, $gift_card_response, 'gift_card', true );
		} else {
			return $this->do_transaction_failed_result( $order, $gift_card_response );
		}

		// At this point, the transactions are only authorised - a requirement for partial payments.
		// We create an array of payment IDs that are authorised and later use this to charge and complete the order.
		$payment_ids = array(
			$gift_card_response->get_transaction_id(),
			$credit_card_response->get_transaction_id(),
		);

		if ( $this->supports( self::FEATURE_CREDIT_CARD_AUTHORIZATION ) && $this->perform_credit_card_authorization( $order ) ) {
			$this->mark_order_as_held( $order, $this->supports( self::FEATURE_CREDIT_CARD_AUTHORIZATION ) && $this->perform_credit_card_authorization( $order ) ? esc_html__( 'Authorization only transaction', 'woocommerce-square' ) : $response->get_status_message(), $response );
		} else {
			// Charge the authorised order which is paid using partial payments.
			$response = $this->get_api()->pay_order( $payment_ids, $order->square_order_id );

			if ( $response->get_data() instanceof \Square\Models\PayOrderResponse ) {
				$captured_amount = Square_Helper::number_format( Money_Utility::cents_to_float( $response->get_order()->getTotalMoney()->getAmount() ) );
				$square_order    = $response->get_order();
				$message         = sprintf(
					/* translators: Placeholders: %1$s - payment gateway title (such as Authorize.net, Braintree, etc), %2$s - transaction amount. Definitions: Capture, as in capture funds from a credit card. */
					esc_html__( '%1$s Capture of %2$s Approved', 'woocommerce-square' ),
					$this->get_method_title(),
					wc_price( $captured_amount, array( 'currency' => Order_Compatibility::get_prop( $order, 'currency', 'view' ) ) )
				);

				$message .= ' ' . $this->build_split_payment_order_note( $order, $square_order );
				/* translators: %s list of transaction IDs for split payments. */
				$message .= ' ' . sprintf( esc_html__( '(Transaction IDs %s)', 'woocommerce-square' ), implode( ', ', $response->get_transaction_ids() ) );
				$order->add_order_note( $message );
				$this->update_order_meta( $order, 'charge_captured', 'yes' );
				$this->update_order_meta( $order, 'charge_type', self::CHARGE_TYPE_PARTIAL );
				$this->update_order_meta( $order, 'credit_card_partial_total', $order->payment->partial_total->credit_card );
				$this->update_order_meta( $order, 'gift_card_partial_total', $order->payment->partial_total->gift_card );
				return true;
			} else {
				$this->update_order_meta( $order, 'charge_captured', 'no' );
				return false;
			}
		}

		return true;
	}

	/**
	 * Tokenizes Credit Card.
	 *
	 * @param Payment_Gateway_API_Response $response Credit Card payment response.
	 * @param \WC_Order                     $order   WooCommerce order object.
	 */
	protected function maybe_tokenize( $response, $order ) {
		if ( $this->supports_tokenization() && 0 !== $order->get_user_id() && $this->get_payment_tokens_handler()->should_tokenize() &&
			( $order->payment_total > 0 && ( $this->tokenize_with_sale() || $this->tokenize_after_sale() ) ) ) {
			try {
				$order = $this->get_payment_tokens_handler()->create_token( $order, $response );
			} catch ( \Exception $e ) {

				// handle the case of a "tokenize-after-sale" request failing by marking the order as on-hold with an explanatory note
				if ( ! $response->transaction_held() && ! ( $this->supports( self::FEATURE_CREDIT_CARD_AUTHORIZATION ) && $this->perform_credit_card_authorization( $order ) ) ) {

					// transaction has already been successful, but we've encountered an issue with the post-tokenization, add an order note to that effect and continue on
					$message = sprintf(
						/* translators: Placeholders: %s - failure message */
						esc_html__( 'Tokenization Request Failed: %s', 'woocommerce-square' ),
						$e->getMessage()
					);

					$this->mark_order_as_held( $order, $message, $response );

				} else {

					// transaction has already been successful, but we've encountered an issue with the post-tokenization, add an order note to that effect and continue on
					$message = sprintf(
						/* translators: Placeholders: %1$s - payment method title, %2$s - failure message */
						esc_html__( '%1$s Tokenization Request Failed: %2$s', 'woocommerce-square' ),
						$this->get_method_title(),
						$e->getMessage()
					);

					$order->add_order_note( $message );
				}
			}
		}
	}


	/** Add Payment Method feature ********************************************/


	/**
	 * Entry method for the Add Payment Method feature flow. Note this is *not*
	 * stubbed in the WC_Payment_Gateway abstract class, but is called if the
	 * gateway declares support for it.
	 *
	 * @since 3.0.0
	 */
	public function add_payment_method() {

		assert( $this->supports_add_payment_method() );

		$order = $this->get_order_for_add_payment_method();

		try {

			$result = $this->do_add_payment_method_transaction( $order );

		} catch ( \Exception $e ) {

			$result = array(
				/* translators: Placeholders: %s - failure message. Payment method as in a specific credit card, e-check or bank account */
				'message' => sprintf( esc_html__( 'Oops, adding your new payment method failed: %s', 'woocommerce-square' ), $e->getMessage() ),
				'success' => false,
			);
		}

		Square_Helper::wc_add_notice( $result['message'], $result['success'] ? 'success' : 'error' );

		if ( $result['success'] ) {
			$redirect_url = wc_get_account_endpoint_url( 'payment-methods' );
		} else {
			$redirect_url = wc_get_endpoint_url( 'add-payment-method' );
		}

		wp_safe_redirect( $redirect_url );
		exit();
	}


	/**
	 * Perform the transaction to add the customer's payment method to their
	 * account
	 *
	 * @since 3.0.0
	 * @return array result with success/error message and request status (success/failure)
	 * @throws \Exception
	 */
	protected function do_add_payment_method_transaction( \WC_Order $order ) {

		$response = $this->get_api()->tokenize_payment_method( $order );

		if ( $response->transaction_approved() ) {

			$token = $response->get_payment_token();

			// set the token to the user account
			$this->get_payment_tokens_handler()->add_token( $order->get_user_id(), $token );

			// order note based on gateway type
			if ( $this->is_credit_card_gateway() ) {

				/* translators: Payment method as in a specific credit card. Placeholders: %1$s - card type (visa, mastercard, ...), %2$s - last four digits of the card, %3$s - card expiry date */
				$message = sprintf(
					// translators: Placeholder: %1$s - card name, %2$s - card's last four digits, %3$s - card's expiration date.
					esc_html__( 'Nice! New payment method added: %1$s ending in %2$s (expires %3$s)', 'woocommerce-square' ),
					$token->get_type_full(),
					$token->get_last_four(),
					$token->get_exp_date()
				);

			} else {
				/* translators: Payment method as in a specific credit card, e-check or bank account */
				$message = esc_html__( 'Nice! New payment method added.', 'woocommerce-square' );
			}

			// add transaction data to user meta
			$this->add_add_payment_method_transaction_data( $response );

			// add customer data, primarily customer ID to user meta
			$this->add_add_payment_method_customer_data( $order, $response );

			/**
			 * Fires after a new payment method is added by a customer.
			 *
			 * @since 3.0.0
			 *
			 * @param string $token_id new token ID
			 * @param int $user_id user ID
			 * @param Payment_Gateway_API_Response_Interface $response API response object
			 */
			do_action( 'wc_payment_gateway_' . $this->get_id() . '_payment_method_added', $token->get_id(), $order->get_user_id(), $response );

			$result = array(
				'message' => $message,
				'success' => true,
			);

		} else {

			if ( $response->get_status_code() && $response->get_status_message() ) {
				// translators: Placeholder: %s The status code.
				$message = sprintf( esc_html__( 'Status code %1$s: %2$s', 'woocommerce-square' ), $response->get_status_code(), $response->get_status_message() );
			} elseif ( $response->get_status_code() ) {
				// translators: Placeholder: %s The status code.
				$message = sprintf( esc_html__( 'Status code: %s', 'woocommerce-square' ), $response->get_status_code() );
			} elseif ( $response->get_status_message() ) {
				// translators: Placeholder: %s The status message.
				$message = sprintf( esc_html__( 'Status message: %s', 'woocommerce-square' ), $response->get_status_message() );
			} else {
				$message = 'Unknown Error';
			}

			$result = array(
				'message' => $message,
				'success' => false,
			);
		}

		/**
		 * Add Payment Method Transaction Result Filter.
		 *
		 * Filter the result data from an add payment method transaction attempt -- this
		 * can be used to control the notice message displayed and whether the
		 * user is redirected back to the My Account page or remains on the add
		 * new payment method screen
		 *
		 * @since 3.0.0
		 * @param array $result {
		 *   @type string $message notice message to render
		 *   @type bool $success true to redirect to my account, false to stay on page
		 * }
		 * @param WooCommerce\Square\Framework\PaymentGateway\Api\Payment_Gateway_API_Create_Payment_Token_Response $response instance
		 * @param \WC_Order $order order instance
		 * @param Payment_Gateway_Direct $this direct gateway instance
		 */
		return apply_filters( 'wc_payment_gateway_' . $this->get_id() . '_add_payment_method_transaction_result', $result, $response, $order, $this );
	}


	/**
	 * Creates the order required for adding a new payment method. Note that
	 * a mock order is generated as there is no actual order associated with the
	 * request.
	 *
	 * @since 3.0.0
	 * @return WC_Order_Square generated order object
	 */
	protected function get_order_for_add_payment_method() {

		// mock order, as all gateway API implementations require an order object for tokenization
		$order = new WC_Order_Square( 0 );
		$order = $this->get_order( $order );

		$user = get_userdata( get_current_user_id() );

		$properties = array(
			'currency'    => get_woocommerce_currency(), // default to base store currency
			'customer_id' => $user->ID,
		);

		$defaults = array(
			// billing
			'billing_first_name'  => '',
			'billing_last_name'   => '',
			'billing_company'     => '',
			'billing_address_1'   => '',
			'billing_address_2'   => '',
			'billing_city'        => '',
			'billing_postcode'    => '',
			'billing_state'       => '',
			'billing_country'     => '',
			'billing_phone'       => '',
			'billing_email'       => $user->user_email,

			// shipping
			'shipping_first_name' => '',
			'shipping_last_name'  => '',
			'shipping_company'    => '',
			'shipping_address_1'  => '',
			'shipping_address_2'  => '',
			'shipping_city'       => '',
			'shipping_postcode'   => '',
			'shipping_state'      => '',
			'shipping_country'    => '',
		);

		foreach ( $defaults as $prop => $value ) {

			$value = ! empty( $user->$prop ) ? $user->$prop : $value;

			if ( ! empty( $value ) ) {
				$properties[ $prop ] = $value;
			}
		}

		$order = Order_Compatibility::set_props( $order, $properties );

		// other default info
		$order->customer_id = $this->get_customer_id( $order->get_user_id() );

		/* translators: Placeholders: %1$s - site title, %2$s - customer email. Payment method as in a specific credit card, e-check or bank account */
		$order->description = sprintf( esc_html__( '%1$s - Add Payment Method for %2$s', 'woocommerce-square' ), sanitize_text_field( Square_Helper::get_site_name() ), $properties['billing_email'] );

		// force zero amount
		$order->payment_total = '0.00';

		/**
		 * Direct Gateway Get Order for Add Payment Method Filter.
		 *
		 * Allow actors to modify the order object used for an add payment method
		 * transaction.
		 *
		 * @since 3.0.0
		 * @param WC_Order_Square $order order object
		 * @param Payment_Gateway_Direct $this instance
		 */
		return apply_filters( 'wc_payment_gateway_' . $this->get_id() . '_get_order_for_add_payment_method', $order, $this );
	}


	/**
	 * Add customer data as part of the add payment method transaction, primarily
	 * customer ID
	 *
	 * @since 3.0.0
	 * @param WC_Order_Square $order mock order
	 * @param \WooCommerce\Square\Framework\PaymentGateway\Api\Payment_Gateway_API_Create_Payment_Token_Response $response
	 */
	protected function add_add_payment_method_customer_data( $order, $response ) {
		$user_id       = $order->get_user_id();
		$response_data = $response->get_data();
		$card          = $response_data instanceof \Square\Models\CreateCardResponse ? $response_data->getCard() : null;

		// set customer ID from response if available
		if ( $this->supports_customer_id() && $card ) {
			$order->customer_id = $customer_id = $card->getCustomerId();

		} else {
			// default to the customer ID on "order"
			$customer_id = $order->customer_id;
		}

		// update the user
		if ( 0 !== $user_id ) {
			$this->update_customer_id( $user_id, $customer_id );
		}
	}


	/**
	 * Adds data from the add payment method transaction, primarily:
	 *
	 * + transaction ID
	 * + transaction date
	 * + transaction environment
	 *
	 * @since 3.0.0
	 *
	 * @param WooCommerce\Square\Framework\PaymentGateway\Api\Payment_Gateway_API_Create_Payment_Token_Response $response
	 */
	protected function add_add_payment_method_transaction_data( $response ) {

		$user_meta_key = '_wc_' . $this->get_id() . '_add_payment_method_transaction_data';

		$data = (array) get_user_meta( get_current_user_id(), $user_meta_key, true );

		$new_data = array(
			'trans_id'    => $response->get_transaction_id() ? $response->get_transaction_id() : null,
			'trans_date'  => current_time( 'mysql' ),
			'environment' => $this->get_environment(),
		);

		$data[] = array_merge( $new_data, $this->get_add_payment_method_payment_gateway_transaction_data( $response ) );

		// only keep the 5 most recent transactions
		if ( count( $data ) > 5 ) {
			array_shift( $data );
		}

		update_user_meta( get_current_user_id(), $user_meta_key, array_filter( $data ) );
	}


	/**
	 * Allow gateway implementations to add additional data to the data saved
	 * during the add payment method transaction
	 *
	 * @since 3.0.0
	 * @param WooCommerce\Square\Framework\PaymentGateway\Api\Payment_Gateway_API_Create_Payment_Token_Response $response create payment token response
	 * @return array
	 */
	protected function get_add_payment_method_payment_gateway_transaction_data( $response ) {

		// stub method
		return array();
	}


	/** Getters ******************************************************/


	/**
	 * Returns true if this is a direct type gateway
	 *
	 * @since 3.0.0
	 * @return boolean if this is a direct payment gateway
	 */
	public function is_direct_gateway() {
		return true;
	}


	/**
	 * Returns true if a transaction should be forced (meaning payment
	 * processed even if the order amount is 0).  This is useful mostly for
	 * testing situations
	 *
	 * @since 3.0.0
	 * @return boolean true if the transaction request should be forced
	 */
	public function transaction_forced() {
		return false;
	}

}
