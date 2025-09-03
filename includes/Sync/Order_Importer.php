<?php
/**
 * Square Order Importer.
 *
 * Handles importing Square orders into WooCommerce.
 * Used by both webhook handlers and scheduled polling.
 *
 * @package WooCommerce\Square\Sync
 * @since 5.0.0
 */

namespace WooCommerce\Square\Sync;

use WooCommerce\Square\Sync\Order_Mapper;
use WooCommerce\Square\Utilities\Money_Utility;
use Automattic\WooCommerce\Utilities\OrderUtil;

defined( 'ABSPATH' ) || exit;

/**
 * Class to handle importing Square orders into WooCommerce.
 *
 * @since 5.0.0
 */
class Order_Importer {

	/**
	 * Update existing WooCommerce order with Square order data.
	 *
	 * @since 5.0.0
	 * @param \WC_Order $wc_order Existing WooCommerce order.
	 * @param \Square\Models\Order $square_order Square order object.
	 * @return array Update result with 'updated' flag and message.
	 */
	public function update_existing_woocommerce_order( $wc_order, $square_order ) {
		$updates_made = array();

		// Get Square order details.
		$square_state      = $square_order->getState();
		$square_updated_at = $square_order->getUpdatedAt();
		$square_total      = $square_order->getTotalMoney();

		// 1. Update order status based on Square state.
		$wc_status     = $wc_order->get_status();
		$new_wc_status = Order_Mapper::map_square_state_to_wc_status( $square_state );
		if ( $wc_status !== $new_wc_status ) {
			// Check if status change is allowed.
			if ( Order_Mapper::is_status_change_allowed( $wc_status, $new_wc_status ) ) {
				$wc_order->set_status( $new_wc_status );
				$updates_made[] = sprintf( 'Status: %s → %s', $wc_status, $new_wc_status );

				// Add order note for status change.
				$wc_order->add_order_note(
					sprintf(
						/* translators: %s is the Square state. */
						__( 'Order status updated from Square. Square state: %s', 'woocommerce-square' ),
						$square_state
					)
				);
			} else {
				// @todo: move this to a separate order sync status page if exists.
				wc_square()->log(
					sprintf(
						'Status change not allowed: %s → %s for order %d',
						$wc_status,
						$new_wc_status,
						$wc_order->get_id()
					),
					'sync'
				);
			}
		}

		// 2. Add order note if fulfillment completed.
		if ( 'COMPLETED' === $square_state ) {
			$wc_order->add_order_note(
				__( 'Fulfillment completed in Square Dashboard/POS.', 'woocommerce-square' )
			);
		}

		// 3. Update order total if changed (with safety checks).
		if ( $square_total ) {
			$currency            = $square_total->getCurrency();
			$square_total_amount = Money_Utility::cents_to_float( $square_total->getAmount(), $currency );
			$current_total       = (float) $wc_order->get_total();
			$decimals            = Money_Utility::get_currency_decimals( $currency );

			$square_total_rounded  = round( $square_total_amount, $decimals );
			$current_total_rounded = round( $current_total, $decimals );

			if ( $square_total_rounded !== $current_total_rounded ) {
				// Only update total if order is not paid yet or if it's a refund scenario.
				if ( ! $wc_order->is_paid() || $square_total_rounded < $current_total_rounded ) {
					$wc_order->set_total( $square_total_rounded );
					$updates_made[] = sprintf( 'Total: %s → %s', $current_total, $square_total_rounded );
				}
			}
		}

		// 4. Update modification timestamps
		$wc_order->update_meta_data( '_square_last_updated', $square_updated_at );
		$wc_order->update_meta_data( '_square_sync_date', current_time( 'mysql' ) );
		$wc_order->update_meta_data( '_square_sync_status', 'updated' );

		// 5. Update fulfillment data from Square
		$this->maybe_update_fulfillment_data( $wc_order, $square_order );

		// Save changes if any updates were made
		if ( ! empty( $updates_made ) ) {
			$wc_order->save();

			return array(
				'updated' => true,
				'message' => 'Updated: ' . implode( ', ', $updates_made ),
				'changes' => $updates_made,
			);
		}

		// Update sync timestamp even if no changes
		$wc_order->update_meta_data( '_square_last_checked', current_time( 'mysql' ) );
		$wc_order->save_meta_data();

		return array(
			'updated' => false,
			'message' => 'No changes detected',
			'changes' => array(),
		);
	}

