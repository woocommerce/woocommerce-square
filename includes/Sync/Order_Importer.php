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

defined( 'ABSPATH' ) || exit;

/**
 * Order Importer Class.
 *
 * @since x.x.x
 */
class Order_Importer {

	/**
	 * Import a Square order into WooCommerce.
	 *
	 * @since x.x.x
	 * @param string $square_order_id Square order ID.
	 * @param string $import_source Source of import ('webhook', 'polling', 'manual').
	 * @return \WC_Order|false WooCommerce order object or false on failure.
	 */
	public static function import_square_order( $square_order_id, $import_source = 'unknown' ) {
		wc_square()->log( "Starting Square order import: {$square_order_id} (source: {$import_source})", 'sync' );

		if ( empty( $square_order_id ) ) {
			wc_square()->log( 'Cannot import order: empty Square order ID', 'sync' );
			return false;
		}

		try {
			// Check if this order was already imported.
			$existing_order = self::find_existing_wc_order_by_square_id( $square_order_id );
			if ( $existing_order ) {
				wc_square()->log( "Order already imported: {$square_order_id} -> WC Order #{$existing_order->get_id()}", 'sync' );
				return $existing_order;
			}

			// Get Square API instance.
			$settings_handler = wc_square()->get_settings_handler();
			$access_token     = $settings_handler->get_access_token();
			$location_id      = $settings_handler->get_location_id();
			$is_sandbox       = $settings_handler->is_sandbox();

			if ( empty( $access_token ) || empty( $location_id ) ) {
				wc_square()->log( 'Square API credentials not configured for order import', 'sync' );
				return false;
			}

			$api = new \WooCommerce\Square\Gateway\API( $access_token, $location_id, $is_sandbox );

			// 1. Fetch the order from Square API.
			$square_order = $api->retrieve_order( $square_order_id );
			if ( ! $square_order ) {
				wc_square()->log( "Failed to retrieve Square order: {$square_order_id}", 'sync' );
				return false;
			}

			// 2. Map Square order data to WooCommerce format.
			$order_data = Order_Mapper::square_to_woocommerce_data( $square_order );
			if ( ! $order_data ) {
				wc_square()->log( "Failed to map Square order data: {$square_order_id}", 'sync' );
				return false;
			}

			// 3. Create WooCommerce order from mapped data.
			$wc_order = self::create_wc_order_from_data( $order_data, $square_order );
			if ( ! $wc_order ) {
				wc_square()->log( "Failed to create WooCommerce order from Square order: {$square_order_id}", 'sync' );
				return false;
			}

			// 4. Tag the order as imported from Square.
			Order_Tagging::tag_order_as_square_imported( $wc_order, $square_order_id );

			// 5. Record the import in sync records.
			self::record_successful_import( $wc_order, $square_order_id, $import_source );

			wc_square()->log( "Order import completed: {$square_order_id} -> WC Order #{$wc_order->get_id()}", 'sync' );

			/**
			 * Fires after a Square order is successfully imported.
			 *
			 * @since x.x.x
			 * @param \WC_Order $wc_order WooCommerce order object.
			 * @param string    $square_order_id Square order ID.
			 * @param string    $import_source Import source.
			 */
			do_action( 'woocommerce_square_order_imported', $wc_order, $square_order_id, $import_source );

			return $wc_order;

		} catch ( \Exception $e ) {
			wc_square()->log( "Order import failed: {$square_order_id} - " . $e->getMessage(), 'sync' );
			
			// Record the failed import.
			self::record_failed_import( $square_order_id, $import_source, $e->getMessage() );
			
			return false;
		}
	}

	/**
	 * Check if Square order should be imported.
	 *
	 * @since x.x.x
	 * @param array $square_order_data Square order data.
	 * @return bool
	 */
	public static function should_import_order( $square_order_data ) {
		// Skip if order was created by WooCommerce.
		if ( self::is_order_from_woocommerce( $square_order_data ) ) {
			return false;
		}

		// Skip if order sync is disabled.
		if ( ! self::is_order_sync_enabled() ) {
			return false;
		}

		// Additional filtering can be added here.
		return apply_filters( 'woocommerce_square_should_import_order', true, $square_order_data );
	}

	/**
	 * Check if Square order was created by WooCommerce.
	 *
	 * @since x.x.x
	 * @param array $order_data Square order data.
	 * @return bool
	 */
	private static function is_order_from_woocommerce( $order_data ) {
		// @TODO: confirm this.
		$metadata = $order_data['metadata'] ?? array();
		return isset( $metadata['orderedViaWoo'] ) && 'true' === $metadata['orderedViaWoo'];
	}

