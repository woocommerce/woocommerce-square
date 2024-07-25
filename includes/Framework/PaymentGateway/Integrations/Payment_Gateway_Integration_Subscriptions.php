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

namespace WooCommerce\Square\Framework\PaymentGateway\Integrations;

use WooCommerce\Square\Framework\PaymentGateway\Payment_Gateway;
use WooCommerce\Square\Framework\Compatibility\Order_Compatibility;
use WooCommerce\Square\Framework\Square_Helper;
use WooCommerce\Square\Framework\PaymentGateway\PaymentTokens\Payment_Gateway_Payment_Token;
use WooCommerce\Square\WC_Order_Square;

defined( 'ABSPATH' ) || exit;

/**
 * Subscriptions Integration
 *
 * @since 3.0.0
 */
class Payment_Gateway_Integration_Subscriptions extends Payment_Gateway_Integration {


	/** @var string|float renewal payment total for Subs 2.0.x renewals */
	protected $renewal_payment_total;


	/**
	 * Bootstraps the class.
	 *
	 * @since 3.0.0
	 *
	 * @param Payment_Gateway|Payment_Gateway_Direct $gateway
	 */
	public function __construct( Payment_Gateway $gateway ) {

		parent::__construct( $gateway );

		// add hooks
		$this->add_support();
	}


	/**
	 * Adds support for subscriptions by hooking in some necessary actions
	 *
	 * @since 3.0.0
	 */
	public function add_support() {

		$this->get_gateway()->add_support(
			array(
				'subscriptions',
				'subscription_suspension',
				'subscription_cancellation',
				'subscription_reactivation',
				'subscription_amount_changes',
				'subscription_date_changes',
				'multiple_subscriptions',
				'subscription_payment_method_change_customer',
				'subscription_payment_method_change_admin',
			)
		);

		// force tokenization when needed
		add_filter( 'wc_payment_gateway_' . $this->get_gateway()->get_id() . '_tokenization_forced', array( $this, 'maybe_force_tokenization' ) );

		// save token/customer ID to subscription objects
		add_action( 'wc_payment_gateway_' . $this->get_gateway()->get_id() . '_add_transaction_data', array( $this, 'save_payment_meta' ), 10, 2 );

		// process renewal payments
		add_action( 'woocommerce_scheduled_subscription_payment_' . $this->get_gateway()->get_id(), array( $this, 'process_renewal_payment' ), 10, 2 );

		// update the customer/token ID on the subscription when updating a previously failing payment method
		add_action( 'woocommerce_subscription_failing_payment_method_updated_' . $this->get_gateway()->get_id(), array( $this, 'update_failing_payment_method' ), 10, 2 );

		// display the current payment method used for a subscription in the "My Subscriptions" table
		add_filter( 'woocommerce_my_subscriptions_payment_method', array( $this, 'maybe_render_payment_method' ), 10, 3 );

		// don't copy over order-specific meta to the WC_Subscription object during renewal processing
		add_filter( 'wc_subscriptions_renewal_order_data', array( $this, 'do_not_copy_order_meta' ) );

		// process the Change Payment "transaction"
		add_filter( 'wc_payment_gateway_' . $this->get_gateway()->get_id() . '_process_payment', array( $this, 'process_change_payment' ), 10, 3 );

		// remove order-specific meta from the Subscription object after the change payment method action
		add_filter( 'woocommerce_subscriptions_process_payment_for_change_method_via_pay_shortcode', array( $this, 'remove_order_meta_from_change_payment' ), 10, 2 );

		// don't copy over order-specific meta to the new WC_Subscription object during upgrade to 2.0.x
		add_filter( 'wcs_upgrade_subscription_meta_to_copy', array( $this, 'do_not_copy_order_meta_during_upgrade' ) );

		// allow concrete gateways to define additional order-specific meta keys to exclude
		if ( is_callable( array( $this->get_gateway(), 'subscriptions_get_excluded_order_meta_keys' ) ) ) {
			add_filter( 'wc_payment_gateway_' . $this->get_gateway()->get_id() . '_subscriptions_order_specific_meta_keys', array( $this->get_gateway(), 'subscriptions_get_excluded_order_meta_keys' ) );
		}

		/* My Payment Methods */

		add_filter( 'wc_' . $this->get_gateway()->get_plugin()->get_id() . '_my_payment_methods_table_headers', array( $this, 'add_my_payment_methods_table_header' ), 10, 2 );
		add_filter( 'wc_' . $this->get_gateway()->get_plugin()->get_id() . '_my_payment_methods_table_body_row_data', array( $this, 'add_my_payment_methods_table_body_row_data' ), 10, 3 );
		add_filter( 'wc_' . $this->get_gateway()->get_plugin()->get_id() . '_my_payment_methods_table_method_actions', array( $this, 'disable_my_payment_methods_table_method_delete' ), 10, 3 );

		/* Admin Change Payment Method support */

		// framework defaults - payment_token and customer_id
		add_filter( 'woocommerce_subscription_payment_meta', array( $this, 'admin_add_payment_meta' ), 9, 2 );
		add_action( 'woocommerce_subscription_validate_payment_meta_' . $this->get_gateway()->get_id(), array( $this, 'admin_validate_payment_meta' ), 9 );

		// allow concrete gateways to add/change defaults
		if ( is_callable( array( $this->get_gateway(), 'subscriptions_admin_add_payment_meta' ) ) ) {
			add_filter( 'woocommerce_subscription_payment_meta', array( $this->get_gateway(), 'subscriptions_admin_add_payment_meta' ), 10, 2 );
		}

		// allow concrete gateways to perform additional validation
		if ( is_callable( array( $this->get_gateway(), 'subscriptions_admin_validate_payment_meta' ) ) ) {
			add_action( 'woocommerce_subscription_validate_payment_meta_' . $this->get_gateway()->get_id(), array( $this->get_gateway(), 'subscriptions_admin_validate_payment_meta' ), 10 );
		}

		// Update customer token and id on subscription update
		add_action( 'wp_ajax_woocommerce_subscription_get_user_id_and_token', array( $this, 'provide_user_token_and_id' ) );
	}

