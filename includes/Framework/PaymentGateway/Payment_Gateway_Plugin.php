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

use WooCommerce\Square\Framework\Plugin;
use WooCommerce\Square\Framework\Plugin_Compatibility;
use WooCommerce\Square\Framework\PaymentGateway\Admin\Payment_Gateway_Admin_Order;
use WooCommerce\Square\Framework\PaymentGateway\Admin\Payment_Gateway_Admin_User_Handler;
use WooCommerce\Square\Framework as SquareFramework;
use WooCommerce\Square\Framework\PaymentGateway\ApplePay as ApplePayFramework;

defined( 'ABSPATH' ) || exit;

/**
 * # WooCommerce Payment Gateway Plugin Framework
 *
 * A payment gateway refinement of the WooCommerce Plugin Framework
 *
 * This framework class provides a base level of configurable and overrideable
 * functionality and features suitable for the implementation of a WooCommerce
 * payment gateway.  This class handles all the non-gateway support tasks such
 * as verifying dependencies are met, loading the text domain, etc.  It also
 * loads the payment gateway when needed now that the gateway is only created
 * on the checkout & settings pages / api hook.  The gateway can also be loaded
 * in the following instances:
 *
 * + On the My Account page to display / change saved payment methods (if supports tokenization)
 * + On the Admin User/Your Profile page to render/persist the customer ID field(s) (if supports customer_id)
 * + On the Admin Order Edit page to render a merchant account transaction direct link (if supports transaction_link)
 *
 * ## Supports (zero or more):
 *
 * + `customer_id`             - adds actions to show/persist the "Customer ID" area of the admin User edit page
 * + `transaction_link`        - adds actions to render the merchant account transaction direct link on the Admin Order Edit page.  (Don't forget to override the Payment_Gateway::get_transaction_url() method!)
 * + `capture_charge`          - adds actions to capture charge for authorization-only transactions
 * + `my_payment_methods`      - adds actions to show/handle a "My Payment Methods" area on the customer's My Account page. This will show saved payment methods for all plugin gateways that support tokenization.
 */
abstract class Payment_Gateway_Plugin extends Plugin {

	/** Customer ID feature */
	const FEATURE_CUSTOMER_ID = 'customer_id';

	/** Charge capture feature */
	const FEATURE_CAPTURE_CHARGE = 'capture_charge';

	/** My Payment Methods feature */
	const FEATURE_MY_PAYMENT_METHODS = 'my_payment_methods';

	/** @var array optional associative array of gateway id to array( 'gateway_class_name' => string, 'gateway' => Payment_Gateway ) */
	private $gateways;

	/** @var array optional array of currency codes this gateway is allowed for */
	private $currencies = array();

	/** @var array named features that this gateway supports which require action from the parent plugin, including 'tokenization' */
	private $supports = array();

	/** @var bool helper for lazy subscriptions active check */
	private $subscriptions_active;

	/** @var bool helper for lazy pre-orders active check */
	private $pre_orders_active;

	/** @var boolean true if this gateway requires SSL for processing transactions, false otherwise */
	private $require_ssl;

	/** @var SquareFramework\PaymentGateway\Payment_Gateway_Privacy payment gateway privacy handler instance */
	protected $privacy_handler;

	/** @var Payment_Gateway_Admin_Order order handler instance */
	protected $admin_order_handler;

	/** @var Payment_Gateway_Admin_User_Handler user handler instance */
	protected $admin_user_handler;

	/** @var Payment_Gateway_My_Payment_Methods adds My Payment Method functionality */
	private $my_payment_methods;

	/** @var Payment_Gateway_Apple_Pay the Apple Pay handler instance */
	private $apple_pay;


	/**
	 * Initializes the plugin.
	 *
	 * Optional args:
	 *
	 * + `require_ssl` - boolean true if this plugin requires SSL for proper functioning, false otherwise. Defaults to false
	 * + `gateways` - array associative array of gateway id to gateway class name.  A single plugin might support more than one gateway, ie credit card, echeck.  Note that the credit card gateway must always be the first one listed.
	 * + `currencies` -  array of currency codes this gateway is allowed for, defaults to all
	 * + `supports` - array named features that this gateway supports, including 'tokenization', 'transaction_link', 'customer_id', 'capture_charge'
	 *
	 * @since 3.0.0
	 *
	 * @see Plugin::__construct()
	 * @param string $id plugin id
	 * @param string $version plugin version number
	 * @param array $args plugin arguments
	 */
	public function __construct( $id, $version, $args ) {

		parent::__construct( $id, $version, $args );

		$args = wp_parse_args(
			$args,
			array(
				'gateways'    => array(),
				'currencies'  => array(),
				'supports'    => array(),
				'require_ssl' => false,
			)
		);

		// add each gateway
		foreach ( $args['gateways'] as $gateway_id => $gateway_class_name ) {
			$this->add_gateway( $gateway_id, $gateway_class_name );
		}

		$this->currencies  = (array) $args['currencies'];
		$this->supports    = (array) $args['supports'];
		$this->require_ssl = (array) $args['require_ssl'];

		// require the files
		$this->includes();

		// add the action & filter hooks
		$this->add_hooks();
	}

