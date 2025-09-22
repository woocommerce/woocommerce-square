<?php
/**
 * Square Order Polling.
 *
 * Handles scheduled polling to sync orders from Square to WooCommerce.
 *
 * @package WooCommerce\Square\Sync
 * @since 5.0.0
 */

namespace WooCommerce\Square\Sync;

defined( 'ABSPATH' ) || exit;

/**
 * Order Polling Class.
 *
 * @since 5.0.0
 */
class Order_Polling {
	/**
	 * Initialize the polling system.
	 *
	 * @since 5.0.0
	 */
	public function __construct() {
		// Add action to schedule polling.
		add_action( 'init', array( $this, 'maybe_schedule_polling' ) );

		// Add action to poll square orders via Action Scheduler.
		add_action( WC_SQUARE_SYNC_ORDERS_EVENT_HOOK, array( $this, 'poll_square_orders' ) );
	}

	/**
	 * Maybe schedule the polling Action Scheduler job.
	 *
	 * @since 5.0.0
	 */
	public function maybe_schedule_polling() {
		if ( false === as_next_scheduled_action( WC_SQUARE_SYNC_ORDERS_EVENT_HOOK, array(), wc_square()->get_id() ) ) {
			$this->schedule_polling();
		}
	}

	/**
	 * Schedule the polling Action Scheduler job.
	 *
	 * @since 5.0.0
	 */
	public function schedule_polling() {
		$interval = $this->get_polling_interval_seconds();

		as_schedule_recurring_action( time() + $interval, $interval, WC_SQUARE_SYNC_ORDERS_EVENT_HOOK, array(), wc_square()->get_id() );

		wc_square()->log( "Scheduled Square order polling with interval: {$interval} seconds", 'sync' );
	}

	/**
	 * Poll Square for new orders.
	 *
	 * @since 5.0.0
	 */
	public function poll_square_orders() {
		// Capture sync start time to avoid missing orders updated during sync.
		$sync_start_time = gmdate( 'c' );

		wc_square()->log( 'Starting Square order polling', 'sync' );

		try {
			$orders = $this->fetch_recent_square_orders( $sync_start_time );

			if ( empty( $orders ) ) {
				wc_square()->log( 'No new Square orders found during polling', 'sync' );
				// Update polling time to sync start time to avoid missing orders.
				$this->update_last_polling_time( $sync_start_time );
				return;
			}

			$this->process_square_orders( $orders );

			// Update polling time to sync start time to avoid missing orders.
			$this->update_last_polling_time( $sync_start_time );

			wc_square()->log( 'Square order polling completed.', 'sync' );

		} catch ( \Exception $e ) {
			wc_square()->log( 'Square order polling failed: ' . $e->getMessage(), 'sync' );
			// Don't update polling time on failure.
		}
	}

	/**
	 * Fetch recent Square orders.
	 *
	 * @since 5.0.0
	 * @param string $sync_start_time Optional sync start time for bounded time window.
	 * @return array Array of Square order objects.
	 */
	private function fetch_recent_square_orders( $sync_start_time = null ) {
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
		$last_polling_time   = $this->get_last_polling_time();
		$adjusted_start_time = gmdate( 'c', strtotime( $last_polling_time ) + 1 ); // To avoid re-processing the same orders.
		$orders              = $this->search_square_orders_since( $api, $adjusted_start_time, $sync_start_time );

		return $orders;
	}

	/**
	 * Search Square orders since a specific time with bounded time window.
	 *
	 * @since 5.0.0
	 * @param \WooCommerce\Square\Gateway\API $api API instance.
	 * @param string                          $since_time ISO 8601 timestamp.
	 * @param string                          $sync_start_time Optional sync start time for bounded time window.
	 * @return array Array of Square order objects.
	 */
	private function search_square_orders_since( $api, $since_time, $sync_start_time = null ) {
		$end_time = isset( $sync_start_time ) ? $sync_start_time : gmdate( 'c' );
		wc_square()->log( "Searching Square orders with bounded time window: {$since_time} to {$end_time}", 'sync' );

		try {
			$settings_handler = wc_square()->get_settings_handler();
			$location_id      = $settings_handler->get_location_id();

			$all_orders  = array();
			$cursor      = '';
			$batch_count = 0;
			$max_batches = 10; // Prevent infinite loops.

			do {
				// Use the API's search_orders method with bounded time window.
				$response = $api->search_orders( array( $location_id ), $since_time, 100, $cursor, $end_time );

				if ( ! empty( $response['orders'] ) ) {
					$all_orders = array_merge( $all_orders, $response['orders'] );

					wc_square()->log(
						sprintf(
							'Batch %d: Found %d orders',
							$batch_count + 1,
							count( $response['orders'] ),
						),
						'sync'
					);
				}

				// Update cursor for next iteration.
				$cursor = $response['cursor'] ?? '';
				++$batch_count;

			} while ( ! empty( $cursor ) && $batch_count < $max_batches );

			wc_square()->log(
				sprintf(
					'Total found: %d Square orders',
					count( $all_orders )
				),
				'sync'
			);

			return $all_orders;

		} catch ( \Exception $e ) {
			wc_square()->log( 'Error searching Square orders: ' . $e->getMessage(), 'error' );
			return array();
		}
	}

