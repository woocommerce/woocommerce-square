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

use WooCommerce\Square\Framework\PaymentGateway\Payment_Gateway_Helper;
use WooCommerce\Square\Framework\PaymentGateway\Payment_Gateway_Payment_Form;

/**
 * The payment form handler.
 *
 * @since 2.0.0
 *
 * @method \WooCommerce\Square\Gateway get_gateway()
 */
class Payment_Form extends Payment_Gateway_Payment_Form {

	/**
	 * Adds hooks for rendering the payment form.
	 * Extended from SV framework and remove render_js action
	 *
	 * @see Payment_Gateway_Payment_Form::render()
	 *
	 * @since 2.2.3
	 */
	protected function add_hooks() {

		$gateway_id = $this->get_gateway()->get_id();

		// payment form description
		add_action( "wc_{$gateway_id}_payment_form_start", array( $this, 'render_payment_form_description' ), 15 );

		// saved payment methods
		add_action( "wc_{$gateway_id}_payment_form_start", array( $this, 'render_saved_payment_methods' ), 20 );

		// fieldset start
		add_action( "wc_{$gateway_id}_payment_form_start", array( $this, 'render_fieldset_start' ), 30 );

		// payment fields
		add_action( "wc_{$gateway_id}_payment_form", array( $this, 'render_payment_fields' ), 0 );

		// fieldset end
		add_action( "wc_{$gateway_id}_payment_form_end", array( $this, 'render_fieldset_end' ), 5 );
	}

	/**
	 * Renders any additional billing information we need for processing on pages other than checkout
	 * e.g. pay page, add payment method page
	 *
	 * @since 2.1.0
	 */
	public function render_supplementary_billing_info() {

		$billing_data        = array();
		$billing_data_source = null;

		if ( is_checkout_pay_page() ) {

			if ( $order = wc_get_order( $this->get_gateway()->get_checkout_pay_page_order_id() ) ) {
				$billing_data_source = $order;
			}
		} elseif ( WC()->customer && ! is_checkout() ) {

			$billing_data_source = WC()->customer;
		}

		if ( $billing_data_source ) {

			$billing_data = array( 'billing_postcode' => $billing_data_source->get_billing_postcode() );

			// 3d secure requires the full billing info
			$billing_data = array_merge(
				$billing_data,
				array(
					'billing_first_name' => $billing_data_source->get_billing_first_name(),
					'billing_last_name'  => $billing_data_source->get_billing_last_name(),
					'billing_email'      => $billing_data_source->get_billing_email(),
					'billing_country'    => $billing_data_source->get_billing_country(),
					'billing_address_1'  => $billing_data_source->get_billing_address_1(),
					'billing_address_2'  => $billing_data_source->get_billing_address_2(),
					'billing_state'      => $billing_data_source->get_billing_state(),
					'billing_city'       => $billing_data_source->get_billing_city(),
					'billing_phone'      => $billing_data_source->get_billing_phone(),
				)
			);
		}

		foreach ( $billing_data as $key => $value ) {
			echo '<input type="hidden" id="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" />';
		}

		echo '<style> #sq-nudata-modal { z-index: 999999 !important; } </style>';
	}


	/**
	 * Renders the payment fields.
	 *
	 * @since 2.0.0
	 */
	public function render_payment_fields() {

		$fields = array(
			'card-type',
			'last-four',
			'exp-month',
			'exp-year',
			'payment-nonce',
			'payment-postcode',
		);

		$fields[] = 'buyer-verification-token';

		foreach ( $fields as $field_id ) {
			echo '<input type="hidden" name="wc-' . esc_attr( $this->get_gateway()->get_id_dasherized() ) . '-' . esc_attr( $field_id ) . '" />';
		}

		echo '<div id="wc-' . esc_attr( $this->get_gateway()->get_id_dasherized() ) . '-container"></div>';

		$this->render_supplementary_billing_info();
	}


	/**
	 * Gets the credit card fields.
	 *
	 * Overridden to add special iframe classes.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	protected function get_credit_card_fields() {

		$fields = parent::get_credit_card_fields();

		// Square JS requires a postal code field for the form, but this is pre-filled and hidden
		$fields['card-postal-code'] = array(
			'id'          => 'wc-' . $this->get_gateway()->get_id_dasherized() . '-postal-code',
			'label'       => __( 'Postal code', 'woocommerce-square' ),
			'class'       => array( 'form-row-wide' ),
			'required'    => true,
			'input_class' => array( 'js-sv-wc-payment-gateway-credit-card-form-input', 'js-sv-wc-payment-gateway-credit-card-form-postal-code' ),
		);

		foreach ( array( 'card-number', 'card-expiry', 'card-csc', 'card-postal-code' ) as $field_key ) {

			if ( isset( $fields[ $field_key ] ) ) {

				// parent div classes - contains both the label and hosted field container div
				$fields[ $field_key ]['class'] = array_merge( $fields[ $field_key ]['class'], array( "wc-{$this->get_gateway()->get_id_dasherized()}-{$field_key}-parent", "wc-{$this->get_gateway()->get_id_dasherized()}-hosted-field-parent" ) );

				// hosted field container classes - contains the iframe element
				$fields[ $field_key ]['input_class'] = array_merge( $fields[ $field_key ]['input_class'], array( "wc-{$this->get_gateway()->get_id_dasherized()}-hosted-field-{$field_key}", "wc-{$this->get_gateway()->get_id_dasherized()}-hosted-field" ) );
			}
		}

		return $fields;
	}


	/**
	 * Renders a payment form field.
	 *
	 * @since 2.0.0
	 *
	 * @param array $field field to render
	 */
	public function render_payment_field( $field ) {

		?>
		<div class="form-row <?php echo implode( ' ', array_map( 'sanitize_html_class', $field['class'] ) ); ?>">
			<label for="<?php echo esc_attr( $field['id'] ) . '-hosted'; ?>">
			<?php
			echo esc_html( $field['label'] );
			if ( $field['required'] ) :
				?>
				<abbr class="required" title="required">&nbsp;*</abbr> <?php endif; ?></label>
			<div id="<?php echo esc_attr( $field['id'] ) . '-hosted'; ?>" class="<?php echo implode( ' ', array_map( 'sanitize_html_class', $field['input_class'] ) ); ?>" data-placeholder="<?php echo isset( $field['placeholder'] ) ? esc_attr( $field['placeholder'] ) : ''; ?>"></div>
		</div>
		<?php
	}