	/**
	 * Update fulfillment data from Square order.
	 *
	 * @since 5.0.0
	 * @param \WC_Order $wc_order WooCommerce order.
	 * @param \Square\Models\Order $square_order Square order.
	 */
	private function maybe_update_fulfillment_data( $wc_order, $square_order ) {
		$fulfillments = $square_order->getFulfillments();

		if ( empty( $fulfillments ) ) {
			return;
		}

		foreach ( $fulfillments as $fulfillment ) {
			$fulfillment_state = $fulfillment->getState();
			$fulfillment_type  = $fulfillment->getType();

			// Update fulfillment meta.
			$wc_order->update_meta_data( '_square_fulfillment_state', $fulfillment_state );
			$wc_order->update_meta_data( '_square_fulfillment_type', $fulfillment_type );

			// Handle shipment details.
			$shipment_details = $fulfillment->getShipmentDetails();
			if ( $shipment_details ) {
				$tracking_number = $shipment_details->getTrackingNumber();
				$tracking_url    = $shipment_details->getTrackingUrl();
				$carrier         = $shipment_details->getCarrier();

				if ( $tracking_number ) {
					$wc_order->update_meta_data( '_square_tracking_number', $tracking_number );
				}
				if ( $tracking_url ) {
					$wc_order->update_meta_data( '_square_tracking_url', $tracking_url );
				}
				if ( $carrier ) {
					$wc_order->update_meta_data( '_square_carrier', $carrier );
				}
			}

			// Add order note if fulfillment completed.
			if ( 'COMPLETED' === $fulfillment_state ) {
				$note = __( 'Order fulfillment completed in Square.', 'woocommerce-square' );
				if ( $tracking_number ) {
					// translators: %s is the tracking number.
					$note .= sprintf( __( ' Tracking: %s', 'woocommerce-square' ), $tracking_number );
				}
				$wc_order->add_order_note( $note );
			}

			// Handle pickup details.
			$pickup_details = $fulfillment->getPickupDetails();
			if ( $pickup_details ) {
				$pickup_at     = $pickup_details->getPickupAt();
				$schedule_type = $pickup_details->getScheduleType();

				if ( $pickup_at ) {
					$wc_order->update_meta_data( '_square_pickup_time', $pickup_at );
				}
				if ( $schedule_type ) {
					$wc_order->update_meta_data( '_square_pickup_schedule', $schedule_type );
				}
			}

			// Add order note if pickup completed.
			if ( 'COMPLETED' === $fulfillment_state ) {
				$wc_order->add_order_note(
					__( 'Order pickup completed in Square.', 'woocommerce-square' )
				);
			}
		}
	}

	/**
	 * Find existing WooCommerce order by Square order ID.
	 *
	 * @since 5.0.0
	 * @param string $square_order_id Square order ID.
	 * @return \WC_Order|false
	 */
	public function find_existing_wc_order_by_square_order_id( $square_order_id ) {
		global $wpdb;

		// Check if HPOS is enabled.
		if ( class_exists( OrderUtil::class ) && OrderUtil::custom_orders_table_usage_is_enabled() ) {
			// HPOS: Query wc_orders_meta table.
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"
					SELECT order_id, meta_key
					FROM {$wpdb->prefix}wc_orders_meta
					WHERE meta_value = %s
					AND meta_key LIKE %s
					LIMIT 1
					",
					$square_order_id,
					'%_square_order_id'
				),
				ARRAY_A
			);
		} else {
			// Traditional: Query wp_postmeta table.
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"
					SELECT p.ID as order_id, pm.meta_key
					FROM {$wpdb->posts} p
					INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
					WHERE pm.meta_value = %s
					AND pm.meta_key LIKE %s
					AND p.post_type = 'shop_order'
					LIMIT 1
					",
					$square_order_id,
					'%_square_order_id'
				),
				ARRAY_A
			);
		}

		if ( ! empty( $results ) ) {
			$order = wc_get_order( $results[0]['order_id'] );
			if ( $order instanceof \WC_Order ) {
				return $order;
			}
		}

		return false;
	}
}
