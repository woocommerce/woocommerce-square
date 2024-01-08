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

namespace WooCommerce\Square\Gateway\API;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce Payment Gateway API Response Message Helper
 *
 * This utility class is meant to provide a standard set of error messages to be
 * displayed to the customer during checkout.
 *
 * Most gateways define a plethora of error conditions, some of which a customer
 * can resolve on their own, and others which must be handled by the admin/
 * merchant.  It's not always clear which conditions should be reported to a
 * customer, or what the best wording is.  This utility class seeks to ease
 * the development burden of handling customer-facing error messages by
 * defining a set of common error conditions/messages which can be used by
 * nearly any gateway.
 *
 * This class, or a subclass, should be instantiated by the API response object,
 * which will use a gateway-specific mapping of error conditions to message,
 * and returned by the `Payment_Gateway_API_Response::get_user_message()`
 * method implementation.  Add new common/generic codes and messages to this
 * base class as they are encountered during gateway integration development,
 * and use a subclass to include any gateway-specific codes/messages.
 *
 * @since 2.2.3
 */
class Response_Message_Helper {


	/**
	 * Returns a message appropriate for a frontend user.  This should be used
	 * to provide enough information to a user to allow them to resolve an
	 * issue on their own, but not enough to help nefarious folks fishing for
	 * info.
	 *
	 * @since 2.2.3
	 *
	 * @param string[] $message_ids array of string $message_id's which identify the message(s) to return
	 * @return string a user message, combining all $message_ids
	 */
	public function get_user_messages( $message_ids ) {
		$messages = array();

		foreach ( $message_ids as $message_id ) {
			$messages[] = $this->get_user_message( $message_id );
		}

		$messages = implode( '<br>', $messages );

		return trim( $messages );
	}


