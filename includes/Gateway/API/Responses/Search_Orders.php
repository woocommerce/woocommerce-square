<?php

namespace WooCommerce\Square\Gateway\API\Responses;

defined( 'ABSPATH' ) || exit;

/**
 * The Search Orders API response object.
 *
 * @since x.x.x
 *
 * @method \Square\Models\SearchOrdersResponse get_data()
 */
class Search_Orders extends \WooCommerce\Square\Gateway\API\Response {

	/**
	 * Gets the orders from the response.
	 *
	 * @since x.x.x
	 *
	 * @return array
	 */
	public function get_orders() {

		return $this->get_data() ? $this->get_data()->getOrders() : array();
	}

	/**
	 * Gets the cursor for pagination.
	 *
	 * @since x.x.x
	 *
	 * @return string
	 */
	public function get_cursor() {

		return $this->get_data() ? $this->get_data()->getCursor() : '';
	}

	/**
	 * Gets the response data as an array with orders and cursor.
	 *
	 * @since x.x.x
	 *
	 * @return array
	 */
	public function get_response_data() {

		return array(
			'orders' => $this->get_orders(),
			'cursor' => $this->get_cursor(),
		);
	}
}