	/**
	 * Check if order sync is enabled.
	 *
	 * @since x.x.x
	 * @return bool
	 */
	private static function is_order_sync_enabled() {
		$settings = wc_square()->get_settings_handler();
		return $settings->get_option( 'enable_order_sync', 'no' ) === 'yes';
	}

	/**
	 * Find existing WooCommerce order by Square order ID.
	 *
	 * @since x.x.x
	 * @param string $square_order_id Square order ID.
	 * @return \WC_Order|false
	 */
	private static function find_existing_wc_order_by_square_id( $square_order_id ) {
		$orders = wc_get_orders( array(
			'meta_key'     => '_square_order_id',
			'meta_value'   => $square_order_id,
			'meta_compare' => '=',
			'limit'        => 1,
		) );

		return ! empty( $orders ) ? $orders[0] : false;
	}

	/**
	 * Record successful import.
	 *
	 * @since x.x.x
	 * @param \WC_Order $wc_order WooCommerce order.
	 * @param string    $square_order_id Square order ID.
	 * @param string    $import_source Import source.
	 */
	private static function record_successful_import( $wc_order, $square_order_id, $import_source ) {
		$wc_order->add_meta_data( '_square_import_source', $import_source );
		$wc_order->add_meta_data( '_square_import_date', current_time( 'mysql' ) );
		$wc_order->save();

		// Log to sync records if table exists.
		// TODO: Implement sync records table logging.
	}

	/**
	 * Record failed import.
	 *
	 * @since x.x.x
	 * @param string $square_order_id Square order ID.
	 * @param string $import_source Import source.
	 * @param string $error_message Error message.
	 */
	private static function record_failed_import( $square_order_id, $import_source, $error_message ) {
		// Store failed import for retry.
		$failed_imports = get_option( 'wc_square_failed_imports', array() );
		$failed_imports[ $square_order_id ] = array(
			'source'        => $import_source,
			'error'         => $error_message,
			'failed_at'     => current_time( 'mysql' ),
			'retry_count'   => 0,
		);
		update_option( 'wc_square_failed_imports', $failed_imports );

		/**
		 * Fires when a Square order import fails.
		 *
		 * @since x.x.x
		 * @param string $square_order_id Square order ID.
		 * @param string $import_source Import source.
		 * @param string $error_message Error message.
		 */
		do_action( 'woocommerce_square_order_import_failed', $square_order_id, $import_source, $error_message );
	}

	/**
	 * Retry failed imports.
	 *
	 * @since x.x.x
	 * @param int $max_retries Maximum number of retries.
	 * @return array Results of retry attempts.
	 */
	public static function retry_failed_imports( $max_retries = 3 ) {
		$failed_imports = get_option( 'wc_square_failed_imports', array() );
		$results        = array();

		foreach ( $failed_imports as $square_order_id => $import_data ) {
			if ( $import_data['retry_count'] >= $max_retries ) {
				continue; // Skip if max retries reached.
			}

			$result = self::import_square_order( $square_order_id, 'retry' );
			
			if ( $result ) {
				// Success - remove from failed imports.
				unset( $failed_imports[ $square_order_id ] );
				$results[ $square_order_id ] = 'success';
			} else {
				// Still failed - increment retry count.
				$failed_imports[ $square_order_id ]['retry_count']++;
				$results[ $square_order_id ] = 'failed';
			}
		}

		update_option( 'wc_square_failed_imports', $failed_imports );
		
		return $results;
	}

