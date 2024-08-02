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
use Automattic\WooCommerce\StoreApi\Payments\PaymentContext;
use Automattic\WooCommerce\StoreApi\Payments\PaymentResult;
use Automattic\WooCommerce\Blocks\Package;
use WooCommerce\Square\Plugin;
use WooCommerce\Square\Framework\PaymentGateway\Payment_Gateway_Helper;

class Blocks_Handler extends AbstractPaymentMethodType {

	/**
	 * @var string $name
	 */
	protected $name = 'square_credit_card';

	/**
	 * @var Plugin $plugin
	 */
	protected $plugin = null;

	/**
	 * @var Gateway $gateway
	 */
	protected $gateway = null;

	protected $digital_wallets_handler;

	/**
	 * Init Square Cart and Checkout Blocks handler class
	 *
	 * @since 2.5
	 */
	public function __construct() {
		$this->plugin = wc_square();

		add_action( 'woocommerce_rest_checkout_process_payment_with_context', array( $this, 'log_js_data' ), 10, 2 );

		add_action( 'admin_notices', array( $this, 'display_compatible_version_notice' ) );
	}


	/**
	 * Initializes the payment method type.
	 */
	public function initialize() {
		$this->settings                = get_option( 'woocommerce_square_credit_card_settings', array() );
		$this->digital_wallets_handler = wc_square()->get_gateway()->get_digital_wallet_handler();
	}

	/**
	 * Returns if this payment method should be active. If false, the scripts will not be enqueued.
	 *
	 * @return boolean
	 */
	public function is_active() {
		return ! empty( $this->get_gateway() ) ? $this->get_gateway()->is_available() : false;
	}

	/**
	 * Register scripts
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		$asset_path   = $this->plugin->get_plugin_path() . '/build/index.asset.php';
		$version      = Plugin::VERSION;
		$dependencies = array();

		if ( file_exists( $asset_path ) ) {
			$asset        = require $asset_path;
			$version      = is_array( $asset ) && isset( $asset['version'] ) ? $asset['version'] : $version;
			$dependencies = is_array( $asset ) && isset( $asset['dependencies'] ) ? $asset['dependencies'] : $dependencies;
		}

		wp_enqueue_style( 'wc-square-cart-checkout-block', $this->plugin->get_plugin_url() . '/build/assets/frontend/wc-square-cart-checkout-blocks.css', array(), Plugin::VERSION );
		wp_register_script(
			'wc-square-credit-card-blocks-integration',
			$this->plugin->get_plugin_url() . '/build/index.js',
			$dependencies,
			$version,
			true
		);

		wp_set_script_translations( 'wc-square-credit-card-blocks-integration', 'woocommerce-square' );

		return array( 'wc-square-credit-card-blocks-integration' );
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
	 * @since 2.5
	 * @return array
	 */
	public function get_payment_method_data() {
		return empty( $this->get_gateway() ) ? array() : array_merge(
			array(
				'title'                      => $this->get_setting( 'title' ),
				'application_id'             => $this->get_gateway()->get_application_id(),
				'location_id'                => $this->plugin->get_settings_handler()->get_location_id(),
				'is_sandbox'                 => $this->plugin->get_settings_handler()->is_sandbox(),
				'available_card_types'       => $this->get_available_card_types(),
				'logging_enabled'            => $this->get_gateway()->debug_log(),
				'general_error'              => __( 'An error occurred, please try again or try an alternate form of payment.', 'woocommerce-square' ),
				'supports'                   => $this->get_supported_features(),
				'show_saved_cards'           => $this->get_gateway()->tokenization_enabled(),
				'show_save_option'           => $this->get_gateway()->tokenization_enabled() && ! $this->get_gateway()->get_payment_form_instance()->tokenization_forced(),
				'is_tokenization_forced'     => $this->get_gateway()->get_payment_form_instance()->tokenization_forced(),
				'is_digital_wallets_enabled' => $this->gateway->is_digital_wallet_available() && 'yes' === $this->gateway->get_option( 'enable_digital_wallets', 'yes' ),
				'payment_token_nonce'        => wp_create_nonce( 'payment_token_nonce' ),
				'is_pay_for_order_page'      => is_wc_endpoint_url( 'order-pay' ),
				'recalculate_totals_nonce'   => wp_create_nonce( 'wc-square-recalculate-totals' ),
			),
			$this->digital_wallets_handler->get_localised_data()
		);
	}

