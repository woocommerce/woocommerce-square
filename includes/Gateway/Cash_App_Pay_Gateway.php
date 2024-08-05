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
use WooCommerce\Square\Utilities\Money_Utility;
use WooCommerce\Square\Framework\PaymentGateway\Payment_Gateway;
use WooCommerce\Square\Framework\Square_Helper;
use WooCommerce\Square\Gateway;
use WooCommerce\Square\Gateway\API\Responses\Create_Payment;
use WooCommerce\Square\Handlers\Order;
use WooCommerce\Square\WC_Order_Square;

/**
 * The Cash App Pay payment gateway class.
 *
 * @since 4.5.0
 */
class Cash_App_Pay_Gateway extends Payment_Gateway {

	/** @var API API base instance */
	private $api;

	/** @var string configuration option: button theme for the Cash App Pay button. */
	public $button_theme;

	/** @var string configuration option: button shape for the Cash App Pay button. */
	public $button_shape;

	/**
	 * Constructs the class.
	 *
	 * @since 4.5.0
	 */
	public function __construct() {
		parent::__construct(
			Plugin::CASH_APP_PAY_GATEWAY_ID,
			wc_square(),
			array(
				'method_title'       => __( 'Cash App Pay (Square)', 'woocommerce-square' ),
				'method_description' => __( 'Allow customers to securely pay with Cash App', 'woocommerce-square' ),
				'payment_type'       => self::PAYMENT_TYPE_CASH_APP_PAY,
				'supports'           => array(
					self::FEATURE_PRODUCTS,
					self::FEATURE_REFUNDS,
					self::FEATURE_AUTHORIZATION,
					self::FEATURE_CHARGE,
					self::FEATURE_CHARGE_VIRTUAL,
					self::FEATURE_CAPTURE,
				),
				'countries'          => array( 'US' ),
				'currencies'         => array( 'USD' ),
			)
		);

		// Payment method image.
		$this->icon = $this->get_payment_method_image_url();

		// Transaction URL format.
		$this->view_transaction_url = $this->get_transaction_url_format();

		// Ajax hooks
		add_action( 'wc_ajax_square_cash_app_pay_get_payment_request', array( $this, 'ajax_get_payment_request' ) );
		add_action( 'wc_ajax_square_cash_app_pay_set_continuation_session', array( $this, 'ajax_set_continuation_session' ) );
		add_action( 'wc_ajax_square_cash_app_log_js_data', array( $this, 'log_js_data' ) );

		// restore refunded Square inventory
		add_action( 'woocommerce_order_refunded', array( $this, 'restore_refunded_inventory' ), 10, 2 );

		// Admin hooks.
		add_action( 'admin_notices', array( $this, 'add_admin_notices' ) );
	}

	/**
	 * Enqueue the necessary scripts & styles for the gateway.
	 *
	 * @since 4.5.0
	 */
	public function enqueue_scripts() {
		if ( ! $this->is_configured() ) {
			return;
		}

		// Enqueue payment gateway assets.
		$this->enqueue_gateway_assets();
	}

	/**
	 * Payment form on checkout page.
	 *
	 * @since 4.5.0
	 */
	public function payment_fields() {
		parent::payment_fields();
		?>
		<br />
		<div id="wc-square-cash-app-pay-hidden-fields">
			<input name="<?php echo 'wc-' . esc_attr( $this->get_id_dasherized() ) . '-payment-nonce'; ?>" id="<?php echo 'wc-' . esc_attr( $this->get_id_dasherized() ) . '-payment-nonce'; ?>" type="hidden" />
		</div>
		<form id="wc-square-cash-app-payment-form">
			<div id="wc-square-cash-app"></div>
		</form>
		<?php
	}

	/**
	 * Return the gateway-specifics JS script handle.
	 *
	 * @since 4.5.0
	 * @return string
	 */
	protected function get_gateway_js_handle() {
		return 'wc-' . $this->get_id_dasherized();
	}