	/**
	 * Create WooCommerce order from mapped data.
	 *
	 * @since x.x.x
	 * @param array                $order_data Mapped order data.
	 * @param \Square\Models\Order $square_order Original Square order object.
	 * @return \WC_Order|false WooCommerce order object or false on failure.
	 */
	private static function create_wc_order_from_data( $order_data, $square_order ) {
		try {
			// Create new WooCommerce order.
			$wc_order = wc_create_order();
			if ( ! $wc_order ) {
				return false;
			}

			// Set basic order information.
			if ( ! empty( $order_data['currency'] ) ) {
				$wc_order->set_currency( $order_data['currency'] );
			}

			if ( ! empty( $order_data['created_at'] ) ) {
				$wc_order->set_date_created( $order_data['created_at'] );
			}

			if ( ! empty( $order_data['status'] ) ) {
				$wc_order->set_status( $order_data['status'] );
			}

			// Add line items.
			self::add_line_items_to_order( $wc_order, $order_data['line_items'] ?? array() );

			// Add taxes.
			self::add_taxes_to_order( $wc_order, $order_data['taxes'] ?? array() );

			// Add discounts.
			self::add_discounts_to_order( $wc_order, $order_data['discounts'] ?? array() );

			// Add service charges.
			self::add_service_charges_to_order( $wc_order, $order_data['service_charges'] ?? array() );

			// Set customer information.
			self::set_customer_data_on_order( $wc_order, $order_data['customer'] ?? array() );

			// Set Square-specific metadata.
			self::set_square_metadata_on_order( $wc_order, $square_order );

			// Calculate totals and save.
			$wc_order->calculate_totals();
			$wc_order->save();

			return $wc_order;

		} catch ( \Exception $e ) {
			wc_square()->log( 'Error in Order_Importer::create_wc_order_from_data: ' . $e->getMessage(), 'sync' );
			return false;
		}
	}

	/**
	 * Add line items to WooCommerce order.
	 *
	 * @since x.x.x
	 * @param \WC_Order $wc_order WooCommerce order.
	 * @param array     $line_items Mapped line items data.
	 */
	private static function add_line_items_to_order( $wc_order, $line_items ) {
		foreach ( $line_items as $line_item_data ) {
			$product = self::find_matching_wc_product( $line_item_data );
			
			if ( $product ) {
				// Add existing product.
				$wc_order->add_product( $product, $line_item_data['quantity'], array(
					'subtotal' => $line_item_data['subtotal'],
					'total'    => $line_item_data['total'],
				) );
			} else {
				// Add as custom line item.
				$item = new \WC_Order_Item_Product();
				$item->set_name( $line_item_data['name'] );
				$item->set_quantity( $line_item_data['quantity'] );
				$item->set_subtotal( $line_item_data['subtotal'] );
				$item->set_total( $line_item_data['total'] );
				$wc_order->add_item( $item );
			}
		}
	}

	/**
	 * Add taxes to WooCommerce order.
	 *
	 * @since x.x.x
	 * @param \WC_Order $wc_order WooCommerce order.
	 * @param array     $taxes Mapped tax data.
	 */
	private static function add_taxes_to_order( $wc_order, $taxes ) {
		foreach ( $taxes as $tax_data ) {
			$tax_rate_id = self::get_or_create_tax_rate( $tax_data['name'], $tax_data['percentage'] );
			
			if ( $tax_rate_id ) {
				// Calculate tax amount based on order subtotal.
				$tax_amount = self::calculate_tax_amount_for_order( $wc_order, $tax_data['percentage'] );
				
				\WooCommerce\Square\Framework\Compatibility\Order_Compatibility::add_tax(
					$wc_order,
					$tax_rate_id,
					$tax_amount,
					$tax_data['shipping_amount'] ?? 0
				);
			}
		}
	}

	/**
	 * Add discounts to WooCommerce order.
	 *
	 * @since x.x.x
	 * @param \WC_Order $wc_order WooCommerce order.
	 * @param array     $discounts Mapped discount data.
	 */
	private static function add_discounts_to_order( $wc_order, $discounts ) {
		foreach ( $discounts as $discount_data ) {
			$fee = new \WC_Order_Item_Fee();
			$fee->set_name( $discount_data['name'] );
			$fee->set_amount( -$discount_data['amount'] );
			$fee->set_total( -$discount_data['amount'] );
			$wc_order->add_item( $fee );
		}
	}

	/**
	 * Add service charges to WooCommerce order.
	 *
	 * @since x.x.x
	 * @param \WC_Order $wc_order WooCommerce order.
	 * @param array     $service_charges Mapped service charge data.
	 */
	private static function add_service_charges_to_order( $wc_order, $service_charges ) {
		foreach ( $service_charges as $charge_data ) {
			$fee = new \WC_Order_Item_Fee();
			$fee->set_name( $charge_data['name'] );
			$fee->set_amount( $charge_data['amount'] );
			$fee->set_total( $charge_data['amount'] );
			$wc_order->add_item( $fee );
		}
	}