	/**
	 * Renders the payment form JS.
	 *
	 * @since 2.0.0
	 */
	public function render_js() {

		$args = array(
			'application_id'                   => $this->get_gateway()->get_application_id(),
			'ajax_log_nonce'                   => wp_create_nonce( 'wc_' . $this->get_gateway()->get_id() . '_log_js_data' ),
			'ajax_url'                         => admin_url( 'admin-ajax.php' ),
			'csc_required'                     => $this->get_gateway()->csc_enabled(),
			'currency_code'                    => get_woocommerce_currency(),
			'general_error'                    => __( 'An error occurred, please try again or try an alternate form of payment.', 'woocommerce-square' ),
			'id'                               => $this->get_gateway()->get_id(),
			'id_dasherized'                    => $this->get_gateway()->get_id_dasherized(),
			'is_checkout_registration_enabled' => 'yes' === get_option( 'woocommerce_enable_signup_and_login_from_checkout', 'no' ),
			'is_user_logged_in'                => is_user_logged_in(),
			'is_add_payment_method_page'       => is_add_payment_method_page(),
			'location_id'                      => wc_square()->get_settings_handler()->get_location_id(),
			'logging_enabled'                  => $this->get_gateway()->debug_log(),
			'ajax_wc_checkout_validate_nonce'  => wp_create_nonce( 'wc_' . $this->get_gateway()->get_id() . '_checkout_validate' ),
			'is_manual_order_payment'          => is_checkout() && is_wc_endpoint_url( 'order-pay' ),
			'payment_token_nonce'              => wp_create_nonce( 'payment_token_nonce' ),
			'order_id'                         => absint( get_query_var( 'order-pay' ) ),
			'ajax_get_order_amount_nonce'      => wp_create_nonce( 'wc_' . $this->get_gateway()->get_id() . '_get_order_amount' ),
		);

		// map the unique square card type string to our framework standards
		$square_card_types = array(
			Payment_Gateway_Helper::CARD_TYPE_MASTERCARD => 'masterCard',
			Payment_Gateway_Helper::CARD_TYPE_AMEX       => 'americanExpress',
			Payment_Gateway_Helper::CARD_TYPE_DINERSCLUB => 'discoverDiners',
			Payment_Gateway_Helper::CARD_TYPE_JCB        => 'JCB',
		);

		$card_types = is_array( $this->get_gateway()->get_card_types() ) ? $this->get_gateway()->get_card_types() : array();

		$framework_card_types = array_map( array( Payment_Gateway_Helper::class, 'normalize_card_type' ), $card_types );
		$square_card_types    = array_merge( array_combine( $framework_card_types, $framework_card_types ), $square_card_types );

		$args['enabled_card_types'] = $framework_card_types;
		$args['square_card_types']  = array_flip( $square_card_types );

		$input_styles = array(
			array(
				'backgroundColor' => 'transparent',
				'fontSize'        => '1.3em',
			),
		);

		/**
		 * Filters the the Square payment form input styles.
		 *
		 * @since 2.0.0
		 *
		 * @param array $styles array of input styles
		 */
		$args['input_styles'] = (array) apply_filters( 'wc_' . $this->get_gateway()->get_id() . '_payment_form_input_styles', $input_styles, $this );

		// TODO remove the deprecated hook in a future version
		if ( has_filter( 'woocommerce_square_payment_input_styles' ) ) {

			_deprecated_hook( 'woocommerce_square_payment_input_styles', '2.0.0', null, 'Use "wc_' . esc_html( $this->get_gateway()->get_id() ) . '_payment_form_input_styles" as a replacement.' );

			/**
			 * Filters the input styles (legacy filter).
			 *
			 * @since 1.0.0
			 *
			 * @param string $input_styles styles as JSON encoded array
			 */
			$args['input_styles'] = json_decode( (string) apply_filters( 'woocommerce_square_payment_input_styles', wp_json_encode( $args['input_styles'] ) ), true );
		}

		/**
		 * Payment Gateway Payment Form JS Arguments Filter.
		 *
		 * Filter the arguments passed to the Payment Form handler JS class
		 *
		 * @since 3.0.0
		 *
		 * @param array $result {
		 *   @type string $plugin_id plugin ID
		 *   @type string $id gateway ID
		 *   @type string $id_dasherized gateway ID dasherized
		 *   @type string $type gateway payment type (e.g. 'credit-card')
		 *   @type bool $csc_required true if CSC field display is required
		 * }
		 * @param Payment_Form $this payment form instance
		 */
		$args = apply_filters( 'wc_' . $this->get_gateway()->get_id() . '_payment_form_js_args', $args, $this );

		wc_enqueue_js( sprintf( 'window.wc_%s_payment_form_handler = new WC_Square_Payment_Form_Handler( %s );', esc_js( $this->get_gateway()->get_id() ), json_encode( $args ) ) );
	}


}
