<?php
/**
 * Square Order Polling.
 *
 * Handles scheduled polling to sync orders from Square to WooCommerce.
 *
 * @package WooCommerce\Square\Sync
 * @since x.x.x
 */

namespace WooCommerce\Square\Sync;

defined( 'ABSPATH' ) || exit;

/**
 * Order Polling Class.
 *
 * @since x.x.x
 */
class Order_Polling {

	/**
	 * Cron hook name for order polling.
	 *
	 * @since x.x.x
	 * @var string
	 */
	const CRON_HOOK = 'woocommerce_square_poll_orders';

	/**
	 * Initialize the polling system.
	 *
	 * @since x.x.x
	 */
	public function __construct() {
		
	}
}