	/**
	 * Adds the action & filter hooks.
	 *
	 * @since 3.0.0
	 */
	private function add_hooks() {

		// add classes to WC Payment Methods
		add_filter( 'woocommerce_payment_gateways', array( $this, 'load_gateways' ) );

		// adjust the available gateways in certain cases
		add_filter( 'woocommerce_available_payment_gateways', array( $this, 'adjust_available_gateways' ) );

		// my payment methods feature
		add_action( 'init', array( $this, 'maybe_init_my_payment_methods' ) );

		// apple pay feature
		add_action( 'init', array( $this, 'maybe_init_apple_pay' ) );

		// TODO: move these to Subscriptions integration
		if ( $this->is_subscriptions_active() ) {

			// filter the payment gateway table on the checkout settings screen to indicate if a gateway can support Subscriptions but requires tokenization to be enabled
			add_action( 'admin_print_styles', array( $this, 'subscriptions_add_renewal_support_status_inline_style' ) );
			add_filter( 'woocommerce_payment_gateways_renewal_support_status_html', array( $this, 'subscriptions_maybe_edit_renewal_support_status' ), 10, 2 );
		}

		// add gateway information to the system status report
		add_action( 'woocommerce_system_status_report', array( $this, 'add_system_status_information' ) );
	}


	/**
	 * Initializes the plugin admin.
	 *
	 * @internal
	 * @see Plugin::init_admin()
	 *
	 * @since 3.0.0
	 */
	public function init_admin() {

		$this->admin_order_handler = new Payment_Gateway_Admin_Order( $this );
		$this->admin_user_handler  = new Payment_Gateway_Admin_User_Handler( $this );
	}


	/**
	 * Adds any gateways supported by this plugin to the list of available payment gateways.
	 *
	 * @internal
	 *
	 * @since 3.0.0
	 *
	 * @param array $gateways
	 * @return array $gateways
	 */
	public function load_gateways( $gateways ) {

		return array_merge( $gateways, $this->get_gateways() );
	}


	/**
	 * Adjust the available gateways in certain cases.
	 *
	 * @since 3.0.0
	 *
	 * @param array $available_gateways the available payment gateways
	 * @return array
	 */
	public function adjust_available_gateways( $available_gateways ) {

		if ( ! is_add_payment_method_page() ) {
			return $available_gateways;
		}

		foreach ( $this->get_gateways() as $gateway ) {

			if ( ! $gateway->supports_tokenization() || ! $gateway->supports_add_payment_method() || ! $gateway->tokenization_enabled() ) {
				unset( $available_gateways[ $gateway->id ] );
			}
		}

		return $available_gateways;
	}


	/**
	 * Include required files.
	 *
	 * @internal
	 *
	 * @since 3.0.0
	 */
	private function includes() {

		$payment_gateway_framework_path = $this->get_payment_gateway_framework_path();

		// interfaces
		require_once $payment_gateway_framework_path . '/Api/Payment_Gateway_Api.php';
		require_once $payment_gateway_framework_path . '/Api/Payment_Gateway_API_Response.php';
		require_once $payment_gateway_framework_path . '/Api/Payment_Gateway_Api_Authorization_Response.php';
		require_once $payment_gateway_framework_path . '/Api/Payment_Gateway_Api_Create_Payment_Token_Response.php';
		require_once $payment_gateway_framework_path . '/Api/Payment_Gateway_Api_Get_Tokenized_Payment_Methods_Response.php';
		require_once $payment_gateway_framework_path . '/Api/Payment_Gateway_Api_Customer_Response.php';

		// gateway
		require_once $payment_gateway_framework_path . '/Payment_Gateway.php';
		require_once $payment_gateway_framework_path . '/Payment_Gateway_Direct.php';
		require_once $payment_gateway_framework_path . '/Payment_Gateway_Payment_Form.php';
		require_once $payment_gateway_framework_path . '/Payment_Gateway_My_Payment_Methods.php';

		// handlers
		require_once $payment_gateway_framework_path . '/Handlers/Capture.php';

		// apple pay
		require_once "{$payment_gateway_framework_path}/ApplePay/Payment_Gateway_Apple_Pay.php";
		require_once "{$payment_gateway_framework_path}/ApplePay/Payment_Gateway_Apple_Pay_Admin.php";
		require_once "{$payment_gateway_framework_path}/ApplePay/Payment_Gateway_Apple_Pay_Frontend.php";
		require_once "{$payment_gateway_framework_path}/ApplePay/Payment_Gateway_Apple_Pay_Ajax.php";
		require_once "{$payment_gateway_framework_path}/ApplePay/Payment_Gateway_Apple_Pay_Orders.php";
		require_once "{$payment_gateway_framework_path}/ApplePay/Api/Payment_Gateway_Apple_Pay_Payment_Response.php";

		// payment tokens
		require_once $payment_gateway_framework_path . '/PaymentTokens/Payment_Gateway_Payment_Token.php';
		require_once $payment_gateway_framework_path . '/PaymentTokens/Payment_Gateway_Payment_Tokens_Handler.php';

		// helpers
		require_once $payment_gateway_framework_path . '/Api/Payment_Gateway_Api_Response_Message_Helper.php';
		require_once $payment_gateway_framework_path . '/Payment_Gateway_Helper.php';

		// admin
		require_once $payment_gateway_framework_path . '/Admin/Payment_Gateway_Admin_Order.php';
		require_once $payment_gateway_framework_path . '/Admin/Payment_Gateway_Admin_User_Handler.php';
		require_once $payment_gateway_framework_path . '/Admin/Payment_Gateway_Admin_Payment_Token_Editor.php';

		// integrations
		require_once $payment_gateway_framework_path . '/Integrations/Payment_Gateway_Integration.php';

		// subscriptions
		if ( $this->is_subscriptions_active() ) {
			require_once $payment_gateway_framework_path . '/Integrations/Payment_Gateway_Integration_Subscriptions.php';
		}

		// pre-orders
		if ( $this->is_pre_orders_active() ) {
			require_once $payment_gateway_framework_path . '/Integrations/Payment_Gateway_Integration_Pre_Orders.php';
		}

		// privacy
		require_once "{$payment_gateway_framework_path}/Payment_Gateway_Privacy.php";
		$this->privacy_handler = new Payment_Gateway_Privacy( $this );
	}


