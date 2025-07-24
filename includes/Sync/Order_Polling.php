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
		// Add filter to add custom cron schedule.
		add_filter( 'cron_schedules', array( $this, 'add_custom_cron_schedule' ) );
	}

	/**
	 * Add custom cron schedule for Square order polling.
	 *
	 * @since x.x.x
	 * @param array $schedules Existing cron schedules.
	 * @return array Modified cron schedules.
	 */
	public function add_custom_cron_schedule( $schedules ) {
		$polling_interval = $this->get_polling_interval_seconds();

		$schedules['square_order_polling'] = array(
			'interval' => $polling_interval,
			'display'  => sprintf(
				/* translators: %d: number of minutes */
				__( 'Every %d minutes (Square Order Polling)', 'woocommerce-square' ),
				round( $polling_interval / MINUTE_IN_SECONDS )
			),
		);

		return $schedules;
	}

	/**
	 * Get polling interval in seconds.
	 *
	 * @since x.x.x
	 * @return int
	 */
	private function get_polling_interval_seconds() {
		/**
		 * Filters the polling interval in seconds for Square orders.
		 *
		 * @since x.x.x
		 * @param int $interval The polling interval in seconds.
		 * @return int The polling interval in seconds.
		 */
		return apply_filters( 'wc_square_order_polling_interval_seconds', 15 * MINUTE_IN_SECONDS );
	}
}
