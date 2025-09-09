<?php

namespace WooCommerce\Square\Gateway\API\Responses;

defined( 'ABSPATH' ) || exit;

/**
 * The Search Orders API response object.
 *
 * @since 5.0.0
 *
 * @method \Square\Models\SearchOrdersResponse get_data()
 */
class Search_Orders extends \WooCommerce\Square\Gateway\API\Response {

	/**
	 * Gets the orders from the response.
	 *
	 * @since 5.0.0
	 *
	 * @return array
	 */
	public function get_orders() {

		return $this->get_data() ? $this->get_data()->getOrders() : array();
	}

	/**
	 * Gets the cursor for pagination.
	 *
	 * @since 5.0.0
	 *
	 * @return string
	 */
	public function get_cursor() {

		return $this->get_data() ? $this->get_data()->getCursor() : '';
	}

	/**
	 * Gets the response data as an array with orders and cursor.
	 *
	 * @since 5.0.0
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