	/**
	 * Enqueue the gateway-specific assets if present, including JS, CSS, and
	 * localized script params
	 *
	 * @since 4.5.0
	 */
	protected function enqueue_gateway_assets() {
		$is_checkout = is_checkout() || ( function_exists( 'has_block' ) && has_block( 'woocommerce/checkout' ) );

		// bail if not a checkout page or cash app pay is not enabled
		if ( ! $is_checkout || ! $this->is_configured() ) {
			return;
		}

		if ( $this->get_plugin()->get_settings_handler()->is_sandbox() ) {
			$url = 'https://sandbox.web.squarecdn.com/v1/square.js';
		} else {
			$url = 'https://web.squarecdn.com/v1/square.js';
		}

		wp_enqueue_script( 'wc-' . $this->get_plugin()->get_id_dasherized() . '-payment-form', $url, array(), Plugin::VERSION, true );

		parent::enqueue_gateway_assets();

		// Render Payment JS
		$this->render_js();
	}

	/**
	 * Validates the entered payment fields.
	 *
	 * @since 4.5.0
	 *
	 * @return bool
	 */
	public function validate_fields() {
		$is_valid = true;

		try {
			if ( ! Square_Helper::get_post( 'wc-' . $this->get_id_dasherized() . '-payment-nonce' ) ) {
				throw new \Exception( 'Payment nonce is missing.' );
			}
		} catch ( \Exception $exception ) {

			$is_valid = false;

			Square_Helper::wc_add_notice( __( 'An error occurred, please try again or try an alternate form of payment.', 'woocommerce-square' ), 'error' );

			$this->add_debug_message( $exception->getMessage(), 'error' );
		}

		return $is_valid;
	}

	/** Admin methods *************************************************************************************************/
	/**
	 * Adds admin notices.
	 *
	 * @since 4.5.0
	 */
	public function add_admin_notices() {
		$base_location      = wc_get_base_location();
		$is_plugin_settings = $this->get_plugin()->is_payment_gateway_configuration_page( $this->get_id() );
		$is_connected       = $this->get_plugin()->get_settings_handler()->is_connected() && $this->get_plugin()->get_settings_handler()->get_location_id();
		$is_enabled         = $this->is_enabled() && $is_connected;

		// Add a notice for cash app pay if the base location is not the US.
		if ( ( $is_enabled || $is_plugin_settings ) && isset( $base_location['country'] ) && 'US' !== $base_location['country'] ) {

			$this->get_plugin()->get_admin_notice_handler()->add_admin_notice(
				sprintf(
					/* translators: Placeholders: %1$s - <strong> tag, %2$s - </strong> tag, %3$s - 2-character country code, %4$s - comma separated list of 2-character country codes */
					__( '%1$sCash App Pay (Square):%2$s Your base country is %3$s, but Cash App Pay can’t accept transactions from merchants outside of US.', 'woocommerce-square' ),
					'<strong>',
					'</strong>',
					esc_html( $base_location['country'] )
				),
				'wc-square-cash-app-pay-base-location',
				array(
					'notice_class' => 'notice-error',
				)
			);
		}

		// Add a notice to enable cash app pay and start accept payments using cash app pay.
		if ( $is_connected && ! $this->is_enabled() && ! $is_plugin_settings && isset( $base_location['country'] ) && 'US' === $base_location['country'] ) {
			$this->get_plugin()->get_admin_notice_handler()->add_admin_notice(
				sprintf(
					/* translators: Placeholders: %1$s - <a> tag, %2$s - </a> tag */
					__( 'You are ready to accept payments using Cash App Pay (Square)! %1$sEnable it%2$s now to start accepting payments.', 'woocommerce-square' ),
					'<a href="' . esc_url( $this->get_plugin()->get_payment_gateway_configuration_url( $this->get_id() ) ) . '">',
					'</a>'
				),
				'wc-square-enable-cash-app-pay',
				array(
					'always_show_on_settings' => false,
				)
			);
		}
	}

	/**
	 * Get the default payment method title, which is configurable within the
	 * admin and displayed on checkout
	 *
	 * @since 4.5.0
	 * @return string payment method title to show on checkout
	 */
	protected function get_default_title() {
		return esc_html__( 'Cash App Pay', 'woocommerce-square' );
	}