	/**
	 * Helper function to get title of Square gateway to be displayed as Label on checkout block.
	 * Defaults to "Credit Card"
	 *
	 * @since 2.5
	 * @return string
	 */
	private function get_title() {
		return ! empty( $this->get_setting( 'title' ) ) ? $this->get_setting( 'title' ) : esc_html__( 'Credit Card', 'woocommerce-square' );
	}

	/**
	 * Get a list of available card types
	 *
	 * @since 2.5
	 * @return array
	 */
	private function get_available_card_types() {
		$card_types        = array();
		$square_card_types = array(
			'visa'       => 'visa',
			'mastercard' => 'masterCard',
			'amex'       => 'americanExpress',
			'dinersclub' => 'discoverDiners',
			'jcb'        => 'JCB',
			'discover'   => 'discover',
		);

		$enabled_card_types = is_array( $this->get_gateway()->get_card_types() ) ? $this->get_gateway()->get_card_types() : array();
		$enabled_card_types = array_map( array( Payment_Gateway_Helper::class, 'normalize_card_type' ), $enabled_card_types );

		foreach ( $enabled_card_types as $card_type ) {
			if ( ! empty( $square_card_types[ $card_type ] ) ) {
				$card_types[ $card_type ] = $square_card_types[ $card_type ];
			}
		}

		return array_flip( $card_types );
	}

	/**
	 * Get a list of features supported by Square
	 *
	 * @since 2.5
	 * @return array
	 */
	public function get_supported_features() {
		$gateway = $this->get_gateway();
		return ! empty( $gateway ) ? array_filter( $gateway->supports, array( $gateway, 'supports' ) ) : array();
	}

	/**
	 * Hooked on before `process_legacy_payment` and logs any data recording during
	 * the checkout process on client-side.
	 *
	 * If the checkout recorded an error, skip validating the checkout fields by setting the
	 * 'error' status on the PaymentResult.
	 *
	 * @since 2.5
	 * @param PaymentContext $context Holds context for the payment.
	 * @param PaymentResult  $result  Result of the payment.
	 */
	public function log_js_data( PaymentContext $context, PaymentResult &$result ) {
		if ( 'square_credit_card' === $context->payment_method ) {
			if ( ! empty( $context->payment_data['log-data'] ) ) {
				$log_data = json_decode( $context->payment_data['log-data'], true );

				if ( ! empty( $log_data ) ) {
					foreach ( $log_data as $data ) {
						$message = sprintf( "[Checkout Block] Square.js Response:\n %s", print_r( wc_clean( $data ), true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
						$this->plugin->log( $message, $this->get_gateway()->get_id() );
					}
				}
			}

			if ( ! empty( $context->payment_data['checkout-notices'] ) ) {
				$payment_details                    = $result->payment_details;
				$payment_details['checkoutNotices'] = $context->payment_data['checkout-notices'];

				$result->set_payment_details( $payment_details );
				$result->set_status( 'error' );
			}
		}
	}

	/**
	 * Display an admin notice for stores that are running WooCommerce Blocks < 4.8 and also have 3D Secure turned on.
	 *
	 * @since 2.5
	 */
	public function display_compatible_version_notice() {
		$wc_blocks_version = Package::get_version();

		if ( version_compare( $wc_blocks_version, '4.8.0', '<' ) && 'yes' === $this->get_gateway()->get_option( 'enabled', 'no' ) ) {
			?>
			<div class="notice notice-warning is-dismissible">
				<?php // translators: %1$s - opening bold HTML tag, %2$s - closing bold HTML tag, %3$s - version number ?>
				<p><?php echo sprintf( esc_html__( '%1$sWarning!%2$s Some Square + Checkout Block features do not work with your version of WooCommerce Blocks (%3$s). Please update to the latest version of WooCommerce Blocks or WooCommerce to fix these issues.', 'woocommerce-square' ), '<strong>', '</strong>', esc_html( $wc_blocks_version ) ); ?></p>
			</div>
			<?php
		}
	}

	/**
	 * Helper function to get and store an instance of the Square gateway
	 *
	 * @since 2.5
	 * @return Gateway
	 */
	private function get_gateway() {
		if ( empty( $this->gateway ) ) {
			$gateways      = $this->plugin->get_gateways();
			$this->gateway = ! empty( $gateways ) ? $gateways[0] : null;
		}

		return $this->gateway;
	}
}
