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
 */

namespace WooCommerce\Square\Gateway;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Square\Plugin;
use WooCommerce\Square\Gateway\Customer_Helper;
use WooCommerce\Square\Gateway\Payment_Form;
use WooCommerce\Square\Utilities\Money_Utility;
use WooCommerce\Square\Framework\PaymentGateway\Payment_Gateway;
use WooCommerce\Square\Framework\Square_Helper;
use WooCommerce\Square\Gateway;
use WooCommerce\Square\WC_Order_Square;

/**
 * The Cash App Pay payment gateway class.
 *
 * @since x.x.x
 */
class WC_Gateway_Cash_App_Pay extends Payment_Gateway {

	/** @var API API base instance */
	private $api;

	/**
	 * Square Payment Form instance
	 * Null by default.
	 */
	private $payment_form = null;

	/**
	 * Constructs the class.
	 *
	 * @since x.x.x
	 */
	public function __construct() {
		parent::__construct(
			Plugin::CASH_APP_PAY_GATEWAY_ID,
			wc_square(),
			array(
				'method_title'       => __( 'Cash App Pay (Square)', 'woocommerce-square' ),
				'method_description' => __( 'Allow customers to use Cash App Pay to securely pay with their Cash App', 'woocommerce-square' ),
				'payment_type'       => 'cash_app_pay',
				'supports'           => array(
					self::FEATURE_PRODUCTS,
					self::FEATURE_DETAILED_CUSTOMER_DECLINE_MESSAGES,
					self::FEATURE_REFUNDS,
					self::FEATURE_PAYMENT_FORM,
				),
				'countries'          => array( 'US' ),
				'currencies'         => array( 'USD' ),
			)
		);

		// payment method image
		$this->icon = $this->get_payment_method_image_url();

		// Ajax hooks
		add_action( 'wc_ajax_square_cash_app_get_payment_request', array( $this, 'ajax_get_payment_request' ) );
	}

	/** Admin methods *************************************************************************************************/

	/**
	 * Get the default payment method title, which is configurable within the
	 * admin and displayed on checkout
	 *
	 * @since x.x.x
	 * @return string payment method title to show on checkout
	 */
	protected function get_default_title() {
		return esc_html__( 'Cash App Pay', 'woocommerce-square' );
	}


	/**
	 * Get the default payment method description, which is configurable
	 * within the admin and displayed on checkout
	 *
	 * @since x.x.x
	 * @return string payment method description to show on checkout
	 */
	protected function get_default_description() {
		return esc_html__( 'Pay securely using Cash App Pay.', 'woocommerce-square' );
	}