	/**
	 * Returns a message appropriate for a frontend user.  This should be used
	 * to provide enough information to a user to allow them to resolve an
	 * issue on their own, but not enough to help nefarious folks fishing for
	 * info.
	 *
	 * @since 2.2.3
	 * @param string $message_id identifies the message to return
	 * @return string a user message
	 */
	public function get_user_message( $message_id ) {
		$error_codes = array(
			// ErrorCodes from https://developer.squareup.com/docs/payments-api/error-codes
			'BAD_EXPIRATION'                      => esc_html__( 'The card expiration date is missing or incorrectly formatted.', 'woocommerce-square' ),
			'INVALID_ACCOUNT'                     => esc_html__( 'An error occurred, please try again or try an alternate form of payment.', 'woocommerce-square' ),
			'CARDHOLDER_INSUFFICIENT_PERMISSIONS' => esc_html__( 'The card issuer has declined the transaction due to restrictions on where the card can be used.', 'woocommerce-square' ),
			'INSUFFICIENT_PERMISSIONS'            => esc_html__( 'An error occurred, please try again or try an alternate form of payment.', 'woocommerce-square' ),
			'INSUFFICIENT_FUNDS'                  => esc_html__( 'The payment source has insufficient funds to cover the payment.', 'woocommerce-square' ),
			'INVALID_LOCATION'                    => esc_html__( 'An error occurred, please try again or try an alternate form of payment.', 'woocommerce-square' ),
			'TRANSACTION_LIMIT'                   => esc_html__( 'The card issuer has determined the payment amount is too high or too low.', 'woocommerce-square' ),
			'CARD_EXPIRED'                        => esc_html__( 'The card issuer declined the request because the card is expired.', 'woocommerce-square' ),
			'CVV_FAILURE'                         => esc_html__( 'The card issuer declined the request because the CVV value is invalid.', 'woocommerce-square' ),
			'ADDRESS_VERIFICATION_FAILURE'        => esc_html__( 'The card issuer declined the request because the postal code is invalid.', 'woocommerce-square' ),
			'TEMPORARY_ERROR'                     => esc_html__( 'A temporary internal error occurred. You can safely retry the payment.', 'woocommerce-square' ),
			'PAN_FAILURE'                         => esc_html__( 'The specified card number is invalid.', 'woocommerce-square' ),
			'EXPIRATION_FAILURE'                  => esc_html__( 'The card expiration date is invalid or indicates that the card is expired.', 'woocommerce-square' ),
			'CARD_NOT_SUPPORTED'                  => esc_html__( 'The card is not supported in the geographic region.', 'woocommerce-square' ),
			'INVALID_POSTAL_CODE'                 => esc_html__( 'The postal code is incorrectly formatted.', 'woocommerce-square' ),
			'PAYMENT_LIMIT_EXCEEDED'              => esc_html__( 'Square declined the request because the payment amount exceeded the processing limit for this seller.', 'woocommerce-square' ),
			'REFUND_DECLINED'                     => esc_html__( 'The card issuer declined the refund.', 'woocommerce-square' ),
			'GENERIC_DECLINE'                     => esc_html__( 'An error occurred, please try again or try an alternate form of payment.', 'woocommerce-square' ),

			// ErrorCodes not listed under Square Docs
			'INVALID_EXPIRATION'                  => esc_html__( 'The card expiration date is invalid or indicates that the card is expired.', 'woocommerce-square' ),

			// ErrorCodes from SV Framework - https://github.com/skyverge/wc-plugin-framework/blob/master/woocommerce/payment-gateway/api/class-sv-wc-payment-gateway-api-response-message-helper.php
			'ERROR'                               => esc_html__( 'An error occurred, please try again or try an alternate form of payment.', 'woocommerce-square' ),
			'DECLINE'                             => esc_html__( 'We cannot process your order with the payment information that you provided. Please use a different payment account or an alternate payment method.', 'woocommerce-square' ),
			'HELD_FOR_REVIEW'                     => esc_html__( 'This order is being placed on hold for review. Please contact us to complete the transaction.', 'woocommerce-square' ),
			'HELD_FOR_INCORRECT_CSC'              => esc_html__( 'This order is being placed on hold for review due to an incorrect card verification number. You may contact the store to complete the transaction.', 'woocommerce-square' ),
			'CVV_FAILURE'                         => esc_html__( 'The card verification number is invalid, please try again.', 'woocommerce-square' ),
			'CSC_MISSING'                         => esc_html__( 'Please enter your card verification number and try again.', 'woocommerce-square' ),
			'UNSUPPORTED_CARD_BRAND'              => esc_html__( 'That card type is not accepted, please use an alternate card or other form of payment.', 'woocommerce-square' ),
			'INVALID_CARD'                        => esc_html__( 'The card type is invalid or does not correlate with the credit card number. Please try again or use an alternate card or other form of payment.', 'woocommerce-square' ),
			'CARD_TYPE_MISSING'                   => esc_html__( 'Please select the card type and try again.', 'woocommerce-square' ),
			'CARD_NUMBER_TYPE_INVALID'            => esc_html__( 'The card type is invalid or does not correlate with the credit card number. Please try again or use an alternate card or other form of payment.', 'woocommerce-square' ),
			'INVALID_EXPIRATION'                  => esc_html__( 'The card expiration date is invalid, please re-enter and try again.', 'woocommerce-square' ),
			'INVALID_EXPIRATION_YEAR'             => esc_html__( 'The card expiration year is invalid, please re-enter and try again.', 'woocommerce-square' ),
			'CARD_EXPIRY_MISSING'                 => esc_html__( 'Please enter your card expiration date and try again.', 'woocommerce-square' ),
			'BANK_ABA_INVALID'                    => esc_html__( 'The bank routing number is invalid, please re-enter and try again.', 'woocommerce-square' ),
			'BANK_ACCOUNT_NUMBER_INVALID'         => esc_html__( 'The bank account number is invalid, please re-enter and try again.', 'woocommerce-square' ),
			'CARD_DECLINED'                       => esc_html__( 'The provided card was declined, please use an alternate card or other form of payment.', 'woocommerce-square' ),
			'CARD_INACTIVE'                       => esc_html__( 'The card is inactivate or not authorized for card-not-present transactions, please use an alternate card or other form of payment.', 'woocommerce-square' ),
			'TRANSACTION_LIMIT'                   => esc_html__( 'The credit limit for the card has been reached, please use an alternate card or other form of payment.', 'woocommerce-square' ),
			'VERIFY_CVV_FAILURE'                  => esc_html__( 'The card verification number does not match. Please re-enter and try again.', 'woocommerce-square' ),
			'AVS_MISMATCH'                        => esc_html__( 'The provided address does not match the billing address for cardholder. Please verify the address and try again.', 'woocommerce-square' ),
		);

		$message = null;

		if ( array_key_exists( $message_id, $error_codes ) ) {
			$message = $error_codes[ $message_id ];
		}

		/**
		 * Payment Gateway API Response User Message Filter.
		 *
		 * Allow actors to modify the error message returned to a user when a transaction
		 * has encountered an error and the admin has enabled the "show detailed
		 * decline messages" setting
		 *
		 * @since 2.2.3
		 * @param string $message message to show to user
		 * @param string $message_id machine code for the message, e.g. card_expired
		 * @param Response_Message_Helper $this instance
		 */
		return apply_filters( 'wc_payment_gateway_transaction_response_user_message', $message, $message_id, $this );
	}
}
