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

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use WooCommerce\Square\Plugin;

class Cash_App_Pay_Blocks_Handler extends AbstractPaymentMethodType {

	/**
	 * @var string $name
	 */
	protected $name = Plugin::CASH_APP_PAY_GATEWAY_ID;

	/**
	 * @var Plugin $plugin
	 */
	protected $plugin = null;

	/**
	 * @var Cash_App_Pay_Gateway $gateway
	 */
	protected $gateway = null;

	/**
	 * Init Square Cash App Pay Cart and Checkout Blocks handler class
	 *
	 * @since 4.5.0
	 */
	public function __construct() {
		$this->plugin = wc_square();
	}

	/**
	 * Initializes the payment method type.
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_square_cash_app_pay_settings', array() );
	}

	/**
	 * Returns if this payment method should be active. If false, the scripts will not be enqueued.
	 *
	 * @return boolean
	 */
	public function is_active() {
		return ! empty( $this->get_gateway() ) ? $this->get_gateway()->is_configured() : false;
	}

	/**
	 * Register scripts
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		$asset_path   = $this->plugin->get_plugin_path() . '/build/cash-app-pay.asset.php';
		$version      = Plugin::VERSION;
		$dependencies = array();

		if ( file_exists( $asset_path ) ) {
			$asset        = require $asset_path;
			$version      = is_array( $asset ) && isset( $asset['version'] ) ? $asset['version'] : $version;
			$dependencies = is_array( $asset ) && isset( $asset['dependencies'] ) ? $asset['dependencies'] : $dependencies;
		}

		wp_register_script(
			'wc-square-cash-app-pay-blocks-integration',
			$this->plugin->get_plugin_url() . '/build/cash-app-pay.js',
			$dependencies,
			$version,
			true
		);

		wp_set_script_translations( 'wc-square-cash-app-pay-blocks-integration', 'woocommerce-square' );

		return array( 'wc-square-cash-app-pay-blocks-integration' );
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
	 * @since 4.5.0
	 * @return array
	 */
	public function get_payment_method_data() {
		if ( ! $this->get_gateway() ) {
			return array();
		}
		return array(
			'title'                      => $this->get_setting( 'title' ),
			'description'                => $this->get_setting( 'description' ),
			'application_id'             => $this->get_gateway()->get_application_id(),
			'location_id'                => $this->plugin->get_settings_handler()->get_location_id(),
			'is_sandbox'                 => $this->plugin->get_settings_handler()->is_sandbox(),
			'logging_enabled'            => $this->get_gateway()->debug_checkout(),
			'general_error'              => __( 'An error occurred, please try again or try an alternate form of payment.', 'woocommerce-square' ),
			'supports'                   => $this->get_supported_features(),
			'show_saved_cards'           => false,
			'show_save_option'           => false,
			'is_pay_for_order_page'      => is_wc_endpoint_url( 'order-pay' ),
			'ajax_url'                   => \WC_AJAX::get_endpoint( '%%endpoint%%' ),
			'payment_request_nonce'      => wp_create_nonce( 'wc-cash-app-get-payment-request' ),
			'continuation_session_nonce' => wp_create_nonce( 'wc-cash-app-set-continuation-session' ),
			'checkout_logging'           => $this->get_gateway()->debug_checkout(),
			'order_id'                   => absint( get_query_var( 'order-pay' ) ),
			'gateway_id_dasherized'      => $this->get_gateway()->get_id_dasherized(),
			'button_styles'              => $this->get_gateway()->get_button_styles(),
			'is_continuation'            => $this->get_gateway()->is_cash_app_pay_continuation(),
			'reference_id'               => WC()->cart ? WC()->cart->get_cart_hash() : '',
		);
	}

	/**
	 * Get a list of features supported by Square
	 *
	 * @since 4.5.0
	 * @return array
	 */
	public function get_supported_features() {
		$gateway = $this->get_gateway();
		return ! empty( $gateway ) ? array_filter( $gateway->supports, array( $gateway, 'supports' ) ) : array();
	}

	/**
	 * Helper function to get and store an instance of the Square gateway
	 *
	 * @since 4.5.0
	 * @return Cash_App_Pay_Gateway|null
	 */
	private function get_gateway() {
		if ( empty( $this->gateway ) ) {
			$this->gateway = $this->plugin->get_gateway( Plugin::CASH_APP_PAY_GATEWAY_ID );
		}

		return $this->gateway;
	}
}