	/**
	 * Force tokenization for subscriptions, this can be forced either during checkout
	 * or when the payment method for a subscription is being changed
	 *
	 * @since 3.0.0
	 * @see Payment_Gateway::tokenization_forced()
	 * @param bool $force_tokenization whether tokenization should be forced
	 * @return bool true if tokenization should be forced, false otherwise
	 */
	public function maybe_force_tokenization( $force_tokenization ) {

		// pay page with subscription?
		$pay_page_subscription      = false;
		$is_manual_renewal_required = class_exists( 'WCS_Manual_Renewal_Manager' ) && \WCS_Manual_Renewal_Manager::is_manual_renewal_required();

		if ( $this->get_gateway()->is_pay_page_gateway() ) {

			$order_id = $this->get_gateway()->get_checkout_pay_page_order_id();

			if ( $order_id ) {
				$pay_page_subscription = function_exists( 'wcs_order_contains_subscription' ) && wcs_order_contains_subscription( $order_id );
			}
		}

		if ( $is_manual_renewal_required ) {
			$force_tokenization = false;
		} elseif (
			( class_exists( 'WC_Subscriptions_Cart' ) && \WC_Subscriptions_Cart::cart_contains_subscription() ) ||
			( function_exists( 'wcs_cart_contains_renewal' ) && wcs_cart_contains_renewal() ) ||
			( class_exists( 'WC_Subscriptions_Change_Payment_Gateway' ) && \WC_Subscriptions_Change_Payment_Gateway::$is_request_to_change_payment ) ||
			$pay_page_subscription
		) {
			$force_tokenization = true;
		}

		return $force_tokenization;
	}


	/**
	 * Save payment meta to the Subscription object after a successful transaction,
	 * this is primarily used for the payment token and customer ID which are then
	 * copied over to a renewal order prior to payment processing.
	 *
	 * @since 3.0.0
	 * @param \WC_Order $order order
	 */
	public function save_payment_meta( $order ) {
		if ( ! function_exists( 'wcs_get_subscriptions_for_order' ) ) {
			return;
		}

		// a single order can contain multiple subscriptions
		$subscriptions = wcs_get_subscriptions_for_order(
			Order_Compatibility::get_prop( $order, 'id' ),
			array(
				'order_type' => array( 'any' ),
			)
		);

		foreach ( $subscriptions as $subscription ) {

			// payment token
			if ( ! empty( $order->payment->token ) ) {
				$subscription->update_meta_data( $this->get_gateway()->get_order_meta_prefix() . 'payment_token', $order->payment->token );
			}

			// customer ID
			if ( ! empty( $order->customer_id ) ) {
				$subscription->update_meta_data( $this->get_gateway()->get_order_meta_prefix() . 'customer_id', $order->customer_id );
			}

			$subscription->save();
		}
	}


