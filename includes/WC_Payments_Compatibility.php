<?php
/**
 * Class for WooPayments compatibility.
 */

namespace WooCommerce\Square;

use WooCommerce\Square\Handlers\Product;

/**
 * WC_Payments_Compatibility Class
 *
 * @version 4.7.3
 */
class WC_Payments_Compatibility {

	/**
	 * Initialize the WooPayments Compatibility class.
	 *
	 * @since 4.7.3
	 */
	public function init() {
		add_filter( 'wcpay_payment_request_is_product_supported', array( $this, 'wcpay_is_product_supported' ), 10, 2 );
		add_filter( 'wcpay_woopay_button_is_product_supported', array( $this, 'wcpay_is_product_supported' ), 10, 2 );
		add_filter( 'wcpay_payment_request_is_cart_supported', array( $this, 'wcpay_is_product_supported' ), 10, 2 );
		add_filter( 'wcpay_platform_checkout_button_are_cart_items_supported', array( $this, 'platform_checkout_button_are_cart_items_supported' ) );
	}

	/**
	 * Filter whether to display express pay buttons on product pages.
	 *
	 * Runs on the `wcpay_payment_request_is_product_supported` and
	 * `wcpay_woopay_button_is_product_supported` filters.
	 *
	 * @since 4.7.3
	 *
	 * @param bool        $is_supported Whether express pay buttons are supported on product pages.
	 * @param \WC_Product $product      The product object.
	 *
	 * @return bool Modified support status.
	 */
	public function wcpay_is_product_supported( $is_supported, $product ) {
		if ( Product::is_gift_card( $product ) ) {
			// Express pay buttons are not supported on product pages.
			return false;
		}

		return $is_supported;
	}

	/**
	 * Filter whether to display WooPay express pay buttons on cart pages.
	 *
	 * Hide the express WooPay button on cart pages containing Square Gift Cards.
	 *
	 * Runs on the `wcpay_platform_checkout_button_are_cart_items_supported` filter.
	 *
	 * @param bool $is_supported Whether express WooPay buttons are supported on cart pages.
	 *
	 * @return bool Modified support status.
	 */
	public function platform_checkout_button_are_cart_items_supported( $is_supported ) {
		if ( ! WC()->cart ) {
			return $is_supported;
		}

		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			$product = $cart_item['data'];

			if ( Product::is_gift_card( $product ) ) {
				return false;
			}
		}

		return $is_supported;
	}
}
