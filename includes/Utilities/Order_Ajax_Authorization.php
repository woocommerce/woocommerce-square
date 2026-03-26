<?php
/**
 * WooCommerce Square
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is bundled with this package in the file license.txt.
 *
 * @package WooCommerce\Square\Utilities
 */

namespace WooCommerce\Square\Utilities;

defined( 'ABSPATH' ) || exit;

/**
 * Authorizes AJAX handlers that return order data during pay-for-order flows.
 *
 * @since 5.3.1
 */
final class Order_Ajax_Authorization {

	/**
	 * Generic error message for any failed pay-for-order authorization (avoid response differentiation).
	 *
	 * @since 5.3.1
	 *
	 * @return string
	 */
	public static function get_invalid_order_message() {
		return esc_html__( 'Invalid order.', 'woocommerce-square' );
	}

	/**
	 * Order key from AJAX POST, or from the pay-for-order URL query string on full page requests.
	 *
	 * @since 5.3.1
	 *
	 * @return string
	 */
	public static function get_request_order_key() {
		if ( isset( $_POST['order_key'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return sanitize_text_field( wp_unslash( $_POST['order_key'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}

		if ( isset( $_GET['key'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return sanitize_text_field( wp_unslash( $_GET['key'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		return '';
	}

	/**
	 * Order key for frontend scripts on the order-pay page.
	 *
	 * @since 5.3.1
	 *
	 * @return string
	 */
	public static function get_order_key_for_frontend_localization() {
		if ( ! is_checkout() || ! is_wc_endpoint_url( 'order-pay' ) ) {
			return '';
		}

		if ( ! isset( $_GET['key'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return '';
		}

		return sanitize_text_field( wp_unslash( $_GET['key'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Whether the current request may load the given order in pay-for-order AJAX context.
	 *
	 * @since 5.3.1
	 *
	 * @param \WC_Order|false $order Order object or false.
	 * @return bool
	 */
	public static function is_authorized_for_pay_for_order( $order ) {
		if ( ! is_a( $order, \WC_Order::class ) ) {
			return false;
		}

		$order_key = self::get_request_order_key();

		if ( ! $order->key_is_valid( $order_key ) ) {
			return false;
		}

		$order_customer_id = (int) $order->get_user_id();

		// Guest orders (`user_id` 0) only need a valid key. Orders with a customer account require the current user to be that customer.
		if ( ! $order_customer_id || get_current_user_id() === $order_customer_id ) {
			return true;
		}

		return false;
	}

	/**
	 * Order ID for payment line-item builders: non-zero only when pay-for-order context applies and authorization passes.
	 *
	 * On normal checkout AJAX, a forged `order_id` must be ignored (returns 0). When the pay-for-order endpoint
	 * is active or `is_pay_for_order_page` is posted as true, returns the sanitized ID for the order being paid.
	 * Used `is_authorized_for_pay_for_order()` to check if the order is authorized to be paid.
	 *
	 * @since 5.3.1
	 *
	 * @param int $order_id Order ID from POST or query.
	 * @return int
	 */
	public static function trusted_line_items_order_id( $order_id ) {
		$order_id = (int) $order_id;
		if ( $order_id <= 0 ) {
			return 0;
		}

		$on_order_pay_endpoint = is_wc_endpoint_url( 'order-pay' );

		$post_claims_pay_for_order = false;
		if ( isset( $_POST['is_pay_for_order_page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$post_claims_pay_for_order = 'true' === sanitize_text_field( wp_unslash( $_POST['is_pay_for_order_page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		} elseif ( isset( $_POST['is_pay_order'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$post_claims_pay_for_order = 'true' === sanitize_key( wp_unslash( $_POST['is_pay_order'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}

		if ( ! $on_order_pay_endpoint && ! $post_claims_pay_for_order ) {
			return 0;
		}

		$order = wc_get_order( $order_id );
		if ( ! self::is_authorized_for_pay_for_order( $order ) ) {
			return 0;
		}

		return $order_id;
	}
}
