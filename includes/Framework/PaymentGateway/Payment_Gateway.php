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

use WooCommerce\Square\Framework\Compatibility\Order_Compatibility;
use WooCommerce\Square\Framework\PaymentGateway\Api\Payment_Gateway_API_Authorization_Response;
use WooCommerce\Square\Framework\Square_Helper;
use WooCommerce\Square\Framework\PaymentGateway\Api\Payment_Gateway_API_Response;
use WooCommerce\Square\Framework\PaymentGateway\ApplePay\Api\Payment_Gateway_Apple_Pay_Payment_Response;
use WooCommerce\Square\Framework\PaymentGateway\Handlers\Capture;
use WooCommerce\Square\Framework\PaymentGateway\Integrations\Payment_Gateway_Integration_Pre_Orders;
use WooCommerce\Square\Framework\PaymentGateway\Integrations\Payment_Gateway_Integration_Subscriptions;
use WooCommerce\Square\Framework\PaymentGateway\PaymentTokens\Payment_Gateway_Payment_Tokens_Handler;
use WooCommerce\Square\Plugin;
use WooCommerce\Square\Framework as SquareFramework;
use WooCommerce\Square\Gateway\API\Responses\Create_Payment;
use WooCommerce\Square\Handlers\Order;
use WooCommerce\Square\Handlers\Product;
use WooCommerce\Square\Utilities\Money_Utility;
use WooCommerce\Square\WC_Order_Square;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce Payment Gateway Framework
 *
 * @since 3.0.0
 */
abstract class Payment_Gateway extends \WC_Payment_Gateway {


	/** Sends through sale and request for funds to be charged to cardholder's credit card. */
	const TRANSACTION_TYPE_CHARGE = 'charge';

	/** Sends through a request for funds to be "reserved" on the cardholder's credit card. A standard authorization is reserved for 2-5 days. Reservation times are determined by cardholder's bank. */
	const TRANSACTION_TYPE_AUTHORIZATION = 'authorization';

	/** The production environment identifier */
	const ENVIRONMENT_PRODUCTION = 'production';

	/** The test environment identifier */
	const ENVIRONMENT_TEST = 'test';

	/** Debug mode log to file */
	const DEBUG_MODE_LOG = 'log';

	/** Debug mode display on checkout */
	const DEBUG_MODE_CHECKOUT = 'checkout';

	/** Debug mode log to file and display on checkout */
	const DEBUG_MODE_BOTH = 'both';

	/** Debug mode disabled */
	const DEBUG_MODE_OFF = 'off';

	/** Credit card payment type */
	const PAYMENT_TYPE_CREDIT_CARD = 'credit-card';

	/** Credit card payment type */
	const PAYMENT_TYPE_CASH_APP_PAY = 'cash_app_pay';

	/** Credit card payment type */
	const PAYMENT_TYPE_GIFT_CARD_PAY = 'enable_gift_cards_pay';

	/** Products feature */
	const FEATURE_PRODUCTS = 'products';

	/** Credit card types feature */
	const FEATURE_CARD_TYPES = 'card_types';

	/** Tokenization feature */
	const FEATURE_TOKENIZATION = 'tokenization';

	/** Credit Card charge transaction feature */
	const FEATURE_CREDIT_CARD_CHARGE = 'charge';

	/** Credit Card authorization transaction feature */
	const FEATURE_CREDIT_CARD_AUTHORIZATION = 'authorization';

	/** Credit Card charge virtual-only orders feature */
	const FEATURE_CREDIT_CARD_CHARGE_VIRTUAL = 'charge-virtual';

	/** Credit Card capture charge transaction feature */
	const FEATURE_CREDIT_CARD_CAPTURE = 'capture_charge';

	/** Credit Card partial capture transaction feature */
	const FEATURE_CREDIT_CARD_PARTIAL_CAPTURE = 'partial_capture';

	/** Gateway charge transaction feature */
	const FEATURE_CHARGE = 'charge';

	/** Gateway authorization transaction feature */
	const FEATURE_AUTHORIZATION = 'authorization';

	/** Gateway charge virtual-only orders feature */
	const FEATURE_CHARGE_VIRTUAL = 'charge-virtual';

	/** Gateway capture charge transaction feature */
	const FEATURE_CAPTURE = 'capture_charge';

	/** Gateway partial capture transaction feature */
	const FEATURE_PARTIAL_CAPTURE = 'partial_capture';

	/** Refunds feature */
	const FEATURE_REFUNDS = 'refunds';

	/** Voids feature */
	const FEATURE_VOIDS = 'voids';

	/** Payment Form feature */
	const FEATURE_PAYMENT_FORM = 'payment_form';

	/** Customer ID feature */
	const FEATURE_CUSTOMER_ID = 'customer_id';

	/** Add new payment method feature */
	const FEATURE_ADD_PAYMENT_METHOD = 'add_payment_method';

	/** Apple Pay feature */
	const FEATURE_APPLE_PAY = 'apple_pay';

	/** Admin token editor feature */
	const FEATURE_TOKEN_EDITOR = 'token_editor';

	/** Subscriptions integration ID */
	const INTEGRATION_SUBSCRIPTIONS = 'subscriptions';

	/** Pre-orders integration ID */
	const INTEGRATION_PRE_ORDERS = 'pre_orders';

	/** Charge type when total amount is charged on a Gift Card. */
	const CHARGE_TYPE_FULL = 'FULL';

	/** Charge type when total amount is partially charged on a Gift Card. */
	const CHARGE_TYPE_PARTIAL = 'PARTIAL';

	/** @var Payment_Gateway_Plugin the parent plugin class */
	private $plugin;

	/** @var string payment type, one of 'credit-card' */
	private $payment_type;

	/** @var array associative array of environment id to display name, defaults to 'production' => 'Production' */
	private $environments;

	/** @var array associative array of card type to display name */
	private $available_card_types;

	/** @var array optional array of currency codes this gateway is allowed for */
	protected $currencies;

	/** @var string configuration option: the transaction environment, one of $this->environments keys */
	private $environment;

	/** @var string configuration option: the type of transaction, whether purchase or authorization, defaults to 'charge' */
	private $transaction_type;

	/** @var string configuration option: whether transactions should always be charged if the order is virtual-only, defaults to 'no' */
	private $charge_virtual_orders;

	/** @var string configuration option: whether orders can be partially captured multiple times */
	private $enable_partial_capture;

	/** @var string configuration option: whether orders are captured when switched to a "paid" status */
	private $enable_paid_capture;

	/** @var array configuration option: card types to show images for */
	private $card_types;

	/** @var string configuration option: indicates whether a Card Security Code field will be presented on checkout, either 'yes' or 'no' */
	private $enable_csc;

	/** @var string configuration option: indicates whether a Card Security Code field will be presented for saved cards at checkout, either 'yes' or 'no' */
	private $enable_token_csc;

	/** @var string configuration option: indicates whether tokenization is enabled, either 'yes' or 'no' */
	private $tokenization;

	/** @var string configuration option: indicates whether detailed customer decline messages should be displayed at checkout, either 'yes' or 'no' */
	private $enable_customer_decline_messages;

	/** @var string configuration option: 4 options for debug mode - off, checkout, log, both */
	private $debug_mode;

	/** @var string configuration option: whether to use a sibling gateway's connection/authentication settings */
	private $inherit_settings;

	/** @var array of shared setting names, if any. */
	private $shared_settings = array();

	/** @var Payment_Gateway_Payment_Tokens_Handler payment tokens handler instance */
	protected $payment_tokens_handler;

	/** @var Payment_Gateway\Handlers\Capture capture handler instance */
	protected $capture_handler;

	/** @var array of Payment_Gateway_Integration objects for Subscriptions, Pre-Orders, etc. */
	protected $integrations;

	/** @var string configuration option: whether Square gateway is enabled. */
	public $enabled;

	/** @var string configuration option: title to show on the Checkout page for Square gateway. */
	public $title;

	/** @var string configuration option: description to show on the Checkout page for Square gateway. */
	public $description;

	/** @var string digital wallets section title field. */
	protected $digital_wallet_settings;

	/** @var string configuration option: whether digital wallets is enabled. */
	protected $enable_digital_wallets;

	/** @var string configuration option: button type: Buy Now | Donate | No Text. */
	protected $digital_wallets_button_type;

	/** @var string configuration option: button color for Apply Pay. */
	protected $digital_wallets_apple_pay_button_color;

	/** @var string configuration option: button color for Google Pay. */
	protected $digital_wallets_google_pay_button_color;

	/** @var array configuration option: list of buttons that are hidden on the front end. */
	protected $digital_wallets_hide_button_options;

	/** @var string configuration option: whether gift cards is enabled. */
	protected $enable_gift_cards;

	/** @var string advanced settings section title field. */
	protected $advanced_settings_title;

	/** @var string whether apple pay domain is registered. */
	protected $apple_pay_domain_registered;

	/** @var string whether apple pay domain registration was attempted. */
	protected $apple_pay_domain_registration_attempted;

	/** @var string Gift Card settings. */
	protected $gift_card_settings;

	/** @var string order note for the voided order. */
	protected $voided_order_message;

	/**
	 * Initialize the gateway
	 *
	 * Args:
	 *
	 * + `method_title` - string admin method title, ie 'Intuit QBMS', defaults to 'Settings'
	 * + `method_description` - string admin method description, defaults to ''
	 * + `supports` - array  list of supported gateway features, possible values include:
	 *   'products', 'card_types', 'tokenziation', 'charge', 'authorization', 'subscriptions',
	 *   'subscription_suspension', 'subscription_cancellation', 'subscription_reactivation',
	 *   'subscription_amount_changes', 'subscription_date_changes', 'subscription_payment_method_change',
	 *   'customer_decline_messages'
	 *   Defaults to 'products', 'charge' (credit-card gateways only)
	 * + `payment_type` - 'credit-card'
	 * + `card_types` - array  associative array of card type to display name, used if the payment_type is 'credit-card' and the 'card_types' feature is supported.  Defaults to:
	 *   'VISA' => 'Visa', 'MC' => 'MasterCard', 'AMEX' => 'American Express', 'DISC' => 'Discover', 'DINERS' => 'Diners', 'JCB' => 'JCB'
	 * + `environments` - associative array of environment id to display name, merged with default of 'production' => 'Production'
	 * + `currencies` -  array of currency codes this gateway is allowed for, defaults to plugin accepted currencies
	 * + `countries` -  array of two-letter country codes this gateway is allowed for, defaults to all
	 * + `shared_settings` - array of shared setting names, if any.
	 *
	 * @since 3.0.0
	 * @param string $id the gateway id
	 * @param Payment_Gateway_Plugin $plugin the parent plugin class
	 * @param array $args gateway arguments
	 */
	public function __construct( $id, $plugin, $args ) {

		// first setup the gateway and payment type for this gateway
		$this->payment_type = isset( $args['payment_type'] ) ? $args['payment_type'] : self::PAYMENT_TYPE_CREDIT_CARD;

		// default credit card gateways to supporting 'charge' transaction type, this could be overridden by the 'supports' constructor parameter to include (or only support) authorization
		if ( $this->is_credit_card_gateway() ) {
			$this->add_support( self::FEATURE_CREDIT_CARD_CHARGE );
		}

		// required fields
		$this->id = $id;  // @see WC_Payment_Gateway::$id

		$this->plugin = $plugin;
		// kind of sucks, but we need to register back to the plugin because
		//  there's no other way of grabbing existing gateways so as to avoid
		//  double-instantiation errors (esp for shared settings)
		$this->get_plugin()->set_gateway( $id, $this );

		// optional parameters
		if ( isset( $args['method_title'] ) ) {
			$this->method_title = $args['method_title'];        // @see WC_Settings_API::$method_title
		}
		if ( isset( $args['method_description'] ) ) {
			$this->method_description = $args['method_description'];  // @see WC_Settings_API::$method_description
		}
		if ( isset( $args['supports'] ) ) {
			$this->set_supports( $args['supports'] );
		}
		if ( isset( $args['card_types'] ) ) {
			$this->available_card_types = $args['card_types'];
		}
		if ( isset( $args['environments'] ) ) {
			$this->environments = array_merge( $this->get_environments(), $args['environments'] );
		}
		if ( isset( $args['countries'] ) ) {
			$this->countries = $args['countries'];  // @see WC_Payment_Gateway::$countries
		}
		if ( isset( $args['shared_settings'] ) ) {
			$this->shared_settings = $args['shared_settings'];
		}
		if ( isset( $args['currencies'] ) ) {
			$this->currencies = $args['currencies'];
		} else {
			$this->currencies = $this->get_plugin()->get_accepted_currencies();
		}
		if ( isset( $args['order_button_text'] ) ) {
			$this->order_button_text = $args['order_button_text'];
		} else {
			$this->order_button_text = $this->get_order_button_text();
		}

		// always want to render the field area, even for gateways with no fields, so we can display messages  @see WC_Payment_Gateway::$has_fields
		$this->has_fields = true;

		// default icon filter  @see WC_Payment_Gateway::$icon
		$this->icon = apply_filters( 'wc_' . $this->get_id() . '_icon', '' );

		$square_settings = get_option( 'wc_square_settings', array() );

		$this->debug_mode = $square_settings['debug_mode'] ?? 'off';

		$this->enable_customer_decline_messages = $square_settings['enable_customer_decline_messages'] ?? 'no';

		// Load the form fields
		$this->init_form_fields();

		// initialize and load the settings
		$this->init_settings();

		$this->load_settings();

		$this->init_payment_tokens_handler();

		$this->init_integrations();

		// initialize the capture handler
		$this->init_capture_handler();

		// pay page fallback
		$this->add_pay_page_handler();

		// filter order received text for held orders
		add_filter( 'woocommerce_thankyou_order_received_text', array( $this, 'maybe_render_held_order_received_text' ), 10, 2 );

		// admin only
		if ( is_admin() ) {

			// save settings
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->get_id(), array( $this, 'process_admin_options' ) );
		}

		// Enqueue the necessary scripts & styles
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// add API request logging
		$this->add_api_request_logging();

