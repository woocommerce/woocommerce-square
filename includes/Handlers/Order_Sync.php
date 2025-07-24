<?php
/**
 * WooCommerce Square - Order Sync Handler
 *
 * @since x.x.x
 */

namespace WooCommerce\Square\Handlers;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Square\Sync\Order_Polling;

/**
 * Handles syncing orders between WooCommerce and Square.
 *
 * @since x.x.x
 */
class Order_Sync {
	public function __construct() {
		// Initialize hooks.
		$this->init();
	}

	/**
	 * Initialize hooks.
	 *
	 * @since x.x.x
	 */
	public static function init() {
		// Initialize order polling system.
		new Order_Polling();

		// Sync updated WooCommerce order to Square.
		add_action( 'woocommerce_update_order', array( __CLASS__, 'maybe_sync_update_to_square' ), 10, 2 );
	}

	/**
	 * Sync updated WooCommerce order to Square if linked.
	 *
	 * @since x.x.x
	 *
	 * @param int       $order_id WooCommerce order ID.
	 * @param \WC_Order $order WooCommerce order object.
	 */
	public static function maybe_sync_update_to_square( $order_id, $order ) {
		// @TODO: For future updates.
	}
}
