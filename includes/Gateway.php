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

namespace WooCommerce\Square;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Square\Gateway\Card_Handler;
use WooCommerce\Square\Gateway\Customer_Helper;
use WooCommerce\Square\Gateway\Payment_Form;
use WooCommerce\Square\Handlers\Product;
use WooCommerce\Square\Utilities\Money_Utility;
use WooCommerce\Square\Gateway\Digital_Wallet;
use WooCommerce\Square\Framework\PaymentGateway\Payment_Gateway_Direct;
use WooCommerce\Square\Framework\PaymentGateway\Payment_Gateway_Helper;
use WooCommerce\Square\Framework\PaymentGateway\Payment_Gateway;
use WooCommerce\Square\Framework\Square_Helper;
use WooCommerce\Square\Gateway\API\Responses\Create_Payment;
use WooCommerce\Square\Gateway\Gift_Card;

/**
 * The Square payment gateway class.
 *
 * @since 2.0.0
 *
 * @method Plugin get_plugin()
 */
class Gateway extends Payment_Gateway_Direct {


	/** @var Gateway\API API base instance */
	private $api;

	/**
	 * As per documentation, as of now, SCA is enabled only for UK merchants, but to be implemented for Europe.
	 * As other currencies get supported, add them here.
	 *
	 * @since 2.2.0
	 *
	 * @var array $sca_supported_currencies Currencies for which SCA(3DS) is supported
	 */
	private $sca_supported_currencies = array( 'GBP', 'EUR' );

	/**
	 * Square Payment Form instance
	 * Null by default.
	 *
	 * @since 2.2.3
	 *
	 */
	private $payment_form = null;

	/**
	 * Holds the instance of the Digital_Wallet class.
	 * @since 4.2.0
	 *
	 * @var null|Digital_Wallet
	 */
	private $digital_wallet = null;

	/**
	 * Constructs the class.
	 *
	 * @since 2.0.0
	 */
	public function __construct() {

		parent::__construct(
			Plugin::GATEWAY_ID,
			wc_square(),
			array(
				'method_title'       => __( 'Square', 'woocommerce-square' ),
				'method_description' => __( 'Allow customers to use Square to securely pay with their credit cards', 'woocommerce-square' ),
				'payment_type'       => self::PAYMENT_TYPE_CREDIT_CARD,
				'supports'           => array(
					self::FEATURE_PRODUCTS,
					self::FEATURE_CARD_TYPES,
					self::FEATURE_PAYMENT_FORM,
					self::FEATURE_CREDIT_CARD_AUTHORIZATION,
					self::FEATURE_CREDIT_CARD_CHARGE,
					self::FEATURE_CREDIT_CARD_CHARGE_VIRTUAL,
					self::FEATURE_CREDIT_CARD_CAPTURE,
					self::FEATURE_REFUNDS,
					self::FEATURE_VOIDS,
					self::FEATURE_CUSTOMER_ID,
					self::FEATURE_TOKENIZATION,
					self::FEATURE_ADD_PAYMENT_METHOD,
					self::FEATURE_TOKEN_EDITOR,
				),
			)
		);

		$this->view_transaction_url = 'https://squareup.com/dashboard/sales/transactions/%s';

		// log accept.js requests and responses
		add_action( 'wp_ajax_wc_' . $this->get_id() . '_log_js_data', array( $this, 'log_js_data' ) );
		add_action( 'wp_ajax_nopriv_wc_' . $this->get_id() . '_log_js_data', array( $this, 'log_js_data' ) );

		add_action( 'wp_ajax_wc_' . $this->get_id() . '_get_token_by_id', array( $this, 'get_token_by_id' ) );
		add_action( 'wp_ajax_nopriv_wc_' . $this->get_id() . '_get_token_by_id', array( $this, 'get_token_by_id' ) );

		// store the Square item variation ID to order items
		add_action( 'woocommerce_new_order_item', array( $this, 'store_new_order_item_square_meta' ), 10, 3 );

		// restore refunded Square inventory
		add_action( 'woocommerce_order_refunded', array( $this, 'restore_refunded_inventory' ), 10, 2 );

		// AJAX Checkout validation handler.
		add_action( 'wc_ajax_' . $this->get_id() . '_checkout_handler', array( $this, 'wc_ajax_square_checkout_handler' ) );

		// Filter gateways depending on cart contents.
		add_action( 'woocommerce_available_payment_gateways', array( $this, 'filter_available_gateways' ) );

		// Display error messages depending on cart contents.
		add_action( 'woocommerce_no_available_payment_methods_message', array( $this, 'filter_no_payment_gatways_message' ) );

		// AJAX handler for get order amount
		add_action( 'wp_ajax_wc_' . $this->get_id() . '_get_order_amount', array( $this, 'get_order_amount' ) );
		add_action( 'wp_ajax_nopriv_wc_' . $this->get_id() . '_get_order_amount', array( $this, 'get_order_amount' ) );

		// Init Square digital wallets.
		$this->digital_wallet = new Digital_Wallet( $this );
	}

