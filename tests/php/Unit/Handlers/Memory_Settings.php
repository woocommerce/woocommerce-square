<?php
/**
 * Settings handler double for the refresh_connection() tests.
 *
 * @package WooCommerce\Square
 */

namespace WooCommerce\Square\Tests\Unit\Handlers;

/**
 * Settings handler double holding tokens in memory.
 */
class Memory_Settings {

	/**
	 * Whether sandbox mode is on.
	 *
	 * @var bool
	 */
	public $sandbox = false;

	/**
	 * Stored access token.
	 *
	 * @var string
	 */
	public $access_token = 'old-access-token';

	/**
	 * Stored refresh token.
	 *
	 * @var string
	 */
	public $refresh_token = 'refresh-token';

	public function is_sandbox() {
		return $this->sandbox;
	}

	public function is_debug_enabled() {
		return false;
	}

	public function get_refresh_token() {
		return $this->refresh_token;
	}

	public function update_access_token( $token ) {
		$this->access_token = $token;
	}

	public function update_refresh_token( $token ) {
		$this->refresh_token = $token;
	}
}
