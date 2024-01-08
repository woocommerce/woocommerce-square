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

use WooCommerce\Square\Framework\PaymentGateway\PaymentTokens\Payment_Gateway_Payment_Token;

defined( 'ABSPATH' ) || exit;

/**
 * My Payment Methods Class
 *
 * Renders the My Payment Methods table on the My Account page and handles
 * any associated actions (deleting a payment method, etc)
 *
 * @since 3.0.0
 */
class Payment_Gateway_My_Payment_Methods {


	/** @var Payment_Gateway_Plugin */
	protected $plugin;

	/** @var Payment_Gateway_Payment_Token[] array of token objects */
	protected $tokens;

	/** @var Payment_Gateway_Payment_Token[] array of token objects */
	protected $credit_card_tokens;

	/** @var Payment_Gateway_Payment_Token[] array of token objects */
	protected $echeck_tokens;

	/** @var bool true if there are tokens */
	protected $has_tokens;


	/**
	 * Setup Class
	 *
	 * Note: this constructor executes during the `wp` action
	 *
	 * @param Payment_Gateway_Plugin $plugin gateway plugin
	 * @since 3.0.0
	 */
	public function __construct( $plugin ) {

		$this->plugin = $plugin;

		add_filter( 'woocommerce_account_payment_methods_columns', array( $this, 'filter_payment_method_columns' ) );
		add_action( 'woocommerce_account_payment_methods_column_method', array( $this, 'render_payment_method_method_data' ) );
		add_action( 'woocommerce_account_payment_methods_column_details', array( $this, 'render_payment_method_details_data' ) );
	}

	/** Payment Method actions ************************************************/

	/**
	 * Adds the details column next to method.
	 *
	 * @param array $columns Array of columns for payment methods.
	 * @return array
	 */
	public function filter_payment_method_columns( $columns ) {
		$filtered_columns = array();

		foreach ( $columns as $slug => $title ) {
			if ( 'method' === $slug ) {
				$filtered_columns[ $slug ]   = $title;
				$filtered_columns['details'] = '&nbsp;';
			} else {
				$filtered_columns[ $slug ] = $title;
			}
		}

		return $filtered_columns;
	}

	/**
	 * Renders the card brand.
	 *
	 * @param array $method Holds data about the payment method.
	 */
	public function render_payment_method_method_data( $method ) {
		echo esc_html( $method['method']['brand'] );
	}

	/**
	 * Renders card details.
	 *
	 * @param array $method Holds data about the payment method.
	 */
	public function render_payment_method_details_data( $method ) {
		$card_brand     = $method['method']['brand'];
		$card_image_url = wc_square()->get_gateway()->get_payment_method_image_url( $card_brand );
		?>

		<?php if ( ! empty( $card_image_url ) ) : ?>
			<img
				src="<?php echo esc_url( $card_image_url ); ?>"
				width="40"
				height="25"
				alt="<?php echo esc_attr( $card_brand ); ?>"
				title="<?php echo esc_attr( $card_brand ); ?>"
			>
		<?php endif; ?>
		<span>&bull; &bull; &bull;<?php echo esc_html( $method['method']['last4'] ); ?></span>
		<?php
	}

	/**
	 * Redirect back to the Payment Methods (WC 2.6+) or My Account page
	 *
	 * @since 3.0.0
	 */
	protected function redirect_to_my_account() {

		wp_safe_redirect( wc_get_account_endpoint_url( 'payment-methods' ) );
		exit;
	}


	/**
	 * Return the gateway plugin, primarily a convenience method to other actors
	 * using filters
	 *
	 * @since 3.0.0
	 *
	 * @return Payment_Gateway_Plugin
	 */
	public function get_plugin() {

		return $this->plugin;
	}


	/**
	 * Returns true if at least one of the plugin's gateways supports the
	 * add new payment method feature
	 *
	 * @since 3.0.0
	 * @return bool
	 */
	protected function supports_add_payment_method() {

		foreach ( $this->get_plugin()->get_gateways() as $gateway ) {

			if ( $gateway->is_direct_gateway() && $gateway->supports_add_payment_method() ) {
				return true;
			}
		}

		return false;
	}


	/**
	 * Determines if we're viewing the My Account -> Payment Methods page.
	 *
	 * @since 3.0.0
	 *
	 * @return bool
	 */
	protected function is_payment_methods_page() {
		global $wp;

		return is_user_logged_in() && is_account_page() && isset( $wp->query_vars['payment-methods'] );
	}
}