	/**
	 * Set customer data on WooCommerce order.
	 *
	 * @since x.x.x
	 * @param \WC_Order $wc_order WooCommerce order.
	 * @param array     $customer_data Mapped customer data.
	 */
	private static function set_customer_data_on_order( $wc_order, $customer_data ) {
		if ( empty( $customer_data ) ) {
			return;
		}

		// Set customer ID if available.
		if ( ! empty( $customer_data['wc_customer_id'] ) ) {
			$wc_order->set_customer_id( $customer_data['wc_customer_id'] );
		}

		// Set billing information.
		if ( ! empty( $customer_data['billing'] ) ) {
			$billing = $customer_data['billing'];
			$wc_order->set_billing_first_name( $billing['first_name'] ?? '' );
			$wc_order->set_billing_last_name( $billing['last_name'] ?? '' );
			$wc_order->set_billing_email( $billing['email'] ?? '' );
		}
	}

	/**
	 * Set Square-specific metadata on WooCommerce order.
	 *
	 * @since x.x.x
	 * @param \WC_Order            $wc_order WooCommerce order.
	 * @param \Square\Models\Order $square_order Square order object.
	 */
	private static function set_square_metadata_on_order( $wc_order, $square_order ) {
		// Set Square order ID.
		$wc_order->update_meta_data( '_square_order_id', $square_order->getId() );

		// Set Square version.
		$wc_order->update_meta_data( '_square_version', wc_square()->get_version() );

		// Set order description.
		$reference_id = $square_order->getReferenceId();
		$description = $reference_id ? "Square Order: {$reference_id}" : "Square Order: {$square_order->getId()}";
		$wc_order->update_meta_data( '_square_order_description', $description );
	}

	/**
	 * Find matching WooCommerce product for mapped line item data.
	 *
	 * @since x.x.x
	 * @param array $line_item_data Mapped line item data.
	 * @return \WC_Product|false
	 */
	private static function find_matching_wc_product( $line_item_data ) {
		// Try to match by catalog object ID if available.
		if ( ! empty( $line_item_data['catalog_object_id'] ) ) {
			$products = wc_get_products( array(
				'meta_key'     => '_square_item_id',
				'meta_value'   => $line_item_data['catalog_object_id'],
				'meta_compare' => '=',
				'limit'        => 1,
			) );
			
			if ( ! empty( $products ) ) {
				return $products[0];
			}
		}

		// Try to match by name.
		if ( ! empty( $line_item_data['name'] ) ) {
			$products = wc_get_products( array(
				'name'  => $line_item_data['name'],
				'limit' => 1,
			) );
			
			if ( ! empty( $products ) ) {
				return $products[0];
			}
		}

		return false;
	}

	/**
	 * Get or create a tax rate for Square tax.
	 *
	 * @since x.x.x
	 * @param string $tax_name Tax name.
	 * @param float  $tax_percentage Tax percentage.
	 * @return int|false Tax rate ID or false on failure.
	 */
	private static function get_or_create_tax_rate( $tax_name, $tax_percentage ) {
		// Try to find existing tax rate with same name and rate.
		$existing_rates = \WC_Tax::get_rates_for_tax_class( '' );
		
		foreach ( $existing_rates as $rate_id => $rate ) {
			if ( $rate->tax_rate_name === $tax_name && (float) $rate->tax_rate === $tax_percentage ) {
				return $rate_id;
			}
		}

		// Create new tax rate if not found.
		$tax_rate = array(
			'tax_rate_country'  => '',
			'tax_rate_state'    => '',
			'tax_rate'          => $tax_percentage,
			'tax_rate_name'     => $tax_name,
			'tax_rate_priority' => 1,
			'tax_rate_compound' => 0,
			'tax_rate_shipping' => 1,
			'tax_rate_order'    => 0,
			'tax_rate_class'    => '',
		);

		return \WC_Tax::_insert_tax_rate( $tax_rate );
	}

	/**
	 * Calculate tax amount for order based on percentage.
	 *
	 * @since x.x.x
	 * @param \WC_Order $wc_order WooCommerce order.
	 * @param float     $tax_percentage Tax percentage.
	 * @return float Tax amount.
	 */
	private static function calculate_tax_amount_for_order( $wc_order, $tax_percentage ) {
		$subtotal = 0;
		
		// Calculate subtotal from order items.
		foreach ( $wc_order->get_items() as $item ) {
			if ( $item instanceof \WC_Order_Item_Product ) {
				$subtotal += (float) $item->get_subtotal();
			}
		}
		
		// Calculate tax amount.
		return ( $subtotal * $tax_percentage ) / 100;
	}
}
