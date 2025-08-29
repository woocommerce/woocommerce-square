<?php
/**
 * WooCommerce Square - Order Sync Handler
 *
 * @since 4.9.9
 */

namespace WooCommerce\Square\Handlers;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Square\Sync\Order_Polling;

/**
 * Handles syncing orders between WooCommerce and Square.
 *
 * @since 4.9.9
 */
class Order_Sync {
	/**
	 * Initialize hooks.
	 *
	 * @since 4.9.9
	 */
	public function init() {
		// Initialize order polling system.
		new Order_Polling();
	}
}
