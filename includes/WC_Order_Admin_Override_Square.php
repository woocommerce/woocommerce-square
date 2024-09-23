<?php
/**
 * This class solely exists to deal with the deprecated warnings on PHP 8.2+.
 *
 * In multiple places, the plugin dynamically adds properties on the instance
 * of the \Automattic\WooCommerce\Admin\Overrides\Order class, which is no
 * longer supported on PHP 8.2+
 */

namespace WooCommerce\Square;

use Automattic\WooCommerce\Admin\Overrides\Order;

/**
 * Override of the \Automattic\WooCommerce\Admin\Overrides\Order class.
 *
 * This class is used to add additional properties to the order object.
 *
 * @since x.x.x
 */
class WC_Order_Admin_Override_Square extends Order {
	/**
	 * Holds the Square customer ID.
	 *
	 * @var string
	 */
	public $customer_id;

	/**
	 * Holds the Square customer ID. Same as $customer_id.
	 *
	 * @var string
	 */
	public $square_customer_id;

	/**
	 * Holds payment information on the order object.
	 *
	 * @var object
	 */
	public $payment;

	/**
	 * Holds order description.
	 *
	 * @var string
	 */
	public $description;

	/**
	 * Holds a combination of order number + retry count, should provide
	 * a unique value for each transaction attempt.
	 *
	 * @var string
	 */
	public $unique_transaction_ref;

	/**
	 * Holds Square order ID.
	 *
	 * @var string
	 */
	public $square_order_id;

	/**
	 * Holds plugin version number.
	 *
	 * @var string
	 */
	public $square_version;

	/**
	 * Holds order payment total.
	 *
	 * @var float
	 */
	public $payment_total;

	/**
	 * Holds capture transaction type information.
	 *
	 * @var object
	 */
	public $capture;

	/**
	 * Holds refund order information.
	 *
	 * @var object
	 */
	public $refund;
}