	/**
	 * Process a subscription renewal payment
	 *
	 * @since 3.0.0
	 * @param float     $amount_to_charge subscription amount to charge, could include multiple renewals if they've previously failed and the admin has enabled it
	 * @param \WC_Order $order original order containing the subscription
	 */
	public function process_renewal_payment( $amount_to_charge, $order ) {

		// set payment total so it can override the default in get_order()
		$this->renewal_payment_total = Square_Helper::number_format( $amount_to_charge );

		$token = $this->get_gateway()->get_order_meta( Order_Compatibility::get_prop( $order, 'id' ), 'payment_token' );

		// payment token must be present and valid
		if ( empty( $token ) || ! $this->get_gateway()->get_payment_tokens_handler()->user_has_token( $order->get_user_id(), $token ) ) {

			$this->get_gateway()->mark_order_as_failed( $order, esc_html__( 'Subscription Renewal: payment token is missing/invalid.', 'woocommerce-square' ) );

			return;
		}

		// add subscriptions data to the order object prior to processing the payment
		add_filter( 'wc_payment_gateway_' . $this->get_gateway()->get_id() . '_get_order', array( $this, 'get_order' ) );

		$this->get_gateway()->process_payment( Order_Compatibility::get_prop( $order, 'id' ) );
	}


	/**
	 * Adds subscriptions data to the order object, currently:
	 *
	 * + renewal order specific description
	 * + renewal payment total
	 * + token and associated data (last four, type, etc)
	 *
	 * @since 3.0.0
	 * @see Payment_Gateway_Direct::get_order()
	 * @param \WC_Order|WC_Order_Square $order renewal order
	 * @return \WC_Order renewal order with payment token data set
	 */
	public function get_order( $order ) {

		// translators: %1$s is Site name, %2$s is order ID.
		$order->description = sprintf( esc_html__( '%1$s - Subscription Renewal Order %2$s', 'woocommerce-square' ), wp_specialchars_decode( Square_Helper::get_site_name(), ENT_QUOTES ), $order->get_order_number() );

		// override the payment total with the amount to charge given by Subscriptions
		$order->payment_total = $this->renewal_payment_total;

		// set payment token
		$order->payment->token = $this->get_gateway()->get_order_meta( Order_Compatibility::get_prop( $order, 'id' ), 'payment_token' );

		// use customer ID from renewal order, not user meta so the admin can update the customer ID for a subscription if needed
		$customer_id = $this->get_gateway()->get_order_meta( Order_Compatibility::get_prop( $order, 'id' ), 'customer_id' );

		// only if a customer ID exists in order meta, otherwise this will default to the previously set value from user meta
		if ( ! empty( $customer_id ) ) {
			$order->customer_id = $customer_id;
		}

		// get the token object
		$token = $this->get_gateway()->get_payment_tokens_handler()->get_token( $order->get_user_id(), $order->payment->token );

		// set token data on the order
		$order->payment->account_number = $token->get_last4();
		$order->payment->last_four      = $token->get_last4();

		if ( $this->get_gateway()->is_credit_card_gateway() ) {
			$order->payment->card_type = $token->get_card_type();
			$order->payment->exp_month = $token->get_expiry_month();
			$order->payment->exp_year  = $token->get_expiry_year();
		}

		return $order;
	}


	/**
	 * Don't copy order-specific meta to renewal orders from the WC_Subscription
	 * object. Generally the subscription object should not have any order-specific
	 * meta (aside from `payment_token` and `customer_id`) as they are not
	 * copied during the upgrade (see do_not_copy_order_meta_during_upgrade()), so
	 * this method is more of a fallback in case meta accidentally is copied.
	 *
	 * @since 3.0.0
	 * @param array $order_meta order meta to copy
	 * @return array
	 */
	public function do_not_copy_order_meta( $order_meta ) {

		$meta_keys = $this->get_order_specific_meta_keys();

		foreach ( $meta_keys as $meta_key ) {
			if ( isset( $order_meta[ $meta_key ] ) ) {
				unset( $order_meta[ $meta_key ] );
			}
		}

		return $order_meta;
	}


