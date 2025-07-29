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
	/**
	 * Initialize hooks.
	 *
	 * @since x.x.x
	 */
	public function init() {
		// Initialize order polling system.
		new Order_Polling();
	}
}
