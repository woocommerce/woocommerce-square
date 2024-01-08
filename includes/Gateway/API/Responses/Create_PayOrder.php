<?php

namespace WooCommerce\Square\Gateway\API\Responses;

defined( 'ABSPATH' ) || exit;

/**
 * The Create PayOrder API response object.
 *
 * @since 3.9.0
 *
 * @method \Square\Models\CreatePayOrderResponse get_data()
 */
class Create_PayOrder extends \WooCommerce\Square\Gateway\API\Response {

	/**
	 * Gets the Square payment object.
	 *
	 * @since 3.9.0
	 * @return \Square\Models\Order|null
	 */
	public function get_order() {
		return ! $this->has_errors() && $this->get_data()->getOrder() ? $this->get_data()->getOrder() : null;
	}

	/**
	 * Returns true if the order status is completed.
	 *
	 * @since 3.9.0
	 * @return boolean
	 */
	public function transaction_approved() {
		return $this->get_order() && 'COMPLETED' === $this->get_order()->getState();
	}

	/**
	 * Returns array of trasaction IDs when payment is done using multiple payment methods.
	 * For example, Square Gift card + Square credit card.
	 *
	 * @since 3.9.0
	 * @return array
	 */
	public function get_transaction_ids() {
		$payments_ids = array();

		if ( ! $this->get_order() ) {
			return $payments_ids;
		}

		$tenders = $this->get_order()->getTenders();

		foreach ( $tenders as $tender ) {
			if ( ! $tender instanceof \Square\Models\Tender ) {
				continue;
			}

			$payments_ids[] = $tender->getId();
		}

		return $payments_ids;
	}
}
