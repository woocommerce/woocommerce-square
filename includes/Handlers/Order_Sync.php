<?php
/**
 * WooCommerce Square - Order Sync Handler
 *
 * @since x.x.x
 */

namespace WooCommerce\Square\Handlers;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Square\Sync\Order_Tagging;
use WooCommerce\Square\API\Webhooks\Order_Webhook;

/**
 * Handles syncing orders between WooCommerce and Square.
 *
 * @since x.x.x
 */
class Order_Sync {
	public function __construct() {
		// Initialize hooks.
		$this->init();
	}

	/**
	 * Initialize hooks.
	 *
	 * @since x.x.x
	 */
	public static function init() {
		// Initialize webhook handler for real-time sync.
		new \WooCommerce\Square\API\Webhooks\Order_Webhook();

		// Initialize polling system for fallback sync.
		new \WooCommerce\Square\Sync\Order_Polling();

		// Initialize order tagging system.
		new Order_Tagging();

		// Hook into WooCommerce order events.
		add_action( 'woocommerce_new_order', array( __CLASS__, 'maybe_new_order_sync_to_square' ) );
		add_action( 'woocommerce_update_order', array( __CLASS__, 'maybe_sync_update_to_square' ), 10, 2 );

		// Add admin hooks.
		add_action( 'admin_init', array( __CLASS__, 'maybe_register_square_webhook' ) );
		// add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_scripts' ) );
	}