	/**
	 * Ajax callback to return payment token by token ID.
	 *
	 * @since 4.2.0
	 */
	public function get_token_by_id() {
		$nonce = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : false;

		if ( ! wp_verify_nonce( $nonce, 'payment_token_nonce' ) ) {
			wp_send_json_error( esc_html__( 'Nonce verification failed.', 'woocommerce-square' ) );
		}

		$token_id = isset( $_GET['token_id'] ) ? absint( wp_unslash( $_GET['token_id'] ) ) : false;

		if ( ! $token_id ) {
			wp_send_json_error( esc_html__( 'Token ID missing.', 'woocommerce-square' ) );
		}

		$token_obj = \WC_Payment_Tokens::get( $token_id );

		if ( is_null( $token_obj ) ) {
			wp_send_json_error( esc_html__( 'No payment token exists for this ID.', 'woocommerce-square' ) );
		}

		wp_send_json_success( $token_obj->get_token() );
	}


	/**
	 * Logs any data sent by the payment form JS via AJAX.
	 *
	 * @since 2.0.0
	 */
	public function log_js_data() {

		check_ajax_referer( 'wc_' . $this->get_id() . '_log_js_data', 'security' );

		$message = sprintf( "Square.js %1\$s:\n ", ! empty( $_REQUEST['type'] ) ? ucfirst( wc_clean( wp_unslash( $_REQUEST['type'] ) ) ) : 'Request' );

		// add the data
		if ( ! empty( $_REQUEST['data'] ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			$message .= print_r( wc_clean( wp_unslash( $_REQUEST['data'] ) ), true );
		}

		$this->get_plugin()->log( $message, $this->get_id() );
	}


	/**
	 * Stores the Square item variation ID to order items when added to orders.
	 *
	 * @internal
	 *
	 * @since 2.0.0
	 *
	 * @param int $item_id order item ID
	 * @param \WC_Order_Item $item order item object
	 * @param int $order_id order ID
	 */
	public function store_new_order_item_square_meta( $item_id, $item, $order_id ) {

		if ( ! $item instanceof \WC_Order_Item_Product ) {
			return;
		}

		$product = $item->get_product();

		if ( ! $product instanceof \WC_Product ) {
			return;
		}

		if ( ! Product::is_synced_with_square( $product ) ) {
			return;
		}

		if ( $square_id = $product->get_meta( Product::SQUARE_VARIATION_ID_META_KEY ) ) {
			$item->update_meta_data( Product::SQUARE_VARIATION_ID_META_KEY, $square_id );
		}

		$item->save_meta_data();
	}

	/**
	 * Overrides enqueue of the gateway-specific assets if present, including JS, CSS, and
	 * localized script params
	 *
	 * @since 2.1.7
	 */
	protected function enqueue_payment_form_assets() {
		// bail if *not* on add payment method page or checkout page.
		if ( ! ( is_add_payment_method_page() || is_checkout() ) ) {
			return;
		}

		parent::enqueue_payment_form_assets();
	}

	/**
	 * Enqueues the gateway JS.
	 *
	 * @since 2.0.0
	 */
	protected function enqueue_gateway_assets() {

		$is_payment_form_or_checkout = is_add_payment_method_page() || is_checkout() || ( function_exists( 'has_block' ) && has_block( 'woocommerce/checkout' ) );
		$is_digital_wallet_page      = 'yes' === $this->get_option( 'enable_digital_wallets', 'yes' ) && ( is_product() || is_cart() );

		// bail if *not* on add payment method page or checkout page or not on the product or cart when digital wallets are enabled
		if ( ! ( $is_payment_form_or_checkout || $is_digital_wallet_page ) ) {
			return;
		}

		if ( $this->get_plugin()->get_settings_handler()->is_sandbox() ) {
			$url = 'https://sandbox.web.squarecdn.com/v1/square.js';
		} else {
			$url = 'https://web.squarecdn.com/v1/square.js';
		}

		wp_enqueue_script( 'wc-' . $this->get_plugin()->get_id_dasherized() . '-payment-form', $url, array(), Plugin::VERSION );

		parent::enqueue_gateway_assets();

		// Render PaymentForm JS
		$this->get_payment_form_instance()->render_js();
	}


	/**
	 * Validates the entered payment fields.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function validate_fields() {

		$is_valid = true;

		if ( $this->is_gift_card_applied() ) {
			return $is_valid;
		}

		try {

			if ( '' === Square_Helper::get_post( 'wc-' . $this->get_id_dasherized() . '-buyer-verification-token' ) ) {
				throw new \Exception( '3D Secure Verification Token is missing' );
			}

			if ( Square_Helper::get_post( 'wc-' . $this->get_id_dasherized() . '-payment-token' ) ) {
				return $is_valid;
			}

			if ( ! Square_Helper::get_post( 'wc-' . $this->get_id_dasherized() . '-payment-nonce' ) ) {
				throw new \Exception( 'Payment nonce is missing' );
			}
		} catch ( \Exception $exception ) {

			$is_valid = false;

			Square_Helper::wc_add_notice( __( 'An error occurred, please try again or try an alternate form of payment.', 'woocommerce-square' ), 'error' );

			$this->add_debug_message( $exception->getMessage(), 'error' );
		}

		return $is_valid;
	}


	/**
	 * Gets the order object with payment information added.
	 *
	 * @since 2.0.0
	 *
	 * @param int|\WC_Order $order_id order ID or object
	 * @return \WC_Order
	 */
	public function get_order( $order_id ) {

		$order = parent::get_order( $order_id );

		$order->payment->verification_token = Square_Helper::get_post( 'wc-' . $this->get_id_dasherized() . '-buyer-verification-token' );

		$order->payment->nonce = new \stdClass();

		if ( empty( $order->payment->token ) ) {

			$order->payment->nonce->credit_card = Square_Helper::get_post( 'wc-' . $this->get_id_dasherized() . '-payment-nonce' );

			$order->payment->card_type      = Payment_Gateway_Helper::normalize_card_type( Square_Helper::get_post( 'wc-' . $this->get_id_dasherized() . '-card-type' ) );
			$order->payment->account_number = $order->payment->last_four = substr( Square_Helper::get_post( 'wc-' . $this->get_id_dasherized() . '-last-four' ), -4 );
			$order->payment->exp_month      = Square_Helper::get_post( 'wc-' . $this->get_id_dasherized() . '-exp-month' );
			$order->payment->exp_year       = Square_Helper::get_post( 'wc-' . $this->get_id_dasherized() . '-exp-year' );
			$order->payment->postcode       = Square_Helper::get_post( 'wc-' . $this->get_id_dasherized() . '-payment-postcode' );
		}

		if ( $this->is_gift_card_applied() ) {
			$order->payment->nonce->gift_card = Square_Helper::get_post( 'square-gift-card-payment-nonce' );
		}

		$order->square_customer_id = $order->customer_id;
		$order->square_order_id    = $this->get_order_meta( $order, 'square_order_id' );
		$order->square_version     = $this->get_order_meta( $order, 'square_version' );

		// look up in the index for guest customers
		if ( ! $order->get_user_id() ) {

			$indexed_customers = Customer_Helper::get_customers_by_email( $order->get_billing_email() );

			// only use an indexed customer ID if there was a single one returned, otherwise we can't know which to use
			if ( ! empty( $indexed_customers ) && count( $indexed_customers ) === 1 ) {
				$order->square_customer_id = $order->customer_id = $indexed_customers[0];
			}
		}

		// if no previous customer could be found, always create a new customer
		if ( empty( $order->square_customer_id ) ) {

			try {

				$response = $this->get_api()->create_customer( $order );

				$order->square_customer_id = $order->customer_id = $response->get_customer_id(); // set $customer_id since we know this customer can be associated with this user

				// store the guests customers in our index to avoid future duplicates
				if ( ! $order->get_user_id() ) {
					Customer_Helper::add_customer( $order->square_customer_id, $order->get_billing_email() );
				}
			} catch ( \Exception $exception ) {

				// log the error, but continue with payment
				if ( $this->debug_log() ) {
					$this->get_plugin()->log( $exception->getMessage(), $this->get_id() );
				}
			}
		}

		return $order;
	}


	/**
	 * Do the transaction.
	 *
	 * @since 2.0.0
	 *
	 * @param WC_Order_Square $order
	 * @return bool
	 * @throws \Exception
	 */
	protected function do_transaction( $order ) {

		// if there is no associated Square order ID, create one
		if ( empty( $order->square_order_id ) ) {

			try {

				$location_id = $this->get_plugin()->get_settings_handler()->get_location_id();
				$response    = $this->get_api()->create_order( $location_id, $order );

				$this->maybe_save_gift_card_order_details( $response, $order );

				$order->square_order_id = $response->getId();

				// adjust order by difference between WooCommerce and Square order totals
				$wc_total     = Money_Utility::amount_to_cents( $order->get_total() );
				$square_total = $response->getTotalMoney()->getAmount();
				$delta_total  = $wc_total - $square_total;

				if ( abs( $delta_total ) > 0 ) {
					$response = $this->get_api()->adjust_order( $location_id, $order, $response->getVersion(), $delta_total );

					// since a downward adjustment causes (downward) tax recomputation, perform an additional (untaxed) upward adjustment if necessary
					$square_total = $response->getTotalMoney()->getAmount();
					$delta_total  = $wc_total - $square_total;

					if ( $delta_total > 0 ) {
						$response = $this->get_api()->adjust_order( $location_id, $order, $response->getVersion(), $delta_total );
					}
				}

				// reset the payment total to the total calculated by Square to prevent errors
				$order->payment_total = Square_Helper::number_format( Money_Utility::cents_to_float( $response->getTotalMoney()->getAmount() ) );

			} catch ( \Exception $exception ) {

				// log the error, but continue with payment
				if ( $this->debug_log() ) {
					$this->get_plugin()->log( $exception->getMessage(), $this->get_id() );
				}
			}
		}

		return parent::do_transaction( $order );
	}

	/**
	 * Performs a credit card transaction for the given order and returns the result.
	 *
	 * @since 4.6.0
	 *
	 * @param WC_Order_Square     $order the order object
	 * @param Create_Payment|null $response optional credit card transaction response
	 * @return Create_Payment     the response
	 * @throws \Exception network timeouts, etc
	 */
	protected function do_payment_method_transaction( $order, $response = null ) {
		return $this->do_credit_card_transaction( $order, $response );
	}

	/**
	 * Adds transaction data to the order.
	 *
	 * @since 2.0.0
	 *
	 * @param \WC_Order $order order object
	 * @param \WooCommerce\Square\Gateway\API\Responses\Charge $response API response object
	 */
	public function add_payment_gateway_transaction_data( $order, $response ) {

		$location_id = $response->get_location_id() ? $response->get_location_id() : $this->get_plugin()->get_settings_handler()->get_location_id();

		if ( $location_id ) {
			$this->update_order_meta( $order, 'square_location_id', $location_id );
		}

		if ( $response->get_square_order_id() ) {
			$this->update_order_meta( $order, 'square_order_id', $response->get_square_order_id() );
		}

		// store the plugin version on the order
		$this->update_order_meta( $order, 'square_version', Plugin::VERSION );
	}


	/**
	 * Gets an order with capture data attached.
	 *
	 * @since 2.0.0
	 *
	 * @param int|\WC_Order $order order object
	 * @param null|float $amount amount to capture
	 * @return \WC_Order
	 */
	public function get_order_for_capture( $order, $amount = null ) {

		$order = parent::get_order_for_capture( $order, $amount );

		$order->capture->location_id = $this->get_order_meta( $order, 'square_location_id' );
		$order->square_version       = $this->get_order_meta( $order, 'square_version' );

		return $order;
	}


	/**
	 * Gets an order with refund data attached.
	 *
	 * @since 2.0.0
	 *
	 * @param int|\WC_Order $order order object
	 * @param float $amount amount to refund
	 * @param string $reason response for the refund
	 *
	 * @return \WC_Order|\WP_Error
	 */
	protected function get_order_for_refund( $order, $amount, $reason ) {

		$order                 = parent::get_order_for_refund( $order, $amount, $reason );
		$order->square_version = $this->get_order_meta( $order, 'square_version' );

		if ( $transaction_date = $this->get_order_meta( $order, 'trans_date' ) ) {
			// refunds with the Refunds API can be made up to 1 year after payment and up to 120 days with the Transactions API
			$max_refund_time = version_compare( $order->square_version, '2.2', '>=' ) ? '+1 year' : '+120 days';

			// throw an error if the payment cannot be refunded
			if ( current_time( 'timestamp' ) >= strtotime( $max_refund_time, strtotime( $transaction_date ) ) ) {
				/* translators: %s maximum refund date. */
				return new \WP_Error( 'wc_square_refund_age_exceeded', sprintf( __( 'Refunds must be made within %s of the original payment date.', 'woocommerce-square' ), '+1 year' === $max_refund_time ? 'a year' : '120 days' ) );
			}
		}

		$order->refund->location_id = $this->get_order_meta( $order, 'square_location_id' );
		$order->refund->tender_id   = $this->get_order_meta( $order, 'authorization_code' );

		if ( ! $order->refund->tender_id ) {

			try {
				$response = version_compare( $order->square_version, '2.2', '>=' ) ? $this->get_api()->get_payment( $order->refund->trans_id ) : $this->get_api()->get_transaction( $order->refund->trans_id, $order->refund->location_id );

				if ( ! $response->get_authorization_code() ) {
					throw new \Exception( 'Tender missing' );
				}

				$this->update_order_meta( $order, 'authorization_code', $response->get_authorization_code() );
				$this->update_order_meta( $order, 'square_location_id', $response->get_location_id() );

				$order->refund->location_id = $response->get_location_id();
				$order->refund->tender_id   = $response->get_authorization_code();

			} catch ( \Exception $exception ) {

				return new \WP_Error( 'wc_square_refund_tender_missing', __( 'Could not find original transaction tender. Please refund this transaction from your Square dashboard.', 'woocommerce-square' ) );
			}
		}

		return $order;
	}

	/**
	 * Gets a mock order for adding a new payment method.
	 *
	 * @since 2.0.0
	 *
	 * @return \WC_Order
	 */
	protected function get_order_for_add_payment_method() {

		$order = parent::get_order_for_add_payment_method();

		// if the customer doesn't have a postcode yet, use the value returned by Square JS
		if ( ! $order->get_billing_postcode() && $postcode = Square_Helper::get_post( 'wc-square-credit-card-payment-postcode' ) ) {
			$order->set_billing_postcode( $postcode );
		}

		return $order;
	}


	/**
	 * Builds the payment tokens handler instance.
	 *
	 * @since 2.0.0
	 *
	 * @return Card_Handler
	 */
	public function build_payment_tokens_handler() {

		return new Card_Handler( $this );
	}

	/**
	 * Returns the gift card object.
	 *
	 * @since 4.2.0
	 *
	 * @return Digital_Wallet;
	 */
	public function get_digital_wallet_handler() {
		return $this->digital_wallet;
	}


	/** Admin methods *************************************************************************************************/


	/**
	 * Initialize payment gateway settings fields
	 *
	 * @since 2.3.0
	 * @see WC_Settings_API::init_form_fields()
	 */
	public function init_form_fields() {
		$this->form_fields = array();
	}

	/**
	 * Adds the tokenization form fields to the gateway settings.
	 *
	 * Overridden to change the setting name to "Customer Profiles."
	 *
	 * @since 2.0.0
	 *
	 * @param array $form_fields existing fields
	 * @return array
	 */
	protected function add_tokenization_form_fields( $form_fields ) {

		$form_fields = parent::add_tokenization_form_fields( $form_fields );

		if ( ! empty( $form_fields['tokenization'] ) ) {
			$form_fields['tokenization']['title'] = __( 'Customer Profiles', 'woocommerce-square' );
			$form_fields['tokenization']['label'] = __( 'Check to enable tokenization and allow customers to securely save their payment details for future checkout.', 'woocommerce-square' );
		}

		return $form_fields;
	}


	/**
	 * Clear the CSC field settings, as CSC is always required by Square.
	 *
	 * @since 2.0.0
	 *
	 * @param array $form_fields
	 * @return array
	 */
	protected function add_csc_form_fields( $form_fields ) {

		return $form_fields;
	}


	/**
	 * Adds the Card Types setting field.
	 *
	 * This adds UnionPay card type option and activates it by default
	 *
	 * @since 2.0.0
	 *
	 * @param array $form_fields
	 * @return array
	 */
	protected function add_card_types_form_fields( $form_fields ) {

		$form_fields = parent::add_card_types_form_fields( $form_fields );

		if ( isset( $form_fields['card_types']['default'] ) ) {

			if ( ! isset( $form_fields['card_types']['options']['UNIONPAY'] ) ) {
				$form_fields['card_types']['options']['UNIONPAY'] = __( 'UnionPay', 'woocommerce-square' );
			}

			if ( ! in_array( 'UNIONPAY', $form_fields['card_types']['default'], true ) ) {
				$form_fields['card_types']['default'][] = 'UNIONPAY';
			}
		}

		return $form_fields;
	}

	/**
	 * Adds the Digital Wallet setting fields.
	 *
	 * @since 2.3.0
	 *
	 * @param array $form_fields
	 * @return array
	 */
	protected function add_digital_wallets_form_fields( $form_fields ) {
		$form_fields['digital_wallet_settings'] = array(
			'title'       => esc_html__( 'Digital Wallet Settings', 'woocommerce-square' ),
			'description' => esc_html__( 'Take payments on your store with Apple Pay and Google Pay.', 'woocommerce-square' ),
			'type'        => 'title',
		);

		$form_fields['enable_digital_wallets'] = array(
			'title'       => esc_html__( 'Enable / Disable', 'woocommerce-square' ),
			/* translators: Placeholders: %1$s - <a> tag, %2$s - </a> tag */
			'description' => sprintf( esc_html__( 'Allow customers to pay with Apple Pay or Google Pay from your Product, Cart and Checkout pages. Read more about the availablity of digital wallets in our %1$sdocumentation%2$s.', 'woocommerce-square' ), '<a href="https://docs.woocommerce.com/document/woocommerce-square/">', '</a>' ),
			'type'        => 'checkbox',
			'default'     => 'yes',
			'label'       => esc_html__( 'Enable digital wallets', 'woocommerce-square' ),
		);

		$form_fields['digital_wallets_button_type'] = array(
			'title'       => esc_html__( 'Button Type', 'woocommerce-square' ),
			'description' => esc_html__( 'This setting only applies to the Apple Pay button. When Google Pay is available, the Google Pay button will always have the "Buy with" button text.', 'woocommerce-square' ),
			'desc_tip'    => esc_html__( 'Select which text is displayed on the digital wallet buttons.', 'woocommerce-square' ),
			'type'        => 'select',
			'default'     => 'buy',
			'class'       => 'wc-enhanced-select wc-square-digital-wallet-options',
			'options'     => array(
				'buy'    => 'Buy Now',
				'donate' => 'Donate',
				'plain'  => 'No Text',
			),
		);

		$form_fields['digital_wallets_apple_pay_button_color'] = array(
			'title'    => esc_html__( 'Apple Pay Button Color', 'woocommerce-square' ),
			'desc_tip' => esc_html__( 'Select the color of the Apple Pay button.', 'woocommerce-square' ),
			'type'     => 'select',
			'default'  => 'black',
			'class'    => 'wc-enhanced-select wc-square-digital-wallet-options',
			'options'  => array(
				'black'         => 'Black',
				'white'         => 'White',
				'white-outline' => 'White with outline',
			),
		);

		$form_fields['digital_wallets_google_pay_button_color'] = array(
			'title'    => esc_html__( 'Google Pay Button Color', 'woocommerce-square' ),
			'desc_tip' => esc_html__( 'Select the color of the Google Pay button.', 'woocommerce-square' ),
			'type'     => 'select',
			'default'  => 'black',
			'class'    => 'wc-enhanced-select wc-square-digital-wallet-options',
			'options'  => array(
				'black' => 'Black',
				'white' => 'White',
			),
		);

		$form_fields['digital_wallets_hide_button_options'] = array(
			'title'    => esc_html__( 'Hide Digital Wallet Buttons', 'woocommerce-square' ),
			'desc_tip' => esc_html__( 'Select any digital wallet buttons you don\'t want to be displayed on your store.', 'woocommerce-square' ),
			'type'     => 'multiselect',
			'default'  => '',
			'class'    => 'wc-enhanced-select wc-square-digital-wallet-options',
			'options'  => array(
				'apple'  => 'Apple Pay',
				'google' => 'Google Pay',
			),
		);

		return $form_fields;
	}

	/** Conditional methods *******************************************************************************************/


	/**
	 * Determines if the gateway is available.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function is_available() {

		return parent::is_available() && $this->get_plugin()->get_settings_handler()->is_connected() && $this->get_plugin()->get_settings_handler()->get_location_id();
	}


	/**
	 * Determines whether the CSC field is enabled.
	 *
	 * This is always required by the Square payment form JS.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function csc_enabled() {

		return true;
	}


	/**
	 * Determines whether new payment customers/tokens should be created before processing a payment.
	 *
	 * Square requires we create a new customer & customer card before referencing that customer in a transaction.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function tokenize_before_sale() {

		return true;
	}


	/**
	 * Determines if 3d secure is enabled.
	 *
	 * @since      2.1.0
	 * @deprecated 3.3.0 Square automatically detects whether SCA is required, so we do not need this function.
	 *
	 * @return bool
	 */
	public function is_3d_secure_enabled() {

		_deprecated_function( __FUNCTION__, '3.3.0' );

		$base_currency = get_woocommerce_currency();

		$sca_enabled_currencies = in_array( $base_currency, $this->sca_supported_currencies, true );

		/**
		 * Filters whether or not 3d Secure should be enabled.
		 *
		 * @since 2.1.0
		 *
		 * @param bool $enabled
		 * @param Gateway $gateway_instance
		 */
		return apply_filters( 'wc_square_is_3d_secure_enabled', $sca_enabled_currencies, $this );
	}

	/**
	 * Determines if digital wallets are available.
	 *
	 * @since 2.3
	 * @return bool
	 */
	public function is_digital_wallet_available() {
		$is_available          = false;
		$base_location         = wc_get_base_location();
		$supported_countries   = array( 'US', 'GB', 'CA', 'IE', 'FR', 'ES', 'AU' );
		$is_currency_supported = in_array( get_woocommerce_currency(), array( 'USD', 'GBP', 'CAD', 'EUR', 'AUD' ), true );
		$is_country_supported  = ( ! empty( $base_location['country'] ) && in_array( $base_location['country'], $supported_countries, true ) );

		if ( ( wc_site_is_https() || wc_square()->get_settings_handler()->is_sandbox() ) && $is_currency_supported && $is_country_supported ) {
			$is_available = true;
		}

		return $is_available;
	}

	/** Getter methods ************************************************************************************************/


	/**
	 * Gets order meta.
	 *
	 * Overridden to handle any missing transaction ID meta from v1.
	 *
	 * @since 2.0.0
	 *
	 * @param \WC_Order|int $order order object or ID
	 * @param string $key meta key
	 * @return mixed
	 */
	public function get_order_meta( $order, $key ) {

		if ( is_numeric( $order ) ) {
			$order = wc_get_order( $order );
		}

		// migrate any missing transaction IDs
		if ( $order && 'trans_id' === $key && ! parent::get_order_meta( $order, $key ) && $order->get_transaction_id() ) {
			$this->update_order_meta( $order, 'trans_id', $order->get_transaction_id() );
		}

		return parent::get_order_meta( $order, $key );
	}


	/**
	 * Gets the authorization -> capture time window.
	 *
	 * Square limits captures to 6 days.
	 *
	 * @since 2.0.0
	 *
	 * @return int
	 */
	public function get_authorization_time_window() {

		return 144;
	}


	/**
	 * Gets the payment form handler instance.
	 *
	 * @since 2.0.0
	 *
	 * @return Payment_Form
	 */
	public function get_payment_form_instance() {

		if ( empty( $this->payment_form ) ) {
			$this->payment_form = new Payment_Form( $this );
		}

		return $this->payment_form;
	}


	/**
	 * Gets the API instance.
	 *
	 * @since 2.0.0
	 *
	 * @return Gateway\API
	 */
	public function get_api() {

		if ( ! $this->api ) {
			$settings  = $this->get_plugin()->get_settings_handler();
			$this->api = new Gateway\API( $settings->get_access_token(), $settings->get_location_id(), $settings->is_sandbox() );
		}

		return $this->api;
	}


	/**
	 * Gets the gateway settings fields.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	protected function get_method_form_fields() {

		return array();
	}


	/**
	 * Gets a user's stored customer ID.
	 *
	 * Overridden to avoid auto-creating customer IDs, as Square generates them.
	 *
	 * @since 2.0.0
	 *
	 * @param int $user_id user ID
	 * @param array $args arguments
	 * @return string
	 */
	public function get_customer_id( $user_id, $args = array() ) {

		// Square generates customer IDs
		$args['autocreate'] = false;

		return parent::get_customer_id( $user_id, $args );
	}


	/**
	 * Gets a guest's customer ID.
	 *
	 * @since 2.0.0
	 *
	 * @param \WC_Order $order order object
	 * @return string|bool
	 */
	public function get_guest_customer_id( \WC_Order $order ) {

		// is there a customer id already tied to this order?
		$customer_id = $this->get_order_meta( $order, 'customer_id' );

		if ( $customer_id ) {
			return $customer_id;
		}

		return false;
	}


	/**
	 * Gets the configured environment ID.
	 *
	 * Square doesn't really support a sandbox, so we don't show a setting for this.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_environment() {

		return self::ENVIRONMENT_PRODUCTION;
	}

	/**
	 * Gets the configured application ID.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_application_id() {

		$square_application_id = 'sq0idp-wGVapF8sNt9PLrdj5znuKA';

		if ( $this->get_plugin()->get_settings_handler()->is_sandbox() ) {
			$square_application_id = $this->get_plugin()->get_settings_handler()->get_option( 'sandbox_application_id' );
		}

		/**
		 * Filters the configured application ID.
		 *
		 * @since 2.0.0
		 *
		 * @param string $application_id application ID
		 */
		return apply_filters( 'wc_square_application_id', $square_application_id );
	}