	/**
	 * Get the default payment method description, which is configurable
	 * within the admin and displayed on checkout
	 *
	 * @since 4.5.0
	 * @return string payment method description to show on checkout
	 */
	protected function get_default_description() {
		return esc_html__( 'Pay securely using Cash App Pay.', 'woocommerce-square' );
	}

	/**
	 * Get transaction URL format.
	 *
	 * @since 4.5.0
	 *
	 * @return string URL format
	 */
	public function get_transaction_url_format() {
		return $this->get_plugin()->get_settings_handler()->is_sandbox() ? 'https://squareupsandbox.com/dashboard/sales/transactions/%s' : 'https://squareup.com/dashboard/sales/transactions/%s';
	}

	/**
	 * Initialize payment gateway settings fields
	 *
	 * @since 4.5.0
	 * @see WC_Settings_API::init_form_fields()
	 */
	public function init_form_fields() {
		$this->form_fields = array();
	}

	/** Conditional methods *******************************************************************************************/

	/**
	 * Determines if the gateway is available.
	 *
	 * @since 4.5.0
	 *
	 * @return bool
	 */
	public function is_available() {

		return parent::is_available() && $this->get_plugin()->get_settings_handler()->is_connected() && $this->get_plugin()->get_settings_handler()->get_location_id();
	}

	/**
	 * Returns true if the gateway is properly configured to perform transactions
	 *
	 * @since 4.5.0
	 * @return boolean true if the gateway is properly configured
	 */
	public function is_configured() {
		// Only available in the US and USD currency.
		$base_location = wc_get_base_location();
		$us_only       = isset( $base_location['country'] ) && 'US' === $base_location['country'];

		return $this->is_enabled() && $us_only && $this->get_plugin()->get_settings_handler()->is_connected() && $this->get_plugin()->get_settings_handler()->get_location_id();
	}

	/** Getter methods ************************************************************************************************/

	/**
	 * Gets the API instance.
	 *
	 * @since 4.5.0
	 *
	 * @return Gateway\API
	 */
	public function get_api() {

		if ( ! $this->api ) {
			$settings  = $this->get_plugin()->get_settings_handler();
			$this->api = new Gateway\API( $settings->get_access_token(), $settings->get_location_id(), $settings->is_sandbox() );
			$this->api->set_api_id( $this->get_id() );
		}

		return $this->api;
	}


	/**
	 * Gets the gateway settings fields.
	 *
	 * @since 4.5.0
	 *
	 * @return array
	 */
	protected function get_method_form_fields() {
		return array();
	}

	/**
	 * Initialize payment tokens handler.
	 *
	 * @since 4.5.1
	 */
	protected function init_payment_tokens_handler() {
		// No payment tokens for Cash App Pay, do nothing.
	}


