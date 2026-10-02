<?php
/**
 * Plugin double for the refresh_connection() tests.
 *
 * @package WooCommerce\Square
 */

namespace WooCommerce\Square\Tests\Unit\Handlers;

/**
 * Plugin double exposing only what refresh_connection() touches on the success path.
 */
class Memory_Plugin {

	/**
	 * Settings handler double.
	 *
	 * @var Memory_Settings
	 */
	public $settings;

	public function __construct() {
		$this->settings = new Memory_Settings();
	}

	public function get_settings_handler() {
		return $this->settings;
	}

	public function log( $message ) {}
}
