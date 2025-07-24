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

		// Add action to schedule polling.
		add_action( 'init', array( $this, 'maybe_schedule_polling' ) );

		// Add action to poll square orders.
		add_action( self::CRON_HOOK, array( $this, 'poll_square_orders' ) );
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
	 * Maybe schedule the polling cron job.
	 *
	 * @since x.x.x
	 */
	public function maybe_schedule_polling() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			$this->schedule_polling();
		}
	}

	/**
	 * Schedule the polling cron job.
	 *
	 * @since x.x.x
	 */
	public function schedule_polling() {
		wp_schedule_event( time(), 'square_order_polling', self::CRON_HOOK );

		wc_square()->log( "Scheduled Square order polling with interval: {$this->get_polling_interval_seconds()}", 'sync' );
	}

	/**
	 * Poll Square for new orders.
	 *
	 * @since x.x.x
	 */
	public function poll_square_orders() {
		wc_square()->log( 'Starting Square order polling', 'sync' );

		try {
			$orders = $this->fetch_recent_square_orders();

			if ( empty( $orders ) ) {
				wc_square()->log( 'No new Square orders found during polling', 'sync' );
				return;
			}

			// Process orders to create WooCommerce orders.
			$this->process_square_orders( $orders );

			wc_square()->log( "Square order polling completed.", 'sync' );

		} catch ( \Exception $e ) {
			wc_square()->log( 'Square order polling failed: ' . $e->getMessage(), 'sync' );
		}
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