	/** My Payment Methods methods ***********************************/


	/**
	 * Instantiates the My Payment Methods table class instance when a user is
	 * logged in on an account page and tokenization is enabled for at least
	 * one of the active gateways.
	 *
	 * @internal
	 *
	 * @since 3.0.0
	 */
	public function maybe_init_my_payment_methods() {

		// bail if not frontend or an AJAX request
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		if ( $this->supports_my_payment_methods() && $this->tokenization_enabled() && is_user_logged_in() ) {
			$this->my_payment_methods = $this->get_my_payment_methods_instance();
		}
	}


	/**
	 * Returns true if tokenization is supported and enabled for at least one
	 * active gateway
	 *
	 * @since 3.0.0
	 *
	 * @return bool
	 */
	public function tokenization_enabled() {

		foreach ( $this->get_gateways() as $gateway ) {

			if ( $gateway->is_enabled() && $gateway->supports_tokenization() && $gateway->tokenization_enabled() ) {
				return true;
			}
		}

		return false;
	}


	/**
	 * Gets the My Payment Methods table instance.
	 *
	 * Overrideable by concrete gateway plugins to return a custom instance as needed
	 *
	 * @since 3.0.0
	 *
	 * @return Payment_Gateway_My_Payment_Methods
	 */
	protected function get_my_payment_methods_instance() {

		return new Payment_Gateway_My_Payment_Methods( $this );
	}


	/**
	 * Determines whether the My Payment Methods feature is supported.
	 *
	 * @since 3.0.0
	 *
	 * @return bool
	 */
	public function supports_my_payment_methods() {

		return $this->supports( self::FEATURE_MY_PAYMENT_METHODS );
	}


	/** Apple Pay *************************************************************/


	/**
	 * Initializes Apple Pay if it's supported.
	 *
	 * @since 3.0.0
	 */
	public function maybe_init_apple_pay() {

		/**
		 * Filters whether Apple Pay is activated.
		 *
		 * @since 3.0.0
		 *
		 * @param bool $activated whether Apple Pay is activated
		 */
		$activated = (bool) apply_filters( 'wc_payment_gateway_square_activate_apple_pay', false );

		if ( $this->supports_apple_pay() && $activated ) {
			$this->apple_pay = $this->build_apple_pay_instance();
		}
	}


	/**
	 * Builds the Apple Pay handler instance.
	 *
	 * Gateways can override this to define their own Apple Pay class.
	 *
	 * @since 3.0.0
	 *
	 * @return ApplePayFramework\Payment_Gateway_Apple_Pay
	 */
	protected function build_apple_pay_instance() {

		return new ApplePayFramework\Payment_Gateway_Apple_Pay( $this );
	}


	/**
	 * Gets the Apple Pay handler instance.
	 *
	 * @since 3.0.0
	 *
	 * @return ApplePayFramework\Payment_Gateway_Apple_Pay
	 */
	public function get_apple_pay_instance() {

		return $this->apple_pay;
	}