		// add milestone action hooks
		$this->add_milestone_hooks();
	}


	/**
	 * Adds the various milestone hooks like "payment processed".
	 *
	 * @since 3.0.0
	 */
	protected function add_milestone_hooks() {

		$plugin = $this->get_plugin();

		// first successful payment
		add_action(
			'wc_payment_gateway_' . $this->get_id() . '_payment_processed',
			function( $order ) use ( &$plugin ) {
				$plugin->get_lifecycle_handler()->trigger_milestone( 'payment-processed', __( 'you successfully processed a payment!', 'woocommerce-square' ) );
			}
		);

		// first successful refund
		add_action(
			'wc_payment_gateway_' . $this->get_id() . '_refund_processed',
			function( $order ) use ( &$plugin ) {
				$plugin->get_lifecycle_handler()->trigger_milestone( 'refund-processed', __( 'you successfully processed a refund!', 'woocommerce-square' ) );
			}
		);
	}


	/**
	 * Loads the plugin configuration settings
	 *
	 * @since 3.0.0
	 */
	public function load_settings() {

		// define user set variables
		foreach ( $this->settings as $setting_key => $setting ) {
			$this->$setting_key = $setting;
		}

		// inherit settings from sibling gateway(s)
		if ( $this->inherit_settings() ) {
			$this->load_shared_settings();
		}
	}


	/**
	 * Loads any shared settings from sibling gateways.
	 *
	 * @since 3.0.0
	 */
	protected function load_shared_settings() {

		// get any other sibling gateways
		$other_gateway_ids = array_diff( $this->get_plugin()->get_gateway_ids(), array( $this->get_id() ) );

		// determine if any sibling gateways have any configured shared settings
		foreach ( $other_gateway_ids as $other_gateway_id ) {

			$other_gateway_settings = $this->get_plugin()->get_gateway_settings( $other_gateway_id );

			// if the other gateway isn't also trying to inherit settings...
			if ( ! isset( $other_gateway_settings['inherit_settings'] ) || 'no' === $other_gateway_settings['inherit_settings'] ) {

				// load the other gateway so we can access the shared settings properly
				$other_gateway = $this->get_plugin()->get_gateway( $other_gateway_id );

				// skip this gateway if it isn't meant to share its settings
				if ( ! $other_gateway->share_settings() ) {
					continue;
				}

				foreach ( $this->shared_settings as $setting_key ) {
					$this->$setting_key = $other_gateway->$setting_key;
				}
			}
		}
	}


	/**
	 * Enqueue the necessary scripts & styles for the gateway, including the
	 * payment form assets (if supported) and any gateway-specific assets.
	 *
	 * @since 3.0.0
	 */
	public function enqueue_scripts() {

		if ( ! $this->is_available() ) {
			return;
		}

		// payment form assets
		if ( $this->supports_payment_form() ) {

			$this->enqueue_payment_form_assets();
		}

		// gateway-specific assets
		$this->enqueue_gateway_assets();
	}


	/**
	 * Enqueue the payment form JS, CSS, and localized
	 * JS params
	 *
	 * @since 3.0.0
	 */
	protected function enqueue_payment_form_assets() {

		// bail if on my account page and *not* on add payment method page
		if ( is_account_page() && ! is_add_payment_method_page() ) {
			return;
		}

		$handle = 'wc-square-payment-gateway-payment-form';

		// Frontend JS
		wp_enqueue_script( $handle, $this->get_plugin()->get_plugin_url() . '/build/assets/frontend/' . $handle . '.js', array( 'jquery-payment' ), Plugin::VERSION, true );

		// Frontend CSS
		wp_enqueue_style( $handle, $this->get_plugin()->get_plugin_url() . '/build/assets/frontend/' . $handle . '.css', array(), Plugin::VERSION );

		// localized JS params
		$this->localize_script( $handle, $this->get_payment_form_js_localized_script_params() );
	}


	/**
	 * Returns an array of JS script params to localize for the
	 * payment form JS. Generally used for i18n purposes.
	 *
	 * @since 3.0.0
	 * @return array associative array of param name to value
	 */
	protected function get_payment_form_js_localized_script_params() {

		/**
		 * Payment Form JS Localized Script Params Filter.
		 *
		 * Allow actors to modify the JS localized script params for the
		 * payment form.
		 *
		 * @since 3.0.0
		 * @param array $params
		 * @return array
		 */
		return apply_filters(
			'sv_wc_payment_gateway_payment_form_js_localized_script_params',
			array(
				'card_number_missing'            => esc_html__( 'Card number is missing', 'woocommerce-square' ),
				'card_number_invalid'            => esc_html__( 'Card number is invalid', 'woocommerce-square' ),
				'card_number_digits_invalid'     => esc_html__( 'Card number is invalid (only digits allowed)', 'woocommerce-square' ),
				'card_number_length_invalid'     => esc_html__( 'Card number is invalid (wrong length)', 'woocommerce-square' ),
				'cvv_missing'                    => esc_html__( 'Card security code is missing', 'woocommerce-square' ),
				'cvv_digits_invalid'             => esc_html__( 'Card security code is invalid (only digits are allowed)', 'woocommerce-square' ),
				'cvv_length_invalid'             => esc_html__( 'Card security code is invalid (must be 3 or 4 digits)', 'woocommerce-square' ),
				'card_exp_date_invalid'          => esc_html__( 'Card expiration date is invalid', 'woocommerce-square' ),
				'check_number_digits_invalid'    => esc_html__( 'Check Number is invalid (only digits are allowed)', 'woocommerce-square' ),
				'check_number_missing'           => esc_html__( 'Check Number is missing', 'woocommerce-square' ),
				'drivers_license_state_missing'  => esc_html__( 'Drivers license state is missing', 'woocommerce-square' ),
				'drivers_license_number_missing' => esc_html__( 'Drivers license number is missing', 'woocommerce-square' ),
				'drivers_license_number_invalid' => esc_html__( 'Drivers license number is invalid', 'woocommerce-square' ),
				'account_number_missing'         => esc_html__( 'Account Number is missing', 'woocommerce-square' ),
				'account_number_invalid'         => esc_html__( 'Account Number is invalid (only digits are allowed)', 'woocommerce-square' ),
				'account_number_length_invalid'  => esc_html__( 'Account number is invalid (must be between 5 and 17 digits)', 'woocommerce-square' ),
				'routing_number_missing'         => esc_html__( 'Routing Number is missing', 'woocommerce-square' ),
				'routing_number_digits_invalid'  => esc_html__( 'Routing Number is invalid (only digits are allowed)', 'woocommerce-square' ),
				'routing_number_length_invalid'  => esc_html__( 'Routing number is invalid (must be 9 digits)', 'woocommerce-square' ),
			)
		);
	}


	/**
	 * Enqueue the gateway-specific assets if present, including JS, CSS, and
	 * localized script params
	 *
	 * @since 3.0.0
	 */
	protected function enqueue_gateway_assets() {

		$handle   = $this->get_gateway_js_handle();
		$js_path  = $this->get_plugin()->get_plugin_path() . '/build/assets/frontend/' . $handle . '.js';
		$css_path = $this->get_plugin()->get_plugin_path() . '/build/assets/frontend/' . $handle . '.css';

		// JS
		if ( is_readable( $js_path ) ) {

			$js_url = $this->get_plugin()->get_plugin_url() . '/build/assets/frontend/' . $handle . '.js';

			/**
			 * Concrete Payment Gateway JS URL
			 *
			 * Allow actors to modify the URL used when loading a concrete
			 * payment gateway's javascript.
			 *
			 * @since 3.0.0
			 * @param string $js_url JS asset URL
			 * @return string
			 */
			$js_url = apply_filters( 'wc_payment_gateway_square_javascript_url', $js_url );

			wp_enqueue_script( $handle, $js_url, array( 'jquery', 'wc-country-select' ), $this->get_plugin()->get_version(), true );
		}

		// CSS
		if ( is_readable( $css_path ) ) {

			$css_url = $this->get_plugin()->get_plugin_url() . '/build/assets/frontend/' . $handle . '.css';

			/**
			 * Concrete Payment Gateway CSS URL
			 *
			 * Allow actors to modify the URL used when loading a concrete payment
			 * gateway's CSS.
			 *
			 * @since 3.0.0
			 * @param string $css_url CSS asset URL
			 * @return string
			 */
			$css_url = apply_filters( 'wc_payment_gateway_square_css_url', $css_url );

			wp_enqueue_style( $handle, $css_url, array(), $this->get_plugin()->get_version() );
		}
	}


	/**
	 * Return the gateway-specifics JS script handle. This is used for:
	 *
	 * + enqueuing the script
	 * + the localized JS script param object name
	 *
	 * Defaults to 'wc-<plugin ID dasherized>'.
	 *
	 * @since 3.0.0
	 * @return string
	 */
	protected function get_gateway_js_handle() {

		return 'wc-' . $this->get_plugin()->get_id_dasherized();
	}

	/**
	 * Localize a script once. Gateway plugins that have multiple gateways should
	 * only have their params localized once.
	 *
	 * @since 3.0.0
	 * @param string $handle script handle to localize
	 * @param array $params script params to localize
	 */
	protected function localize_script( $handle, $params ) {

		// If the script isn't loaded, bail
		if ( ! wp_script_is( $handle, 'enqueued' ) ) {
			return;
		}

		global $wp_scripts;

		$object_name = str_replace( '-', '_', $handle ) . '_params';

		// If the plugin's JS params already exists in the localized data, bail
		if ( $wp_scripts instanceof \WP_Scripts && strpos( $wp_scripts->get_data( $handle, 'data' ), $object_name ) ) {
			return;
		}

		wp_localize_script( $handle, $object_name, $params );
	}


	/**
	 * Returns true if on the pay page and this is the currently selected gateway
	 *
	 * @since 3.0.0
	 *
	 * @return null|bool true if on pay page and is currently selected gateways, false if on pay page and not the selected gateway, null otherwise
	 */
	public function is_pay_page_gateway() {

		if ( is_checkout_pay_page() ) {

			$order_id = $this->get_checkout_pay_page_order_id();

			if ( $order_id ) {
				$order = wc_get_order( $order_id );

				return Order_Compatibility::get_prop( $order, 'payment_method' ) === $this->get_id();
			}
		}

		return null;
	}


	/**
	 * Gets the order button text:
	 *
	 * Direct gateway: "Place order"
	 * Redirect/Hosted gateway: "Continue"
	 *
	 * @since 3.0.0
	 */
	protected function get_order_button_text() {

		$text = $this->is_hosted_gateway() ? esc_html__( 'Continue', 'woocommerce-square' ) : esc_html__( 'Place order', 'woocommerce-square' );

		/**
		 * Payment Gateway Place Order Button Text Filter.
		 *
		 * Allow actors to modify the "place order" button text.
		 *
		 * @since 3.0.0
		 * @param string $text button text
		 * @param Payment_Gateway $this instance
		 */
		return apply_filters( 'wc_payment_gateway_' . $this->get_id() . '_order_button_text', $text, $this );
	}


	/**
	 * Adds a default simple pay page handler
	 *
	 * @since 3.0.0
	 */
	protected function add_pay_page_handler() {
		add_action( 'woocommerce_receipt_' . $this->get_id(), array( $this, 'payment_page' ) );
	}


	/**
	 * Render a simple payment page
	 *
	 * @since 3.0.0
	 * @param int $order_id identifies the order
	 */
	public function payment_page( $order_id ) {
		echo '<p>' . esc_html__( 'Thank you for your order.', 'woocommerce-square' ) . '</p>';
	}


	/** Payment Form Feature **************************************************/


	/**
	 * Returns true if the gateway supports the payment form feature
	 *
	 * @since 3.0.0
	 * @return bool
	 */
	public function supports_payment_form() {

		return $this->supports( self::FEATURE_PAYMENT_FORM );
	}


	/**
	 * Render the payment fields
	 *
	 * @since 3.0.0
	 * @see WC_Payment_Gateway::payment_fields()
	 * @see Payment_Gateway_Payment_Form class
	 */
	public function payment_fields() {

		if ( $this->supports_payment_form() ) {

			$this->get_payment_form_instance()->render();

		} else {

			parent::payment_fields();
		}
	}


	/**
	 * Gets the payment form class instance.
	 *
	 * @since 3.0.0
	 *
	 * @return Payment_Gateway_Payment_Form
	 */
	public function get_payment_form_instance() {

		return new Payment_Gateway_Payment_Form( $this );
	}


	/**
	 * Get the payment form field defaults, primarily for gateways to override
	 * and set dummy credit card info when in the test environment
	 *
	 * @since 3.0.0
	 * @return array
	 */
	public function get_payment_method_defaults() {

		assert( $this->supports_payment_form() );

		$defaults = array(
			'account-number' => '',
			'routing-number' => '',
			'expiry'         => '',
			'csc'            => '',
		);

		if ( $this->is_test_environment() ) {
			$defaults['expiry'] = '01/' . ( date( 'y' ) + 1 );
			$defaults['csc']    = '123';
		}

		return $defaults;
	}


	/** Tokenization **************************************************/


	/**
	 * Initialize payment tokens handler.
	 *
	 * @since 3.0.0
	 */
	protected function init_payment_tokens_handler() {

		$this->payment_tokens_handler = $this->build_payment_tokens_handler();
	}


	/**
	 * Gets the payment tokens handler instance.
	 *
	 * Concrete classes can override this method to return a custom implementation.
	 *
	 * @since 3.0.0
	 *
	 * @return Payment_Gateway_Payment_Tokens_Handler
	 */
	protected function build_payment_tokens_handler() {
		return new Payment_Gateway_Payment_Tokens_Handler( $this );
	}


	/**
	 * Gets the payment tokens handler instance.
	 *
	 * @since 3.0.0
	 *
	 * @return Payment_Gateway_Payment_Tokens_Handler
	 */
	public function get_payment_tokens_handler() {

		return $this->payment_tokens_handler;
	}


	/**
	 * Determines if tokenization takes place prior to transaction processing.
	 *
	 * @since 3.0.0
	 *
	 * @return bool
	 */
	public function tokenize_before_sale() {
		return false;
	}


	/**
	 * Determines tokenization takes place during a transaction request.
	 *
	 * @since 3.0.0
	 *
	 * @return bool
	 */
	public function tokenize_with_sale() {
		return false;
	}


	/**
	 * Determines tokenization takes place after a transaction request.
	 *
	 * @since 3.0.0
	 *
	 * @return bool
	 */
	public function tokenize_after_sale() {
		return false;
	}


	/**
	 * Determines if the gateway supports the admin token editor feature.
	 *
	 * @since 3.0.0
	 *
	 * @return bool
	 */
	public function supports_token_editor() {
		return $this->supports( self::FEATURE_TOKEN_EDITOR );
	}


	/** Integrations Feature **************************************************/


	/**
	 * Initializes supported integrations.
	 *
	 * @since 3.0.0
	 */
	public function init_integrations() {

		if ( $this->supports_subscriptions() ) {
			$this->integrations[ self::INTEGRATION_SUBSCRIPTIONS ] = $this->build_subscriptions_integration();
		}

		if ( $this->supports_pre_orders() ) {
			$this->integrations[ self::INTEGRATION_PRE_ORDERS ] = $this->build_pre_orders_integration();
		}

		/**
		 * Payment Gateway Integrations Initialized Action.
		 *
		 * Fired when integrations (Subscriptons/Pre-Orders) have been loaded and
		 * initialized.
		 *
		 * @since 3.0.0
		 *
		 * @param Payment_Gateway_Direct $this instance
		 */
		do_action( 'wc_payment_gateway_' . $this->get_id() . '_init_integrations', $this );
	}


	/**
	 * Gets an array of available integration objects
	 *
	 * @since 3.0.0
	 * @return array
	 */
	public function get_integrations() {

		return $this->integrations;
	}


	/**
	 * Gets the integration object for the given ID.
	 *
	 * @since 3.0.0
	 *
	 * @param string $id the integration ID, e.g. subscriptions
	 * @return SquareFramework\PaymentGateway\Integrations\Payment_Gateway_Integration|null
	 */
	public function get_integration( $id ) {

		return isset( $this->integrations[ $id ] ) ? $this->integrations[ $id ] : null;
	}


	/**
	 * Builds the Subscriptions integration class instance.
	 *
	 * Concrete classes can override this method to return a custom implementation.
	 *
	 * @since 3.0.0
	 *
	 * @return Payment_Gateway_Integration_Subscriptions
	 */
	protected function build_subscriptions_integration() {

		return new Payment_Gateway_Integration_Subscriptions( $this );
	}


	/**
	 * Gets the Subscriptions integration class instance.
	 *
	 * @since 3.0.0
	 *
	 * @return Payment_Gateway_Integration_Subscriptions|null
	 */
	public function get_subscriptions_integration() {

		return isset( $this->integrations[ self::INTEGRATION_SUBSCRIPTIONS ] ) ? $this->integrations[ self::INTEGRATION_SUBSCRIPTIONS ] : null;
	}


	/**
	 * Builds the Pre-Orders integration class instance.
	 *
	 * Concrete classes can override this method to return a custom implementation.
	 *
	 * @since 3.0.0
	 *
	 * @return Payment_Gateway_Integration_Pre_Orders
	 */
	protected function build_pre_orders_integration() {

		return new Payment_Gateway_Integration_Pre_Orders( $this );
	}


	/**
	 * Gets the Pre-Orders integration class instance.
	 *
	 * @since 3.0.0
	 *
	 * @return Payment_Gateway_Integration_Pre_Orders|null
	 */
	public function get_pre_orders_integration() {

		return isset( $this->integrations[ self::INTEGRATION_PRE_ORDERS ] ) ? $this->integrations[ self::INTEGRATION_PRE_ORDERS ] : null;
	}


	/**
	 * Determines if the gateway supports Subscriptions.
	 *
	 * A gateway supports Subscriptions if all of the following are true:
	 *
	 * + Subscriptions is active
	 * + tokenization is supported
	 * + tokenization is enabled
	 *
	 * Concrete gateways can override this to conditionally support Subscriptions
	 * based on certain settings (e.g. only when CSC is not required, etc.)
	 *
	 * @since 3.0.0
	 *
	 * @return bool
	 */
	public function supports_subscriptions() {

		return $this->get_plugin()->is_subscriptions_active() && $this->supports_tokenization() && $this->tokenization_enabled();
	}


	/**
	 * Determines if the gateway supports Pre-Orders.
	 *
	 * A gateway supports Pre-Orders if all of the following are true:
	 *
	 * + Pre-Orders is active
	 * + tokenization is supported
	 * + tokenization is enabled
	 *
	 * Concrete gateways can override this to conditionally support Pre-Orders
	 * based on certain settings (e.g. only when CSC is not required, etc.)
	 *
	 * @since 3.0.0
	 *
	 * @return bool
	 */
	public function supports_pre_orders() {

		return $this->get_plugin()->is_pre_orders_active() && $this->supports_tokenization() && $this->tokenization_enabled();
	}


	/** Apple Pay Feature *****************************************************/


	/**
	 * Determines whether this gateway supports Apple Pay.
	 *
	 * @since 3.0.0
	 *
	 * @return bool
	 */
	public function supports_apple_pay() {

		return $this->supports( self::FEATURE_APPLE_PAY );
	}


	/**
	 * Gets the Apple Pay gateway capabilities.
	 *
	 * Gateways should override this if they have more or less capabilities than
	 * the default. See https://developer.apple.com/reference/applepayjs/paymentrequest/1916123-merchantcapabilities
	 * for valid values.
	 *
	 * @since 3.0.0
	 *
	 * @return array
	 */
	public function get_apple_pay_capabilities() {

		return array(
			'supports3DS',
			'supportsCredit',
			'supportsDebit',
		);
	}


	/**
	 * Gets the currencies supported by Apple Pay.
	 *
	 * @since 3.0.0
	 *
	 * @return array
	 */
	public function get_apple_pay_currencies() {

		return array( 'USD' );
	}


	/**
	 * Adds the Apple Pay payment data to the order object.
	 *
	 * Gateways should override this to set the appropriate values depending on
	 * how their processing API needs to handle the data.
	 *
	 * @since 3.0.0
	 *
	 * @param \WC_Order the order object
	 * @param Payment_Gateway_Apple_Pay_Payment_Response authorized payment response
	 * @return \WC_Order
	 */
	public function get_order_for_apple_pay( \WC_Order $order, Payment_Gateway_Apple_Pay_Payment_Response $response ) {

		$order->payment->account_number = $response->get_last_four();
		$order->payment->last_four      = $response->get_last_four();
		$order->payment->card_type      = $response->get_card_type();

		return $order;
	}


	/**
	 * Get the default payment method title, which is configurable within the
	 * admin and displayed on checkout
	 *
	 * @since 3.0.0
	 * @return string payment method title to show on checkout
	 */
	protected function get_default_title() {

		// defaults for credit card, override for others
		if ( $this->is_credit_card_gateway() ) {
			return esc_html__( 'Credit Card', 'woocommerce-square' );
		}

		return '';
	}


	/**
	 * Get the default payment method description, which is configurable
	 * within the admin and displayed on checkout
	 *
	 * @since 3.0.0
	 * @return string payment method description to show on checkout
	 */
	protected function get_default_description() {

		// defaults for credit card, override for others
		if ( $this->is_credit_card_gateway() ) {
			return esc_html__( 'Pay securely using your credit card.', 'woocommerce-square' );
		}

		return '';
	}


	/**
	 * Initialize payment gateway settings fields
	 *
	 * @since 3.0.0
	 * @see WC_Settings_API::init_form_fields()
	 */
	public function init_form_fields() {

		// common top form fields
		$this->form_fields = array(

			'enabled'     => array(
				'title'   => esc_html__( 'Enable / Disable', 'woocommerce-square' ),
				'label'   => esc_html__( 'Enable this gateway', 'woocommerce-square' ),
				'type'    => 'checkbox',
				'default' => 'no',
			),

			'title'       => array(
				'title'    => esc_html__( 'Title', 'woocommerce-square' ),
				'type'     => 'text',
				'desc_tip' => esc_html__( 'Payment method title that the customer will see during checkout.', 'woocommerce-square' ),
				'default'  => $this->get_default_title(),
			),

			'description' => array(
				'title'    => esc_html__( 'Description', 'woocommerce-square' ),
				'type'     => 'textarea',
				'desc_tip' => esc_html__( 'Payment method description that the customer will see during checkout.', 'woocommerce-square' ),
				'default'  => $this->get_default_description(),
			),

		);

		// Card Security Code (CVV) field
		if ( $this->is_credit_card_gateway() ) {
			$this->form_fields = $this->add_csc_form_fields( $this->form_fields );
		}

		// both credit card authorization & charge supported
		if ( $this->supports_credit_card_authorization() && $this->supports_credit_card_charge() ) {
			$this->form_fields = $this->add_authorization_charge_form_fields( $this->form_fields );
		}

		// card types support
		if ( $this->supports_card_types() ) {
			$this->form_fields = $this->add_card_types_form_fields( $this->form_fields );
		}

		// tokenization support
		if ( $this->supports_tokenization() ) {
			$this->form_fields = $this->add_tokenization_form_fields( $this->form_fields );
		}

		// if there is more than just the production environment available
		if ( count( $this->get_environments() ) > 1 ) {
			$this->form_fields = $this->add_environment_form_fields( $this->form_fields );
		}

		// add the "inherit settings" toggle if there are settings shared with a sibling gateway
		if ( count( $this->shared_settings ) ) {
			$this->form_fields = $this->add_shared_settings_form_fields( $this->form_fields );
		}

		// add unique method fields added by concrete gateway class
		$gateway_form_fields = $this->get_method_form_fields();
		$this->form_fields   = array_merge( $this->form_fields, $gateway_form_fields );

		// add the special 'shared-settings-field' class name to any shared settings fields
		foreach ( $this->shared_settings as $field_name ) {
			$this->form_fields[ $field_name ]['class'] = trim( isset( $this->form_fields[ $field_name ]['class'] ) ? $this->form_fields[ $field_name ]['class'] : '' ) . ' shared-settings-field';
		}

		/**
		 * Payment Gateway Form Fields Filter.
		 *
		 * Actors can use this to add, remove, or tweak gateway form fields
		 *
		 * @since 3.0.0
		 * @param array $form_fields array of form fields in format required by WC_Settings_API
		 * @param Payment_Gateway $this gateway instance
		 */
		$this->form_fields = apply_filters( 'wc_payment_gateway_' . $this->get_id() . '_form_fields', $this->form_fields, $this );
	}


	/**
	 * Returns an array of form fields specific for this method.
	 *
	 * To add environment-dependent fields, include the 'class' form field argument
	 * with 'environment-field production-field' where "production" matches a
	 * key from the environments member
	 *
	 * @since 3.0.0
	 * @return array of form fields
	 */
	abstract protected function get_method_form_fields();


	/**
	 * Adds the gateway environment form fields
	 *
	 * @since 3.0.0
	 * @param array $form_fields gateway form fields
	 * @return array $form_fields gateway form fields
	 */
	protected function add_environment_form_fields( $form_fields ) {

		$form_fields['environment'] = array(
			/* translators: environment as in a software environment (test/production) */
			'title'    => esc_html__( 'Environment', 'woocommerce-square' ),
			'type'     => 'select',
			'default'  => key( $this->get_environments() ),  // default to first defined environment
			'desc_tip' => esc_html__( 'Select the gateway environment to use for transactions.', 'woocommerce-square' ),
			'options'  => $this->get_environments(),
		);

		return $form_fields;
	}


	/**
	 * Adds the optional shared settings toggle element.  The 'shared_settings'
	 * optional constructor parameter must have been used in order for shared
	 * settings to be supported.
	 *
	 * @since 3.0.0
	 * @see Payment_Gateway::$shared_settings
	 * @see Payment_Gateway::$inherit_settings
	 * @param array $form_fields gateway form fields
	 * @return array $form_fields gateway form fields
	 */
	protected function add_shared_settings_form_fields( $form_fields ) {

		// get any sibling gateways
		$other_gateway_ids                  = array_diff( $this->get_plugin()->get_gateway_ids(), array( $this->get_id() ) );
		$configured_other_gateway_ids       = array();
		$inherit_settings_other_gateway_ids = array();

		// determine if any sibling gateways have any configured shared settings
		foreach ( $other_gateway_ids as $other_gateway_id ) {

			$other_gateway_settings = $this->get_plugin()->get_gateway_settings( $other_gateway_id );

			// if the other gateway isn't also trying to inherit settings...
			if ( isset( $other_gateway_settings['inherit_settings'] ) && 'yes' == $other_gateway_settings['inherit_settings'] ) {
				$inherit_settings_other_gateway_ids[] = $other_gateway_id;
			}

			foreach ( $this->shared_settings as $setting_name ) {

				// if at least one shared setting is configured in the other gateway
				if ( isset( $other_gateway_settings[ $setting_name ] ) && $other_gateway_settings[ $setting_name ] ) {

					$configured_other_gateway_ids[] = $other_gateway_id;
					break;
				}
			}
		}

		$form_fields['connection_settings'] = array(
			'title' => esc_html__( 'Connection Settings', 'woocommerce-square' ),
			'type'  => 'title',
		);

		// disable the field if the sibling gateway is already inheriting settings
		$form_fields['inherit_settings'] = array(
			'title'       => esc_html__( 'Share connection settings', 'woocommerce-square' ),
			'type'        => 'checkbox',
			'label'       => esc_html__( 'Use connection/authentication settings from other gateway', 'woocommerce-square' ),
			'default'     => count( $configured_other_gateway_ids ) > 0 ? 'yes' : 'no',
			'disabled'    => count( $inherit_settings_other_gateway_ids ) > 0 ? true : false,
			'description' => count( $inherit_settings_other_gateway_ids ) > 0 ? esc_html__( 'Disabled because the other gateway is using these settings', 'woocommerce-square' ) : '',
		);

		return $form_fields;
	}


	/**
	 * Adds the enable Card Security Code form fields
	 *
	 * @since 3.0.0
	 * @param array $form_fields gateway form fields
	 * @return array $form_fields gateway form fields
	 */
	protected function add_csc_form_fields( $form_fields ) {

		$form_fields['enable_csc'] = array(
			'title'   => esc_html__( 'Card Verification (CSC)', 'woocommerce-square' ),
			'label'   => esc_html__( 'Display the Card Security Code (CV2) field on checkout', 'woocommerce-square' ),
			'type'    => 'checkbox',
			'default' => 'yes',
		);

		if ( $this->supports_tokenization() ) {

			$form_fields['enable_token_csc'] = array(
				'title'   => esc_html__( 'Saved Card Verification', 'woocommerce-square' ),
				'label'   => esc_html__( 'Display the Card Security Code field when paying with a saved card', 'woocommerce-square' ),
				'type'    => 'checkbox',
				'default' => 'yes',
			);
		}

		return $form_fields;
	}


	/**
	 * Displays settings page with some additional javascript for hiding conditional fields.
	 *
	 * @see \WC_Settings_API::admin_options()
	 *
	 * @since 3.0.0
	 */
	public function admin_options() {

		parent::admin_options();

		?>
		<style type="text/css">.nowrap { white-space: nowrap; }</style>
		<?php

		if ( isset( $this->form_fields['enable_csc'] ) ) {

			// add inline javascript to show/hide any shared settings fields as needed
			ob_start();
			?>
				$( '#woocommerce_<?php echo esc_js( $this->get_id() ); ?>_enable_csc' ).change( function() {

					var enabled = $( this ).is( ':checked' );

					if ( enabled ) {
						$( '#woocommerce_<?php echo esc_js( $this->get_id() ); ?>_enable_token_csc' ).closest( 'tr' ).show();
					} else {
						$( '#woocommerce_<?php echo esc_js( $this->get_id() ); ?>_enable_token_csc' ).closest( 'tr' ).hide();
					}

				} ).change();
			<?php

			wc_enqueue_js( ob_get_clean() );

		}

		// if transaction types are supported, show/hide the "charge virtual-only" setting
		if ( isset( $this->form_fields['transaction_type'] ) ) {

			// add inline javascript
			ob_start();
			?>
				$( '#woocommerce_<?php echo esc_js( $this->get_id() ); ?>_transaction_type' ).change( function() {

					var transaction_type = $( this ).val();
					var hidden_settings   = $( '#woocommerce_<?php echo esc_js( $this->get_id() ); ?>_charge_virtual_orders, #woocommerce_<?php echo esc_js( $this->get_id() ); ?>_enable_partial_capture, #woocommerce_<?php echo esc_js( $this->get_id() ); ?>_enable_paid_capture' ).closest( 'tr' );

					if ( '<?php echo esc_js( self::TRANSACTION_TYPE_AUTHORIZATION ); ?>' === transaction_type ) {
						$( hidden_settings ).show();
					} else {
						$( hidden_settings ).hide();
					}

				} ).change();
			<?php

			wc_enqueue_js( ob_get_clean() );
		}

		// if there's more than one environment include the environment settings switcher code
		if ( count( $this->get_environments() ) > 1 ) {

			// add inline javascript
			ob_start();
			?>
				$( '#woocommerce_<?php echo esc_js( $this->get_id() ); ?>_environment' ).change( function() {

					// inherit settings from other gateway?
					var inheritSettings = $( '#woocommerce_<?php echo esc_js( $this->get_id() ); ?>_inherit_settings' ).is( ':checked' );

					var environment = $( this ).val();

					// hide all environment-dependant fields
					$( '.environment-field' ).closest( 'tr' ).hide();

					// show the currently configured environment fields that are not also being hidden as any shared settings
					var $environmentFields = $( '.' + environment + '-field' );
					if ( inheritSettings ) {
						$environmentFields = $environmentFields.not( '.shared-settings-field' );
					}

					$environmentFields.not( '.hidden' ).closest( 'tr' ).show();

				} ).change();
			<?php

			wc_enqueue_js( ob_get_clean() );

		}

		if ( ! empty( $this->shared_settings ) ) {

			// add inline javascript to show/hide any shared settings fields as needed
			ob_start();
			?>
				$( '#woocommerce_<?php echo esc_js( $this->get_id() ); ?>_inherit_settings' ).change( function() {

					var enabled = $( this ).is( ':checked' );

					if ( enabled ) {
						$( '.shared-settings-field' ).closest( 'tr' ).hide();
					} else {
						// show the fields
						$( '.shared-settings-field' ).closest( 'tr' ).show();

						// hide any that may not be available for the currently selected environment
						$( '#woocommerce_<?php echo esc_js( $this->get_id() ); ?>_environment' ).change();
					}

				} ).change();
			<?php

			wc_enqueue_js( ob_get_clean() );

		}

	}


	/**
	 * Checks for proper gateway configuration including:
	 *
	 * + gateway enabled
	 * + correct configuration (gateway specific)
	 * + any dependencies met
	 * + required currency
	 * + required country
	 *
	 * @since 3.0.0
	 * @see WC_Payment_Gateway::is_available()
	 * @return true if this gateway is available for checkout, false otherwise
	 */
	public function is_available() {

		// is enabled check
		$is_available = parent::is_available();

		// proper configuration
		if ( ! $this->is_configured() ) {
			$is_available = false;
		}

		// all plugin dependencies met
		if ( count( $this->get_plugin()->get_dependency_handler()->get_missing_php_extensions() ) > 0 ) {
			$is_available = false;
		}

		// any required currencies?
		if ( ! $this->currency_is_accepted() ) {
			$is_available = false;
		}

		// any required countries?
		if ( $this->countries && WC()->customer ) {

			$customer_country = WC()->customer->get_billing_country();

			if ( $customer_country && ! in_array( $customer_country, $this->countries, true ) ) {
				$is_available = false;
			}
		}

		/**
		 * Payment Gateway Is Available Filter.
		 *
		 * Allow actors to modify whether the gateway is available or not.
		 *
		 * @since 3.0.0
		 * @param bool $is_available
		 */
		return apply_filters( 'wc_gateway_' . $this->get_id() . '_is_available', $is_available );
	}


	/**
	 * Returns true if the gateway is properly configured to perform transactions
	 *
	 * @since 3.0.0
	 * @see Payment_Gateway::is_configured()
	 * @return boolean true if the gateway is properly configured
	 */
	protected function is_configured() {
		// override this to check for gateway-specific required settings (user names, passwords, secret keys, etc)
		return true;
	}

	/**
	 * Returns the payment method image URL (if any) for the given $type, ie
	 * if $type is 'amex' a URL to the american express card icon will be
	 * returned.
	 *
	 * @since 3.0.0
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
		 * @since 3.0.0
		 * @param bool $use_svg true by default, false to use PNGs
		 */
		$image_extension = apply_filters( 'wc_payment_gateway_square_use_svg', true ) ? '.svg' : '.png';

		// first, is the card image available within the plugin?
		if ( is_readable( $this->get_plugin()->get_payment_gateway_framework_assets_path() . '/images/card-' . $image_type . $image_extension ) ) {
			return \WC_HTTPS::force_https_url( $this->get_plugin()->get_payment_gateway_framework_assets_url() . '/images/card-' . $image_type . $image_extension );
		}

		// default: is the card image available within the framework?
		if ( is_readable( $this->get_plugin()->get_payment_gateway_framework_assets_path() . '/images/card-' . $image_type . $image_extension ) ) {
			return \WC_HTTPS::force_https_url( $this->get_plugin()->get_payment_gateway_framework_assets_url() . '/images/card-' . $image_type . $image_extension );
		}

		return null;
	}


	/**
	 * Add payment and transaction information as class members of WC_Order
	 * instance.  The standard information that can be added includes:
	 *
	 * $order->payment_total           - the payment total
	 * $order->customer_id             - optional payment gateway customer id (useful for tokenized payments, etc)
	 * $order->payment->type           - one of 'credit_card' or 'check'
	 * $order->description             - an order description based on the order
	 * $order->unique_transaction_ref  - a combination of order number + retry count, should provide a unique value for each transaction attempt
	 *
	 * Note that not all gateways will necessarily pass or require all of the
	 * above.  These represent the most common attributes used among a variety
	 * of gateways, it's up to the specific gateway implementation to make use
	 * of, or ignore them, or add custom ones by overridding this method.
	 *
	 * The returned order is expected to be used in a transaction request.
	 *
	 * @since 3.0.0
	 * @param int|WC_Order_Square $order the order or order ID being processed
	 * @return WC_Order_Square object with payment and transaction information attached
	 */
	public function get_order( $order ) {
		if ( is_numeric( $order ) ) {
			$order = WC_Order_Square::wc_get_order( $order );
		}

		// set payment total here so it can be modified for later by add-ons like subscriptions which may need to charge an amount different than the get_total()
		$order->payment_total = number_format( $order->get_total(), 2, '.', '' );

		$order->customer_id = '';

		// logged in customer?
		$customer_id = $this->get_customer_id( $order->get_user_id(), array( 'order' => $order ) );
		if ( 0 !== $order->get_user_id() && false !== $customer_id ) {
			$order->customer_id = $customer_id;
		}

		// add payment info
		$order->payment = new \stdClass();

		// payment type (credit_card/check/etc)
		$order->payment->type = str_replace( '-', '_', $this->get_payment_type() );

		/* translators: Placeholders: %1$s - site title, %2$s - order number */
		$order->description = sprintf( esc_html__( '%1$s - Order %2$s', 'woocommerce-square' ), wp_specialchars_decode( Square_Helper::get_site_name(), ENT_QUOTES ), $order->get_order_number() );

		$order = $this->get_order_with_unique_transaction_ref( $order );

		/**
		 * Filters the base order for a payment transaction.
		 *
		 * Actors can use this filter to adjust or add additional information to
		 * the order object that gateways use for processing transactions.
		 *
		 * @since 3.0.0
		 *
		 * @param WC_Order_Square $order order object
		 * @param Payment_Gateway $this payment gateway instance
		 */
		return apply_filters( 'wc_payment_gateway_' . $this->get_id() . '_get_order_base', $order, $this );
	}

	/**
	 * Performs a transaction for the given order and returns the result.
	 *
	 * @since 4.6.0
	 *
	 * @param WC_Order_Square     $order the order object
	 * @param Create_Payment|null $response optional gateway transaction response
	 * @return Create_Payment     the response
	 * @throws \Exception network timeouts, etc
	 */
	abstract protected function do_payment_method_transaction( $order, $response = null );

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
				/* translators: Placeholders: %1$s - environment ("Test"), %2$s - transaction type (authorization/charge), %3$s - amount, %4$s - last four digits of the card */
				esc_html__( 'Square Gift Card %1$s %2$s Approved for an amount of %3$s: Gift Card ending in %4$s', 'woocommerce-square' ),
				wc_square()->get_settings_handler()->is_sandbox() ? esc_html_x( 'Test', 'noun, software environment', 'woocommerce-square' ) : '',
				'APPROVED' === $response->get_payment()->getStatus() ? esc_html_x( 'Authorization', 'credit card transaction type', 'woocommerce-square' ) : esc_html_x( 'Charge', 'noun, credit card transaction type', 'woocommerce-square' ),
				wc_price( Money_Utility::cents_to_float( $payment->getTotalMoney()->getAmount(), $order->get_currency() ) ),
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
			 * @param Create_Payment $response transaction response
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

			// If payment is partial, i.e.; Gift Card is used along with a Square Credit Card or Cash App Pay, then save the partial totals
			// in a `$order->payment->partial_total` property.
			if ( $is_partial_payment ) {
				$order->payment->partial_total                = new \stdClass();
				$order->payment->partial_total->gift_card     = $this->get_partial_total_on_gift_card();
				$order->payment->partial_total->other_gateway = $this->get_partial_total_on_other_gateway();

				// If the sum of partial totals is not equal to the order total, show an error.
				if ( ! $this->verify_order_total( $order ) ) {
					Square_Helper::wc_add_notice( esc_html__( 'The sum of partial totals does not match the order total.', 'woocommerce-square' ), 'error' );
					return false;
				}

				// Save the charge type as `PARTIAL`.
				$this->update_order_meta( $order, 'charge_type', self::CHARGE_TYPE_PARTIAL );
			}

			// Perform a Gift Card transaction.
			$gift_card_response = $this->do_gift_card_transaction( $order );

			// If the payment is partial, then perform a other payment method (Square Credit Card/Cash App Pay) transaction as well.
			if ( $is_partial_payment ) {
				// Bail if the Gift Card transaction fails.
				if ( ! $gift_card_response->transaction_approved() && ! $gift_card_response->transaction_held() ) {
					return $this->do_transaction_failed_result( $order, $gift_card_response );
				}

				$payment_method_response = $this->do_payment_method_transaction( $order );

				// Handle responses when partial payments are made using either Gift and Square Credit Card/Cash App Pay.
				return $this->handle_multi_payment_methods( $gift_card_response, $payment_method_response, $order );
			} else {
				// Handle response when payment is made using either Gift or Square Credit Card.
				return $this->handle_single_payment_method( $gift_card_response, $order );
			}
		} elseif ( $this->is_credit_card_gateway() || $this->is_cash_app_pay_gateway() ) {
			$response = $this->do_payment_method_transaction( $order );
		} else {
			$do_payment_type_transaction = 'do_' . $this->get_payment_type() . '_transaction';
			$response                    = $this->$do_payment_type_transaction( $order );
		}

		// Handle response when payment is made using a Square Credit Card.
		return $this->handle_single_payment_method( $response, $order );
	}

	/**
	 * Stores gift card details as order meta.
	 *
	 * @since 4.2.0
	 *
	 * @param \Square\Models\Order $square_order
	 * @param \WC_Order $order
	 */
	public function maybe_save_gift_card_order_details( $square_order, $order ) {
		$line_items = $square_order->getLineItems();

		/** @var \Square\Models\OrderLineItem */
		foreach ( $line_items as $line_item ) {
			if ( \Square\Models\OrderLineItemItemType::GIFT_CARD !== $line_item->getItemType() ) {
				continue;
			}

			$gift_card_line_item_id = $line_item->getUid();
			$gift_card_amount       = Square_Helper::number_format(
				Money_Utility::cents_to_float(
					$line_item->getTotalMoney()->getAmount()
				)
			);

			$this->update_order_meta( $order, 'gift_card_line_item_id', wc_clean( $gift_card_line_item_id ) );
			$this->update_order_meta( $order, 'gift_card_balance', wc_clean( $gift_card_amount ) );
			$this->update_order_meta( $order, 'is_gift_card_purchased', 'yes' );
		}
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
		$this->update_order_meta( $order, 'gift_card_number', wc_clean( $gan ) );

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

		return true;
	}

	/**
	 * Returns true if the order total equals to the sum of partial totals.
	 * False otherwise.
	 *
	 * @param \WC_Order $order WooCommerce order object.
	 * @return boolean
	 */
	protected function verify_order_total( $order ) {
		$order_total         = (float) $order->get_total();
		$gift_card_total     = $order->payment->partial_total->gift_card;
		$other_gateway_total = $order->payment->partial_total->other_gateway;

		return $order_total === $gift_card_total + $other_gateway_total;
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
			if ( ! $this->is_cash_app_pay_gateway() && $response->is_gift_card_payment() ) {
				$this->maybe_tokenize( $response, $order );
			}

			// add the standard transaction data
			$this->add_transaction_data( $order, $response );

			// allow the concrete class to add any gateway-specific transaction data to the order
			$this->add_payment_gateway_transaction_data( $order, $response );

			// if the transaction was held (ie fraud validation failure) mark it as such
			// TODO: consider checking whether the response *was* an authorization, rather than blanket-assuming it was because of the settings.  There are times when an auth will be used rather than charge, ie when performing in-plugin AVS handling (moneris)
			if ( $response->transaction_held() || ( $this->supports( self::FEATURE_AUTHORIZATION ) && $this->perform_authorization( $order ) ) ) {
				// TODO: need to make this more flexible, and not force the message to 'Authorization only transaction' for auth transactions (re moneris efraud handling)
				/* translators: This is a message describing that the transaction in question only performed a credit card authorization and did not capture any funds. */
				$this->mark_order_as_held( $order, $this->supports( self::FEATURE_AUTHORIZATION ) && $this->perform_authorization( $order ) ? esc_html__( 'Authorization only transaction', 'woocommerce-square' ) : $response->get_status_message(), $response );
			}

			return true;

		} else {
			return $this->do_transaction_failed_result( $order, $response );
		}
	}

	/**
	 * Handles responses when the order total payment is split between a Gift and Square Credit Card.
	 *
	 * @param Create_Payment $gift_card_response      Gift Card payment response.
	 * @param Create_Payment $payment_method_response Other Payment Method payment response.
	 * @param \WC_Order      $order                   WooCommerce Order object.
	 */
	protected function handle_multi_payment_methods( $gift_card_response, $payment_method_response, $order ) {
		if ( $payment_method_response->transaction_approved() || $payment_method_response->transaction_held() ) {
			$this->maybe_tokenize( $payment_method_response, $order );

			// add the standard transaction data
			$payment_method = str_replace( 'square_', '', $this->get_id() );
			$this->add_transaction_data( $order, $payment_method_response, $payment_method, true );

			// allow the concrete class to add any gateway-specific transaction data to the order
			$this->add_payment_gateway_transaction_data( $order, $payment_method_response );
		} else {
			// Cancel the Gift Card transaction if the other payment method transaction fails.
			$this->gift_card_cancel_payment( $order, $gift_card_response );

			return $this->do_transaction_failed_result( $order, $payment_method_response );
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
			$payment_method_response->get_transaction_id(),
		);

		if ( $this->supports( self::FEATURE_AUTHORIZATION ) && $this->perform_authorization( $order ) ) {
			$this->mark_order_as_held( $order, $this->supports( self::FEATURE_AUTHORIZATION ) && $this->perform_authorization( $order ) ? esc_html__( 'Authorization only transaction', 'woocommerce-square' ) : $payment_method_response->get_status_message(), $payment_method_response );
			$this->update_order_meta( $order, 'other_gateway_partial_total', $order->payment->partial_total->other_gateway );
			$this->update_order_meta( $order, 'gift_card_partial_total', $order->payment->partial_total->gift_card );
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
				$this->update_order_meta( $order, 'other_gateway_partial_total', $order->payment->partial_total->other_gateway );
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

	/**
	 * Cancel authorized gift card payment.
	 *
	 * This method cancels gift card payment, and adds a relevant order note.
	 *
	 * @since 4.6.0
	 *
	 * @param \WC_Order $order order object
	 * @param Payment_Gateway_API_Response $response response object
	 * @throws \Exception
	 */
	protected function gift_card_cancel_payment( \WC_Order $order, Payment_Gateway_API_Response $response ) {
		// Cancel the Gift Card transaction if it is approved and  the other payment method transaction fails.
		if ( $response->transaction_approved() || $response->transaction_held() ) {
			$cancel_payment = $this->get_api()->cancel_payment( $response->get_transaction_id() );

			if ( $cancel_payment && ! $cancel_payment->has_errors() ) {
				// Add an order note to inform the user that the Gift Card payment is cancelled.
				$message = sprintf(
					/* translators: Placeholders: %1$s - transaction ID */
					esc_html__( 'Square Gift Card payment cancelled (Transaction ID: %1$s)', 'woocommerce-square' ),
					$response->get_transaction_id()
				);

				$order->add_order_note( $message );
			}
		}
	}

	/**
	 * Completes an order payment.
	 *
	 * This method marks the order with an appropriate status, and adds a relevant order note.
	 *
	 * @since 3.0.0
	 *
	 * @param \WC_Order $order order object
	 * @param Payment_Gateway_API_Response $response response object
	 * @throws \Exception
	 */
	protected function complete_payment( \WC_Order $order, Payment_Gateway_API_Response $response ) {

		if ( self::PAYMENT_TYPE_CREDIT_CARD == $response->get_payment_type() ) {
			$order->add_order_note( $this->get_credit_card_transaction_approved_message( $order, $response ) );
		} else {
			$message_method = 'get_' . $response->get_payment_type() . '_transaction_approved_message';

			if ( is_callable( array( $this, $message_method ) ) ) {
				$order->add_order_note( $this->$message_method( $order, $response ) );
			}
		}

		if ( $response->transaction_held() || ( $this->supports_credit_card_authorization() && $this->perform_credit_card_authorization( $order ) ) ) {

			$message = $this->supports_credit_card_authorization() && $this->perform_credit_card_authorization( $order ) ? __( 'Authorization only transaction', 'woocommerce-square' ) : $response->get_status_message();

			$this->mark_order_as_held( $order, $message, $response );

			wc_reduce_stock_levels( $order->get_id() );

		} else {

			$order->payment_complete();
		}

		/**
		 * Fires after a payment transaction is successfully completed.
		 *
		 * @since 3.0.0
		 *
		 * @param \WC_Order $order order object
		 * @param Payment_Gateway $gateway gateway object
		 */
		do_action( 'wc_payment_gateway_' . $this->get_id() . '_complete_payment', $order, $this );
	}


	/** Capture Methods ***********************************************************************************************/


	/**
	 * Builds the capture handler instance.
	 *
	 * @since 3.0.0
	 */
	public function init_capture_handler() {

		$this->capture_handler = new Capture( $this );
	}


	/**
	 * Gets the capture handler instance.
	 *
	 * @since 3.0.0
	 *
	 * @return Payment_Gateway\Handlers\Capture
	 */
	public function get_capture_handler() {

		return $this->capture_handler;
	}


	/**
	 * Gets an order object with payment data added for use in credit card capture transactions.
	 *
	 * This was intentionally not moved to the capture handler since we'll likely be refactoring how this information is
	 * set in the future, and plenty of gateways override it.
	 *
	 * @since 3.0.0
	 *
	 * @param \WC_Square_Order|int $order the order being processed
	 * @param float|null $amount amount to capture or null for the full order amount
	 * @return WC_Order_Square
	 */
	public function get_order_for_capture( $order, $amount = null ) {
		if ( is_numeric( $order ) ) {
			$order = WC_Order_Square::wc_get_order( $order );
		}

		// add capture info
		$order->capture = new \stdClass();

		$total_captured = $this->get_order_meta( $order, 'capture_total' );

		// if no amount is specified, as in a bulk capture situation, always use the amount remaining
		if ( ! $amount ) {
			$amount = (float) $order->get_total() - (float) $total_captured;
		}

		$order->capture->amount = Square_Helper::number_format( $amount );

		/* translators: Placeholders: %1$s - site title, %2$s - order number. Definitions: Capture as in capture funds from a credit card. */
		$order->capture->description = sprintf( esc_html__( '%1$s - Capture for Order %2$s', 'woocommerce-square' ), wp_specialchars_decode( Square_Helper::get_site_name() ), $order->get_order_number() );
		$order->capture->trans_id    = $this->get_order_meta( Order_Compatibility::get_prop( $order, 'id' ), 'trans_id' );

		/**
		 * Direct Gateway Capture Get Order Filter.
		 *
		 * Allow actors to modify the order object used for performing charge captures.
		 *
		 * @since 3.0.0
		 * @param WC_Order_Square $order order object
		 * @param Payment_Gateway $this instance
		 */
		return apply_filters( 'wc_payment_gateway_' . $this->get_id() . '_get_order_for_capture', $order, $this );
	}

	/**
	* Processes a refund.
	*
	* @since 3.0.0
	*
	* @param int $order_id order being refunded
	* @param float $amount refund amount
	* @param string $reason user-entered reason text for refund
	* @return bool|\WP_Error true on success, or a WP_Error object on failure/error
	*/
	public function process_refund( $order_id, $amount = null, $reason = '' ) {

		// add transaction-specific refund info (amount, reason, transaction IDs, etc)
		$order = $this->get_order_for_refund( $order_id, $amount, $reason );

		// let implementations/actors error out early (e.g. order is missing required data for refund, etc)
		if ( is_wp_error( $order ) ) {
			return $order;
		}

		// if captures are supported and the order has an authorized, but not captured charge, void it instead
		if ( $this->supports_voids() && ! $this->get_capture_handler()->is_order_captured( $order ) ) {
			return $this->process_void( $order );
		}

		$refund_amount       = (float) $amount;
		$refund_payment_data = array();

		// If payment is split between a gift card and a square credit card.
		if ( $this->get_order_meta( $order, 'charge_type' ) === self::CHARGE_TYPE_PARTIAL ) {
			$gift_card_partial_total         = (float) $this->get_order_meta( $order, 'gift_card_partial_total' );
			$gift_card_recorded_refund_total = (float) $this->get_order_meta( $order, 'gift_card_recorded_refund_total' );
			$gift_card_recorded_refund_total = empty( $gift_card_recorded_refund_total ) ? 0 : $gift_card_recorded_refund_total;

			// If refund amount is less than the amount charged on the gift card, then refund the total refund amount to Gift card.
			if ( $refund_amount <= $gift_card_partial_total - $gift_card_recorded_refund_total ) {
				$refund_payment_data[] = array(
					'amount'       => $refund_amount,
					'tender_id'    => $this->get_order_meta( $order, 'gift_card_trans_id' ),
					'payment_type' => 'gift_card',
				);

				// Record the total amount that has been refunded to the Gift card.
				$this->update_order_meta( $order, 'gift_card_recorded_refund_total', $gift_card_recorded_refund_total + $refund_amount );
				// Else, if the refund amount is more than the amount charged on the Gift Card, then prioritize refunding the
				// total amount charged on the Gift Card first. Then, refund the remaining amount to the Square Gift Card.
			} else {
				if ( $gift_card_partial_total - $gift_card_recorded_refund_total > 0 ) {
					$refund_payment_data[] = array(
						'amount'       => $gift_card_partial_total - $gift_card_recorded_refund_total,
						'tender_id'    => $this->get_order_meta( $order, 'gift_card_trans_id' ),
						'payment_type' => 'gift_card',
					);

					$this->update_order_meta( $order, 'gift_card_recorded_refund_total', $gift_card_partial_total );
				}

				$payment_method        = str_replace( 'square_', '', $this->get_id() );
				$refund_payment_data[] = array(
					'amount'       => $refund_amount - ( $gift_card_partial_total - $gift_card_recorded_refund_total ),
					'tender_id'    => $this->get_order_meta( $order, 'trans_id' ),
					'payment_type' => $payment_method,
				);
			}
			// If payment is not split, then refund the amount.
		} else {
			$refund_payment_data[] = array(
				'amount'       => $order->refund->amount,
				'tender_id'    => $order->refund->tender_id,
				'payment_type' => $this->get_order_tender_types( $order ),
			);
		}

		try {
			foreach ( $refund_payment_data as $refund_data ) {
				$response = $this->get_api()->refund( $order, $refund_data );

				if ( $response->transaction_approved() ) {
					if ( $this->get_order_meta( $order, 'charge_type' ) === self::CHARGE_TYPE_PARTIAL ) {
						$this->add_multi_payment_refund_order_note( $order, $response, $refund_data );
					} else {
						// add standard refund-specific transaction data.
						$this->add_refund_data( $order, $response );

						// add order note.
						$this->add_refund_order_note( $order, $response );
					}
				} else {

					$error = $this->get_refund_failed_wp_error( $response->get_status_code(), $response->get_status_message() );

					$order->add_order_note( $error->get_error_message() );

					return $error;
				}
			}

			$this->maybe_refund_gift_card( $order );
		} catch ( \Exception $e ) {

			$error = $this->get_refund_failed_wp_error( $e->getCode(), $e->getMessage() );

			$order->add_order_note( $error->get_error_message() );

			return $error;
		}

		// when full amount is refunded, update status to refunded.
		if ( (float) $order->get_total() === (float) $order->get_total_refunded() ) {
			$this->mark_order_as_refunded( $order );
		}

		/**
		 * Fires after a refund is successfully processed.
		 *
		 * @since 3.0.0
		 *
		 * @param \WC_Order $order order object
		 * @param Payment_Gateway $gateway payment gateway instance
		 */
		do_action( 'wc_payment_gateway_' . $this->get_id() . '_refund_processed', $order, $this );

		return true;
	}

	/**
	 * Refunds a Gift Card purchase/reload order.
	 *
	 * @since 4.2.0
	 *
	 * @param \WC_Order $order WooCommerce order.
	 */
	public function maybe_refund_gift_card( $order ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is handled in WooCommerce core.
		$line_item_qtys   = isset( $_POST['line_item_qtys'] ) ? json_decode( sanitize_text_field( wp_unslash( $_POST['line_item_qtys'] ) ), true ) : array();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is handled in WooCommerce core.
		$line_item_totals = isset( $_POST['line_item_totals'] ) ? json_decode( sanitize_text_field( wp_unslash( $_POST['line_item_totals'] ) ), true ) : array();
		$line_items       = array();
		$item_ids         = array_unique( array_merge( array_keys( $line_item_qtys ), array_keys( $line_item_totals ) ) );

		foreach ( $item_ids as $item_id ) {
			$line_items[ $item_id ] = array(
				'qty'          => 0,
				'refund_total' => 0,
			);
		}

		foreach ( $line_item_qtys as $item_id => $qty ) {
			$line_items[ $item_id ]['qty'] = max( $qty, 0 );
		}

		foreach ( $line_item_totals as $item_id => $total ) {
			$line_items[ $item_id ]['refund_total'] = wc_format_decimal( $total );
		}

		foreach ( $line_items as $line_item_order_id => $refund_data ) {
			$line_item    = $order->get_item( $line_item_order_id );
			$product_data = $line_item->get_data();
			$product      = wc_get_product( $product_data['product_id'] );

			if ( ! $product instanceof \WC_Product ) {
				continue;
			}

			if ( ! Product::is_gift_card( $product ) ) {
				continue;
			}

			$purchase_type = Order::get_gift_card_purchase_type( $order );

			if ( 'new' === $purchase_type ) {
				$gan = $this->get_order_meta( $order, 'gift_card_number' );
			} elseif ( 'load' === $purchase_type ) {
				$gan = Order::get_gift_card_gan( $order );
			}

			$total = (float) $refund_data['refund_total'];

			if ( ! $total ) {
				continue;
			}

			$amount_money = Money_Utility::amount_to_money( $total, $order->get_currency() );
			$response     = $this->get_api()->refund_gift_card( $gan, $amount_money, $order );

			if ( $response->get_data() instanceof \Square\Models\CreateGiftCardActivityResponse ) {
				/** @var \Square\Models\CreateGiftCardActivityResponse */
				$gift_card_activity_response = $response->get_data();
				$gift_card_activity          = $gift_card_activity_response->getGiftCardActivity();
				$gan                         = $gift_card_activity->getGiftCardGan();
				$decrement_activity_details  = $gift_card_activity->getAdjustDecrementActivityDetails();
				$decrement_money             = $decrement_activity_details->getAmountMoney();
				$decrement_amount            = $decrement_money->getAmount();
				$float_amount                = Money_Utility::cents_to_float( $decrement_amount, $order->get_currency() );

				$order->add_order_note(
					sprintf(
						esc_html__( '-%1$s adjusted from the gift card with number %2$s.', 'woocommerce-square' ),
						wc_price( $float_amount, array( 'currency' => $order->get_currency() ) ),
						esc_html( $gan )
					)
				);
			}
		}
	}

	/**
	 * Add refund information as class members of WC_Order
	 * instance for use in refund transactions.  Standard information includes:
	 *
	 * $order->refund->amount = refund amount
	 * $order->refund->reason = user-entered reason text for the refund
	 * $order->refund->trans_id = the ID of the original payment transaction for the order
	 *
	 * Payment gateway implementations can override this to add their own
	 * refund-specific data
	 *
	 * @since 3.0.0
	 *
	 * @param \WC_Square_Order|int $order order being processed
	 * @param float $amount refund amount
	 * @param string $reason optional refund reason text
	 * @return \WC_Order|\WP_Error object with refund information attached
	 */
	protected function get_order_for_refund( $order, $amount, $reason ) {
		if ( is_numeric( $order ) ) {
			$order = WC_Order_Square::wc_get_order( $order );
		}

		// add refund info
		$order->refund         = new \stdClass();
		$order->refund->amount = number_format( $amount, 2, '.', '' );

		/* translators: Placeholders: %1$s - site title, %2$s - order number */
		$order->refund->reason = $reason ? $reason : sprintf( esc_html__( '%1$s - Refund for Order %2$s', 'woocommerce-square' ), esc_html( Square_Helper::get_site_name() ), $order->get_order_number() );

		// almost all gateways require the original transaction ID, so include it by default
		$order->refund->trans_id = $this->get_order_meta( Order_Compatibility::get_prop( $order, 'id' ), 'trans_id' );

		/**
		 * Payment Gateway Get Order For Refund Filter.
		 *
		 * Allow actors to modify the order object used for refund transactions.
		 *
		 * @since 3.0.0
		 *
		 * @param \WC_Order|\WP_Error $order order object
		 * @param Payment_Gateway $this instance
		 */
		return apply_filters( 'wc_payment_gateway_' . $this->get_id() . '_get_order_for_refund', $order, $this );
	}


	/**
	 * Adds the standard refund transaction data to the order.
	 *
	 * Note that refunds can be performed multiple times for a single order so
	 * transaction IDs keys are not unique
	 *
	 * @since 3.0.0
	 *
	 * @param \WC_Order $order the order object
	 * @param Payment_Gateway_API_Response $response transaction response
	 */
	protected function add_refund_data( \WC_Order $order, $response ) {

		// indicate the order was refunded along with the refund amount
		$this->add_order_meta( $order, 'refund_amount', $order->refund->amount );

		// add refund transaction ID
		if ( $response && $response->get_transaction_id() ) {
			$this->add_order_meta( $order, 'refund_trans_id', $response->get_transaction_id() );
		}
	}

	/**
	 * Adds an order note with the amount and (optional) refund transaction ID.
	 *
	 * @since 3.0.0
	 *
	 * @param \WC_Order $order order object
	 * @param Payment_Gateway_API_Response $response transaction response
	 */
	protected function add_refund_order_note( \WC_Order $order, $response ) {

		$message = sprintf(
			/* translators: Placeholders: %1$s - payment gateway title (such as Authorize.net, Braintree, etc), %2$s - a monetary amount */
			esc_html__( '%1$s Refund in the amount of %2$s approved.', 'woocommerce-square' ),
			$this->get_method_title(),
			wc_price( $order->refund->amount, array( 'currency' => Order_Compatibility::get_prop( $order, 'currency', 'view' ) ) )
		);

		// adds the transaction id (if any) to the order note
		if ( $response->get_transaction_id() ) {
			$message .= ' ' . sprintf( esc_html__( '(Transaction ID %s)', 'woocommerce-square' ), $response->get_transaction_id() );
		}

		$order->add_order_note( $message );
	}

	/**
	 * Adds an order note with the amount and (optional) refund transaction ID.
	 *
	 * @since 3.0.0
	 *
	 * @param \WC_Order $order order object
	 * @param Payment_Gateway_API_Response $response transaction response
	 */
	protected function add_multi_payment_refund_order_note( \WC_Order $order, $response, $payment_data = array() ) {
		$method = $this->get_method_title();
		if ( 'gift_card' === $payment_data['payment_type'] ) {
			$method = esc_html__( 'Square Gift Card', 'woocommerce-square' );
		} elseif ( 'credit_card' === $payment_data['payment_type'] ) {
			$method = esc_html__( 'Square Credit Card', 'woocommerce-square' );
		}

		$message = sprintf(
			/* translators: Placeholders: %1$s - payment gateway title (such as Authorize.net, Braintree, etc), %2$s - a monetary amount, %3$s Total refund amount. */
			esc_html__( '%1$s Refund in the amount of %2$s of total %3$s approved.', 'woocommerce-square' ),
			$method,
			wc_price( $payment_data['amount'], array( 'currency' => Order_Compatibility::get_prop( $order, 'currency', 'view' ) ) ),
			wc_price( $order->refund->amount, array( 'currency' => Order_Compatibility::get_prop( $order, 'currency', 'view' ) ) )
		);

		// adds the transaction id (if any) to the order note
		if ( $response->get_transaction_id() ) {
			/* translators %s transaction ID. */
			$message .= ' ' . sprintf( esc_html__( '(Transaction ID %s)', 'woocommerce-square' ), $response->get_transaction_id() );
		}

		$order->add_order_note( $message );
	}


	/**
	 * Builds the WP_Error object for a failed refund.
	 *
	 * @since 3.0.0
	 *
	 * @param int|string $error_code error code
	 * @param string $error_message error message
	 * @return \WP_Error suitable for returning from the process_refund() method
	 */
	protected function get_refund_failed_wp_error( $error_code, $error_message ) {

		if ( $error_code ) {
			$message = sprintf(
				/* translators: Placeholders: %1$s - payment gateway title (such as Authorize.net, Braintree, etc), %2$s - error code, %3$s - error message */
				esc_html__( '%1$s Refund Failed: %2$s - %3$s', 'woocommerce-square' ),
				$this->get_method_title(),
				$error_code,
				$error_message
			);
		} else {
			$message = sprintf(
				/* translators: Placeholders: %1$s - payment gateway title (such as Authorize.net, Braintree, etc), %2$s - error message */
				esc_html__( '%1$s Refund Failed: %2$s', 'woocommerce-square' ),
				$this->get_method_title(),
				$error_message
			);
		}

		return new \WP_Error( 'wc_' . $this->get_id() . '_refund_failed', $message );
	}


	/**
	 * Mark an order as refunded. This should only be used when the full order
	 * amount has been refunded.
	 *
	 * @since 3.0.0
	 *
	 * @param \WC_Order $order order object
	 */
	public function mark_order_as_refunded( $order ) {

		/* translators: Placeholders: %s - payment gateway title (such as Authorize.net, Braintree, etc) */
		$order_note = sprintf( esc_html__( '%s Order completely refunded.', 'woocommerce-square' ), $this->get_method_title() );

		// Mark order as refunded if not already set
		if ( ! $order->has_status( 'refunded' ) ) {
			$order->update_status( 'refunded', $order_note );
		} else {
			$order->add_order_note( $order_note );
		}
	}


	/** Void feature ********************************************************/


	/**
	 * Returns true if this is gateway that supports voids
	 *
	 * @since 3.0.0
	 * @return boolean true if the gateway supports voids
	 */
	public function supports_voids() {

		return $this->supports( self::FEATURE_VOIDS ) && $this->supports_credit_card_capture();
	}


	/**
	 * Allow gateways to void an order that was attempted to be refunded. This is
	 * particularly useful for gateways that can void an authorized & captured
	 * charge that has not yet settled (e.g. Authorize.net AIM/CIM)
	 *
	 * @since 3.0.0
	 *
	 * @param \WC_Order $order order
	 * @param Payment_Gateway_API_Response $response refund response
	 * @return boolean true if a void should be performed for the given order/response
	 */
	protected function maybe_void_instead_of_refund( $order, $response ) {

		return false;
	}


	/**
	 * Processes a void order.
	 *
	 * @since 3.0.0
	 *
	 * @param \WC_Order $order order object (with refund class member already added)
	 * @return bool|\WP_Error true on success, or a WP_Error object on failure/error
	 */
	protected function process_void( \WC_Order $order ) {

		// partial voids are not supported
		if ( $order->refund->amount !== $order->get_total() ) {
			return new \WP_Error( 'wc_' . $this->get_id() . '_void_error', esc_html__( 'Oops, you cannot partially void this order. Please use the full order amount.', 'woocommerce-square' ), 500 );
		}

		try {

			$response = $this->get_api()->void( $order );

			if ( $response->transaction_approved() ) {

				// add standard void-specific transaction data
				$this->add_void_data( $order, $response );

				// update order status to "refunded" and add an order note
				$this->mark_order_as_voided( $order, $response );

				return true;

			} else {

				$error = $this->get_void_failed_wp_error( $response->get_status_code(), $response->get_status_message() );

				$order->add_order_note( $error->get_error_message() );

				return $error;
			}
		} catch ( \Exception $e ) {

			$error = $this->get_void_failed_wp_error( $e->getCode(), $e->getMessage() );

			$order->add_order_note( $error->get_error_message() );

			return $error;
		}
	}


	/**
	 * Adds the standard void transaction data to the order.
	 *
	 * @since 3.0.0
	 *
	 * @param \WC_Order $order the order object
	 * @param Payment_Gateway_API_Response $response transaction response
	 */
	protected function add_void_data( \WC_Order $order, $response ) {

		// indicate the order was voided along with the amount
		$this->update_order_meta( $order, 'void_amount', $order->refund->amount );

		// add refund transaction ID
		if ( $response && $response->get_transaction_id() ) {
			$this->add_order_meta( $order, 'void_trans_id', $response->get_transaction_id() );
		}
	}

	/**
	 * Builds the WP_Error object for a failed void.
	 *
	 * @since 3.0.0
	 *
	 * @param int|string $error_code error code
	 * @param string $error_message error message
	 * @return \WP_Error suitable for returning from the process_refund() method
	 */
	protected function get_void_failed_wp_error( $error_code, $error_message ) {

		if ( $error_code ) {
			$message = sprintf(
				/* translators: Placeholders: %1$s - payment gateway title, %2$s - error code, %3$s - error message. Void as in to void an order. */
				esc_html__( '%1$s Void Failed: %2$s - %3$s', 'woocommerce-square' ),
				$this->get_method_title(),
				$error_code,
				$error_message
			);
		} else {
			$message = sprintf(
				/* translators: Placeholders: %1$s - payment gateway title, %2$s - error message. Void as in to void an order. */
				esc_html__( '%1$s Void Failed: %2$s', 'woocommerce-square' ),
				$this->get_method_title(),
				$error_message
			);
		}

		return new \WP_Error( 'wc_' . $this->get_id() . '_void_failed', $message );
	}


	/**
	 * Marks an order as voided.
	 *
	 * Because WC has no status for "void", we use refunded.
	 *
	 * @since 3.0.0
	 *
	 * @param \WC_Order $order order object
	 * @param Payment_Gateway_API_Response $response object
	 */
	public function mark_order_as_voided( $order, $response ) {

		$message = sprintf(
			/* translators: Placeholders: %1$s - payment gateway title, %2$s - a monetary amount. Void as in to void an order. */
			esc_html__( '%1$s Void in the amount of %2$s approved.', 'woocommerce-square' ),
			$this->get_method_title(),
			wc_price( $order->refund->amount, array( 'currency' => Order_Compatibility::get_prop( $order, 'currency', 'view' ) ) )
		);

		// adds the transaction id (if any) to the order note
		if ( $response->get_transaction_id() ) {
			$message .= ' ' . sprintf( esc_html__( '(Transaction ID %s)', 'woocommerce-square' ), $response->get_transaction_id() );
		}

		// mark order as cancelled, since no money was actually transferred
		if ( ! $order->has_status( 'cancelled' ) ) {

			$this->voided_order_message = $message;

			add_filter( 'woocommerce_order_fully_refunded_status', array( $this, 'maybe_cancel_voided_order' ), 10, 2 );

		} else {

			$order->add_order_note( $message );
		}
	}


	/**
	 * Maybe change the order status for a voided order to cancelled
	 *
	 * @hooked woocommerce_order_fully_refunded_status filter
	 *
	 * @see Payment_Gateway::mark_order_as_voided()
	 * @since 3.0.0
	 * @param string $order_status default order status for fully refunded orders
	 * @param int $order_id order ID
	 * @return string 'cancelled'
	 */
	public function maybe_cancel_voided_order( $order_status, $order_id ) {

		if ( empty( $this->voided_order_message ) ) {
			return $order_status;
		}

		$order = wc_get_order( $order_id );

		// no way to set the order note with the status change
		$order->add_order_note( $this->voided_order_message );

		return 'cancelled';
	}


	/**
	 * Returns the $order object with a unique transaction ref member added.
	 *
	 * @since 3.0.0
	 *
	 * @param \WC_Order|WC_Order_Square $order the order object
	 * @return \WC_Order|WC_Order_Square order object with member named unique_transaction_ref
	 */
	protected function get_order_with_unique_transaction_ref( $order ) {

		$order_id = Order_Compatibility::get_prop( $order, 'id' );

		// generate a unique retry count
		if ( is_numeric( $this->get_order_meta( $order_id, 'retry_count' ) ) ) {
			$retry_count = $this->get_order_meta( $order_id, 'retry_count' );

			$retry_count++;
		} else {
			$retry_count = 0;
		}

		// keep track of the retry count
		$this->update_order_meta( $order, 'retry_count', $retry_count );

		// generate a unique transaction ref based on the order number and retry count, for gateways that require a unique identifier for every transaction request
		$order->unique_transaction_ref = ltrim( $order->get_order_number(), esc_html_x( '#', 'hash before order number', 'woocommerce-square' ) ) . ( $retry_count > 0 ? '-' . $retry_count : '' );

		return $order;
	}


	/**
	 * Called after an unsuccessful transaction attempt.
	 *
	 * @since 3.0.0
	 *
	 * @param \WC_Order $order the order
	 * @param Payment_Gateway_API_Response $response the transaction response
	 * @return false
	 */
	protected function do_transaction_failed_result( \WC_Order $order, Payment_Gateway_API_Response $response ) {

		$order_note = '';

		// build the order note with what data we have
		if ( $response->get_status_code() && $response->get_status_message() ) {
			/* translators: Placeholders: %1$s - status code, %2$s - status message */
			$order_note = sprintf( esc_html__( 'Status code %1$s: %2$s', 'woocommerce-square' ), $response->get_status_code(), $response->get_status_message() );
		} elseif ( $response->get_status_code() ) {
			/* translators: Placeholders: %s - status code */
			$order_note = sprintf( esc_html__( 'Status code: %s', 'woocommerce-square' ), $response->get_status_code() );
		} elseif ( $response->get_status_message() ) {
			/* translators: Placeholders; %s - status message */
			$order_note = sprintf( esc_html__( 'Status message: %s', 'woocommerce-square' ), $response->get_status_message() );
		}

		// add transaction id if there is one
		if ( $response->get_transaction_id() ) {
			$order_note .= ' ' . sprintf( esc_html__( 'Transaction ID %s', 'woocommerce-square' ), $response->get_transaction_id() );
		}

		$this->mark_order_as_failed( $order, $order_note, $response );

		return false;
	}

	/**
	 * Adds the standard transaction data to the order.
	 *
	 * @since 3.0.0
	 *
	 * @param \WC_Order                         $order          The WooCommerce order object
	 * @param Payment_Gateway_API_Response|null $response       Optional transaction response
	 * @param string                            $payment_method Describes whether the payment was done using a Square Gift or a Credit card.
	 * @param boolean                           $is_partial     Describes whether if the order payment was split using multiple payment methods.
	 */
	public function add_transaction_data( $order, $response = null, $payment_method = 'credit_card', $is_partial = false ) {
		$key_prefix = 'gift_card' === $payment_method ? 'gift_card_' : '';

		// transaction id if available
		if ( $response && $response->get_transaction_id() ) {
			$this->update_order_meta( $order, $key_prefix . 'trans_id', $response->get_transaction_id() );

			if ( ! $is_partial && $order instanceof \WC_Order ) {
				$order->set_transaction_id( $response->get_transaction_id() );
				$order->save();
			}

			update_post_meta( Order_Compatibility::get_prop( $order, 'id' ), '_transaction_id', $response->get_transaction_id() );
		}

		// transaction date
		$this->update_order_meta( $order, $key_prefix . 'trans_date', current_time( 'mysql' ) );

		// if there's more than one environment
		if ( count( $this->get_environments() ) > 1 ) {
			$this->update_order_meta( $order, 'environment', $this->get_environment() );
		}

		// customer data
		if ( $this->supports_customer_id() ) {
			$this->add_customer_data( $order, $response );
		}

		if ( isset( $order->payment->token ) && $order->payment->token ) {
			$this->update_order_meta( $order, 'payment_token', $order->payment->token );
		}

		// account number
		if ( isset( $order->payment->account_number ) && $order->payment->account_number ) {
			$this->update_order_meta( $order, 'account_four', substr( $order->payment->account_number, -4 ) );
		}

		if ( $this->is_credit_card_gateway() || $this->is_cash_app_pay_gateway() ) {

			// credit card gateway data
			if ( ! $is_partial && $response && $response instanceof Payment_Gateway_API_Authorization_Response ) {

				$this->update_order_meta( $order, 'authorization_amount', $order->payment_total );

				if ( $response->get_authorization_code() ) {
					$this->update_order_meta( $order, 'authorization_code', $response->get_authorization_code() );
				}

				if ( $order->payment_total > 0 ) {

					// mark as captured
					if ( $this->perform_charge( $order ) ) {
						$captured = 'yes';
					} else {
						$captured = 'no';
					}

					$this->update_order_meta( $order, 'charge_captured', $captured );
				}
			}

			if ( isset( $order->payment->exp_year ) && $order->payment->exp_year && isset( $order->payment->exp_month ) && $order->payment->exp_month ) {
				$this->update_order_meta( $order, 'card_expiry_date', $order->payment->exp_year . '-' . $order->payment->exp_month );
			}

			if ( isset( $order->payment->card_type ) && $order->payment->card_type ) {
				$this->update_order_meta( $order, 'card_type', $order->payment->card_type );
			}
		}

		/**
		 * Payment Gateway Add Transaction Data Action.
		 *
		 * Fired after a transaction is processed and provides actors a way to add additional
		 * transactional data to an order given the transaction response object.
		 *
		 * @since 3.0.0
		 *
		 * @param \WC_Order $order order object
		 * @param Payment_Gateway_API_Response|null $response transaction response
		 * @param Payment_Gateway $this instance
		 */
		do_action( 'wc_payment_gateway_' . $this->get_id() . '_add_transaction_data', $order, $response, $this );
	}


	/**
	 * Adds any gateway-specific transaction data to the order.
	 *
	 * @since 3.0.0
	 *
	 * @param \WC_Order $order the order object
	 * @param SquareFramework\PaymentGateway\Api\Payment_Gateway_API_Customer_Response $response the transaction response
	 */
	public function add_payment_gateway_transaction_data( $order, $response ) {
		// Optional method
	}


	/**
	 * Add customer data to an order/user if the gateway supports the customer ID
	 * response
	 *
	 * @since 3.0.0
	 *
	 * @param WC_Order_Square $order order
	 * @param SquareFramework\PaymentGateway\Api\Payment_Gateway_API_Customer_Response $response
	 */
	protected function add_customer_data( $order, $response = null ) {

		$user_id = $order->get_user_id();

		if ( $response && method_exists( $response, 'get_customer_id' ) && $response->get_customer_id() ) {

			$order->customer_id = $customer_id = $response->get_customer_id();

		} else {

			// default to the customer ID set on the order
			$customer_id = $order->customer_id;
		}

		// update the order with the customer ID, note environment is not appended here because it's already available
		// on the `environment` order meta
		$this->update_order_meta( $order, 'customer_id', $customer_id );

		// update the user
		if ( 0 !== $user_id ) {
			$this->update_customer_id( $user_id, $customer_id );
		}
	}


	/**
	 * Gets the order note message for approved credit card transactions.
	 *
	 * @since 3.0.0
	 *
	 * @param \WC_Order $order order object
	 * @param Payment_Gateway_API_Response $response response object
	 * @return string
	 * @throws \Exception
	 */
	public function get_credit_card_transaction_approved_message( \WC_Order $order, $response ) {

		$last_four = ! empty( $order->payment->last_four ) ? $order->payment->last_four : substr( $order->payment->account_number, -4 );

		// use direct card type if set, or try to guess it from card number
		if ( ! empty( $order->payment->card_type ) ) {
			$card_type = $order->payment->card_type;
		} elseif ( $first_four = substr( $order->payment->account_number, 0, 4 ) ) {
			$card_type = Payment_Gateway_Helper::card_type_from_account_number( $first_four );
		} else {
			$card_type = 'card';
		}

		$message = sprintf(
			/* translators: Placeholders: %1$s - payment method title, %2$s - environment ("Test"), %3$s - transaction type (authorization/charge) */
			esc_html__( '%1$s %2$s %3$s Approved', 'woocommerce-square' ),
			$this->get_method_title(),
			$this->is_test_environment() ? esc_html_x( 'Test', 'noun, software environment', 'woocommerce-square' ) : '',
			$this->perform_credit_card_authorization( $order ) ? esc_html_x( 'Authorization', 'credit card transaction type', 'woocommerce-square' ) : esc_html_x( 'Charge', 'noun, credit card transaction type', 'woocommerce-square' )
		);

		if ( $last_four ) {

			$message .= ': ' . sprintf(
				/* translators: Placeholders: %1$s - credit card type (MasterCard, Visa, etc...), %2$s - last four digits of the card */
				esc_html__( '%1$s ending in %2$s', 'woocommerce-square' ),
				Payment_Gateway_Helper::payment_type_to_name( $card_type ),
				$last_four
			);
		}

		// add the expiry date if it is available
		if ( ! empty( $order->payment->exp_month ) && ! empty( $order->payment->exp_year ) ) {

			$message .= ' ' . sprintf(
				/* translators: Placeholders: %s - credit card expiry date */
				esc_html__( '(expires %s)', 'woocommerce-square' ),
				$order->payment->exp_month . '/' . substr( $order->payment->exp_year, -2 )
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
		 * @param Payment_Gateway_API_Response $response transaction response
		 * @param Payment_Gateway_Direct $this instance
		 */
		return apply_filters( 'wc_payment_gateway_' . $this->get_id() . '_credit_card_transaction_approved_order_note', $message, $order, $response, $this );
	}

	/**
	 * Marks the given order as 'on-hold', set an order note and display a message to the customer.
	 *
	 * @since 3.0.0
	 *
	 * @param \WC_Order $order the order
	 * @param string $message a message to display within the order note
	 * @param Payment_Gateway_API_Response $response optional, the transaction response object
	 */
	public function mark_order_as_held( $order, $message, $response = null ) {

		/* translators: Placeholders: %1$s - payment gateway title, %2$s - message (probably reason for the transaction being held for review) */
		$order_note = sprintf( esc_html__( '%1$s Transaction Held for Review (%2$s)', 'woocommerce-square' ), $this->get_method_title(), $message );

		/**
		 * Held Order Status Filter.
		 *
		 * This filter is deprecated. Use wc_<gateway_id>_held_order_status instead.
		 *
		 * @since 3.0.0
		 *
		 * @param string $order_status 'on-hold' by default
		 * @param \WC_Order $order WC order
		 * @param Payment_Gateway_API_Response $response instance
		 * @param Payment_Gateway $gateway gateway instance
		 */
		$order_status = apply_filters( 'wc_payment_gateway_' . $this->get_id() . '_held_order_status', 'on-hold', $order, $response, $this );

		/**
		 * Filters the order status that's considered to be "held".
		 *
		 * @since 3.0.0
		 *
		 * @param string $status held order status
		 * @param \WC_Order $order order object
		 * @param Payment_Gateway_API_Response|null $response API response object, if any
		 */
		$order_status = apply_filters( 'wc_' . $this->get_id() . '_held_order_status', $order_status, $order, $response );

		// mark order as held
		if ( ! $order->has_status( $order_status ) ) {
			$order->update_status( $order_status, $order_note );
		} else {
			$order->add_order_note( $order_note );
		}

		// user message
		$user_message = '';
		if ( $response && $this->is_detailed_customer_decline_messages_enabled() ) {
			$user_message = $response->get_user_message();
		}

		if ( ! $user_message || ( $this->supports_authorization() && $this->perform_authorization( $order ) ) ) {
			$user_message = esc_html__( 'Your order has been received and is being reviewed. Thank you for your business.', 'woocommerce-square' );
		}

		if ( isset( WC()->session ) ) {
			WC()->session->held_order_received_text = $user_message;
		}
	}


	/**
	 * Maybe render custom order received text on the thank you page when
	 * an order is held
	 *
	 * If detailed customer decline messages are enabled, this message may
	 * additionally include more detailed information.
	 *
	 * @since 3.0.0
	 *
	 * @param string $text order received text
	 * @param \WC_Order|null $order order object
	 * @return string
	 */
	public function maybe_render_held_order_received_text( $text, $order ) {

		if ( $order && $order->has_status( 'on-hold' ) && isset( WC()->session->held_order_received_text ) ) {

			$text = WC()->session->held_order_received_text;

			unset( WC()->session->held_order_received_text );
		}

		return $text;
	}


	/**
	 * Marks the given order as failed and set the order note.
	 *
	 * @since 3.0.0
	 *
	 * @param \WC_Order $order the order
	 * @param string $error_message a message to display inside the "Payment Failed" order note
	 * @param Payment_Gateway_API_Response optional $response the transaction response object
	 */
	public function mark_order_as_failed( $order, $error_message, $response = null ) {

		/* translators: Placeholders: %1$s - payment gateway title, %2$s - error message; e.g. Order Note: [Payment method] Payment failed [error] */
		$order_note = sprintf( esc_html__( '%1$s Payment Failed (%2$s)', 'woocommerce-square' ), $this->get_method_title(), $error_message );

		// Mark order as failed if not already set, otherwise, make sure we add the order note so we can detect when someone fails to check out multiple times
		if ( ! $order->has_status( 'failed' ) ) {
			$order->update_status( 'failed', $order_note );
		} else {
			$order->add_order_note( $order_note );
		}

		$this->add_debug_message( $error_message, 'error' );

		// user message
		$user_message = '';
		if ( $response && $this->is_detailed_customer_decline_messages_enabled() ) {
			$user_message = $response->get_user_message();
		}
		if ( ! $user_message ) {
			$user_message = esc_html__( 'An error occurred, please try again or try an alternate form of payment.', 'woocommerce-square' );
		}
		Square_Helper::wc_add_notice( $user_message, 'error' );
	}

	/** Customer ID Feature  **************************************************/


	/**
	 * Returns true if this is gateway that supports gateway customer IDs
	 *
	 * @since 3.0.0
	 * @return boolean true if the gateway supports gateway customer IDs
	 */
	public function supports_customer_id() {

		return $this->supports( self::FEATURE_CUSTOMER_ID );
	}


	/**
	 * Gets/sets the payment gateway customer id, this defaults to wc-{user id}
	 * and retrieves/stores to the user meta named by get_customer_id_user_meta_name()
	 * This can be overridden for gateways that use some other value, or made to
	 * return false for gateways that don't support a customer id.
	 *
	 * @since 3.0.0
	 * @see Payment_Gateway::get_customer_id_user_meta_name()
	 * @param int $user_id WordPress user identifier
	 * @param array $args optional additional arguments which can include: environment_id, autocreate (true/false), and order
	 * @return string payment gateway customer id
	 */
	public function get_customer_id( $user_id, $args = array() ) {

		$defaults = array(
			'environment_id' => $this->get_environment(),
			'autocreate'     => true,
			'order'          => null,
		);

		$args = array_merge( $defaults, $args );

		// does an id already exist for this user?
		$customer_id = get_user_meta( $user_id, $this->get_customer_id_user_meta_name( $args['environment_id'] ), true );

		if ( ! $customer_id && $args['autocreate'] ) {

			$billing_email = ( $args['order'] ) ? Order_Compatibility::get_prop( $args['order'], 'billing_email' ) : '';

			// generate a new customer id.  We try to use 'wc-<hash of billing email>'
			//  if an order is available, on the theory that it will avoid clashing of
			//  accounts if a customer uses the same merchant account on multiple independent
			//  shops.  Otherwise, we use 'wc-<user_id>-<random>'
			if ( $billing_email ) {
				$customer_id = 'wc-' . md5( $billing_email );
			} else {
				$customer_id = uniqid( 'wc-' . $user_id . '-' );
			}

			$this->update_customer_id( $user_id, $customer_id, $args['environment_id'] );
		}

		return $customer_id;
	}


	/**
	 * Updates the payment gateway customer id for the given $environment, or
	 * for the plugin current environment
	 *
	 * @since 3.0.0
	 * @see Payment_Gateway::get_customer_id()
	 * @param int $user_id WP user ID
	 * @param string $customer_id payment gateway customer id
	 * @param string $environment_id optional environment id, defaults to current environment
	 * @return boolean|int false if no change was made (if the new value was the same as previous value) or if the update failed, meta id if the value was different and the update a success
	 */
	public function update_customer_id( $user_id, $customer_id, $environment_id = null ) {

		// default to current environment
		if ( is_null( $environment_id ) ) {
			$environment_id = $this->get_environment();
		}

		return update_user_meta( $user_id, $this->get_customer_id_user_meta_name( $environment_id ), $customer_id );
	}


	/**
	 * Removes the payment gateway customer id for the given $environment, or
	 * for the plugin current environment
	 *
	 * @since 3.0.0
	 * @param int $user_id WP user ID
	 * @param string $environment_id optional environment id, defaults to current environment
	 * @return boolean true on success, false on failure
	 */
	public function remove_customer_id( $user_id, $environment_id = null ) {

		if ( is_null( $environment_id ) ) {
			$environment_id = $this->get_environment();
		}

		// remove the user meta entry so it can be re-created
		return delete_user_meta( $user_id, $this->get_customer_id_user_meta_name( $environment_id ) );
	}


	/**
	 * Returns a payment gateway customer id for a guest customer.  This
	 * defaults to wc-guest-{order id} but can be overridden for gateways that
	 * use some other value, or made to return false for gateways that don't
	 * support a customer id
	 *
	 * @since 3.0.0
	 *
	 * @param \WC_Order $order order object
	 * @return string payment gateway guest customer id
	 */
	public function get_guest_customer_id( \WC_Order $order ) {

		// is there a customer id already tied to this order?
		$customer_id = $this->get_order_meta( Order_Compatibility::get_prop( $order, 'id' ), 'customer_id' );

		if ( $customer_id ) {
			return $customer_id;
		}

		// default
		return 'wc-guest-' . Order_Compatibility::get_prop( $order, 'id' );
	}


	/**
	 * Returns the payment gateway customer id user meta name for persisting the
	 * gateway customer id.  Defaults to wc_{plugin id}_customer_id for the
	 * production environment and wc_{plugin id}_customer_id_{environment}
	 * for other environments.  A particular environment can be passed,
	 * otherwise this will default to the plugin current environment.
	 *
	 * This can be overridden and made to return false for gateways that don't
	 * support a customer id.
	 *
	 * @since 3.0.0
	 * @param string $environment_id optional environment id, defaults to plugin current environment
	 * @return string payment gateway customer id user meta name
	 */
	public function get_customer_id_user_meta_name( $environment_id = null ) {

		if ( is_null( $environment_id ) ) {
			$environment_id = $this->get_environment();
		}

		// no leading underscore since this is meant to be visible to the admin
		return 'wc_square_customer_id' . ( ! $this->is_production_environment( $environment_id ) ? '_' . $environment_id : '' );
	}


	/** Authorization/Charge feature ******************************************/


	/**
	 * Returns true if this is a credit card gateway which supports
	 * authorization transactions
	 *
	 * @since 3.0.0
	 * @return boolean true if the gateway supports authorization
	 */
	public function supports_credit_card_authorization() {
		return $this->is_credit_card_gateway() && $this->supports( self::FEATURE_CREDIT_CARD_AUTHORIZATION );
	}


	/**
	 * Returns true if this is a credit card gateway which supports
	 * charge transactions
	 *
	 * @since 3.0.0
	 * @return boolean true if the gateway supports charges
	 */
	public function supports_credit_card_charge() {
		return $this->is_credit_card_gateway() && $this->supports( self::FEATURE_CREDIT_CARD_CHARGE );
	}


	/**
	 * Determines if this is a credit card gateway that supports charging virtual-only orders.
	 *
	 * @since 3.0.0
	 * @return bool
	 */
	public function supports_credit_card_charge_virtual() {
		return $this->is_credit_card_gateway() && $this->supports( self::FEATURE_CREDIT_CARD_CHARGE_VIRTUAL );
	}


	/**
	 * Returns true if the gateway supports capturing a charge
	 *
	 * @since 3.0.0
	 * @return boolean true if the gateway supports capturing a charge
	 */
	public function supports_credit_card_capture() {
		return $this->supports( self::FEATURE_CREDIT_CARD_CAPTURE );
	}


	/**
	 * Determines if the gateway supports capturing a partial charge.
	 *
	 * @since 3.0.0
	 *
	 * @return bool
	 */
	public function supports_credit_card_partial_capture() {
		return $this->supports_credit_card_capture() && $this->supports( self::FEATURE_CREDIT_CARD_PARTIAL_CAPTURE );
	}

	/**
	 * Returns true if gateway supports authorization transactions
	 *
	 * @since 4.6.0
	 * @return boolean true if the gateway supports authorization
	 */
	public function supports_authorization() {
		return $this->supports( self::FEATURE_AUTHORIZATION );
	}


	/**
	 * Returns true if gateway supports charge transactions
	 *
	 * @since 4.6.0
	 * @return boolean true if the gateway supports charges
	 */
	public function supports_charge() {
		return $this->supports( self::FEATURE_CHARGE );
	}


	/**
	 * Determines if gateway supports charging virtual-only orders.
	 *
	 * @since 4.6.0
	 * @return bool
	 */
	public function supports_charge_virtual() {
		return $this->supports( self::FEATURE_CHARGE_VIRTUAL );
	}


	/**
	 * Returns true if the gateway supports capturing a charge
	 *
	 * @since 4.6.0
	 * @return boolean true if the gateway supports capturing a charge
	 */
	public function supports_capture() {
		return $this->supports( self::FEATURE_CAPTURE );
	}


	/**
	 * Determines if the gateway supports capturing a partial charge.
	 *
	 * @since 4.6.0
	 *
	 * @return bool
	 */
	public function supports_partial_capture() {
		return $this->supports( self::FEATURE_PARTIAL_CAPTURE );
	}


	/**
	 * Adds any credit card authorization/charge admin fields, allowing the
	 * administrator to choose between performing authorizations or charges
	 *
	 * @since 3.0.0
	 * @param array $form_fields gateway form fields
	 * @return array $form_fields gateway form fields
	 */
	protected function add_authorization_charge_form_fields( $form_fields ) {

		assert( $this->supports_authorization() && $this->supports_charge() );

		$form_fields['transaction_type'] = array(
			'title'    => esc_html__( 'Transaction Type', 'woocommerce-square' ),
			'type'     => 'select',
			'class'    => 'wc-enhanced-select',
			'desc_tip' => esc_html__( 'Select how transactions should be processed. Charge submits all transactions for settlement, Authorization simply authorizes the order total for capture later.', 'woocommerce-square' ),
			'default'  => self::TRANSACTION_TYPE_CHARGE,
			'options'  => array(
				self::TRANSACTION_TYPE_CHARGE        => esc_html_x( 'Charge', 'noun, credit card transaction type', 'woocommerce-square' ),
				self::TRANSACTION_TYPE_AUTHORIZATION => esc_html_x( 'Authorization', 'credit card transaction type', 'woocommerce-square' ),
			),
		);

		if ( $this->supports_charge_virtual() ) {

			$form_fields['charge_virtual_orders'] = array(
				'label'       => esc_html__( 'Charge Virtual-Only Orders', 'woocommerce-square' ),
				'type'        => 'checkbox',
				'description' => esc_html__( 'If the order contains exclusively virtual items, enable this to immediately charge, rather than authorize, the transaction.', 'woocommerce-square' ),
				'default'     => 'no',
			);
		}

		if ( $this->supports_partial_capture() ) {

			$form_fields['enable_partial_capture'] = array(
				'label'       => esc_html__( 'Enable Partial Capture', 'woocommerce-square' ),
				'type'        => 'checkbox',
				'description' => esc_html__( 'Allow orders to be partially captured multiple times.', 'woocommerce-square' ),
				'default'     => 'no',
			);
		}

		if ( $this->supports_capture() ) {

			// get a list of the "paid" status names
			$paid_statuses = array_map( 'wc_get_order_status_name', (array) wc_get_is_paid_statuses() );
			$conjuction    = _x( 'or', 'coordinating conjunction for a list of order statuses: on-hold, processing, or completed', 'woocommerce-square' );

			$form_fields['enable_paid_capture'] = array(
				'label'       => __( 'Capture Paid Orders', 'woocommerce-square' ),
				'type'        => 'checkbox',
				'description' => sprintf(
					esc_html__( 'Automatically capture orders when they are changed to %s.', 'woocommerce-square' ),
					esc_html( ! empty( $paid_statuses ) ? Square_Helper::list_array_items( $paid_statuses, $conjuction ) : __( 'a paid status', 'woocommerce-square' ) )
				),
				'default'     => 'no',
			);
		}

		return $form_fields;
	}


	/**
	 * Return the authorization time window in hours. An authorization is considered
	 * expired if it is older than this.
	 *
	 * 30 days (720 hours) is the standard authorization window. Individual gateways
	 * can override this as necessary.
	 *
	 * @since 3.0.0
	 * @return int hours
	 */
	public function get_authorization_time_window() {

		return 720;
	}


	/**
	 * Determines if a credit card transaction should result in a charge.
	 *
	 * @since 3.0.0
	 *
	 * @param \WC_Order $order Optional. The order being charged
	 * @return bool
	 */
	public function perform_credit_card_charge( \WC_Order $order = null ) {

		assert( $this->supports_credit_card_charge() );

		$perform = self::TRANSACTION_TYPE_CHARGE === $this->transaction_type;

		if ( ! $perform && $order && $this->supports_credit_card_charge_virtual() && 'yes' === $this->charge_virtual_orders ) {
			$perform = Square_Helper::is_order_virtual( $order );
		}

		/**
		 * Filters whether a credit card transaction should result in a charge.
		 *
		 * @since 3.0.0
		 *
		 * @param bool $perform whether the transaction should result in a charge
		 * @param \WC_Order|null $order the order being charged
		 * @param Payment_Gateway $gateway the gateway object
		 */
		return apply_filters( 'wc_' . $this->get_id() . '_perform_credit_card_charge', $perform, $order, $this );
	}


	/**
	 * Determines if a credit card transaction should result in an authorization.
	 *
	 * @since 3.0.0
	 *
	 * @param \WC_Order $order Optional. The order being authorized
	 * @return bool
	 */
	public function perform_credit_card_authorization( \WC_Order $order = null ) {

		assert( $this->supports_credit_card_authorization() );

		$perform = self::TRANSACTION_TYPE_AUTHORIZATION === $this->transaction_type && ! $this->perform_credit_card_charge( $order );

		/**
		 * Filters whether a credit card transaction should result in an authorization.
		 *
		 * @since 3.0.0
		 * @param bool $perform whether the transaction should result in an authorization
		 * @param \WC_Order|null $order the order being authorized
		 * @param Payment_Gateway $gateway the gateway object
		 */
		return apply_filters( 'wc_' . $this->get_id() . '_perform_credit_card_authorization', $perform, $order, $this );
	}

	/**
	 * Determines if a gateway transaction should result in a charge.
	 *
	 * @since 4.6.0
	 *
	 * @param \WC_Order $order Optional. The order being charged
	 * @return bool
	 */
	public function perform_charge( \WC_Order $order = null ) {

		assert( $this->supports_charge() );

		$perform = self::TRANSACTION_TYPE_CHARGE === $this->transaction_type;

		if ( ! $perform && $order && $this->supports_charge_virtual() && 'yes' === $this->charge_virtual_orders ) {
			$perform = Square_Helper::is_order_virtual( $order );
		}

		/**
		 * Filters whether a gateway transaction should result in a charge.
		 *
		 * @since 4.6.0
		 *
		 * @param bool $perform whether the transaction should result in a charge
		 * @param \WC_Order|null $order the order being charged
		 * @param Payment_Gateway $gateway the gateway object
		 */
		return apply_filters( 'wc_' . $this->get_id() . '_perform_charge', $perform, $order, $this );
	}


	/**
	 * Determines if a gateway transaction should result in an authorization.
	 *
	 * @since 4.6.0
	 *
	 * @param \WC_Order $order Optional. The order being authorized
	 * @return bool
	 */
	public function perform_authorization( \WC_Order $order = null ) {

		assert( $this->supports_authorization() );

		$perform = self::TRANSACTION_TYPE_AUTHORIZATION === $this->transaction_type && ! $this->perform_charge( $order );

		/**
		 * Filters whether a gateway transaction should result in an authorization.
		 *
		 * @since 3.0.0
		 * @param bool $perform whether the transaction should result in an authorization
		 * @param \WC_Order|null $order the order being authorized
		 * @param Payment_Gateway $gateway the gateway object
		 */
		return apply_filters( 'wc_' . $this->get_id() . '_perform_authorization', $perform, $order, $this );
	}


	/**
	 * Determines if partial capture is enabled.
	 *
	 * @since 3.0.0
	 *
	 * @return bool
	 */
	public function is_partial_capture_enabled() {

		assert( $this->supports_partial_capture() );

		/**
		 * Filters whether partial capture is enabled.
		 *
		 * @since 3.0.0
		 *
		 * @param bool $enabled whether partial capture is enabled
		 * @param Payment_Gateway $gateway gateway object
		 */
		return apply_filters( 'wc_' . $this->get_id() . '_partial_capture_enabled', 'yes' === $this->enable_partial_capture, $this );
	}


	/**
	 * Determines if orders should be captured when switched to a "paid" status.
	 *
	 * @since 3.0.0
	 *
	 * @return bool
	 */
	public function is_paid_capture_enabled() {

		/**
		 * Filters whether orders should be captured when switched to a "paid" status.
		 *
		 * @since 3.0.0
		 *
		 * @param bool $enabled whether "paid" capture is enabled
		 * @param Payment_Gateway $gateway gateway object
		 */
		return apply_filters( 'wc_' . $this->get_id() . '_paid_capture_enabled', 'yes' === $this->enable_paid_capture, $this );
	}


	/** Add Payment Method feature ********************************************/


	/**
	 * Determines if the gateway supports the add payment method feature.
	 *
	 * @since 3.0.0
	 *
	 * @return bool
	 */
	public function supports_add_payment_method() {

		return $this->supports( self::FEATURE_ADD_PAYMENT_METHOD );
	}


	// TODO: generalize the direct methods


	/** Card Types feature ******************************************************/


	/**
	 * Returns true if the gateway supports card_types: allows the admin to
	 * configure card type icons to display at checkout
	 *
	 * @since 3.0.0
	 * @return boolean true if the gateway supports card_types
	 */
	public function supports_card_types() {
		return $this->is_credit_card_gateway() && $this->supports( self::FEATURE_CARD_TYPES );
	}


	/**
	 * Returns the array of accepted card types if this is a credit card gateway
	 * that supports card types.  Return format is 'VISA', 'MC', 'AMEX', etc
	 *
	 * @since 3.0.0
	 * @see get_available_card_types()
	 * @return array of accepted card types, ie 'VISA', 'MC', 'AMEX', etc
	 */
	public function get_card_types() {

		return $this->card_types;
	}


	/**
	 * Adds any card types form fields, allowing the admin to configure the card
	 * types icons displayed during checkout
	 *
	 * @since 3.0.0
	 * @param array $form_fields gateway form fields
	 * @return array $form_fields gateway form fields
	 */
	protected function add_card_types_form_fields( $form_fields ) {

		assert( $this->supports_card_types() );

		$form_fields['card_types'] = array(
			'title'       => esc_html__( 'Accepted Card Logos', 'woocommerce-square' ),
			'type'        => 'multiselect',
			'desc_tip'    => __( 'These are the card logos that are displayed to customers as accepted during checkout.', 'woocommerce-square' ),
			'description' => sprintf(
				/* translators: Placeholders: %1$s - <strong> tag, %2$s - </strong> tag */
				__( 'This setting %1$sdoes not%2$s change which card types the gateway will accept. Accepted cards are configured from your payment processor account.', 'woocommerce-square' ),
				'<strong>',
				'</strong>'
			),
			'default'     => array_keys( $this->get_available_card_types() ),
			'class'       => 'wc-enhanced-select',
			'css'         => 'width: 350px;',
			'options'     => $this->get_available_card_types(),
		);

		return $form_fields;
	}


	/**
	 * Returns available card types, ie 'VISA' => 'Visa', 'MC' => 'MasterCard', etc
	 *
	 * @since 3.0.0
	 * @return array associative array of card type to display name
	 */
	public function get_available_card_types() {

		assert( $this->supports_card_types() );

		// default available card types
		if ( ! isset( $this->available_card_types ) ) {

			$this->available_card_types = array(
				'VISA'   => esc_html_x( 'Visa', 'credit card type', 'woocommerce-square' ),
				'MC'     => esc_html_x( 'MasterCard', 'credit card type', 'woocommerce-square' ),
				'AMEX'   => esc_html_x( 'American Express', 'credit card type', 'woocommerce-square' ),
				'DISC'   => esc_html_x( 'Discover', 'credit card type', 'woocommerce-square' ),
				'DINERS' => esc_html_x( 'Diners', 'credit card type', 'woocommerce-square' ),
				'JCB'    => esc_html_x( 'JCB', 'credit card type', 'woocommerce-square' ),
			);

		}

		/**
		 * Payment Gateway Available Card Types Filter.
		 *
		 * Allow actors to modify the available card types.
		 *
		 * @since 3.0.0
		 * @param array $available_card_types
		 */
		return apply_filters( 'wc_' . $this->get_id() . '_available_card_types', $this->available_card_types );
	}


	/** Tokenization feature **************************************************/


	/**
	 * Returns true if the gateway supports tokenization
	 *
	 * @since 3.0.0
	 * @return boolean true if the gateway supports tokenization
	 */
	public function supports_tokenization() {
		return $this->supports( self::FEATURE_TOKENIZATION );
	}


	/**
	 * Returns true if tokenization is enabled
	 *
	 * @since 3.0.0
	 * @return boolean true if tokenization is enabled
	 */
	public function tokenization_enabled() {

		assert( $this->supports_tokenization() );

		return 'yes' == $this->tokenization;
	}


	/**
	 * Adds any tokenization form fields for the settings page
	 *
	 * @since 3.0.0
	 * @param array $form_fields gateway form fields
	 * @return array $form_fields gateway form fields
	 */
	protected function add_tokenization_form_fields( $form_fields ) {

		assert( $this->supports_tokenization() );

		$form_fields['tokenization'] = array(
			/* translators: http://www.cybersource.com/products/payment_security/payment_tokenization/ and https://en.wikipedia.org/wiki/Tokenization_(data_security) */
			'title'   => esc_html__( 'Tokenization', 'woocommerce-square' ),
			'label'   => esc_html__( 'Allow customers to securely save their payment details for future checkout.', 'woocommerce-square' ),
			'type'    => 'checkbox',
			'default' => 'no',
		);

		return $form_fields;
	}

	/**
	 * Add API request logging for the gateway. The main plugin class typically handles this, but the payment
	 * gateway plugin class no-ops the method so each gateway's requests can be logged individually (e.g. credit card)
	 * and make use of the payment gateway-specific add_debug_message() method
	 *
	 * @since 3.0.0
	 * @see Plugin::add_api_request_logging()
	 */
	public function add_api_request_logging() {

		if ( ! has_action( 'wc_' . $this->get_id() . '_api_request_performed' ) ) {
			add_action( 'wc_' . $this->get_id() . '_api_request_performed', array( $this, 'log_api_request' ), 10, 2 );
		}
	}


	/**
	 * Log gateway API requests/responses
	 *
	 * @since 3.0.0
	 * @param array $request request data, see Base::broadcast_request() for format
	 * @param array $response response data
	 */
	public function log_api_request( $request, $response ) {

		// request
		$this->add_debug_message( $this->get_plugin()->get_api_log_message( $request ), 'message' );

		// response
		if ( ! empty( $response ) ) {
			$this->add_debug_message( $this->get_plugin()->get_api_log_message( $response ), 'message' );
		}
	}


	/**
	 * Adds debug messages to the page as a WC message/error, and/or to the WC Error log
	 *
	 * @since 3.0.0
	 * @param string $message message to add
	 * @param string $type how to add the message, options are:
	 *     'message' (styled as WC message), 'error' (styled as WC Error)
	 */
	public function add_debug_message( $message, $type = 'message' ) {

		// do nothing when debug mode is off or no message
		if ( 'off' == $this->debug_off() || ! $message ) {
			return;
		}

		// add log message to WC logger if log/both is enabled
		if ( $this->debug_log() ) {
			$this->get_plugin()->log( $message, $this->get_id() );
		}

		// avoid adding notices when performing refunds, these occur in the admin as an Ajax call, so checking the current filter
		// is the only reliably way to do so
		if ( in_array( 'wp_ajax_woocommerce_refund_line_items', $GLOBALS['wp_current_filter'], true ) ) {
			return;
		}

		// add debug message to woocommerce->errors/messages if checkout or both is enabled, the admin/Ajax check ensures capture charge transactions aren't logged as notices to the front end
		if ( ( $this->debug_checkout() || ( 'error' === $type && $this->is_test_environment() ) ) && ( ! is_admin() || wp_doing_ajax() ) ) {

			if ( 'message' === $type ) {

				Square_Helper::wc_add_notice( str_replace( "\n", '<br/>', htmlspecialchars( $message ) ), 'notice' );

			} else {

				// defaults to error message
				Square_Helper::wc_add_notice( str_replace( "\n", '<br/>', htmlspecialchars( $message ) ), 'error' );
			}
		}
	}


	/**
	 * Get payment currency, either from current order or WC settings
	 *
	 * @since 3.0.0
	 * @return string three-letter currency code
	 */
	protected function get_payment_currency() {

		$currency = get_woocommerce_currency();
		$order_id = $this->get_checkout_pay_page_order_id();

		// Gets currency for the current order, that is about to be paid for
		if ( $order_id ) {

			$order    = wc_get_order( $order_id );
			$currency = Order_Compatibility::get_prop( $order, 'currency', 'view' );
		}

		return $currency;
	}


	/**
	 * Returns true if $currency is accepted by this gateway
	 *
	 * @since 3.0.0
	 * @param string $currency optional three-letter currency code, defaults to
	 *        order currency (if available) or currently configured WooCommerce
	 *        currency
	 * @return boolean true if $currency is accepted, false otherwise
	 */
	public function currency_is_accepted( $currency = null ) {

		// accept all currencies
		if ( ! $this->currencies ) {
			return true;
		}

		// default to order/WC currency
		if ( is_null( $currency ) ) {
			$currency = $this->get_payment_currency();
		}

		return in_array( $currency, $this->currencies, true );
	}

	/**
	 * Adds order meta data.
	 *
	 * @since 3.0.0
	 *
	 * @param \WC_Order|int the order to add meta to
	 * @param string $key meta key (already prefixed with gateway ID)
	 * @param mixed $value meta value
	 * @param bool $unique whether the meta value should be unique
	 * @return false|void
	 */
	public function add_order_meta( $order, $key, $value, $unique = false ) {

		if ( is_numeric( $order ) ) {
			$order = wc_get_order( $order );
		}

		if ( ! $order instanceof \WC_Order ) {
			return false;
		}

		return Order_Compatibility::add_meta_data( $order, $this->get_order_meta_prefix() . $key, $value, $unique );
	}

	/**
	 * Builds the order note string for orders made using a gift and a credit card.
	 *
	 * @since 4.2.0
	 *
	 * @var \WC_Order            $order   WooCommerce order.
	 * @var \Square\Models\Order $square_order Array of tenders.
	 * @return string
	 */
	public function build_split_payment_order_note( $order, $square_order ) {
		$message = '';

		$tenders = $square_order->getTenders();

		if ( is_array( $tenders ) && ! empty( $tenders ) ) {
			/** @var \Square\Models\Tender $tender */
			foreach ( $tenders as $tender ) {
				$tender_amount = Money_Utility::cents_to_float( $tender->getAmountMoney()->getAmount(), $order->get_currency() );

				if ( \Square\Models\TenderType::SQUARE_GIFT_CARD === $tender->getType() ) {
					$this->update_order_meta( $order, 'gift_card_partial_total', $tender_amount );
					$message .= ' ' . sprintf( wp_kses_post( 'for an amount of %1$s on the gift card' ), wc_price( $tender_amount ) );
				} elseif ( \Square\Models\TenderType::CARD === $tender->getType() ) {
					$this->update_order_meta( $order, 'other_gateway_partial_total', $tender_amount );
					$message .= ' ' . sprintf( wp_kses_post( 'and %1$s on the credit card' ), wc_price( $tender_amount ) );
				} elseif ( \Square\Models\TenderType::WALLET === $tender->getType() ) {
					$this->update_order_meta( $order, 'other_gateway_partial_total', $tender_amount );
					$message .= ' ' . sprintf( wp_kses_post( 'and %1$s on the cash app pay' ), wc_price( $tender_amount ) );
				}
			}
		}

		return $message;
	}


	/**
	 * Gets order meta data.
	 *
	 * Note this is hardcoded to return a single value for the get_post_meta() call.
	 *
	 * @since 3.0.0
	 * @param \WC_Order|int the order to get meta for
	 * @param string $key meta key
	 * @return mixed
	 */
	public function get_order_meta( $order, $key ) {

		if ( is_numeric( $order ) ) {
			$order = wc_get_order( $order );
		}

		if ( ! $order instanceof \WC_Order ) {
			return false;
		}

		return Order_Compatibility::get_meta( $order, $this->get_order_meta_prefix() . $key, true );
	}


	/**
	 * Updates order meta data.
	 *
	 * @since 3.0.0
	 *
	 * @param \WC_Order|int the order to update meta for
	 * @param string $key meta key
	 * @param mixed $value meta value
	 * @return false|void
	 */
	public function update_order_meta( $order, $key, $value ) {

		if ( is_numeric( $order ) ) {
			$order = wc_get_order( $order );
		}

		if ( ! $order instanceof \WC_Order ) {
			return false;
		}

		return Order_Compatibility::update_meta_data( $order, $this->get_order_meta_prefix() . $key, $value );
	}


	/**
	 * Delete order meta data.
	 *
	 * @since 3.0.0
	 * @param \WC_Order|int the order to delete meta for
	 * @param string $key meta key
	 * @return bool
	 */
	public function delete_order_meta( $order, $key ) {

		if ( is_numeric( $order ) ) {
			$order = wc_get_order( $order );
		}

		if ( ! $order instanceof \WC_Order ) {
			return false;
		}

		return Order_Compatibility::delete_meta_data( $order, $this->get_order_meta_prefix() . $key );
	}


	/**
	 * Gets the order meta prefixed used for the *_order_meta() methods
	 *
	 * Defaults to `_wc_{gateway_id}_`
	 *
	 * @since 3.0.0
	 * @return string
	 */
	public function get_order_meta_prefix() {
		return '_wc_' . $this->get_id() . '_';
	}

	/**
	 * Returns the payment gateway id
	 *
	 * @since 3.0.0
	 * @see WC_Payment_Gateway::$id
	 * @return string payment gateway id
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Returns the payment gateway id with dashes in place of underscores, and
	 * appropriate for use in frontend element names, classes and ids
	 *
	 * @since 3.0.0
	 * @return string payment gateway id with dashes in place of underscores
	 */
	public function get_id_dasherized() {
		return str_replace( '_', '-', $this->get_id() );
	}


	/**
	 * Returns the parent plugin object
	 *
	 * @since 3.0.0
	 *
	 * @return Plugin the parent plugin object
	 */
	public function get_plugin() {

		return $this->plugin;
	}


	/**
	 * Returns the admin method title.  This should be the gateway name, ie
	 * 'Intuit QBMS'
	 *
	 * @since 3.0.0
	 * @see WC_Settings_API::$method_title
	 * @return string method title
	 */
	public function get_method_title() {
		return $this->method_title;
	}


	/**
	 * Determines if the Card Security Code (CVV) field should be used at checkout.
	 *
	 * @since 3.0.0
	 * @return bool
	 */
	public function csc_enabled() {
		return 'yes' === $this->enable_csc;
	}


	/**
	 * Determines if the Card Security Code (CVV) field should be used for saved cards at checkout.
	 *
	 * @since 3.0.0
	 * @return bool
	 */
	public function csc_enabled_for_tokens() {
		return $this->csc_enabled() && 'yes' === $this->enable_token_csc;
	}


	/**
	 * Determines if the Card Security Code (CVV) field should be required at checkout.
	 *
	 * @since 3.0.0
	 * @return bool
	 */
	public function csc_required() {
		return $this->csc_enabled();
	}


	/**
	 * Determines if the gateway supports sharing settings with sibling gateways.
	 *
	 * @since 3.0.0
	 * @return bool
	 */
	public function share_settings() {
		return true;
	}


	/**
	 * Determines if settings should be inherited for this gateway.
	 *
	 * @since 3.0.0
	 * @return bool
	 */
	public function inherit_settings() {
		return 'yes' === $this->inherit_settings;
	}

	/**
	 * Returns an array of two-letter country codes this gateway is allowed for, defaults to all
	 *
	 * @since 3.0.0
	 * @see WC_Payment_Gateway::$countries
	 * @return array of two-letter country codes this gateway is allowed for, defaults to all
	 */
	public function get_available_countries() {
		return $this->countries;
	}

	/**
	 * Add support for the named feature or features
	 *
	 * @since 3.0.0
	 * @param string|array $feature the feature name or names supported by this gateway
	 */
	public function add_support( $feature ) {

		if ( ! is_array( $feature ) ) {
			$feature = array( $feature );
		}

		foreach ( $feature as $name ) {

			// add support for feature if it's not already declared
			if ( ! in_array( $name, $this->supports, true ) ) {

				$this->supports[] = $name;

				/**
				 * Payment Gateway Add Support Action.
				 *
				 * Fired when declaring support for a specific gateway feature. Allows other actors
				 * (including ourselves) to take action when support is declared.
				 *
				 * @since 3.0.0
				 *
				 * @param Payment_Gateway $this instance
				 * @param string $name of supported feature being added
				 */
				do_action( 'wc_payment_gateway_' . $this->get_id() . '_supports_' . str_replace( '-', '_', $name ), $this, $name );
			}
		}
	}

	/**
	 * Set all features supported
	 *
	 * @since 3.0.0
	 * @param array $features array of supported feature names
	 */
	public function set_supports( $features ) {
		$this->supports = $features;
	}

	/**
	 * Gets the set of environments supported by this gateway.  All gateways
	 * support at least the production environment
	 *
	 * @since 3.0.0
	 * @return array associative array of environment id to name supported by this gateway
	 */
	public function get_environments() {

		// default set of environments consists of 'production'
		if ( ! isset( $this->environments ) ) {
			$this->environments = array( self::ENVIRONMENT_PRODUCTION => esc_html_x( 'Production', 'software environment', 'woocommerce-square' ) );
		}

		return $this->environments;
	}


	/**
	 * Returns the environment setting, one of the $environments keys, ie
	 * 'production'
	 *
	 * @since 3.0.0
	 * @return string the configured environment id
	 */
	public function get_environment() {
		return $this->environment;
	}


	/**
	 * Get the configured environment's display name.
	 *
	 * @since 3.0.0
	 * @return string The configured environment name
	 */
	public function get_environment_name() {

		$environments = $this->get_environments();

		$environment_id   = $this->get_environment();
		$environment_name = ( isset( $environments[ $environment_id ] ) ) ? $environments[ $environment_id ] : $environment_id;

		return $environment_name;
	}


	/**
	 * Returns true if the current environment is $environment_id.
	 *
	 * @since 3.0.0
	 *
	 * @param string|mixed $environment_id
	 * @return bool
	 */
	public function is_environment( $environment_id ) {
		return $environment_id == $this->get_environment();
	}


	/**
	 * Returns true if the current gateway environment is configured to
	 * 'production'.  All gateways have at least the production environment
	 *
	 * @since 3.0.0
	 * @param string $environment_id optional environment id to check, otherwise defaults to the gateway current environment
	 * @return boolean true if $environment_id (if non-null) or otherwise the current environment is production
	 */
	public function is_production_environment( $environment_id = null ) {

		// if an environment was passed in, see whether it's the production environment
		if ( ! is_null( $environment_id ) ) {
			return self::ENVIRONMENT_PRODUCTION == $environment_id;
		}

		// default: check the current environment
		return $this->is_environment( self::ENVIRONMENT_PRODUCTION );
	}


	/**
	 * Returns true if the current gateway environment is configured to 'test'
	 *
	 * @since 3.0.0
	 * @param string $environment_id optional environment id to check, otherwise defaults to the gateway current environment
	 * @return boolean true if $environment_id (if non-null) or otherwise the current environment is test
	 */
	public function is_test_environment( $environment_id = null ) {

		// if an environment was passed in, see whether it's the production environment
		if ( ! is_null( $environment_id ) ) {
			return self::ENVIRONMENT_TEST == $environment_id;
		}

		// default: check the current environment
		return $this->is_environment( self::ENVIRONMENT_TEST );
	}


	/**
	 * Returns true if the gateway is enabled.  This has nothing to do with
	 * whether the gateway is properly configured or functional.
	 *
	 * @since 3.0.0
	 * @see WC_Payment_Gateway::$enabled
	 * @return boolean true if the gateway is enabled
	 */
	public function is_enabled() {
		return 'yes' == $this->enabled;
	}


	/**
	 * Returns true if detailed decline messages should be displayed to
	 * customers on checkout when available, rather than a single generic
	 * decline message
	 *
	 * @since 3.0.0
	 * @see Payment_Gateway_API_Response_Message_Helper
	 * @see Payment_Gateway_API_Response::get_user_message()
	 * @return boolean true if detailed decline messages should be displayed
	 *         on checkout
	 */
	public function is_detailed_customer_decline_messages_enabled() {
		return 'yes' == $this->enable_customer_decline_messages;
	}


	/**
	 * Returns the set of accepted currencies, or empty array if all currencies
	 * are accepted by this gateway
	 *
	 * @since 3.0.0
	 * @return array of currencies accepted by this gateway
	 */
	public function get_accepted_currencies() {
		return $this->currencies;
	}


	/**
	 * Returns true if all debugging is disabled
	 *
	 * @since 3.0.0
	 * @return boolean if all debuging is disabled
	 */
	public function debug_off() {
		return self::DEBUG_MODE_OFF === $this->debug_mode;
	}


	/**
	 * Returns true if debug logging is enabled
	 *
	 * @since 3.0.0
	 * @return boolean if debug logging is enabled
	 */
	public function debug_log() {
		return self::DEBUG_MODE_LOG === $this->debug_mode || self::DEBUG_MODE_BOTH === $this->debug_mode;
	}


	/**
	 * Returns true if checkout debugging is enabled.  This will cause debugging
	 * statements to be displayed on the checkout/pay pages
	 *
	 * @since 3.0.0
	 * @return boolean if checkout debugging is enabled
	 */
	public function debug_checkout() {
		return self::DEBUG_MODE_CHECKOUT === $this->debug_mode || self::DEBUG_MODE_BOTH === $this->debug_mode;
	}


	/**
	 * Returns true if this is a direct type gateway
	 *
	 * @since 3.0.0
	 * @return boolean if this is a direct payment gateway
	 */
	public function is_direct_gateway() {
		return false;
	}


	/**
	 * Returns true if this is a hosted type gateway
	 *
	 * @since 3.0.0
	 * @return boolean if this is a hosted IPN payment gateway
	 */
	public function is_hosted_gateway() {
		return false;
	}


	/**
	 * Returns the payment type for this gateway
	 *
	 * @since 3.0.0
	 * @return string the payment type, ie 'credit-card'.
	 */
	public function get_payment_type() {
		return $this->payment_type;
	}


	/**
	 * Returns true if this is a credit card gateway
	 *
	 * @since 3.0.0
	 * @return boolean true if this is a credit card gateway
	 */
	public function is_credit_card_gateway() {
		return self::PAYMENT_TYPE_CREDIT_CARD == $this->get_payment_type();
	}

	/**
	 * Returns true if this is a Cash App Pay gateway
	 *
	 * @since 4.6.0
	 * @return boolean true if this is a Cash App Pay gateway
	 */
	public function is_cash_app_pay_gateway() {
		return self::PAYMENT_TYPE_CASH_APP_PAY === $this->get_payment_type();
	}

	/**
	 * Returns true if a gift card is applied during checkout.
	 *
	 * @since 3.7.0
	 * @return boolean
	 */
	public function is_gift_card_applied() {
		return '' !== Square_Helper::get_post( 'square-gift-card-payment-nonce' );
	}

	/**
	 * Returns the charge type for a Gift card payment.
	 * Gets the data from the $_POST array.
	 *
	 * If the entire amount is charged on the gift card, `FULL` is returned,
	 * else if the total is split, then `PARTIAL` is returned.
	 *
	 * @return string
	 */
	public function get_charge_type() {
		return Square_Helper::get_post( 'square-charge-type' );
	}

	/**
	 * Returns the partial total amount paid using Square Gift Card.
	 * Gets the data from the $_POST array.
	 *
	 * @return float
	 */
	public function get_partial_total_on_gift_card() {
		return (float) Square_Helper::get_post( 'square-gift-card-charged-amount' );
	}

	/**
	 * Returns the partial total amount paid using other gateway (Credit card/cash app pay etc...).
	 * Gets the data from the $_POST array.
	 *
	 * @return float
	 */
	public function get_partial_total_on_other_gateway() {
		return (float) Square_Helper::get_post( 'square-gift-card-difference-amount' );
	}

	/**
	 * Returns the partial total amount paid using Square Credit Card.
	 * Gets the data from the $_POST array.
	 *
	 * @return float
	 */
	public function get_partial_total_on_credit_card() {
		wc_deprecated_function( __METHOD__, '4.6.0', __CLASS__ . '::get_partial_total_on_other_gateway()' );

		return $this->get_partial_total_on_other_gateway();
	}

	/**
	 * Returns the tender types used for a Woo Order.
	 *
	 * @since 3.9.0
	 *
	 * @param \WC_Order $order Woo Order
	 *
	 * @return string
	 */
	public function get_order_tender_types( $order ) {
		$is_credit_card  = Order::is_tender_type_card( $order );
		$is_gift_card    = Order::is_tender_type_gift_card( $order );
		$is_cash_app_pay = Order::is_tender_type_cash_app_pay( $order );

		if ( ( $is_credit_card || $is_cash_app_pay ) && $is_gift_card ) {
			return 'both';
		}

		if ( $is_credit_card ) {
			return 'credit_card';
		}

		if ( $is_gift_card ) {
			return 'gift_card';
		}

		return 'cash_app_pay';
	}

	/**
	 * Returns the API instance for this gateway if it uses direct communication
	 *
	 * This is a stub method which must be overridden if this gateway performs
	 * direct communication
	 *
	 * @since 3.0.0
	 * @return \WooCommerce\Square\Gateway\API|void the payment gateway API instance
	 */
	public function get_api() {

		// concrete stub method
		assert( false );
	}


	/**
	 * Returns the order_id if on the checkout pay page
	 *
	 * @since 3.0.0
	 * @return int order identifier
	 */
	public function get_checkout_pay_page_order_id() {
		global $wp;

		return isset( $wp->query_vars['order-pay'] ) ? absint( $wp->query_vars['order-pay'] ) : 0;
	}

	/**
	 * Gets the maximum amount that can be captured from an order.
	 *
	 * Gateways can override this for an value above or below the order total.
	 * For instance, some processors allow capturing an amount a certain
	 * percentage higher than the payment total.
	 *
	 * @since 3.0.0
	 *
	 * @param \WC_Order $order order object
	 * @return float
	 */
	public function get_order_capture_maximum( \WC_Order $order ) {

		wc_deprecated_function( __METHOD__, '3.0.0', get_class( $this->get_capture_handler() ) . '::get_order_capture_maximum()' );

		return $this->get_capture_handler()->get_order_capture_maximum( $order );
	}


	/**
	 * Gets the amount originally authorized for an order.
	 *
	 * @since 3.0.0
	 *
	 * @param \WC_Order $order order object
	 * @return float
	 */
	public function get_order_authorization_amount( \WC_Order $order ) {

		wc_deprecated_function( __METHOD__, '3.0.0', get_class( $this->get_capture_handler() ) . '::get_order_authorization_amount()' );

		return $this->get_capture_handler()->get_order_authorization_amount( $order );
	}

	/**
	 * Returns the gateway icon markup
	 *
	 * @since 3.0.0
	 * @see WC_Payment_Gateway::get_icon()
	 * @return string icon markup
	 */
	public function get_icon() {

		$icon = '';

		// specific icon
		if ( $this->icon ) {

			// use icon provided by filter
			$icon = sprintf( '<img src="%s" alt="%s" class="sv-wc-payment-gateway-icon wc-%s-payment-gateway-icon" />', esc_url( \WC_HTTPS::force_https_url( $this->icon ) ), esc_attr( $this->get_title() ), esc_attr( $this->get_id_dasherized() ) );
		}

		// credit card images
		if ( ! $icon && $this->supports_card_types() && $this->get_card_types() ) {

			// display icons for the selected card types
			foreach ( $this->get_card_types() as $card_type ) {

				$card_type = Payment_Gateway_Helper::normalize_card_type( $card_type );

				if ( $url = $this->get_payment_method_image_url( $card_type ) ) {
					$icon .= sprintf( '<img src="%s" alt="%s" class="sv-wc-payment-gateway-icon wc-%s-payment-gateway-icon" width="40" height="25" style="width: 40px; height: 25px;" />', esc_url( $url ), esc_attr( $card_type ), esc_attr( $this->get_id_dasherized() ) );
				}
			}
		}

		/* This filter is documented in WC core */
		return apply_filters( 'woocommerce_gateway_icon', $icon, $this->get_id() );
	}

	/**
	 * Restores refunded Square inventory.
	 *
	 * @internal
	 *
	 * @since 2.0.0
	 *
	 * @param int $order_id order ID
	 * @param int $refund_id refund ID
	 */
	public function restore_refunded_inventory( $order_id, $refund_id ) {
		$inventory_adjustments = array();

		// no handling if inventory sync is disabled
		if ( ! $this->get_plugin()->get_settings_handler()->is_inventory_sync_enabled() ) {
			return;
		}

		$order = wc_get_order( $order_id );

		// check that the order was paid using our gateway
		if ( ! $order instanceof \WC_Order || $order->get_payment_method() !== $this->get_id() ) {
			return;
		}

		// don't refund items if the "Restock refunded items" option is unchecked - maintains backwards compatibility if this function is called outside of the `woocommerce_order_refunded` do_action
		if ( isset( $_POST['restock_refunded_items'] ) ) {
			// Validate the user has permissions to process this request.
			if ( ! check_ajax_referer( 'order-item', 'security', false ) || ! current_user_can( 'edit_shop_orders' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
				return;
			}

			if ( 'false' === $_POST['restock_refunded_items'] ) {
				return;
			}
		}

		$refund = wc_get_order( $refund_id );

		if ( $refund instanceof \WC_Order_Refund ) {

			foreach ( $refund->get_items() as $item ) {
				if ( $item->is_type( 'line_item' ) ) {
					$product = $item->get_product();

					if ( $product ) {
						$inventory_adjustment = Product::get_inventory_change_adjustment_type( $product, absint( $item->get_quantity() ) );

						if ( ! empty( $inventory_adjustment ) ) {
							$inventory_adjustments[] = $inventory_adjustment;
						}
					}
				}
			}
		}

		if ( ! empty( $inventory_adjustments ) ) {
			wc_square()->get_api()->batch_change_inventory(
				wc_square()->get_idempotency_key( $refund_id . '_' . time() . '_change_inventory' ),
				$inventory_adjustments
			);
		}
	}

}
