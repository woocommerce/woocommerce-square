<?php
/**
 * Connection handler double for the authorization error tests.
 *
 * @package WooCommerce\Square
 */

namespace WooCommerce\Square\Tests\Unit\API;

/**
 * Connection handler double that reports a scripted refresh result and records what was called.
 */
class Recording_Connection_Handler {

	/**
	 * Value refresh_connection() returns.
	 *
	 * @var bool
	 */
	public $refresh_result = false;

	/**
	 * Number of refresh_connection() calls.
	 *
	 * @var int
	 */
	public $refresh_calls = 0;

	/**
	 * Number of disconnect() calls.
	 *
	 * @var int
	 */
	public $disconnect_calls = 0;

	/**
	 * Records a refresh and returns the scripted result.
	 *
	 * @return bool
	 */
	public function refresh_connection() {
		++$this->refresh_calls;

		return $this->refresh_result;
	}

	/**
	 * Records a disconnect.
	 */
	public function disconnect() {
		++$this->disconnect_calls;
	}
}
