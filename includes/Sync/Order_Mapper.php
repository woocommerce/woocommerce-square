<?php
/**
 * Order Data Mapper.
 *
 * Maps data between Square and WooCommerce order formats.
 *
 * @package WooCommerce\Square\Sync
 * @since   5.0.0
 */

namespace WooCommerce\Square\Sync;

/**
 * Class Order_Mapper
 *
 * Provides static methods to map order data between Square and WooCommerce.
 *
 * @since 5.0.0
 */
class Order_Mapper {
	/**
	 * Map Square order state to WooCommerce order status.
	 *
	 * @since 5.0.0
	 * @param string $square_state Square order state.
	 * @return string WooCommerce order status.
	 */
	public static function map_square_state_to_wc_status( $square_state ) {
		$status_map = array(
			'OPEN'      => 'processing',
			'COMPLETED' => 'completed',
			'CANCELED'  => 'cancelled',
			'DRAFT'     => 'pending',
		);

		return isset( $status_map[ $square_state ] ) ? $status_map[ $square_state ] : 'processing';
	}

	/**
	 * Check if order status change is allowed.
	 *
	 * @since 5.0.0
	 * @param string $from_status Current WooCommerce status.
	 * @param string $to_status New status from Square.
	 * @return bool True if status change is allowed.
	 */
	public static function is_status_change_allowed( $from_status, $to_status ) {
		// Don't allow status changes from terminal states unless it's a specific case.
		$terminal_statuses = array( 'completed', 'cancelled', 'refunded', 'failed' );

		/**
		 * Filters the allowed status changes.
		 *
		 * @since 5.0.0
		 *
		 * @param bool $allowed_status_changes True if status change is allowed.
		 * @param string $from_status Current WooCommerce status.
		 * @param string $to_status New status from Square.
		 *
		 * @return bool True if status change is allowed.
		 */
		$allowed_status_changes = apply_filters( 'wc_square_allowed_status_changes', false, $from_status, $to_status );

		// Return early true if status change is allowed by filter.
		if ( $allowed_status_changes ) {
			return true;
		}

		if ( in_array( $from_status, $terminal_statuses, true ) ) {
			// Only allow specific transitions from terminal states.
			$allowed_terminal_transitions = array(
				'completed' => array( 'cancelled' ), // Allow cancellation of completed orders.
				'cancelled' => array(), // No transitions from cancelled.
				'refunded'  => array(), // No transitions from refunded.
				'failed'    => array( 'pending', 'processing' ), // Allow retry of failed orders.
			);

			return in_array( $to_status, $allowed_terminal_transitions[ $from_status ] ?? array(), true );
		}

		// Allow all other status changes.
		return true;
	}
}
