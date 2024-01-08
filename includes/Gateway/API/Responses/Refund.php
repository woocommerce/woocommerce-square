<?php

namespace WooCommerce\Square\Gateway\API\Responses;

defined( 'ABSPATH' ) || exit;

/**
 * The refund API response object.
 *
 * @since 2.0.0
 *
 * @method \Square\Models\CreateRefundResponse|\Square\Models\GetPaymentRefundResponse get_data()
 */
class Refund extends \WooCommerce\Square\Gateway\API\Response {

	/**
	 * Determines if the transaction was approved.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function transaction_approved() {
		return parent::transaction_approved() && ( in_array( $this->get_status_code(), array( 'APPROVED', 'COMPLETED', 'PENDING' ), true ) );
	}

	/**
	 * Gets the transaction ID.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_transaction_id() {

		return $this->get_data() && $this->get_data()->getRefund() ? $this->get_data()->getRefund()->getId() : '';
	}

	/**
	 * Gets the response status code.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_status_code() {

		if ( ! $this->has_errors() && $this->get_data() ) {
			$code = $this->get_data()->getRefund()->getStatus();
		} else {
			$code = parent::get_status_code();
		}

		return $code;
	}
}
