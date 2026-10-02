<?php
/**
 * Connection handler double for the refresh_connection() tests.
 *
 * @package WooCommerce\Square
 */

namespace WooCommerce\Square\Tests\Unit\Handlers;

use WooCommerce\Square\Handlers\Connection;

/**
 * Connection handler built around the plugin double, with rescheduling stubbed out.
 */
class Testable_Connection extends Connection {

	/**
	 * Skips the parent constructor so no hooks are registered.
	 *
	 * @param Memory_Plugin $plugin Plugin double.
	 */
	public function __construct( Memory_Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Rescheduling is Action Scheduler's concern, not this test's.
	 */
	public function schedule_refresh() {}
}
