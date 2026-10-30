<?php
/**
 * Settings handler double for the Sync handler tests.
 *
 * @package WooCommerce\Square
 */

namespace WooCommerce\Square\Tests\Unit\Handlers;

/**
 * Settings handler double for the Sync handler tests.
 */
class Sync_Settings {

	/**
	 * Whether product sync is enabled.
	 *
	 * @var bool
	 */
	public $product_sync_enabled = false;

	/**
	 * Whether the store is connected to Square.
	 *
	 * @var bool
	 */
	public $connected = true;

	public function is_product_sync_enabled() {
		return $this->product_sync_enabled;
	}

	public function is_connected() {
		return $this->connected;
	}

	public function get_sync_interval() {
		return HOUR_IN_SECONDS;
	}
}
