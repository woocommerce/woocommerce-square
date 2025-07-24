<?php
/**
 * Square Order Importer.
 *
 * Handles importing Square orders into WooCommerce.
 * Used by both webhook handlers and scheduled polling.
 *
 * @package WooCommerce\Square\Sync
 * @since x.x.x
 */

namespace WooCommerce\Square\Sync;

use WooCommerce\Square\Sync\Order_Mapper;

defined( 'ABSPATH' ) || exit;

/**
 * Class to handle importing Square orders into WooCommerce.
 *
 * @since x.x.x
 */
class Order_Importer {

	/**
	 * Update existing WooCommerce order with Square order data.
	 *
	 * @since x.x.x
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
				$wc_order->add_order_note( sprintf( 
					__( 'Order status updated from Square. Square state: %s', 'woocommerce-square' ), 
					$square_state 
				) );
			} else {
				wc_square()->log( sprintf( 
					'Status change not allowed: %s → %s for order %d', 
					$wc_status, 
					$new_wc_status, 
					$wc_order->get_id() 
				), 'sync' );
			}
		}

		// 2. Update fulfillment status if Square order is completed.
		if ( 'COMPLETED' === $square_state && ! in_array( $wc_status, array( 'completed', 'cancelled', 'refunded' ) ) ) {
			$wc_order->set_status( 'completed' );
			$updates_made[] = 'Marked as completed (fulfillment done in Square)';

			$wc_order->add_order_note( 
				__( 'Order marked as completed - fulfillment completed in Square Dashboard/POS.', 'woocommerce-square' )
			);
		}

		// 3. Update order total if changed (with safety checks).
		if ( $square_total ) {
			$square_total_amount = $square_total->getAmount() / 100;
			$current_total = (float) $wc_order->get_total();

			if ( abs( $current_total - $square_total_amount ) > 0.01 ) { // Allow for rounding differences.
				// Only update total if order is not paid yet or if it's a refund scenario.
				if ( ! $wc_order->is_paid() || $square_total_amount < $current_total ) {
					$wc_order->set_total( $square_total_amount );
					$updates_made[] = sprintf( 'Total: %s → %s', $current_total, $square_total_amount );
				}
			}
		}

		// 4. Update modification timestamps
		$wc_order->update_meta_data( '_square_last_updated', $square_updated_at );
		$wc_order->update_meta_data( '_square_sync_date', current_time( 'mysql' ) );
		$wc_order->update_meta_data( '_square_sync_status', 'updated' );

		// 5. Update fulfillment data from Square
		$this->update_fulfillment_data( $wc_order, $square_order );

		// Save changes if any updates were made
		if ( ! empty( $updates_made ) ) {
			$wc_order->save();

			return array(
				'updated' => true,
				'message' => 'Updated: ' . implode( ', ', $updates_made ),
				'changes' => $updates_made
			);
		}

		// Update sync timestamp even if no changes
		$wc_order->update_meta_data( '_square_last_checked', current_time( 'mysql' ) );
		$wc_order->save_meta_data();

		return array(
			'updated' => false,
			'message' => 'No changes detected',
			'changes' => array()
		);
	}
}