	/**
	 * Initialize payment gateway settings fields
	 *
	 * @since x.x.x
	 * @see WC_Settings_API::init_form_fields()
	 */
	public function init_form_fields() {

		// common top form fields
		$this->form_fields = array(
			'enabled'      => array(
				'title'   => esc_html__( 'Enable / Disable', 'woocommerce-square' ),
				'label'   => esc_html__( 'Enable this gateway', 'woocommerce-square' ),
				'type'    => 'checkbox',
				'default' => 'no',
			),

			'title'        => array(
				'title'    => esc_html__( 'Title', 'woocommerce-square' ),
				'type'     => 'text',
				'desc_tip' => esc_html__( 'Payment method title that the customer will see during checkout.', 'woocommerce-square' ),
				'default'  => $this->get_default_title(),
			),

			'description'  => array(
				'title'    => esc_html__( 'Description', 'woocommerce-square' ),
				'type'     => 'textarea',
				'desc_tip' => esc_html__( 'Payment method description that the customer will see during checkout.', 'woocommerce-square' ),
				'default'  => $this->get_default_description(),
			),

			'button_theme' => array(
				'title'    => esc_html__( 'Cash App Pay Button Theme', 'woocommerce-square' ),
				'desc_tip' => esc_html__( 'Select the theme of the Cash App Pay button.', 'woocommerce-square' ),
				'type'     => 'select',
				'default'  => 'dark',
				'class'    => 'wc-enhanced-select wc-square-cash-app-pay-options',
				'options'  => array(
					'dark'  => esc_html__( 'Dark', 'woocommerce-square' ),
					'light' => esc_html__( 'Light', 'woocommerce-square' ),
				),
			),

			'button_shape' => array(
				'title'    => esc_html__( 'Cash App Pay Button shape', 'woocommerce-square' ),
				'desc_tip' => esc_html__( 'Select the shape of the Cash App Pay button.', 'woocommerce-square' ),
				'type'     => 'select',
				'default'  => 'semiround',
				'class'    => 'wc-enhanced-select wc-square-cash-app-pay-options',
				'options'  => array(
					'semiround' => esc_html__( 'Semi round', 'woocommerce-square' ),
					'round'     => esc_html__( 'Round', 'woocommerce-square' ),
				),
			),
		);

		// add "detailed customer decline messages" option if the feature is supported
		if ( $this->supports( self::FEATURE_DETAILED_CUSTOMER_DECLINE_MESSAGES ) ) {
			$this->form_fields['enable_customer_decline_messages'] = array(
				'title'   => esc_html__( 'Detailed Decline Messages', 'woocommerce-square' ),
				'type'    => 'checkbox',
				'label'   => esc_html__( 'Check to enable detailed decline messages to the customer during checkout when possible, rather than a generic decline message.', 'woocommerce-square' ),
				'default' => 'no',
			);
		}

		// debug mode
		$this->form_fields['debug_mode'] = array(
			'title'   => esc_html__( 'Debug Mode', 'woocommerce-square' ),
			'type'    => 'select',
			'class'   => 'wc-enhanced-select',
			/* translators: Placeholders: %1$s - <a> tag, %2$s - </a> tag */
			'desc'    => sprintf( esc_html__( 'Show Detailed Error Messages and API requests/responses on the checkout page and/or save them to the %1$sdebug log%2$s', 'woocommerce-square' ), '<a href="' . Square_Helper::get_wc_log_file_url( $this->get_id() ) . '">', '</a>' ),
			'default' => self::DEBUG_MODE_OFF,
			'options' => array(
				self::DEBUG_MODE_OFF      => esc_html__( 'Off', 'woocommerce-square' ),
				self::DEBUG_MODE_CHECKOUT => esc_html__( 'Show on Checkout Page', 'woocommerce-square' ),
				self::DEBUG_MODE_LOG      => esc_html__( 'Save to Log', 'woocommerce-square' ),
				/* translators: show debugging information on both checkout page and in the log */
				self::DEBUG_MODE_BOTH     => esc_html__( 'Both', 'woocommerce-square' ),
			),
		);

		// if there is more than just the production environment available
		if ( count( $this->get_environments() ) > 1 ) {
			$this->form_fields = $this->add_environment_form_fields( $this->form_fields );
		}

		/**
		 * Payment Gateway Form Fields Filter.
		 *
		 * Actors can use this to add, remove, or tweak gateway form fields
		 *
		 * @since x.x.x
		 * @param array $form_fields array of form fields in format required by WC_Settings_API
		 * @param Payment_Gateway $this gateway instance
		 */
		$this->form_fields = apply_filters( 'wc_payment_gateway_' . $this->get_id() . '_form_fields', $this->form_fields, $this );
	}

	/** Conditional methods *******************************************************************************************/

	/**
	 * Determines if the gateway is available.
	 *
	 * @since x.x.x
	 *
	 * @return bool
	 */
	public function is_available() {

		return parent::is_available() && $this->get_plugin()->get_settings_handler()->is_connected() && $this->get_plugin()->get_settings_handler()->get_location_id();
	}

	/** Getter methods ************************************************************************************************/

	/**
	 * Gets the payment form handler instance.
	 *
	 * @since x.x.x
	 *
	 * @return Payment_Form
	 */
	public function get_payment_form_instance() {

		if ( empty( $this->payment_form ) ) {
			$this->payment_form = new Cash_App_Pay_Payment_Form( $this );
		}

		return $this->payment_form;
	}

