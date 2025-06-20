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