	/**
	 * Don't copy order-specific meta to the new WC_Subscription object during
	 * upgrade to 2.0.x. This only allows the `payment_token` and `customer_id`
	 * meta to be copied.
	 *
	 * @since 3.0.0
	 * @param array $order_meta order meta to copy
	 * @return array
	 */
	public function do_not_copy_order_meta_during_upgrade( $order_meta ) {

		foreach ( $this->get_order_specific_meta_keys() as $meta_key ) {

			if ( isset( $order_meta[ $meta_key ] ) ) {
				unset( $order_meta[ $meta_key ] );
			}
		}

		return $order_meta;
	}


	/**
	 * Processes a Change Payment transaction.
	 *
	 * This hooks in before standard payment processing to simply add or create
	 * token data and avoid certain failure conditions affecting the subscription
	 * object.
	 *
	 * @internal
	 *
	 * @since 3.0.0
	 *
	 * @param bool|array             $result result from any others filtering this
	 * @param int                    $order_id an order or subscription ID
	 * @param Payment_Gateway_Direct $gateway gateway object
	 * @return array $result change payment result
	 */
	public function process_change_payment( $result, $order_id, $gateway ) {

		// if this is not a subscription and not changing payment, bail for normal order processing
		if ( ! function_exists( 'wcs_is_subscription' ) || ! wcs_is_subscription( $order_id ) || ! did_action( 'woocommerce_subscription_change_payment_method_via_pay_shortcode' ) ) {
			return $result;
		}

		$subscription = $gateway->get_order( $order_id );

		try {

			// if using a saved method, just add the data
			if ( isset( $subscription->payment->token ) && $subscription->payment->token ) {

				$gateway->add_transaction_data( $subscription );

				// otherwise...tokenize
			} else {

				$subscription = $gateway->get_payment_tokens_handler()->create_token( $subscription );
			}

			$result = array(
				'result'   => 'success',
				'redirect' => $subscription->get_view_order_url(),
			);

		} catch ( \Exception $e ) {

			/* translators: Placeholders: %1$s - payment gateway title, %2$s - error message; e.g. Order Note: [Payment method] Payment Change failed [error] */
			$note = sprintf( esc_html__( '%1$s Payment Change Failed (%2$s)', 'woocommerce-square' ), $gateway->get_method_title(), $e->getMessage() );

			// add a subscription note to keep track of failures
			$subscription->add_order_note( $note );

			Square_Helper::wc_add_notice( esc_html__( 'An error occurred, please try again or try an alternate form of payment.', 'woocommerce-square' ), 'error' );

			// this isn't used by Subscriptions, but return a failure result anyway
			$result = array(
				'result'  => 'failure',
				'message' => $e->getMessage(),
			);
		}

		return $result;
	}


	/**
	 * Remove order meta (like trans ID) that's added to a Subscription object
	 * during the change payment method flow, which uses WC_Payment_Gateway::process_payment(),
	 * thus some order-specific meta is added that is undesirable to have copied
	 * over to renewal orders.
	 *
	 * @since 3.0.0
	 * @param array            $result process_payment() result, unused
	 * @param \WC_Subscription $subscription subscription object
	 * @return array
	 */
	public function remove_order_meta_from_change_payment( $result, $subscription ) {

		// remove order-specific meta
		foreach ( $this->get_order_specific_meta_keys() as $meta_key ) {
			delete_post_meta( Order_Compatibility::get_prop( $subscription, 'id' ), $meta_key );
		}

		// get a fresh subscription object after previous metadata changes
		$subscription = wcs_get_subscription( Order_Compatibility::get_prop( $subscription, 'id' ) ); // @phpstan-ignore-line

		$old_payment_method = Order_Compatibility::get_meta( $subscription, '_old_payment_method' );
		$new_payment_method = Order_Compatibility::get_prop( $subscription, 'payment_method' );

		// if the payment method has been changed to another gateway, additionally remove the old payment token and customer ID meta
		if ( $new_payment_method !== $this->get_gateway()->get_id() && $old_payment_method === $this->get_gateway()->get_id() ) {
			$this->get_gateway()->delete_order_meta( $subscription, 'payment_token' );
			$this->get_gateway()->delete_order_meta( $subscription, 'customer_id' );
		}

		return $result;
	}