	/**
	 * Process Square orders to create or update WooCommerce orders.
	 *
	 * @since 5.0.0
	 * @param array $square_orders Array of Square order objects.
	 */
	private function process_square_orders( $square_orders ) {
		if ( empty( $square_orders ) ) {
			wc_square()->log( 'No Square orders to process', 'sync' );
			return;
		}

		$updated_count = 0;
		$skipped_count = 0;
		$error_count   = 0;

		$importer = new Order_Importer();

		foreach ( $square_orders as $square_order ) {
			$order_id = $square_order->getId();

			try {
				// Check if order already exists in WooCommerce.
				$existing_order = $importer->find_existing_wc_order_by_square_order_id( $order_id );

				if ( $existing_order ) {
					// Update existing order.
					$update_result = $importer->update_existing_woocommerce_order( $existing_order, $square_order );

					if ( $update_result['updated'] ) {
						wc_square()->log(
							sprintf(
								'Successfully updated WooCommerce order: Square ID %s -> WC ID %d (%s)',
								$order_id,
								$existing_order->get_id(),
								$update_result['message']
							),
							'sync'
						);
						++$updated_count;
					} else {
						wc_square()->log(
							sprintf(
								'No updates needed for order: Square ID %s -> WC ID %d (%s)',
								$order_id,
								$existing_order->get_id(),
								$update_result['message']
							),
							'sync'
						);
						++$skipped_count;
					}
				} else {
					// Order doesn't exist in WooCommerce - skip for now.
					wc_square()->log(
						sprintf(
							'Skipping Square order %s - no corresponding WooCommerce order found',
							$order_id
						),
						'sync'
					);
					++$skipped_count;
				}
			} catch ( \Exception $e ) {
				wc_square()->log(
					sprintf(
						'Error processing Square order %s: %s',
						$order_id,
						$e->getMessage()
					),
					'error'
				);
				++$error_count;

				// Add order note and meta tag if order exists.
				if ( isset( $existing_order ) && $existing_order instanceof \WC_Order ) {
					$existing_order->add_order_note(
						sprintf(
							'Error processing Square order %s: %s',
							$order_id,
							$e->getMessage()
						)
					);
					$existing_order->update_meta_data( '_square_sync_status', 'error' );
				}
			}
		}

		wc_square()->log(
			sprintf(
				'Order processing complete: %d updated, %d skipped, %d errors',
				$updated_count,
				$skipped_count,
				$error_count
			),
			'sync'
		);
	}

	/**
	 * Get polling interval in seconds.
	 *
	 * @since 5.0.0
	 * @return int
	 */
	private function get_polling_interval_seconds() {
		/**
		 * Filters the polling interval in seconds for Square orders.
		 *
		 * @since 5.0.0
		 * @param int $interval The polling interval in seconds.
		 * @return int The polling interval in seconds.
		 */
		return apply_filters( 'wc_square_order_polling_interval_seconds', 15 * MINUTE_IN_SECONDS );
	}

	/**
	 * Get last polling time.
	 *
	 * @since 5.0.0
	 * @return string ISO 8601 timestamp.
	 */
	private function get_last_polling_time() {
		$last_time    = get_option( 'wc_square_last_order_polling_time' );
		$seconds_past = filter_input( INPUT_GET, 'seconds_past', FILTER_VALIDATE_INT );

		// If the secondsPast parameter is set, reset the last polling time to the number of seconds past.
		if ( $seconds_past ) {
			$last_time = gmdate( 'c', time() - intval( $seconds_past ) );
		}

		if ( ! $last_time ) {
			// Default to 24 hours ago for first run.
			$last_time = gmdate( 'c', time() - DAY_IN_SECONDS );
		}

		return $last_time;
	}

	/**
	 * Update last polling time.
	 *
	 * @since 5.0.0
	 * @param string $timestamp Optional timestamp to set. Defaults to current time.
	 */
	private function update_last_polling_time( $timestamp = null ) {
		if ( null === $timestamp ) {
			$timestamp = gmdate( 'c' );
		}

		update_option( 'wc_square_last_order_polling_time', $timestamp );
		wc_square()->log( "Updated last polling time to: {$timestamp}", 'sync' );
	}
}