	/**
	 * Gets the API instance.
	 *
	 * @since x.x.x
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
	 * @since x.x.x
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
	 * @since x.x.x
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
	 * @since x.x.x
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
	 * Gets the order object with payment information added.
	 *
	 * @since 2.0.0
	 *
	 * @param int|\WC_Order $order_id order ID or object
	 * @return \WC_Order
	 */
	public function get_order( $order_id ) {
		$order = parent::get_order( $order_id );

		$order->payment->nonce               = new \stdClass();
		$order->payment->nonce->cash_app_pay = Square_Helper::get_post( 'wc-' . $this->get_id_dasherized() . '-payment-nonce' );

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
	 * Gets the configured environment ID.
	 *
	 * Square doesn't really support a sandbox, so we don't show a setting for this.
	 *
	 * @since x.x.x
	 *
	 * @return string
	 */
	public function get_environment() {
		return self::ENVIRONMENT_PRODUCTION;
	}

	/**
	 * Gets the configured application ID.
	 *
	 * @since x.x.x
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
		 * @since x.x.x
		 *
		 * @param string $application_id application ID
		 */
		return apply_filters( 'wc_square_application_id', $square_application_id );
	}

	/**
	 * Validate WooCommerce checkout data on Square JS AJAX call
	 *
	 * Returns validation errors (or success) as JSON and exits to prevent checkout
	 *
	 * @since x.x.x
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
	 * @since x.x.x
	 * @param WC_Order $order the order object
	 * @return WC_Order order object with member named unique_transaction_ref
	 */
	protected function get_order_with_unique_transaction_ref( $order ) {
		$order_id = $order->get_id();

		// generate a unique retry count
		if ( is_numeric( $this->get_order_meta( $order_id, 'retry_count' ) ) ) {
			$retry_count = $this->get_order_meta( $order_id, 'retry_count' );
			++$retry_count;
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
	 * @since x.x.x
	 * @param string $type the payment method cc type or name
	 * @return string the image URL or null
	 */
	public function get_payment_method_image_url( $type = '' ) {
		/**
		 * Payment Gateway Fallback to PNG Filter.
		 *
		 * Allow actors to enable the use of PNGs over SVGs for payment icon images.
		 *
		 * @since x.x.x
		 * @param bool $use_svg true by default, false to use PNGs
		 */
		$image_extension = apply_filters( 'wc_payment_gateway_' . $this->get_id() . '_use_svg', true ) ? '.svg' : '.png';

		// first, is the card image available within the plugin?
		if ( is_readable( $this->get_plugin()->get_plugin_path() . '/assets/images/cash-app' . $image_extension ) ) {
			return \WC_HTTPS::force_https_url( $this->get_plugin()->get_plugin_url() . '/assets/images/cash-app' . $image_extension );
		}

		// Fall back to framework image URL.
		return parent::get_payment_method_image_url( $type );
	}

	/**
	 * Mark an order as refunded. This should only be used when the full order
	 * amount has been refunded.
	 *
	 * @since x.x.x
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
	 * Enqueue the payment form JS, CSS, and localized
	 * JS params
	 *
	 * @since x.x.x
	 */
	protected function enqueue_payment_form_assets() {

		// bail if on my account page and *not* on add payment method page
		if ( is_account_page() && ! is_add_payment_method_page() ) {
			return;
		}

		$handle = 'wc-square-cash-app';

		// Frontend JS
		wp_enqueue_script( $handle, $this->get_plugin()->get_plugin_url() . '/assets/js/frontend/' . $handle . '.min.js', array( 'jquery-payment' ), Plugin::VERSION, true );

		// Frontend CSS
		// wp_enqueue_style( $handle, $this->get_plugin()->get_plugin_url() . '/assets/css/frontend/' . $handle . '.min.css', array(), Plugin::VERSION );
	}

	/**
	 * Build the payment request object for the cash app pay payment form.
	 *
	 * Payment request objects are used by the Payments and need to be in a specific format.
	 * Reference: https://developer.squareup.com/docs/api/paymentform#paymentform-paymentrequestobjects
	 *
	 * @since x.x.x
	 * @return array
	 */
	public function get_payment_request() {
		// Ignoring nonce verification checks as it is already handled in the parent function.
		$payment_request       = array();
		$is_pay_for_order_page = isset( $_POST['is_pay_for_order_page'] ) ? 'true' === sanitize_text_field( wp_unslash( $_POST['is_pay_for_order_page'] ) ) : is_wc_endpoint_url( 'order-pay' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$order_id              = isset( $_POST['order_id'] ) ? (int) sanitize_text_field( wp_unslash( $_POST['order_id'] ) ) : absint( get_query_var( 'order-pay' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( is_wc_endpoint_url( 'order-pay' ) || $is_pay_for_order_page ) {
			$order           = wc_get_order( $order_id );
			$payment_request = $this->build_payment_request(
				$order->get_total(),
				array(
					'order_id'              => $order_id,
					'is_pay_for_order_page' => $is_pay_for_order_page,
				)
			);
		} elseif ( isset( WC()->cart ) ) {
			WC()->cart->calculate_totals();
			$payment_request = $this->build_payment_request( WC()->cart->total );
		}

		return $payment_request;
	}

	/**
	 * Build a payment request object to be sent to Payments.
	 *
	 * Documentation: https://developer.squareup.com/docs/api/paymentform#paymentform-paymentrequestobjects
	 *
	 * @since x.x.x
	 * @param string $amount - format '100.00'
	 * @param array $data
	 * @return array
	 */
	public function build_payment_request( $amount, $data = array() ) {
		$is_pay_for_order_page = isset( $data['is_pay_for_order_page'] ) ? $data['is_pay_for_order_page'] : false;
		$order_id              = isset( $data['order_id'] ) ? $data['order_id'] : 0;

		$order_data = array();
		$data       = wp_parse_args(
			$data,
			array(
				'countryCode'  => substr( get_option( 'woocommerce_default_country' ), 0, 2 ),
				'currencyCode' => get_woocommerce_currency(),
			)
		);

		if ( $is_pay_for_order_page ) {
			$order      = wc_get_order( $order_id );
			$order_data = array(
				'subtotal' => $order->get_subtotal(),
				'discount' => $order->get_discount_total(),
				'shipping' => $order->get_shipping_total(),
				'fees'     => $order->get_total_fees(),
				'taxes'    => $order->get_total_tax(),
			);

			unset( $data['is_pay_for_order_page'], $data['order_id'] );
		}

		if ( ! isset( $data['lineItems'] ) ) {
			$data['lineItems'] = $this->build_payment_request_line_items( $order_data );
		}

		/**
		 * Filters the payment request Total Label Suffix.
		 *
		 * @since x.x.x
		 * @param string $total_label_suffix
		 * @return string
		 */
		$total_label_suffix = apply_filters( 'woocommerce_square_payment_request_total_label_suffix', __( 'via WooCommerce', 'woocommerce-square' ) );
		$total_label_suffix = $total_label_suffix ? " ($total_label_suffix)" : '';

		$data['total'] = array(
			'label'   => get_bloginfo( 'name', 'display' ) . esc_html( $total_label_suffix ),
			'amount'  => number_format( $amount, 2, '.', '' ),
			'pending' => false,
		);

		return $data;
	}

	/**
	 * Builds an array of line items/totals to be sent back to Square in the lineItems array.
	 *
	 * @since x.x.x
	 * @param array $totals
	 * @return array
	 */
	public function build_payment_request_line_items( $totals = array() ) {
		// Ignoring nonce verification checks as it is already handled in the parent function.
		$totals     = empty( $totals ) ? $this->get_cart_totals() : $totals;
		$line_items = array();
		$order_id   = isset( $_POST['order_id'] ) ? (int) sanitize_text_field( wp_unslash( $_POST['order_id'] ) ) : absint( get_query_var( 'order-pay' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( $order_id ) {
			$order    = wc_get_order( $order_id );
			$iterable = $order->get_items();
		} else {
			$iterable = WC()->cart->get_cart();
		}

		foreach ( $iterable as $item ) {
			$amount = number_format( $order_id ? $order->get_subtotal() : $item['line_subtotal'], 2, '.', '' );

			if ( $order_id ) {
				$quantity_label = 1 < $item->get_quantity() ? ' x ' . $item->get_quantity() : '';
			} else {
				$quantity_label = 1 < $item['quantity'] ? ' x ' . $item['quantity'] : '';
			}

			$item = array(
				'label'   => $order_id ? $item->get_name() . $quantity_label : $item['data']->get_name() . $quantity_label,
				'amount'  => $amount,
				'pending' => false,
			);

			$line_items[] = $item;
		}

		if ( $totals['shipping'] > 0 ) {
			$line_items[] = array(
				'label'   => __( 'Shipping', 'woocommerce-square' ),
				'amount'  => number_format( $totals['shipping'], 2, '.', '' ),
				'pending' => false,
			);
		}

		if ( $totals['taxes'] > 0 ) {
			$line_items[] = array(
				'label'   => __( 'Tax', 'woocommerce-square' ),
				'amount'  => number_format( $totals['taxes'], 2, '.', '' ),
				'pending' => false,
			);
		}

		if ( $totals['discount'] > 0 ) {
			$line_items[] = array(
				'label'   => __( 'Discount', 'woocommerce-square' ),
				'amount'  => number_format( $totals['discount'], 2, '.', '' ),
				'pending' => false,
			);
		}

		if ( $totals['fees'] > 0 ) {
			$line_items[] = array(
				'label'   => __( 'Fees', 'woocommerce-square' ),
				'amount'  => number_format( $totals['fees'], 2, '.', '' ),
				'pending' => false,
			);
		}

		return $line_items;
	}

	/**
	 * Get the payment request object in an ajax request
	 *
	 * @since x.x.x
	 * @return void
	 */
	public function ajax_get_payment_request() {
		check_ajax_referer( 'wc-cash-app-get-payment-request', 'security' );

		$payment_request = array();

		try {
			$payment_request = $this->get_payment_request();

			if ( empty( $payment_request ) ) {
				/* translators: Context (product, cart, checkout or page) */
				throw new \Exception( esc_html__( 'Empty payment request data for page.', 'woocommerce-square' ) );
			}
		} catch ( \Exception $e ) {
			wp_send_json_error( $e->getMessage() );
		}

		wp_send_json_success( wp_json_encode( $payment_request ) );
	}

	/**
	 * Returns cart totals in an array format
	 *
	 * @since x.x.x
	 * @throws \Exception if no cart is found
	 * @return array
	 */
	public function get_cart_totals() {
		if ( ! isset( WC()->cart ) ) {
			throw new \Exception( 'Cart data cannot be found.' );
		}

		return array(
			'subtotal' => WC()->cart->subtotal_ex_tax,
			'discount' => WC()->cart->get_cart_discount_total(),
			'shipping' => WC()->cart->shipping_total,
			'fees'     => WC()->cart->fee_total,
			'taxes'    => WC()->cart->tax_total + WC()->cart->shipping_tax_total,
		);
	}


	/**
	 * Enqueue the gateway-specific assets if present, including JS, CSS, and
	 * localized script params
	 *
	 * @since x.x.x
	 */
	protected function enqueue_gateway_assets() {}

	/**
	 * Returns true if a transaction should be forced (meaning payment
	 * processed even if the order amount is 0).  This is useful mostly for
	 * testing situations
	 *
	 * @since x.x.x
	 * @return boolean true if the transaction request should be forced
	 */
	public function transaction_forced() {
		return false;
	}

	/**
	 * Handles payment processing.
	 *
	 * @see WC_Payment_Gateway::process_payment()
	 *
	 * @since x.x.x
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
		 * @since x.x.x
		 * @param bool $result default true
		 * @param int|string $order_id order ID for the payment
		 * @param WC_Gateway_Cash_App_Pay $this instance
		 */
		$result = apply_filters( 'wc_payment_gateway_' . $this->get_id() . '_process_payment', true, $order_id, $this );

		if ( is_array( $result ) ) {
			return $result;
		}

		// add payment information to order
		$order = $this->get_order( $order_id );

		try {
			// Charge the order.
			$transcation_result = $this->do_transaction( $order );

			if ( $transcation_result ) {

				/**
				 * Filters the order status that's considered to be "held".
				 *
				 * @since x.x.x
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
				 * @since x.x.x
				 * @param \WC_Order $order order object
				 * @param Payment_Gateway_Direct $this instance
				 */
				do_action( 'wc_payment_gateway_' . $this->get_id() . '_payment_processed', $order, $this );

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
	 * Do the transaction.
	 *
	 * @since x.x.x
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

		// Charge the order.
		$response = $this->get_api()->cash_app_pay_charge( $order );

		// success! update order record
		if ( $response->transaction_approved() ) {

			$payment_response = $response->get_data();
			$payment          = $payment_response->getPayment();

			// credit card order note
			$message = sprintf(
				/* translators: Placeholders: %1$s - payment method title, %2$s - environment ("Test"), %3$s - transaction type (authorization/charge), %4$s - card type (mastercard, visa, ...), %5$s - last four digits of the card */
				esc_html__( '%1$s %2$s %3$s Approved for an amount of %4$s', 'woocommerce-square' ),
				$this->get_method_title(),
				wc_square()->get_settings_handler()->is_sandbox() ? esc_html_x( 'Test', 'noun, software environment', 'woocommerce-square' ) : '',
				'APPROVED' === $response->get_payment()->getStatus() ? esc_html_x( 'Authorization', 'Cash App transaction type', 'woocommerce-square' ) : esc_html_x( 'Charge', 'noun, Cash App transaction type', 'woocommerce-square' ),
				wc_price( Money_Utility::cents_to_float( $payment->getTotalMoney()->getAmount(), $order->get_currency() ) )
			);

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
			 * @since x.x.x
			 *
			 * @param string $message order note
			 * @param \WC_Order $order order object
			 * @param Payment_Gateway_API_Response_Interface $response transaction response
			 * @param Payment_Gateway_Direct $this instance
			 */
			$message = apply_filters( 'wc_payment_gateway_' . $this->get_id() . '_transaction_approved_order_note', $message, $order, $response, $this );

			$order->add_order_note( $message );

		}

		if ( $response->transaction_approved() || $response->transaction_held() ) {

			// add the standard transaction data
			$this->add_transaction_data( $order, $response );

			// allow the concrete class to add any gateway-specific transaction data to the order
			$this->add_payment_gateway_transaction_data( $order, $response );

			// // if the transaction was held (ie fraud validation failure) mark it as such
			// // TODO: consider checking whether the response *was* an authorization, rather than blanket-assuming it was because of the settings.  There are times when an auth will be used rather than charge, ie when performing in-plugin AVS handling (moneris)
			// if ( $response->transaction_held() || ( $this->supports( self::FEATURE_CREDIT_CARD_AUTHORIZATION ) && $this->perform_credit_card_authorization( $order ) ) ) {
			// // TODO: need to make this more flexible, and not force the message to 'Authorization only transaction' for auth transactions (re moneris efraud handling)
			// /* translators: This is a message describing that the transaction in question only performed a credit card authorization and did not capture any funds. */
			// $this->mark_order_as_held( $order, $this->supports( self::FEATURE_CREDIT_CARD_AUTHORIZATION ) && $this->perform_credit_card_authorization( $order ) ? esc_html__( 'Authorization only transaction', 'woocommerce-square' ) : $response->get_status_message(), $response );
			// }

			return true;

		} else {
			return $this->do_transaction_failed_result( $order, $response );
		}
	}

	/**
	 * Adds transaction data to the order.
	 *
	 * @since x.x.x
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
	 * Get the Cash App Pay button styles.
	 *
	 * @return array Button styles.
	 */
	public function get_button_styles() {
		return array(
			'theme' => $this->settings['button_theme'] ?? 'dark',
			'shape' => $this->settings['button_shape'] ?? 'semiround',
			'size'  => 'medium',
			'width' => 'full',
		);
	}
}