	/**
	 * Determines if this plugin has any gateways with Apple Pay support.
	 *
	 * @since 3.0.0
	 *
	 * @return bool
	 */
	public function supports_apple_pay() {

		$is_supported = false;

		foreach ( $this->get_gateways() as $gateway ) {

			if ( $gateway->supports_apple_pay() ) {
				$is_supported = true;
			}
		}

		return $is_supported;
	}


	/** Admin methods ******************************************************/

	/**
	 * Determines if on the admin gateway settings screen for this plugin.
	 *
	 * Multi-gateway plugins will return true if on either settings page
	 *
	 * @since 3.0.0
	 *
	 * @see Plugin::is_plugin_settings()
	 * @return bool
	 */
	public function is_plugin_settings() {

		foreach ( $this->get_gateways() as $gateway ) {
			if ( $this->is_payment_gateway_configuration_page( $gateway->get_id() ) ) {
				return true;
			}
		}

		return false;
	}


	/**
	 * Convenience method to add delayed admin notices, which may depend upon
	 * some setting being saved prior to determining whether to render.
	 *
	 * @since 3.0.0
	 *
	 * @see Plugin::add_delayed_admin_notices()
	 */
	public function add_delayed_admin_notices() {

		// reload all gateway settings so notices are correct after saving the settings
		foreach ( $this->get_gateways() as $gateway ) {
			$gateway->init_settings();
			$gateway->load_settings();
		}

		// notices for ssl requirement
		$this->add_ssl_admin_notices();

		// notices for currency issues
		$this->add_currency_admin_notices();

		// notices for subscriptions/pre-orders
		$this->add_integration_requires_tokenization_notices();

		// add notices about enabled debug logging
		$this->add_debug_setting_notices();
	}


	/**
	 * Adds any SSL admin notices.
	 *
	 * Checks if SSL is required and not available and adds a dismissible admin
	 * notice if so.
	 *
	 * @since 3.0.0
	 *
	 * @see Payment_Gateway_Plugin::add_admin_notices()
	 */
	protected function add_ssl_admin_notices() {

		if ( ! $this->requires_ssl() ) {
			return;
		}

		foreach ( $this->get_gateways() as $gateway ) {

			// don't display any notices for disabled gateways
			if ( ! $gateway->is_enabled() ) {
				continue;
			}

			// SSL check if gateway enabled/production mode
			if ( ! wc_checkout_is_https() ) {

				if ( $gateway->is_production_environment() && $this->get_admin_notice_handler()->should_display_notice( 'ssl-required' ) ) {

					$message = sprintf(
						/* translators: Placeholders: %1$s - plugin name, %2$s - <a> tag, %3$s - </a> tag */
						esc_html__( '%1$s: WooCommerce is not being forced over SSL; your customers\' payment data may be at risk. %2$sVerify your site URLs here%3$s', 'woocommerce-square' ),
						'<strong>' . $this->get_plugin_name() . '</strong>',
						'<a href="' . admin_url( 'options-general.php' ) . '">',
						' &raquo;</a>'
					);

					$this->get_admin_notice_handler()->add_admin_notice(
						$message,
						'ssl-required',
						array(
							'notice_class' => 'error',
						)
					);

					// just show the message once for plugins with multiple gateway support
					break;
				}
			} elseif ( $gateway->get_api() && is_callable( array( $gateway->get_api(), 'require_tls_1_2' ) ) && is_callable( array( $gateway->get_api(), 'is_tls_1_2_available' ) ) && $gateway->get_api()->require_tls_1_2() && ! $gateway->get_api()->is_tls_1_2_available() ) {

				/* translators: Placeholders: %s - payment gateway name */
				$message = sprintf( esc_html__( '%s will soon require TLS 1.2 support to process transactions and your server environment may need to be updated. Please contact your hosting provider to confirm that your site can send and receive TLS 1.2 connections and request they make any necessary updates.', 'woocommerce-square' ), '<strong>' . $gateway->get_method_title() . '</strong>' );

				$this->get_admin_notice_handler()->add_admin_notice(
					$message,
					'tls-1-2-required',
					array(
						'notice_class'            => 'notice-warning',
						'always_show_on_settings' => false,
					)
				);

				// just show the message once for plugins with multiple gateway support
				break;
			}
		}
	}


