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

namespace WooCommerce\Square\Framework\PaymentGateway\Api;

defined( 'ABSPATH' ) or exit;

/**
 * WooCommerce Payment Gateway API Response Message Helper
 *
 * This utility class is meant to provide a standard set of error messages to be
 * displayed to the customer during checkout.
 *
 * @since 3.0.0
 */
class Payment_Gateway_API_Response_Message_Helper {

	/**
	 * Returns a message appropriate for a frontend user.  This should be used
	 * to provide enough information to a user to allow them to resolve an
	 * issue on their own, but not enough to help nefarious folks fishing for
	 * info.
	 *
	 * @since 3.0.0
	 * @param string $message_id identifies the message to return
	 * @return string a user message
	 */
	public function get_user_message( $message_id ) {

		$message = null;

		switch ( $message_id ) {

			// generic messages
			case 'error':           $message = esc_html__( 'An error occurred, please try again or try an alternate form of payment', 'woocommerce-square' ); break;
			case 'decline':         $message = esc_html__( 'We cannot process your order with the payment information that you provided. Please use a different payment account or an alternate payment method.', 'woocommerce-square' ); break;
			case 'held_for_review': $message = esc_html__( 'This order is being placed on hold for review. Please contact us to complete the transaction.', 'woocommerce-square' ); break;

			/* missing/invalid info */

			// csc
			case 'held_for_incorrect_csc':    $message = esc_html__( 'This order is being placed on hold for review due to an incorrect card verification number.  You may contact the store to complete the transaction.', 'woocommerce-square' ); break;
			case 'csc_invalid':               $message = esc_html__( 'The card verification number is invalid, please try again.', 'woocommerce-square' ); break;
			case 'csc_missing':               $message = esc_html__( 'Please enter your card verification number and try again.', 'woocommerce-square' ); break;

			// card type
			case 'card_type_not_accepted':    $message = esc_html__( 'That card type is not accepted, please use an alternate card or other form of payment.', 'woocommerce-square' ); break;
			case 'card_type_invalid':         $message = esc_html__( 'The card type is invalid or does not correlate with the credit card number.  Please try again or use an alternate card or other form of payment.', 'woocommerce-square' ); break;
			case 'card_type_missing':         $message = esc_html__( 'Please select the card type and try again.', 'woocommerce-square' ); break;

			// card number
			case 'card_number_type_invalid': $message = esc_html__( 'The card type is invalid or does not correlate with the credit card number.  Please try again or use an alternate card or other form of payment.', 'woocommerce-square' ); break;
			case 'card_number_invalid':      $message = esc_html__( 'The card number is invalid, please re-enter and try again.', 'woocommerce-square' ); break;
			case 'card_number_missing':      $message = esc_html__( 'Please enter your card number and try again.', 'woocommerce-square' ); break;

			// card expiry
			case 'card_expiry_invalid':       $message = esc_html__( 'The card expiration date is invalid, please re-enter and try again.', 'woocommerce-square' ); break;
			case 'card_expiry_month_invalid': $message = esc_html__( 'The card expiration month is invalid, please re-enter and try again.', 'woocommerce-square' ); break;
			case 'card_expiry_year_invalid':  $message = esc_html__( 'The card expiration year is invalid, please re-enter and try again.', 'woocommerce-square' ); break;
			case 'card_expiry_missing':       $message = esc_html__( 'Please enter your card expiration date and try again.', 'woocommerce-square' ); break;

			// bank
			case 'bank_aba_invalid':            $message_id = esc_html__( 'The bank routing number is invalid, please re-enter and try again.', 'woocommerce-square' ); break;
			case 'bank_account_number_invalid': $message_id = esc_html__( 'The bank account number is invalid, please re-enter and try again.', 'woocommerce-square' ); break;

			/* decline reasons */
			case 'card_expired':         $message = esc_html__( 'The provided card is expired, please use an alternate card or other form of payment.', 'woocommerce-square' ); break;
			case 'card_declined':        $message = esc_html__( 'The provided card was declined, please use an alternate card or other form of payment.', 'woocommerce-square' ); break;
			case 'insufficient_funds':   $message = esc_html__( 'Insufficient funds in account, please use an alternate card or other form of payment.', 'woocommerce-square' ); break;
			case 'card_inactive':        $message = esc_html__( 'The card is inactivate or not authorized for card-not-present transactions, please use an alternate card or other form of payment.', 'woocommerce-square' ); break;
			case 'credit_limit_reached': $message = esc_html__( 'The credit limit for the card has been reached, please use an alternate card or other form of payment.', 'woocommerce-square' ); break;
			case 'csc_mismatch':         $message = esc_html__( 'The card verification number does not match. Please re-enter and try again.', 'woocommerce-square' ); break;
			case 'avs_mismatch':         $message = esc_html__( 'The provided address does not match the billing address for cardholder. Please verify the address and try again.', 'woocommerce-square' ); break;
		}

		/**
		 * Payment Gateway API Response User Message Filter.
		 *
		 * Allow actors to modify the error message returned to a user when a transaction
		 * has encountered an error and the admin has enabled the "show detailed
		 * decline messages" setting
		 *
		 * @since 3.0.0
		 * @param string $message message to show to user
		 * @param string $message_id machine code for the message, e.g. card_expired
		 * @param Payment_Gateway_API_Response_Message_Helper $this instance
		 */
		return apply_filters( 'wc_payment_gateway_transaction_response_user_message', $message, $message_id, $this );
	}
}