	/**
	 * Gets a user's stored customer ID.
	 *
	 * Overridden to avoid auto-creating customer IDs, as Square generates them.
	 *
	 * @since 4.5.0
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
	 * @since 4.5.0
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
	 * @since 4.5.0
	 *
	 * @param int|\WC_Order $order_id order ID or object
	 * @return \WC_Order
	 */
	public function get_order( $order_id ) {
		$order = parent::get_order( $order_id );

		$order->payment->nonce = new \stdClass();

		if ( $this->is_gift_card_applied() ) {
			$order->payment->nonce->gift_card = Square_Helper::get_post( 'square-gift-card-payment-nonce' );
		}

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
	 * Gets an order with capture data attached.
	 *
	 * @since 4.6.0
	 *
	 * @param int|\WC_Order $order order object
	 * @param null|float    $amount amount to capture
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
	 * @since 4.5.0
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
		$transaction_date      = $this->get_order_meta( $order, 'trans_date' );

		if ( $transaction_date ) {
			// refunds with the Refunds API can be made up to 1 year after payment and up to 120 days with the Transactions API
			$max_refund_time = version_compare( $order->square_version, '2.2', '>=' ) ? '+1 year' : '+120 days';

			// throw an error if the payment cannot be refunded
			if ( time() >= strtotime( $max_refund_time, strtotime( $transaction_date ) ) ) {
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
	 * Gets the configured environment ID.
	 *
	 * @since 4.5.0
	 *
	 * @return string
	 */
	public function get_environment() {
		return self::ENVIRONMENT_PRODUCTION;
	}

	/**
	 * Gets the configured application ID.
	 *
	 * @since 4.5.0
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
		 * @since 4.5.0
		 *
		 * @param string $application_id application ID
		 */
		return apply_filters( 'wc_square_application_id', $square_application_id );
	}

	/**
	 * Returns the $order object with a unique transaction ref member added
	 *
	 * @since 4.5.0
	 * @param WC_Order_Square $order the order object
	 * @return WC_Order_Square order object with member named unique_transaction_ref
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
	 * Returns the payment method image URL.
	 *
	 * @since 4.5.0
	 * @param string $type the payment method type or name
	 * @return string the image URL or null
	 */
	public function get_payment_method_image_url( $type = '' ) {
		/**
		 * Payment Gateway Fallback to PNG Filter.
		 *
		 * Allow actors to enable the use of PNGs over SVGs for payment icon images.
		 *
		 * @since 4.5.0
		 * @param bool $use_svg true by default, false to use PNGs
		 */
		$image_extension = apply_filters( 'wc_payment_gateway_' . $this->get_id() . '_use_svg', true ) ? '.svg' : '.png';

		// first, is the image available within the plugin?
		if ( is_readable( $this->get_plugin()->get_plugin_path() . '/build/images/cash-app' . $image_extension ) ) {
			return \WC_HTTPS::force_https_url( $this->get_plugin()->get_plugin_url() . '/build/images/cash-app' . $image_extension );
		}

		// Fall back to framework image URL.
		return parent::get_payment_method_image_url( $type );
	}

	/**
	 * Get the Cash App Pay button styles.
	 *
	 * @return array Button styles.
	 */
	public function get_button_styles() {
		$button_styles = array(
			'theme' => $this->settings['button_theme'] ?? 'dark',
			'shape' => $this->settings['button_shape'] ?? 'semiround',
			'size'  => 'medium',
			'width' => 'full',
		);

		/**
		 * Filters the Cash App Pay button styles.
		 *
		 * @since 4.5.0
		 * @param array $button_styles Button styles.
		 * @return array Button styles.
		 */
		return apply_filters( 'wc_' . $this->get_id() . '_button_styles', $button_styles );
	}


	/**
	 * Mark an order as refunded. This should only be used when the full order
	 * amount has been refunded.
	 *
	 * @since 4.5.0
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
	 * Build the payment request object for the cash app pay payment form.
	 *
	 * Payment request objects are used by the Payments and need to be in a specific format.
	 * Reference: https://developer.squareup.com/docs/api/paymentform#paymentform-paymentrequestobjects
	 *
	 * @since 4.5.0
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
			$amount = WC()->cart->total;

			// Check if a gift card is applied.
			$check_for_giftcard = isset( $_POST['check_for_giftcard'] ) ? 'true' === sanitize_text_field( wp_unslash( $_POST['check_for_giftcard'] ) ) : false; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$gift_card_applied  = false;
			if ( $check_for_giftcard ) {
				$partial_amount = $this->get_partial_cash_app_amount();
				if ( $partial_amount < $amount ) {
					$amount            = $partial_amount;
					$gift_card_applied = true;
				}
			}

			$payment_request = $this->build_payment_request( $amount, array(), $gift_card_applied );
		}

		return $payment_request;
	}

	/**
	 * Get the partial amount to be paid by Cash App Pay.
	 * This is the amount after deducting the gift card balance.
	 *
	 * @since 4.6.0
	 * @return float Partial amount to be paid by Cash App Pay.
	 */
	public function get_partial_cash_app_amount() {
		$amount        = WC()->cart->total;
		$payment_token = WC()->session->woocommerce_square_gift_card_payment_token;
		if ( ! Gift_Card::does_checkout_support_gift_card() || ! $payment_token ) {
			return $amount;
		}

		$is_sandbox = wc_square()->get_settings_handler()->is_sandbox();
		if ( $is_sandbox ) {
			// The card allowed for testing with the Sandbox account has fund of $1.
			$balance = 1;
			$amount  = $amount - $balance;
		} else {
			$api_response   = $this->get_api()->retrieve_gift_card( $payment_token );
			$gift_card_data = $api_response->get_data();
			if ( $gift_card_data instanceof \Square\Models\RetrieveGiftCardFromNonceResponse ) {
				$gift_card     = $gift_card_data->getGiftCard();
				$balance_money = $gift_card->getBalanceMoney();
				$balance       = (float) Square_Helper::number_format( Money_Utility::cents_to_float( $balance_money->getAmount() ) );

				$amount = $amount - $balance;
			}
		}

		return $amount;
	}

	/**
	 * Build a payment request object to be sent to Payments.
	 *
	 * Documentation: https://developer.squareup.com/docs/api/paymentform#paymentform-paymentrequestobjects
	 *
	 * @since 4.5.0
	 * @param string $amount - format '100.00'
	 * @param array $data
	 * @return array
	 */
	public function build_payment_request( $amount, $data = array(), $gift_card_applied = false ) {
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

			// Set currency of order if order-pay page.
			if ( $order && $order->get_currency() ) {
				$data['currencyCode'] = $order->get_currency();
			}

			unset( $data['is_pay_for_order_page'], $data['order_id'] );
		}

		if ( ! isset( $data['lineItems'] ) && ! $gift_card_applied ) {
			$data['lineItems'] = $this->build_payment_request_line_items( $order_data );
		}

		/**
		 * Filters the payment request Total Label Suffix.
		 *
		 * @since 4.5.0
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
	 * @since 4.5.0
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
	 * @since 4.5.0
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
	 * Set continuation session to select the cash app payment method after the redirect back from the cash app
	 *
	 * @since 4.5.0
	 * @return void
	 */
	public function ajax_set_continuation_session() {
		check_ajax_referer( 'wc-cash-app-set-continuation-session', 'security' );
		$clear_session = ( isset( $_POST['clear'] ) && 'true' === sanitize_text_field( wp_unslash( $_POST['clear'] ) ) );

		try {
			if ( $clear_session ) {
				WC()->session->set( 'wc_square_cash_app_pay_continuation', null );
			} else {
				WC()->session->set( 'wc_square_cash_app_pay_continuation', 'yes' );
			}
		} catch ( \Exception $e ) {
			wp_send_json_error( $e->getMessage() );
		}

		wp_send_json_success( wp_json_encode( array( 'success' => true ) ) );
	}

	/**
	 * Determines if the current request is a continuation of a cash app pay payment.
	 *
	 * @return boolean
	 */
	public function is_cash_app_pay_continuation() {
		return WC()->session && 'yes' === WC()->session->get( 'wc_square_cash_app_pay_continuation' );
	}

	/**
	 * Returns cart totals in an array format
	 *
	 * @since 4.5.0
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
	 * Handles payment processing.
	 *
	 * @see WC_Payment_Gateway::process_payment()
	 *
	 * @since 4.5.0
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
		 * @since 4.5.0
		 * @param bool $result default true
		 * @param int|string $order_id order ID for the payment
		 * @param Cash_App_Pay_Gateway $this instance
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
				 * @since 4.5.0
				 *
				 * @param string $status held order status
				 * @param \WC_Order $order order object
				 * @param \WooCommerce\Square\Gateway\API\Response|null $response API response object, if any
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
				 * @since 4.5.0
				 * @param \WC_Order $order order object
				 * @param Payment_Gateway $this instance
				 */
				do_action( 'wc_payment_gateway_' . $this->get_id() . '_payment_processed', $order, $this );

				// To create/activate/load a gift card, a payment must be in COMPLETE state.
				if ( $this->perform_charge( $order ) ) {
					$gift_card_purchase_type = Order::get_gift_card_purchase_type( $order );
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
	 * Do the transaction.
	 *
	 * @since 4.5.0
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
		// Generate a new transaction ref if the order payment is split using multiple payment methods.
		if ( isset( $order->payment->partial_total ) ) {
			$order->unique_transaction_ref = $this->get_order_with_unique_transaction_ref( $order );
		}

		// Charge/Authorize the order.
		if ( $this->perform_charge( $order ) && self::CHARGE_TYPE_PARTIAL !== $this->get_charge_type() ) {
			$response = $this->get_api()->cash_app_pay_charge( $order );
		} else {
			$response = $this->get_api()->cash_app_pay_authorization( $order );
		}

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
			 * @since 4.5.0
			 *
			 * @param string $message order note
			 * @param \WC_Order $order order object
			 * @param \WooCommerce\Square\Gateway\API\Response $response transaction response
			 * @param Cash_App_Pay_Gateway $this instance
			 */
			$message = apply_filters( 'wc_payment_gateway_' . $this->get_id() . '_transaction_approved_order_note', $message, $order, $response, $this );

			$this->update_order_meta( $order, 'is_tender_type_cash_app_wallet', true );

			$order->add_order_note( $message );
		}

		return $response;
	}

	/**
	 * Adds transaction data to the order.
	 *
	 * @since 4.5.0
	 *
	 * @param \WC_Order $order order object
	 * @param \WooCommerce\Square\Gateway\API\Responses\Create_Payment $response API response object
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
	 * Renders the payment form JS.
	 *
	 * @since 4.5.0
	 */
	public function render_js() {

		try {
			$payment_request = $this->get_payment_request();
		} catch ( \Exception $e ) {
			$this->get_plugin()->log( 'Error: ' . $e->getMessage() );
		}

		$args = array(
			'application_id'        => $this->get_application_id(),
			'ajax_log_nonce'        => wp_create_nonce( 'wc_' . $this->get_id() . '_log_js_data' ),
			'location_id'           => wc_square()->get_settings_handler()->get_location_id(),
			'gateway_id'            => $this->get_id(),
			'gateway_id_dasherized' => $this->get_id_dasherized(),
			'payment_request'       => $payment_request,
			'general_error'         => __( 'An error occurred, please try again or try an alternate form of payment.', 'woocommerce-square' ),
			'ajax_url'              => \WC_AJAX::get_endpoint( '%%endpoint%%' ),
			'payment_request_nonce' => wp_create_nonce( 'wc-cash-app-get-payment-request' ),
			'checkout_logging'      => $this->debug_checkout(),
			'logging_enabled'       => $this->debug_log(),
			'is_pay_for_order_page' => is_checkout() && is_wc_endpoint_url( 'order-pay' ),
			'order_id'              => absint( get_query_var( 'order-pay' ) ),
			'button_styles'         => $this->get_button_styles(),
			'reference_id'          => WC()->cart ? WC()->cart->get_cart_hash() : '',
		);

		/**
		 * Payment Gateway Payment JS Arguments Filter.
		 *
		 * Filter the arguments passed to the Payment handler JS class
		 *
		 * @since 4.5.0
		 *
		 * @param array           $args arguments passed to the Payment Gateway handler JS class
		 * @param Payment_Gateway $this payment gateway instance
		 */
		$args = apply_filters( 'wc_' . $this->get_id() . '_payment_js_args', $args, $this );

		wc_enqueue_js( sprintf( 'window.wc_%s_payment_handler = new WC_Square_Cash_App_Pay_Handler( %s );', esc_js( $this->get_id() ), wp_json_encode( $args ) ) );
	}

	/**
	 * Logs any data sent by the payment form JS via AJAX.
	 *
	 * @since 4.5.0
	 */
	public function log_js_data() {
		check_ajax_referer( 'wc_' . $this->get_id() . '_log_js_data', 'security' );

		$message = sprintf( "wc-square-cash-app-pay.js %1\$s:\n ", ! empty( $_REQUEST['type'] ) ? ucfirst( wc_clean( wp_unslash( $_REQUEST['type'] ) ) ) : 'Request' );

		// add the data
		if ( ! empty( $_REQUEST['data'] ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			$message .= print_r( wc_clean( wp_unslash( $_REQUEST['data'] ) ), true );
		}

		$this->get_plugin()->log( $message, $this->get_id() );
	}
}
