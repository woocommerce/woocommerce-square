<?php
/**
 * WooCommerce Square - Order Sync Handler
 *
 * @since 5.0.0
 */

namespace WooCommerce\Square\Handlers;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Square\Sync\Order_Polling;

/**
 * Handles syncing orders between WooCommerce and Square.
 *
 * @since 5.0.0
 */
class Order_Sync {
	/**
	 * Initialize hooks.
	 *
	 * @since 5.0.0
	 */
	public function init() {
		// Initialize order polling system.
		new Order_Polling();
	}
}
