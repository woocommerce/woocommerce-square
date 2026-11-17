<?php
/**
 * Orders request double for the tax lookup tests.
 *
 * @package WooCommerce\Square
 */

namespace WooCommerce\Square\Tests\Unit\Gateway\Fixtures;

use WooCommerce\Square\Gateway\API\Requests\Orders;

/**
 * Exposes protected methods and records missing rate logs instead of writing them.
 */
class Orders_Tax_Lookup_Test_Request extends Orders {

	/**
	 * Skips the parent constructor: these tests never call the Square API.
	 */
	public function __construct() {}

	/** @var array rate IDs passed to log_missing_order_tax() */
	public $missing_rate_ids = array();

	/**
	 * Calls a protected method.
	 *
	 * @param string $method method name
	 * @param mixed  ...$args arguments
	 * @return mixed
	 */
	public function call( $method, ...$args ) {
		return $this->$method( ...$args );
	}

	protected function log_missing_order_tax( \WC_Order $order, $item, $rate_id ) {
		$this->missing_rate_ids[] = $rate_id;
	}
}