	/**
	 * Update the payment token and optional customer ID for a subscription after a customer
	 * uses this gateway to successfully complete the payment for an automatic
	 * renewal payment which had previously failed.
	 *
	 * @since 3.0.0
	 * @param \WC_Subscription $subscription subscription being updated
	 * @param \WC_Order        $renewal_order order which recorded the successful payment (to make up for the failed automatic payment).
	 */
	public function update_failing_payment_method( $subscription, $renewal_order ) {

		// if the order doesn't have a transaction date stored, bail
		// this prevents updating the subscription with a failing token in case the merchant is switching the order status manually without new payment
		if ( ! $this->get_gateway()->get_order_meta( Order_Compatibility::get_prop( $renewal_order, 'id' ), 'trans_date' ) ) {
			return;
		}

		$customer_id = $this->get_gateway()->get_order_meta( Order_Compatibility::get_prop( $renewal_order, 'id' ), 'customer_id' );

		if ( $customer_id ) {
			$this->get_gateway()->update_order_meta( $subscription, 'customer_id', $customer_id );
		}

		$this->get_gateway()->update_order_meta( $subscription, 'payment_token', $this->get_gateway()->get_order_meta( Order_Compatibility::get_prop( $renewal_order, 'id' ), 'payment_token' ) );
	}


	/**
	 * Get the order-specific meta keys that should not be copied to the WC_Subscription
	 * object during upgrade to 2.0.x or during change payment method actions
	 *
	 * @since 3.0.0
	 * @return array
	 */
	protected function get_order_specific_meta_keys() {

		$keys = array(
			'trans_id',
			'trans_date',
			'account_four',
			'card_expiry_date',
			'card_type',
			'authorization_code',
			'auth_can_be_captured',
			'charge_captured',
			'capture_trans_id',
			'account_type',
			'check_number',
			'environment',
			'retry_count',
		);

		foreach ( $keys as $index => $key ) {

			$keys[ $index ] = $this->get_gateway()->get_order_meta_prefix() . $key;
		}

		/**
		 * Filter Subscriptions order-specific meta keys
		 *
		 * Use this to include additional meta keys that should not be copied over
		 * to the WC_Subscriptions object during renewal payments, the
		 * change payment method action, or the upgrade to 2.0.x.
		 *
		 * @since 3.0.0
		 * @param array $keys meta keys, with gateway order meta prefix included
		 * @param \Payment_Gateway_Integration_Subscriptions $this subscriptions integration class instance
		 */
		return apply_filters( 'wc_payment_gateway_' . $this->get_gateway()->get_id() . '_subscriptions_order_specific_meta_keys', $keys, $this );
	}


	/**
	 * Render the payment method used for a subscription in the "My Subscriptions" table
	 *
	 * @since 3.0.0
	 * @param string           $payment_method_to_display the default payment method text to display
	 * @param \WC_Subscription $subscription
	 * @return string the subscription payment method
	 */
	public function maybe_render_payment_method( $payment_method_to_display, $subscription ) {

		// bail for other payment methods
		if ( $this->get_gateway()->get_id() !== Order_Compatibility::get_prop( $subscription, 'payment_method' ) ) {
			return $payment_method_to_display;
		}

		$token = $this->get_gateway()->get_payment_tokens_handler()->get_token( $subscription->get_user_id(), $this->get_gateway()->get_order_meta( Order_Compatibility::get_prop( $subscription, 'id' ), 'payment_token' ) );

		if ( $token instanceof Payment_Gateway_Payment_Token ) {
			// translators: %1$s card type, %2$s is last 4 digits.
			$payment_method_to_display = sprintf( esc_html__( 'Via %1$s ending in %2$s', 'woocommerce-square' ), $token->get_type_full(), $token->get_last_four() );
		}

		return $payment_method_to_display;
	}


