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
		add_action( self::CRON_HOOK, array( $this, 'poll_square_orders' ) );
		add_action( 'init', array( $this, 'maybe_schedule_polling' ) );
	}

	/**
	 * Maybe schedule the polling cron job.
	 *
	 * @since x.x.x
	 */
	public function maybe_schedule_polling() {
		if ( ! $this->is_polling_enabled() ) {
			$this->unschedule_polling();
			return;
		}

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
		$interval = $this->get_polling_interval();
		wp_schedule_event( time(), $interval, self::CRON_HOOK );
		
		wc_square()->log( "Scheduled Square order polling with interval: {$interval}", 'sync' );
	}

	/**
	 * Unschedule the polling cron job.
	 *
	 * @since x.x.x
	 */
	public function unschedule_polling() {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
			wc_square()->log( 'Unscheduled Square order polling', 'sync' );
		}
	}

	/**
	 * Poll Square for new orders.
	 *
	 * @since x.x.x
	 */
	public function poll_square_orders() {
		wc_square()->log( 'Starting Square order polling', 'sync' );

		if ( ! $this->is_polling_enabled() ) {
			wc_square()->log( 'Order polling is disabled, skipping', 'sync' );
			return;
		}

		try {
			$orders = $this->fetch_recent_square_orders();
			
			if ( empty( $orders ) ) {
				wc_square()->log( 'No new Square orders found during polling', 'sync' );
				return;
			}

			$imported_count = 0;
			$skipped_count  = 0;

			foreach ( $orders as $square_order ) {
				$square_order_id = $square_order->getId();
				
				// Check if we should import this order.
				$order_data = $this->convert_square_order_to_array( $square_order );
				if ( ! Order_Importer::should_import_order( $order_data ) ) {
					$skipped_count++;
					continue;
				}

				// Import the order.
				$result = Order_Importer::import_square_order( $square_order_id, 'polling' );
				
				if ( $result ) {
					$imported_count++;
				}
			}

			wc_square()->log( "Square order polling completed. Imported: {$imported_count}, Skipped: {$skipped_count}", 'sync' );

			// Update last polling timestamp.
			$this->update_last_polling_time();

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
		// TODO: Implement Square Orders API search.
		// For now, we'll return an empty array as the search functionality
		// needs to be implemented using the Square Orders API search endpoint.
		
		wc_square()->log( "Searching Square orders since: {$since_time}", 'sync' );
		
		// This would use the Square Orders API search endpoint:
		// https://developer.squareup.com/reference/square/orders-api/search-orders
		
		return array();
	}

	/**
	 * Convert Square order object to array format.
	 *
	 * @since x.x.x
	 * @param \Square\Models\Order $square_order Square order object.
	 * @return array
	 */
	private function convert_square_order_to_array( $square_order ) {
		// Convert Square order object to array format for compatibility.
		return array(
			'id'       => $square_order->getId(),
			'metadata' => $square_order->getMetadata() ?? array(),
			'state'    => $square_order->getState(),
			// Add other necessary fields.
		);
	}

	/**
	 * Check if order polling is enabled.
	 *
	 * @since x.x.x
	 * @return bool
	 */
	private function is_polling_enabled() {
		$settings = wc_square()->get_settings_handler();
		return $settings->get_option( 'enable_order_polling', 'no' ) === 'yes';
	}

	/**
	 * Get polling interval.
	 *
	 * @since x.x.x
	 * @return string
	 */
	private function get_polling_interval() {
		$settings = wc_square()->get_settings_handler();
		return $settings->get_option( 'order_polling_interval', 'hourly' );
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

	/**
	 * Manual polling trigger for admin.
	 *
	 * @since x.x.x
	 * @return array Results of the polling operation.
	 */
	public static function trigger_manual_polling() {
		$poller = new self();
		
		wc_square()->log( 'Manual Square order polling triggered', 'sync' );
		
		ob_start();
		$poller->poll_square_orders();
		$output = ob_get_clean();
		
		return array(
			'success' => true,
			'message' => __( 'Manual polling completed. Check logs for details.', 'woocommerce-square' ),
			'output'  => $output,
		);
	}
}