	/**
	 * Adds any currency admin notices.
	 *
	 * Checks if a particular currency is required and not being used and adds a
	 * dismissible admin notice if so.
	 *
	 * @since 3.0.0
	 *
	 * @see Payment_Gateway_Plugin::render_admin_notices()
	 */
	protected function add_currency_admin_notices() {

		// report any currency issues
		if ( $this->get_accepted_currencies() ) {

			// we might have a currency issue, go through any gateways provided by this plugin and see which ones (or all) have any unmet currency requirements
			// (gateway classes will already be instantiated, so it's not like this is a huge deal)
			$gateways = array();
			foreach ( $this->get_gateways() as $gateway ) {
				if ( $gateway->is_enabled() && ! $gateway->currency_is_accepted() ) {
					$gateways[] = $gateway;
				}
			}

			if ( count( $gateways ) === 0 ) {
				// no active gateways with unmet currency requirements
				return;
			} elseif ( count( $gateways ) === 1 && count( $this->get_gateways() ) > 1 ) {
				// one gateway out of many has a currency issue
				$suffix              = '-' . $gateway->get_id();
				$name                = $gateway->get_method_title();
				$accepted_currencies = $gateway->get_accepted_currencies();
			} else {
				// multiple gateways have a currency issue
				$suffix              = '';
				$name                = $this->get_plugin_name();
				$accepted_currencies = $this->get_accepted_currencies();
			}

			/* translators: [Plugin name] accepts payments in [currency/list of currencies] only */
			$message = sprintf(
				/* translators: Placeholders: %1$s - plugin name, %2$s - a currency/comma-separated list of currencies, %3$s - <a> tag, %4$s - </a> tag */
				_n(
					'%1$s accepts payment in %2$s only. %3$sConfigure%4$s WooCommerce to accept %2$s to enable this gateway for checkout.',
					'%1$s accepts payment in one of %2$s only. %3$sConfigure%4$s WooCommerce to accept one of %2$s to enable this gateway for checkout.',
					count( $accepted_currencies ),
					'woocommerce-square'
				),
				$name,
				'<strong>' . implode( ', ', $accepted_currencies ) . '</strong>',
				'<a href="' . $this->get_general_configuration_url() . '">',
				'</a>'
			);

			$this->get_admin_notice_handler()->add_admin_notice(
				$message,
				'accepted-currency' . $suffix,
				array(
					'notice_class' => 'error',
				)
			);

		}
	}


	/**
	 * Adds notices about enabled debug logging.
	 *
	 * @since 3.0.0
	 */
	protected function add_debug_setting_notices() {

		foreach ( $this->get_gateways() as $gateway ) {

			// Get the debug mode.
			$square_settings = get_option( 'wc_square_settings', array() );
			$debug_mode      = $square_settings['debug_mode'] ?? 'off';

			if ( $gateway->is_enabled() && $gateway->is_production_environment() && 'off' !== $debug_mode ) {

				$is_gateway_settings = $this->is_payment_gateway_configuration_page( $gateway->get_id() );

				$message = sprintf(
					/* translators: Placeholders: %1$s - payment gateway name, %2$s - opening <a> tag, %3$s - closing </a> tag */
					esc_html__( 'Heads up! %1$s is currently configured to log transaction data for debugging purposes. If you are not experiencing any problems with payment processing, we recommend %2$sturning off Debug Mode%3$s', 'woocommerce-square' ),
					$gateway->get_method_title(),
					! $is_gateway_settings ? '<a href="' . esc_url( $this->get_payment_gateway_configuration_url( $gateway->get_id() ) ) . '">' : '',
					! $is_gateway_settings ? ' &raquo;</a>' : ''
				);

				$this->get_admin_notice_handler()->add_admin_notice(
					$message,
					'debug-in-production',
					array(
						'notice_class' => 'notice-warning',
					)
				);

				break;
			}
		}
	}


	/** Integration methods ***************************************************/


	/**
	 * Checks if a supported integration is activated (Subscriptions or Pre-Orders)
	 * and adds a notice if a gateway supports the integration *and* tokenization,
	 * but tokenization is not enabled
	 *
	 * @since 3.0.0
	 */
	protected function add_integration_requires_tokenization_notices() {

		// either integration requires tokenization
		if ( $this->is_subscriptions_active() || $this->is_pre_orders_active() ) {

			foreach ( $this->get_gateways() as $gateway ) {

				$tokenization_supported_but_not_enabled = $gateway->supports_tokenization() && ! $gateway->tokenization_enabled();

				// subscriptions
				if ( $this->is_subscriptions_active() && $gateway->is_enabled() && $tokenization_supported_but_not_enabled ) {

					$message = sprintf(
						/* translators: Placeholders: %1$s - payment gateway title (such as Authorize.net, Braintree, etc), %2$s - <a> tag, %3$s - </a> tag */
						esc_html__( '%1$s is inactive for subscription transactions. Please %2$senable tokenization%3$s to activate %1$s for Subscriptions.', 'woocommerce-square' ),
						$gateway->get_method_title(),
						'<a href="' . $this->get_payment_gateway_configuration_url( $gateway->get_id() ) . '">',
						'</a>'
					);

					// add notice -- allow it to be dismissed even on the settings page as the admin may not want to use subscriptions with a particular gateway
					$this->get_admin_notice_handler()->add_admin_notice(
						$message,
						'subscriptions-tokenization-' . $gateway->get_id(),
						array(
							'always_show_on_settings' => false,
							'notice_class'            => 'error',
						)
					);
				}

				// pre-orders
				if ( $this->is_pre_orders_active() && $gateway->is_enabled() && $tokenization_supported_but_not_enabled ) {

					$message = sprintf(
						/* translators: Placeholders: %1$s - payment gateway title (such as Authorize.net, Braintree, etc), %2$s - <a> tag, %3$s - </a> tag */
						esc_html__( '%1$s is inactive for pre-order transactions. Please %2$senable tokenization%3$s to activate %1$s for Pre-Orders.', 'woocommerce-square' ),
						$gateway->get_method_title(),
						'<a href="' . $this->get_payment_gateway_configuration_url( $gateway->get_id() ) . '">',
						'</a>'
					);

					// add notice -- allow it to be dismissed even on the settings page as the admin may not want to use pre-orders with a particular gateway
					$this->get_admin_notice_handler()->add_admin_notice(
						$message,
						'pre-orders-tokenization-' . $gateway->get_id(),
						array(
							'always_show_on_settings' => false,
							'notice_class'            => 'error',
						)
					);
				}
			}
		}
	}