	/**
	 * Add a subscriptions header to the My Payment Methods table.
	 *
	 * @since 3.0.0
	 * @param array                               $headers the table headers
	 * @param \Payment_Gateway_My_Payment_Methods $handler the my payment methods instance
	 * @return array
	 */
	public function add_my_payment_methods_table_header( $headers, $handler ) {

		if ( isset( $headers['subscriptions'] ) ) {
			return $headers;
		}

		$new_headers = array();

		foreach ( $headers as $id => $label ) {

			// Add the header before the actions
			if ( 'actions' === $id ) {
				$new_headers['subscriptions'] = esc_html__( 'Subscriptions', 'woocommerce-square' );
			}

			$new_headers[ $id ] = $label;
		}

		return $new_headers;
	}


	/**
	 * Add a subscriptions header to the My Payment Methods table.
	 *
	 * @since 3.0.0
	 * @param array                               $method the table row data
	 * @param \Payment_Gateway_Payment_Token      $token the payment token
	 * @param \Payment_Gateway_My_Payment_Methods $handler the my payment methods instance
	 * @return array
	 */
	public function add_my_payment_methods_table_body_row_data( $method, $token, $handler ) {

		// If the subscription data has already been added or this method is for a different gateway, bail
		if ( isset( $method['subscriptions'] ) || str_replace( '_', '-', $token->get_type() ) !== $this->get_gateway()->get_payment_type() ) {
			return $method;
		}

		$subscription_ids = array();

		// Build a link for each subscription
		foreach ( $this->get_payment_token_subscriptions( get_current_user_id(), $token ) as $subscription ) {
			// translators: %1$s subscription order URL, %2$s is order ID.
			$subscription_ids[] = sprintf( '<a href="%1$s">%2$s</a>', esc_url( $subscription->get_view_order_url() ), esc_attr( sprintf( _x( '#%s', 'hash before order number', 'woocommerce-square' ), $subscription->get_order_number() ) ) );
		}

		if ( ! empty( $subscription_ids ) ) {
			$method['subscriptions'] = implode( ', ', $subscription_ids );
		}

		return $method;
	}


	/**
	 * Disables the "Delete" My Payment Methods method action button if there is an associated subscription.
	 *
	 * @since 3.0.0
	 *
	 * @param array                              $actions the token actions
	 * @param Payment_Gateway_Payment_Token      $token the token object
	 * @param Payment_Gateway_My_Payment_Methods $handler the my payment methods instance
	 * @return array
	 */
	public function disable_my_payment_methods_table_method_delete( $actions, $token, $handler ) {

		$disable_delete = false;

		$subscriptions = $this->get_payment_token_subscriptions( get_current_user_id(), $token );

		// Check each subscription for the ability to change the payment method
		foreach ( $subscriptions as $subscription ) {

			if ( $subscription->can_be_updated_to( 'new-payment-method' ) ) {
				$disable_delete = true;
				break;
			}
		}

		// if at least one can be changed, no deleting for you!
		if ( isset( $actions['delete'] ) && $disable_delete ) {
			$actions['delete']['class'] = array_merge( (array) $actions['delete']['class'], array( 'disabled' ) );
			$actions['delete']['tip']   = esc_html__( 'This payment method is tied to a subscription and cannot be deleted. Please switch the subscription to another method first.', 'woocommerce-square' );
		}

		return $actions;
	}


	/**
	 * Gets the subscriptions tied to a user payment token.
	 *
	 * @since 3.0.0
	 *
	 * @param int                           $user_id the user
	 * @param Payment_Gateway_Payment_Token $token the token object
	 * @return array the subscriptions or an empty array
	 */
	protected function get_payment_token_subscriptions( $user_id, $token ) {

		if ( ! function_exists( 'wcs_get_users_subscriptions' ) ) {
			return array();
		}

		$subscriptions = wcs_get_users_subscriptions( $user_id );

		foreach ( $subscriptions as $key => $subscription ) {

			$payment_method  = Order_Compatibility::get_prop( $subscription, 'payment_method' );
			$stored_token_id = (string) $this->get_gateway()->get_order_meta( Order_Compatibility::get_prop( $subscription, 'id' ), 'payment_token' );

			if ( $stored_token_id !== (string) $token->get_id() || $payment_method !== $this->get_gateway()->get_id() ) {
				unset( $subscriptions[ $key ] );
			}
		}

		return $subscriptions;
	}


