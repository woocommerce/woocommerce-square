<?php
/**
 * Plugin double for the Sync handler tests.
 *
 * @package WooCommerce\Square
 */

namespace WooCommerce\Square\Tests\Unit\Handlers;

/**
 * Plugin double exposing only what the Sync handler touches.
 */
class Sync_Plugin {

	/**
	 * @var Sync_Settings
	 */
	public $settings;

	/**
	 * @var Sync_Job_Handler
	 */
	public $jobs;

	/**
	 * @var Sync_Notice_Handler
	 */
	public $notices;

	public function __construct() {
		$this->settings = new Sync_Settings();
		$this->jobs     = new Sync_Job_Handler();
		$this->notices  = new Sync_Notice_Handler();
	}

	public function get_settings_handler() {
		return $this->settings;
	}

	public function get_background_job_handler() {
		return $this->jobs;
	}

	public function get_admin_notice_handler() {
		return $this->notices;
	}

	public function get_id() {
		return 'square';
	}
}