	/**
	 * Edit the Subscriptions automatic renewal payments support column content
	 * when a gateway supports subscriptions (via tokenization) but tokenization
	 * is not enabled
	 *
	 * @since 3.0.0
	 *
	 * @param string $html column content
	 * @param WC_Payment_Gateway|Payment_Gateway $gateway payment gateway being checked for support
	 * @return string html
	 */
	public function subscriptions_maybe_edit_renewal_support_status( $html, $gateway ) {

		// only for our gateways
		if ( ! in_array( $gateway->id, $this->get_gateway_ids(), true ) ) {
			return $html;
		}

		if ( $gateway->is_enabled() && $gateway->supports_tokenization() && ! $gateway->tokenization_enabled() ) {

			$tool_tip = esc_attr__( 'You must enable tokenization for this gateway in order to support automatic renewal payments with the WooCommerce Subscriptions extension.', 'woocommerce-square' );
			$status   = esc_html__( 'Inactive', 'woocommerce-square' );

			$html = sprintf(
				'<a href="%1$s"><span class="sv-wc-payment-gateway-renewal-status-inactive tips" data-tip="%2$s">%3$s</span></a>',
				esc_url( $this->get_payment_gateway_configuration_url( $gateway->get_id() ) ),
				$tool_tip,
				$status
			);
		}

		return $html;
	}


	/**
	 * Add some inline CSS to render the failed order status icon for the
	 * automatic renewal payment support status column
	 *
	 * @since 3.0.0
	 */
	public function subscriptions_add_renewal_support_status_inline_style() {

		if ( Plugin_Compatibility::normalize_wc_screen_id() === get_current_screen()->id ) {
			wp_add_inline_style( 'woocommerce_admin_styles', '.sv-wc-payment-gateway-renewal-status-inactive{font-size:1.4em;display:block;text-indent:-9999px;position:relative;height:1em;width:1em;cursor:pointer}.sv-wc-payment-gateway-renewal-status-inactive:before{line-height:1;margin:0;position:absolute;width:100%;height:100%;content:"\e016";color:#ffba00;font-family:WooCommerce;speak:none;font-weight:400;font-variant:normal;text-transform:none;-webkit-font-smoothing:antialiased;text-indent:0;top:0;left:0;text-align:center}' );
		}
	}


	/**
	 * Add gateway information to the system status report.
	 *
	 * @since 3.0.0
	 */
	public function add_system_status_information() {

		foreach ( $this->get_gateways() as $gateway ) {

			// Skip gateways that aren't enabled
			if ( ! $gateway->is_enabled() ) {
				continue;
			}

			$environment = $gateway->get_environment_name();

			include $this->get_payment_gateway_framework_path() . '/Admin/views/html-admin-gateway-status.php';
		}
	}


	/** Helper methods ******************************************************/


	/**
	 * Determines if the plugin supports the capture charge feature.
	 *
	 * @since 3.0.0
	 *
	 * @return bool
	 */
	public function supports_capture_charge() {

		return $this->supports( self::FEATURE_CAPTURE_CHARGE );
	}


	/**
	 * Returns true if the gateway supports the named feature
	 *
	 * @since 3.0.0
	 * @param string $feature the feature
	 * @return boolean true if the named feature is supported
	 */
	public function supports( $feature ) {
		return in_array( $feature, $this->supports, true );
	}


	/** Getter methods ******************************************************/


	/**
	 * Gets the privacy handler instance.
	 *
	 * @since 3.0.0
	 *
	 * @return Payment_Gateway_Privacy
	 */
	public function get_privacy_instance() {

		return $this->privacy_handler;
	}


	/**
	 * Get the admin order handler instance.
	 *
	 * @since 3.0.0
	 *
	 * @return Payment_Gateway_Admin_Order
	 */
	public function get_admin_order_handler() {
		return $this->admin_order_handler;
	}


