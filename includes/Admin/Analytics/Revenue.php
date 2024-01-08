<?php

namespace WooCommerce\Square\Admin\Analytics;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Square\Handlers\Product;

/**
 * Adds necessary functions to modify analytics stats data.
 */
class Revenue {
	/**
	 * Constructor function.
	 */
	public function __construct() {
		add_filter( 'woocommerce_analytics_update_order_stats_data', array( $this, 'filter_net_sales_stats' ) );
	}

	/**
	 * Removes the purchase of a gift card from the net revenue.
	 *
	 * @since 4.2.0
	 *
	 * @param array $order_data Order data.
	 * @return array
	 */
	public function filter_net_sales_stats( $order_data ) {
		$order            = wc_get_order( $order_data['order_id'] );
		$line_items       = $order->get_items( 'line_item' );
		$amount_to_deduct = 0;

		/** @var \WC_Order_Item_Product $line_item */
		foreach ( $line_items as $line_item ) {
			$product = $line_item->get_product();

			if ( ! Product::is_gift_card( $product ) ) {
				continue;
			}

			$amount_to_deduct += $line_item->get_total();
		}

		$order_data['net_total'] = $order_data['net_total'] - $amount_to_deduct;

		return $order_data;
	}
}