	/**
	 * AJAX WooCommerce checkout validation handler
	 *
	 * Tap into  woocommerce_after_checkout_validation hook
	 * and return WooCommerce checkout validation errors
	 *
	 * @since 2.2
	 */
	public function wc_ajax_square_checkout_handler() {
		// Nonce verfication.
		if ( ! check_ajax_referer( 'wc_' . $this->get_id() . '_checkout_validate', 'wc_' . $this->get_id() . '_checkout_validate_nonce', false ) ) {
			return wp_send_json_error( __( 'An error occurred, please try again or try an alternate form of payment.', 'woocommerce-square' ) );
		}

		// Nonce successfully verified. Proceed with validation.
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'wc_ajax_square_checkout_validate' ), 10, 2 );
		WC()->checkout->process_checkout();
	}

	/**
	 * Filters to show only the Square gateway when cart contains a Gift card product.
	 *
	 * @since 4.2.0
	 *
	 * @param array $gateways Array of payment gateways.
	 * @return array
	 */
	public function filter_available_gateways( $gateways ) {
		$location_id  = $this->get_plugin()->get_settings_handler()->get_location_id();
		$is_connected = $this->get_plugin()->get_settings_handler()->is_connected();

		if ( $is_connected ) {
			foreach ( $this->get_plugin()->get_settings_handler()->get_locations() as $location ) {
				if ( $location_id === $location->getId() && get_woocommerce_currency() !== $location->getCurrency() ) {
					unset( $gateways[ Plugin::GATEWAY_ID ] );
				}
			}
		}

		if ( ! Gift_Card::cart_contains_gift_card() ) {
			return $gateways;
		}

		$allowed_gateways = array();
		$plugin_gateways  = wc_square()->get_gateway_ids();
		foreach ( $gateways as $gateway_id => $gateway ) {
			if ( in_array( $gateway_id, $plugin_gateways, true ) ) {
				$allowed_gateways[ $gateway_id ] = $gateway;
			}
		}

		return $allowed_gateways;
	}

	/**
	 * If no payment gateways are available and cart contains gift card, then show error message.
	 *
	 * @since 4.2.0
	 *
	 * @param string $text Error message.
	 * @return string
	 */
	public function filter_no_payment_gatways_message( $text ) {
		if ( ! Gift_Card::cart_contains_gift_card() ) {
			return $text;
		}

		return esc_html__( 'Your cart contains a Square Gift Card product which can only be purchased using the Square payment gateway.', 'woocommerce-square' );
	}

	/**
	 * Validate WooCommerce checkout data on Square JS AJAX call
	 *
	 * Returns validation errors (or success) as JSON and exits to prevent checkout
	 *
	 * @since 2.2
	 *
	 * @param array    $data WooCommerce checkout POST data.
	 * @param WP_Error $errors WooCommerce checkout errors.
	 */
	public function wc_ajax_square_checkout_validate( $data, $errors = null ) {
		$error_messages = null;
		if ( ! is_null( $errors ) ) {
			$error_messages = $errors->get_error_messages();
		}

		// Clear all existing notices.
		wc_clear_notices();

		if ( empty( $error_messages ) ) {
			wp_send_json_success( 'validation_successful' );
		} else {
			wp_send_json_error( array( 'messages' => $error_messages ) );
		}
		exit;
	}

	/**
	 * Returns the $order object with a unique transaction ref member added
	 *
	 * @since 2.2.1
	 * @param WC_Order $order the order object
	 * @return WC_Order order object with member named unique_transaction_ref
	 */
	protected function get_order_with_unique_transaction_ref( $order ) {
		$order_id = $order->get_id();

		// generate a unique retry count
		if ( is_numeric( $this->get_order_meta( $order_id, 'retry_count' ) ) ) {
			$retry_count = $this->get_order_meta( $order_id, 'retry_count' );
			$retry_count++;
		} else {
			$retry_count = 0;
		}

		// keep track of the retry count
		$this->update_order_meta( $order, 'retry_count', $retry_count );

		$order->unique_transaction_ref = time() . '-' . $order_id . ( $retry_count >= 0 ? '-' . $retry_count : '' );
		return $order;
	}

	/**
	 * Returns the payment method image URL (if any) for the given $type, ie
	 * if $type is 'amex' a URL to the american express card icon will be
	 * returned.  If $type is 'echeck', a URL to the echeck icon will be
	 * returned.
	 *
	 * @since 3.4.0
	 * @param string $type the payment method cc type or name
	 * @return string the image URL or null
	 */
	public function get_payment_method_image_url( $type ) {

		$image_type = strtolower( $type );

		if ( 'card' === $type ) {
			$image_type = 'cc-plain';
		}

		/**
		 * Payment Gateway Fallback to PNG Filter.
		 *
		 * Allow actors to enable the use of PNGs over SVGs for payment icon images.
		 *
		 * @since 3.4.0
		 * @param bool $use_svg true by default, false to use PNGs
		 */
		$image_extension = apply_filters( 'wc_payment_gateway_' . $this->get_plugin()->get_id() . '_use_svg', true ) ? '.svg' : '.png';

		// first, is the card image available within the plugin?
		if ( is_readable( $this->get_plugin()->get_plugin_path() . '/build/images/card-' . $image_type . $image_extension ) ) {
			return \WC_HTTPS::force_https_url( $this->get_plugin()->get_plugin_url() . '/build/images/card-' . $image_type . $image_extension );
		}

		// Fall back to framework image URL.
		return parent::get_payment_method_image_url( $type );
	}

	/**
	 * Mark an order as refunded. This should only be used when the full order
	 * amount has been refunded.
	 *
	 * @since 3.2.0
	 *
	 * @param \WC_Order $order order object
	 */
	public function mark_order_as_refunded( $order ) {

		/* translators: Placeholders: %s - payment gateway title (such as Authorize.net, Braintree, etc) */
		$order_note = sprintf( esc_html__( '%s Order completely refunded.', 'woocommerce-square' ), $this->get_method_title() );

		// Add order note and continue with WC refund process.
		$order->add_order_note( $order_note );
	}

	/**
	 * Get order amount via AJAX.
	 *
	 * @since 4.1.0
	 */
	public function get_order_amount() {
		check_ajax_referer( 'wc_' . $this->get_id() . '_get_order_amount', 'security' );
		$total_amount = '';
		$is_pay_order = isset( $_POST['is_pay_order'] ) && 'true' === sanitize_key( $_POST['is_pay_order'] );
		if ( $is_pay_order ) {
			$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
			$order    = wc_get_order( $order_id );
			if ( $order ) {
				$total_amount = $order->get_total();
			}
		} else {
			$total_amount = WC()->cart->total;
		}
		wp_send_json_success( $total_amount );
	}
}