	/**
	 * Get the admin user handler instance.
	 *
	 * @since 3.0.0
	 *
	 * @return Payment_Gateway_Admin_User_Handler
	 */
	public function get_admin_user_handler() {
		return $this->admin_user_handler;
	}


	/**
	 * Returns the gateway settings option name for the identified gateway.
	 * Defaults to woocommerce_{gateway id}_settings
	 *
	 * @since 3.0.0
	 *
	 * @param string $gateway_id
	 * @return string the gateway settings option name
	 */
	protected function get_gateway_settings_name( $gateway_id ) {

		return 'woocommerce_' . $gateway_id . '_settings';

	}


	/**
	 * Returns the settings array for the identified gateway.  Note that this
	 * will not include any defaults if the gateway has yet to be saved
	 *
	 * @since 3.0.0
	 *
	 * @param string $gateway_id gateway identifier
	 * @return array settings array
	 */
	public function get_gateway_settings( $gateway_id ) {

		return get_option( $this->get_gateway_settings_name( $gateway_id ) );
	}


	/**
	 * Returns true if this plugin requires SSL to function properly
	 *
	 * @since 3.0.0
	 *
	 * @return boolean true if this plugin requires ssl
	 */
	protected function requires_ssl() {
		return $this->require_ssl;
	}


	/**
	 * Gets the plugin configuration URL
	 *
	 * @since 3.0.0
	 *
	 * @see Plugin::get_settings_url()
	 * @param string $gateway_id the gateway identifier
	 * @return string gateway settings URL
	 */
	public function get_settings_url( $gateway_id = null ) {

		// default to first gateway
		if ( is_null( $gateway_id ) || $gateway_id === 'square' ) {
			reset( $this->gateways );
			$gateway_id = key( $this->gateways );
		}

		return $this->get_payment_gateway_configuration_url( $gateway_id );
	}