	/**
	 * Include the payment meta data required to process automatic recurring
	 * payments so that store managers can manually set up automatic recurring
	 * payments for a customer via the Edit Subscriptions screen in 2.0.x
	 *
	 * @since 3.0.0
	 * @param array            $meta associative array of meta data required for automatic payments
	 * @param \WC_Subscription $subscription subscription object
	 * @return array
	 */
	public function admin_add_payment_meta( $meta, $subscription ) {

		$prefix = $this->get_gateway()->get_order_meta_prefix();

		$meta[ $this->get_gateway()->get_id() ] = array(
			'post_meta' => array(
				$prefix . 'payment_token' => array(
					'value' => $this->get_gateway()->get_order_meta( Order_Compatibility::get_prop( $subscription, 'id' ), 'payment_token' ),
					'label' => esc_html__( 'Payment Token', 'woocommerce-square' ),
				),
				$prefix . 'customer_id'   => array(
					'value' => $this->get_gateway()->get_order_meta( Order_Compatibility::get_prop( $subscription, 'id' ), 'customer_id' ),
					'label' => esc_html__( 'Customer ID', 'woocommerce-square' ),
				),
			),
		);

		return $meta;
	}


	/**
	 * Validate the payment meta data required to process automatic recurring
	 * payments so that store managers can manually set up automatic recurring
	 * payments for a customer via the Edit Subscriptions screen in 2.0.x
	 *
	 * @since 3.0.0
	 *
	 * @param array $meta associative array of meta data required for automatic payments
	 * @throws \Exception if payment token or customer ID is missing or blank
	 */
	public function admin_validate_payment_meta( $meta ) {

		$prefix = $this->get_gateway()->get_order_meta_prefix();

		// payment token
		if ( empty( $meta['post_meta'][ $prefix . 'payment_token' ]['value'] ) ) {
			// translators: %1$s payment token label.
			throw new \Exception( sprintf( esc_html__( '%s is required.', 'woocommerce-square' ), esc_html( $meta['post_meta'][ $prefix . 'payment_token' ]['label'] ) ) );
		}

		// customer ID - optional for some gateways so check if it's set first
		if ( isset( $meta['post_meta'][ $prefix . 'customer_id' ] ) && empty( $meta['post_meta'][ $prefix . 'customer_id' ]['value'] ) ) {
			// translators: %1$s customer label.
			throw new \Exception( sprintf( esc_html__( '%s is required.', 'woocommerce-square' ), esc_html( $meta['post_meta'][ $prefix . 'customer_id' ]['label'] ) ) );
		}
	}

	/**
	 * Provides customer ID and token of user through ajax call.
	 */
	public function provide_user_token_and_id() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! check_ajax_referer( 'wc_payment_gateway_admin_get_customer_token', 'security', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid permissions', 'woocommerce-square' ) ) );
		}

		// Existing Subscription data
		$subscription_user_id = (int) sanitize_text_field( wp_unslash( ! empty( $_POST['user_id'] ) ? $_POST['user_id'] : 0 ) );
		if ( ! $subscription_user_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid user ID provided', 'woocommerce-square' ) ) );
		}

		// Newly User Data
		$user_token         = get_user_meta( $subscription_user_id, '_wc_square_credit_card_payment_tokens', true );
		$square_customer_id = get_user_meta( $subscription_user_id, 'wc_square_customer_id', true );
		if ( empty( $user_token ) || ! is_array( $user_token ) || empty( $square_customer_id ) ) {
			wp_send_json_error( array( 'message' => __( 'No card added through Square', 'woocommerce-square' ) ) );
		}

		// Set a token to start, in case we're unable to find a declared default token.
		$subscription_token = array_keys( $user_token )[0];
		foreach ( $user_token as $token => $config ) {
			if ( ! empty( $config['default'] ) && $config['default'] ) {
				$subscription_token = $token;
				break;
			}
		}

		wp_send_json_success(
			array(
				'customer_id' => $square_customer_id,
				'token'       => $subscription_token,
			)
		);
	}
}
