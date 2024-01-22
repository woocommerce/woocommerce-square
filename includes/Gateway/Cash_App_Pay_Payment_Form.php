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
 */

namespace WooCommerce\Square\Gateway;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Square\Framework\PaymentGateway\Payment_Gateway_Payment_Form;

/**
 * The payment form handler.
 *
 * @since x.x.x
 *
 * @method \WooCommerce\Square\Gateway get_gateway()
 */
class Cash_App_Pay_Payment_Form extends Payment_Gateway_Payment_Form {

	/**
	 * Adds hooks for rendering the payment form.
	 * Extended from SV framework and remove render_js action
	 *
	 * @see Payment_Gateway_Payment_Form::render()
	 *
	 * @since x.x.x
	 */
	protected function add_hooks() {

		$gateway_id = $this->get_gateway()->get_id();

		// payment form description
		add_action( "wc_{$gateway_id}_payment_form_start", array( $this, 'render_payment_form_description' ), 15 );

		// fieldset start
		add_action( "wc_{$gateway_id}_payment_form_start", array( $this, 'render_fieldset_start' ), 30 );

		// payment fields
		add_action( "wc_{$gateway_id}_payment_form", array( $this, 'render_payment_fields' ), 0 );

		// fieldset end
		add_action( "wc_{$gateway_id}_payment_form_end", array( $this, 'render_fieldset_end' ), 5 );

		// payment form JS
		add_action( "wc_{$gateway_id}_payment_form_end", array( $this, 'render_js' ), 5 );
	}

	/**
	 * Renders the payment fields.
	 *
	 * @since x.x.x
	 */
	public function render_payment_fields() {
		?>
		<form id="wc-cash-app-payment-form">
			<div id="wc-square-cash-app"></div>
		</form>
		<div id="square-cash-app-pay-hidden-fields">
			<input name="<?php echo 'wc-' . esc_attr( $this->get_gateway()->get_id_dasherized() ) . '-payment-nonce'; ?>" id="<?php echo 'wc-' . esc_attr( $this->get_gateway()->get_id_dasherized() ) . '-payment-nonce'; ?>" type="hidden" />
			</div>
		<div id="wc-cash-app-payment-status-container"></div>
		<?php
	}

	/**
	 * Renders the payment form JS.
	 *
	 * @since x.x.x
	 */
	public function render_js() {

		try {
			$payment_request = $this->get_gateway()->get_payment_request();
		} catch ( \Exception $e ) {
			$this->get_gateway()->get_plugin()->log( 'Error: ' . $e->getMessage() );
		}

		$args = array(
			'application_id'        => $this->get_gateway()->get_application_id(),
			'location_id'           => wc_square()->get_settings_handler()->get_location_id(),
			'gateway_id'            => $this->get_gateway()->get_id(),
			'gateway_id_dasherized' => $this->get_gateway()->get_id_dasherized(),
			'payment_request'       => $payment_request,
			'general_error'         => __( 'An error occurred, please try again or try an alternate form of payment.', 'woocommerce-square' ),
			'ajax_url'              => \WC_AJAX::get_endpoint( '%%endpoint%%' ),
			'payment_request_nonce' => wp_create_nonce( 'wc-cash-app-get-payment-request' ),
			'logging_enabled'       => $this->get_gateway()->debug_log(),
			'is_pay_for_order_page' => is_checkout() && is_wc_endpoint_url( 'order-pay' ),
			'order_id'              => absint( get_query_var( 'order-pay' ) ),
			'button_styles'         => $this->get_gateway()->get_button_styles(),
			'reference_id'          => WC()->cart ? WC()->cart->get_cart_hash() : '',
		);

		/**
		 * Payment Gateway Payment Form JS Arguments Filter.
		 *
		 * Filter the arguments passed to the Payment Form handler JS class
		 *
		 * @since x.x.x
		 *
		 * @param array        $args arguments passed to the Payment Form handler JS class
		 * @param Payment_Form $this payment form instance
		 */
		$args = apply_filters( 'wc_' . $this->get_gateway()->get_id() . '_payment_form_js_args', $args, $this );

		wc_enqueue_js( sprintf( 'window.wc_%s_payment_form_handler = new WC_Square_Cash_App_Pay_Handler( %s );', esc_js( $this->get_gateway()->get_id() ), json_encode( $args ) ) );
	}

}