	/**
	 * Returns the admin configuration url for a gateway
	 *
	 * @since 3.0.0
	 *
	 * @param string $gateway_id the gateway ID
	 * @return string admin configuration url for the gateway
	 */
	public function get_payment_gateway_configuration_url( $gateway_id ) {

		return admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . $gateway_id );
	}


	/**
	 * Returns true if the current page is the admin configuration page for a gateway
	 *
	 * @since 3.0.0
	 *
	 * @param string $gateway_id the gateway ID
	 * @return boolean true if the current page is the admin configuration page for the gateway
	 */
	public function is_payment_gateway_configuration_page( $gateway_id ) {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Not required, only comparing against known string, read-only action.
		return isset( $_GET['page'] ) && 'wc-settings' === $_GET['page'] &&
		isset( $_GET['tab'] ) && 'checkout' === $_GET['tab'] &&
		isset( $_GET['section'] ) && $gateway_id === $_GET['section'];
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Adds the given gateway id and gateway class name as an available gateway
	 * supported by this plugin
	 *
	 * @since 3.0.0
	 *
	 * @param string $gateway_id the gateway identifier
	 * @param string $gateway_class_name the corresponding gateway class name
	 */
	public function add_gateway( $gateway_id, $gateway_class_name ) {

		$this->gateways[ $gateway_id ] = array(
			'gateway_class_name' => $gateway_class_name,
			'gateway'            => null,
		);
	}


	/**
	 * Gets all supported gateway class names; typically this will be just one,
	 * unless the plugin supports credit card and echeck variations
	 *
	 * @since 3.0.0
	 *
	 * @return array of string gateway class names
	 */
	public function get_gateway_class_names() {

		assert( ! empty( $this->gateways ) );

		$gateway_class_names = array();

		foreach ( $this->gateways as $gateway ) {
			$gateway_class_names[] = $gateway['gateway_class_name'];
		}

		return $gateway_class_names;
	}


	/**
	 * Gets the gateway class name for the given gateway id
	 *
	 * @since 3.0.0
	 *
	 * @param string $gateway_id the gateway identifier
	 * @return string gateway class name
	 */
	public function get_gateway_class_name( $gateway_id ) {

		assert( isset( $this->gateways[ $gateway_id ]['gateway_class_name'] ) );

		return $this->gateways[ $gateway_id ]['gateway_class_name'];
	}


	/**
	 * Gets all supported gateway objects; typically this will be just one,
	 * unless the plugin supports credit card and echeck variations
	 *
	 * @since 3.0.0
	 *
	 * @return Payment_Gateway[]
	 */
	public function get_gateways() {

		assert( ! empty( $this->gateways ) );

		$gateways = array();

		foreach ( $this->get_gateway_ids() as $gateway_id ) {
			$gateways[] = $this->get_gateway( $gateway_id );
		}

		return $gateways;
	}


	/**
	 * Adds the given $gateway to the internal gateways store
	 *
	 * @since 3.0.0
	 *
	 * @param string $gateway_id the gateway identifier
	 * @param Payment_Gateway $gateway the gateway object
	 */
	public function set_gateway( $gateway_id, $gateway ) {
		$this->gateways[ $gateway_id ]['gateway'] = $gateway;
	}


	/**
	 * Returns the identified gateway object
	 *
	 * @since 3.0.0
	 *
	 * @param string $gateway_id optional gateway identifier, defaults to first gateway, which will be the credit card gateway in plugins with support for both credit cards and echecks
	 * @return Payment_Gateway the gateway object
	 */
	public function get_gateway( $gateway_id = null ) {

		// default to first gateway
		if (
			is_null( $gateway_id ) ||
			! in_array( $gateway_id, $this->get_gateway_ids(), true )
		) {
			reset( $this->gateways );
			$gateway_id = key( $this->gateways );
		}

		if ( ! isset( $this->gateways[ $gateway_id ]['gateway'] ) ) {

			// instantiate and cache
			$gateway_class_name = $this->get_gateway_class_name( $gateway_id );
			$this->set_gateway( $gateway_id, new $gateway_class_name() );
		}

		return $this->gateways[ $gateway_id ]['gateway'];
	}


	/**
	 * Returns true if the plugin supports this gateway
	 *
	 * @since 3.0.0
	 *
	 * @param string $gateway_id the gateway identifier
	 * @return boolean true if the plugin has this gateway available, false otherwise
	 */
	public function has_gateway( $gateway_id ) {
		return isset( $this->gateways[ $gateway_id ] );
	}


	/**
	 * Returns all available gateway ids for the plugin
	 *
	 * @since 3.0.0
	 *
	 * @return array of gateway id strings
	 */
	public function get_gateway_ids() {

		assert( ! empty( $this->gateways ) );

		return array_keys( $this->gateways );
	}


	/**
	 * Returns the gateway for a given token
	 *
	 * @since 3.0.0
	 *
	 * @param string|int $user_id the user ID associated with the token
	 * @param string $token the token string
	 * @return Payment_Gateway|null gateway if found, null otherwise
	 */
	public function get_gateway_from_token( $user_id, $token ) {

		foreach ( $this->get_gateways() as $gateway ) {

			if ( $gateway->get_payment_tokens_handler()->user_has_token( $user_id, $token ) ) {
				return $gateway;
			}
		}

		return null;
	}


	/**
	 * No-op the plugin class implementation so the payment gateway class can
	 * implement its own request logging. This is primarily done to keep the log
	 * files separated by gateway ID
	 *
	 * @see Plugin::add_api_request_logging()
	 *
	 * @since 3.0.0
	 */
	public function add_api_request_logging() { }


	/**
	 * Returns the set of accepted currencies, or empty array if all currencies
	 * are accepted.  This is the intersection of all currencies accepted by
	 * any gateways this plugin supports.
	 *
	 * @since 3.0.0
	 *
	 * @return array of accepted currencies
	 */
	public function get_accepted_currencies() {
		return $this->currencies;
	}


	/**
	 * Checks is WooCommerce Subscriptions is active
	 *
	 * @since 3.0.0
	 *
	 * @return bool true if the WooCommerce Subscriptions plugin is active, false if not active
	 */
	public function is_subscriptions_active() {

		if ( is_bool( $this->subscriptions_active ) ) {
			return $this->subscriptions_active;
		}

		return $this->subscriptions_active = $this->is_plugin_active( 'woocommerce-subscriptions.php' );
	}


	/**
	 * Checks is WooCommerce Pre-Orders is active
	 *
	 * @since 3.0.0
	 *
	 * @return bool true if WC Pre-Orders is active, false if not active
	 */
	public function is_pre_orders_active() {

		if ( is_bool( $this->pre_orders_active ) ) {
			return $this->pre_orders_active;
		}

		return $this->pre_orders_active = $this->is_plugin_active( 'woocommerce-pre-orders.php' );
	}


	/**
	 * Returns the loaded payment gateway framework __FILE__
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public function get_payment_gateway_framework_file() {

		return __FILE__;
	}


	/**
	 * Returns the loaded payment gateway framework path, without trailing slash.
	 *
	 * This is the highest version payment gateway framework that was loaded by
	 * the bootstrap.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public function get_payment_gateway_framework_path() {

		return untrailingslashit( plugin_dir_path( $this->get_payment_gateway_framework_file() ) );
	}


	/**
	 * Returns the absolute path to the loaded payment gateway framework image
	 * directory, without a trailing slash
	 *
	 * @since 3.0.0
	 *
	 * @return string relative path to framework image directory
	 */
	public function get_payment_gateway_framework_assets_path() {

		return WC_SQUARE_PLUGIN_PATH . '/assets';
	}


	/**
	 * Returns the loaded payment gateway framework assets URL, without a trailing slash
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public function get_payment_gateway_framework_assets_url() {

		return untrailingslashit( WC_SQUARE_PLUGIN_URL . '/assets' );
	}
}
