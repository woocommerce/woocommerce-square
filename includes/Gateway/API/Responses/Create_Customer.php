<?php

namespace WooCommerce\Square\Gateway\API\Responses;

use WooCommerce\Square\Framework\PaymentGateway\Api\Payment_Gateway_API_Customer_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Customer create response.
 *
 * @since 2.0.0
 *
 * @method \Square\Models\CreateCustomerResponse get_data()
 */
class Create_Customer extends \WooCommerce\Square\Gateway\API\Response implements Payment_Gateway_API_Customer_Response {
	/**
	 * Gets the new customer ID.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_customer_id() {
		return $this->get_data() instanceof \Square\Models\CreateCustomerResponse ? $this->get_data()->getCustomer()->getId() : '';
	}
}
