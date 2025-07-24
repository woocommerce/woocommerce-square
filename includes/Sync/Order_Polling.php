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
	 * Fetch recent Square orders.
	 *
	 * @since x.x.x
	 * @return array Array of Square order objects.
	 */
	private function fetch_recent_square_orders() {
		$settings_handler = wc_square()->get_settings_handler();
		$access_token     = $settings_handler->get_access_token();
		$location_id      = $settings_handler->get_location_id();
		$is_sandbox       = $settings_handler->is_sandbox();

		if ( empty( $access_token ) || empty( $location_id ) ) {
			wc_square()->log( 'Square API credentials not configured for order polling', 'sync' );
			return array();
		}

		$api = new \WooCommerce\Square\Gateway\API( $access_token, $location_id, $is_sandbox );

		// Get orders since last polling time.
		$last_polling_time = $this->get_last_polling_time();
		$orders            = $this->search_square_orders_since( $api, $last_polling_time );

		return $orders;
	}

	/**
	 * Search Square orders since a specific time.
	 *
	 * @since x.x.x
	 * @param \WooCommerce\Square\Gateway\API $api API instance.
	 * @param string                          $since_time ISO 8601 timestamp.
	 * @return array Array of Square order objects.
	 */
	private function search_square_orders_since( $api, $since_time ) {
		wc_square()->log( "Searching Square orders since: {$since_time}", 'sync' );

		try {
			$settings_handler = wc_square()->get_settings_handler();
			$location_id      = $settings_handler->get_location_id();

			$all_orders = array();
			$cursor = '';
			$batch_count = 0;
			$max_batches = 10; // Prevent infinite loops

			do {
				// Use the API's search_orders method with cursor
				$response = $api->search_orders( array( $location_id ), $since_time, 100, $cursor );

				if ( ! empty( $response['orders'] ) ) {
					$all_orders = array_merge( $all_orders, $response['orders'] );

					wc_square()->log( sprintf( 
						'Batch %d: Found %d orders', 
						$batch_count + 1, 
						count( $response['orders'] ),
					), 'sync' );
				}

				// Update cursor for next iteration
				$cursor = $response['cursor'] ?? '';
				$batch_count++;

			} while ( ! empty( $cursor ) && $batch_count < $max_batches );

			wc_square()->log( sprintf( 
				'Total found: %d Square orders', 
				count( $all_orders ) 
			), 'sync' );

			return $all_orders;

		} catch ( \Exception $e ) {
			wc_square()->log( 'Error searching Square orders: ' . $e->getMessage(), 'error' );
			return array();
		}
	}

	/**
	 * Process Square orders and create/update WooCommerce orders.
	 *
	 * @since x.x.x
	 * @param array $square_orders Array of Square order objects.
	 */
	private function process_square_orders( $square_orders ) {
		if ( empty( $square_orders ) ) {
			wc_square()->log( 'No Square orders to process', 'sync' );
			return;
		}

		$processed_count = 0;
		$updated_count = 0;
		$skipped_count = 0;
		$error_count = 0;

		$importer = new Order_Importer();

		foreach ( $square_orders as $square_order ) {
			try {
				// Check if order already exists in WooCommerce
				$existing_order = $importer->find_existing_wc_order_by_square_id( $square_order->getId() );

				if ( $existing_order ) {
					// Update existing order
					$update_result = $importer->update_existing_woocommerce_order( $existing_order, $square_order );

					if ( $update_result['updated'] ) {
						wc_square()->log( sprintf( 
							'Successfully updated WooCommerce order: Square ID %s -> WC ID %d (%s)', 
							$square_order->getId(), 
							$existing_order->get_id(),
							$update_result['message']
						), 'sync' );
						$updated_count++;
					} else {
						wc_square()->log( sprintf( 
							'No updates needed for order: Square ID %s -> WC ID %d (%s)', 
							$square_order->getId(), 
							$existing_order->get_id(),
							$update_result['message']
						), 'sync' );
						$skipped_count++;
					}
				}

			} catch ( \Exception $e ) {
				wc_square()->log( sprintf( 
					'Error processing Square order %s: %s', 
					$square_order->getId(), 
					$e->getMessage() 
				), 'error' );
				$error_count++;

				// Also add a order note and a meta tag to the order.
				$existing_order->add_order_note( sprintf( 
					'Error processing Square order %s: %s', 
					$square_order->getId(), 
					$e->getMessage() 
				) );
				$existing_order->update_meta_data( '_square_sync_status', 'error' );
			}
		}

		wc_square()->log( sprintf( 
			'Order processing complete: %d created, %d updated, %d skipped, %d errors', 
			$processed_count, 
			$updated_count,
			$skipped_count, 
			$error_count 
		), 'sync' );

		// Update last polling timestamp.
		$this->update_last_polling_time();
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

	/**
	 * Get last polling time.
	 *
	 * @since x.x.x
	 * @return string ISO 8601 timestamp.
	 */
	private function get_last_polling_time() {
		$last_time = get_option( 'wc_square_last_order_polling_time' );

		if ( ! $last_time ) {
			// Default to 24 hours ago for first run.
			$last_time = gmdate( 'c', time() - DAY_IN_SECONDS );
		}

		return $last_time;
	}

	/**
	 * Update last polling time.
	 *
	 * @since x.x.x
	 */
	private function update_last_polling_time() {
		$current_time = gmdate( 'c' );
		update_option( 'wc_square_last_order_polling_time', $current_time );
	}
}