	/**
	 * Sync a new WooCommerce order to Square.
	 *
	 * @since x.x.x
	 *
	 * @param int $order_id WooCommerce order ID.
	 */
	public static function maybe_new_order_sync_to_square( $order_id ) {
		return; // For now. // @TODO: remove this and handle the logic with the webhook event.

		if ( empty( $order_id ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// Don't sync orders that were imported from Square back to Square.
		if ( Order_Tagging::is_square_imported_order( $order ) ) {
			return;
		}

		// Return if the order is already synced to Square.
		if ( Order_Tagging::get_square_order_id( $order ) ) {
			return;
		}

		$location_id = wc_square()->get_settings_handler()->get_location_id();
		if ( empty( $location_id ) ) {
			wc_square()->log( __( 'Square location ID is missing. Cannot sync order.', 'woocommerce-square' ) );
			return;
		}

		try {
			// NOTE: Tagging for Woo origin is handled in Orders::set_create_order_data_with_woo_tag, called by the API.
			$settings_handler = wc_square()->get_settings_handler();
			$access_token     = $settings_handler->get_access_token();
			$is_sandbox       = $settings_handler->is_sandbox();
			$api              = new \WooCommerce\Square\Gateway\API( $access_token, $location_id, $is_sandbox );
			$response_order   = $api->create_order( $location_id, $order );

			if ( $response_order && method_exists( $response_order, 'getId' ) ) {
				$square_order_id = $response_order->getId();
				$order->update_meta_data( '_square_order_id', $square_order_id );
				$order->save();
			} else {
				wc_square()->log( __( 'Failed to sync Woo order to Square: No order ID returned.', 'woocommerce-square' ) );
			}
		} catch ( \Exception $e ) {
			wc_square()->log( sprintf( __( 'Error syncing Woo order to Square: %s', 'woocommerce-square' ), $e->getMessage() ) );
		}
	}

	/**
	 * Pull orders from Square to WooCommerce.
	 *
	 * @since x.x.x
	 */
	public static function sync_from_square() {
		// TODO: Implement logic to pull orders from Square.
	}

	/**
	 * Handle duplicate/conflicting orders.
	 *
	 * @since x.x.x
	 */
	public static function handle_sync_conflicts() {
		// TODO: Implement conflict resolution.
	}

	/**
	 * Schedule background sync jobs.
	 *
	 * @since x.x.x
	 */
	public static function schedule_sync_jobs() {
		// TODO: Implement job scheduling.
	}

	/**
	 * Sync updated WooCommerce order to Square if linked.
	 *
	 * @since x.x.x
	 *
	 * @param int       $order_id WooCommerce order ID.
	 * @param \WC_Order $order WooCommerce order object.
	 */
	public static function maybe_sync_update_to_square( $order_id, $order ) {
		return; // For now.  // @TODO: remove this and handle the logic with the webhook event.

		if ( empty( $order_id ) || ! $order instanceof \WC_Order ) {
			return;
		}

		// Don't sync orders that were imported from Square back to Square.
		if ( Order_Tagging::is_square_imported_order( $order ) ) {
			return;
		}

		// Get the Square order ID from meta or property.
		$square_order_id = $order->get_meta( '_square_order_id', true );
		if ( empty( $square_order_id ) && property_exists( $order, 'square_order_id' ) ) {
			$square_order_id = $order->square_order_id;
		}
		if ( empty( $square_order_id ) ) {
			return; // Not a synced order.
		}

		$location_id  = wc_square()->get_settings_handler()->get_location_id();
		$access_token = wc_square()->get_settings_handler()->get_access_token();
		$is_sandbox   = wc_square()->get_settings_handler()->is_sandbox();
		$api          = new \WooCommerce\Square\Gateway\API( $access_token, $location_id, $is_sandbox );

		try {
			// Retrieve the current Square order to get the version.
			$square_order = $api->retrieve_order( $square_order_id );
			if ( ! $square_order ) {
				wc_square()->log( __( 'Could not retrieve Square order for update sync.', 'woocommerce-square' ) );
				return;
			}
			// Update the Square order with the latest Woo data.
			$api->update_order( $order, $square_order );
		} catch ( \Exception $e ) {
			wc_square()->log( sprintf( __( 'Failed to sync Woo order update to Square: %s', 'woocommerce-square' ), $e->getMessage() ) );
		}
	}

	/**
	 * Maybe register webhook with Square.
	 *
	 * @since x.x.x
	 */
	public static function maybe_register_square_webhook() {
		// Only register if order sync is enabled and webhook not already registered.
		if ( ! self::is_order_sync_enabled() ) {
			// return;
		}

		$webhook_subscription_id = get_option( 'square_webhook_subscription_id' );
		$square_webhook_url      = get_option( 'square_webhook_url' );
		
		// If the webhook is already registered and the URL is the same, return.
		if ( ! empty( $webhook_subscription_id ) && $square_webhook_url === Order_Webhook::get_webhook_url() ) {
			return;
		}

		// If the webhook is in progress, return.
		if ( get_option( 'square_webhook_in_progress' ) ) {
			// Attempts count.
			$attempts = get_option( 'square_webhook_attempts', 0 );

			// If the attempts are more than 10, delete the options and return.
			if ( $attempts >= 10 ) {
				delete_option( 'square_webhook_in_progress' );
				delete_option( 'square_webhook_attempts' );
				wc_square()->log( 'Webhook registration attempts exceeded 10. Deleting options and returning.', 'webhook' );
				return;
			}

			// Increment the attempts.
			$attempts++;
			update_option( 'square_webhook_attempts', $attempts );
			return;
		}

		// Tag for in-progress.
		update_option( 'square_webhook_in_progress', true );

		// Register webhook with Square.
		$registered = Order_Webhook::register_with_square();

		if ( $registered ) {
			wc_square()->log( 'Webhook registered successfully with Square', 'webhook' );
		} else {
			wc_square()->log( 'Failed to register webhook with Square', 'webhook' );
		}

		// Delete the tag and attempts.
		delete_option( 'square_webhook_in_progress' );
		delete_option( 'square_webhook_attempts' );
	}

	/**
	 * Check if order sync is enabled.
	 *
	 * @since x.x.x
	 * @return bool
	 */
	private static function is_order_sync_enabled() {
		return get_option( 'order_sync_enabled', 'no' ) === 'yes';
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @since x.x.x
	 */
	public static function enqueue_admin_scripts() {
		$screen = get_current_screen();
		
		// Only enqueue on order-related screens.
		if ( ! $screen || ! in_array( $screen->id, array( 'edit-shop_order', 'shop_order', 'woocommerce_page_wc-orders' ), true ) ) {
			return;
		}

		// Enqueue order tagging styles.
		// Order_Tagging::enqueue_admin_styles();

		// Enqueue JavaScript for order actions.
		// wp_enqueue_script(
		//     'wc-square-order-sync',
		//     plugins_url( 'assets/js/order-sync.js', WC_SQUARE_PLUGIN_FILE ),
		//     array( 'jquery' ),
		//     WC_SQUARE_VERSION,
		//     true
		// );

		// wp_localize_script(
		//     'wc-square-order-sync',
		//     'wcSquareOrderSync',
		//     array(
		//         'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		//         'nonce'   => wp_create_nonce( 'wc-square-order-sync' ),
		//         'i18n'    => array(
		//             'confirmSync' => __( 'Are you sure you want to sync this order to Square?', 'woocommerce-square' ),
		//             'syncSuccess' => __( 'Order synced successfully!', 'woocommerce-square' ),
		//             'syncError'   => __( 'Failed to sync order. Please try again.', 'woocommerce-square' ),
		//         ),
		//     )
		// );
	}

	/**
	 * Get sync statistics.
	 *
	 * @since x.x.x
	 * @return array
	 */
	public static function get_sync_stats() {
		global $wpdb;

		$stats = array(
			'total_square_orders' => 0,
			'total_woo_orders'    => 0,
			'sync_errors'         => 0,
			'last_sync'           => null,
		);

		// Count Square imported orders.
		$square_orders = $wpdb->get_var(
			"SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} 
			WHERE meta_key = '_ordered_via_square' 
			AND meta_value = 'true'"
		);
		$stats['total_square_orders'] = (int) $square_orders;

		// Count WooCommerce orders synced to Square.
		$woo_orders = $wpdb->get_var(
			"SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} 
			WHERE meta_key = '_square_order_id' 
			AND meta_value != ''"
		);
		$stats['total_woo_orders'] = (int) $woo_orders;

		// Get last sync timestamp.
		$last_sync = $wpdb->get_var(
			"SELECT MAX(meta_value) FROM {$wpdb->postmeta} 
			WHERE meta_key = '_square_sync_timestamp'"
		);
		$stats['last_sync'] = $last_sync;

		return $stats;
	}

	/**
	 * Manual sync trigger for admin.
	 *
	 * @since x.x.x
	 * @param int $order_id WooCommerce order ID.
	 * @return bool
	 */
	public static function manual_sync_to_square( $order_id ) {
		if ( ! self::is_order_sync_enabled() ) {
			return false;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return false;
		}

		// Check if order was imported from Square.
		if ( Order_Tagging::is_square_imported_order( $order ) ) {
			wc_square()->log( "Cannot sync Square-imported order to Square: {$order_id}", 'sync' );
			return false;
		}

		// Perform sync.
		$result = self::maybe_new_order_sync_to_square( $order_id );
		
		if ( $result ) {
			$order->add_order_note( __( 'Order manually synced to Square', 'woocommerce-square' ) );
		}

		return $result;
	}

	/**
	 * Get webhook status.
	 *
	 * @since x.x.x
	 * @return array
	 */
	public static function get_webhook_status() {
		$webhook_subscription_id = get_option( 'square_webhook_subscription_id' );
		$webhook_url             = Order_Webhook::get_webhook_url();

		return array(
			'registered'         => ! empty( $webhook_subscription_id ),
			'subscription_id'    => $webhook_subscription_id,
			'square_webhook_url' => $webhook_url,
		);
	}
}
