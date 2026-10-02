<?php
/**
 * Plugin double for the authorization error tests.
 *
 * @package WooCommerce\Square
 */

namespace WooCommerce\Square\Tests\Unit\API;

/**
 * Plugin double exposing only what the response validator touches.
 */
class Recording_Plugin {

	/**
	 * Connection handler double.
	 *
	 * @var Recording_Connection_Handler
	 */
	public $connection_handler;

	/**
	 * Logged messages, in order.
	 *
	 * @var array
	 */
	public $logs = array();

	/**
	 * Sets up the connection handler double.
	 */
	public function __construct() {
		$this->connection_handler = new Recording_Connection_Handler();
	}

	/**
	 * Returns the connection handler double.
	 *
	 * @return Recording_Connection_Handler
	 */
	public function get_connection_handler() {
		return $this->connection_handler;
	}

	/**
	 * Records a log message.
	 *
	 * @param string $message Message.
	 */
	public function log( $message ) {
		$this->logs[] = $message;
	}
}
