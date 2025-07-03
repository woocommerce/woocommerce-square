<?php
/**
 * Field Mapping Reference.
 *
 * Comprehensive documentation of field mappings between Square and WooCommerce orders.
 * This file serves as a reference for developers and documents all implemented mappings.
 *
 * @since x.x.x
 */

namespace WooCommerce\Square\Sync;

/**
 * Class Field_Mapping_Reference
 *
 * Documentation and reference for all field mappings between Square and WooCommerce.
 *
 * @since x.x.x
 */
class Field_Mapping_Reference {

	/**
	 * Order Status Mapping: Square ↔ WooCommerce
	 *
	 * @since x.x.x
	 * @return array
	 */
	public static function get_order_status_mappings() {
		return array(
			'square_to_wc' => array(
				'OPEN'      => 'processing', // Order is open and can be updated
				'COMPLETED' => 'completed',  // Order is fully paid and fulfilled
				'CANCELED'  => 'cancelled',  // Order was canceled
				'DRAFT'     => 'pending',    // Order is in draft state (Beta)
			),
			'wc_to_square' => array(
				'pending'    => 'OPEN',      // Payment not received yet
				'processing' => 'OPEN',      // Payment received, order being processed
				'on-hold'    => 'OPEN',      // Awaiting payment confirmation
				'completed'  => 'COMPLETED', // Order fulfilled and complete
				'cancelled'  => 'CANCELED',  // Order was canceled
				'refunded'   => 'CANCELED',  // Order fully refunded (Square doesn't have refunded state)
				'failed'     => 'CANCELED',  // Payment failed (Square doesn't have failed state)
				'draft'      => 'DRAFT',     // Draft order (if supported)
			),
		);
	}

	/**
	 * Core Order Fields Mapping: Square ↔ WooCommerce
	 *
	 * @since x.x.x
	 * @return array
	 */
	public static function get_core_order_fields_mapping() {
		return array(
			'wc_field'                => 'square_field',
			'ID'                      => 'id',
			'order_key'               => 'reference_id',
			'status'                  => 'state',
			'currency'                => 'total_money.currency',
			'total'                   => 'total_money.amount', // Amount in cents (Square)
			'date_created'            => 'created_at',
			'date_modified'           => 'updated_at',
			'date_completed'          => 'closed_at',
			'customer_id'             => 'customer_id',
			'customer_note'           => 'metadata.customer_note',
			'payment_method'          => 'source.name',
			'transaction_id'          => 'metadata.transaction_id',
			'tax_total'               => 'total_tax_money.amount',
			'discount_total'          => 'total_discount_money.amount',
			'shipping_total'          => 'service_charges[].applied_money.amount',
		);
	}

	/**
	 * Customer/Address Fields Mapping: Square ↔ WooCommerce
	 *
	 * @since x.x.x
	 * @return array
	 */
	public static function get_customer_address_fields_mapping() {
		return array(
			'billing_first_name'  => 'fulfillments[].pickup_details.recipient.display_name', // Split from display_name
			'billing_last_name'   => 'fulfillments[].pickup_details.recipient.display_name', // Split from display_name
			'billing_company'     => 'metadata.billing_company',
			'billing_address_1'   => 'fulfillments[].pickup_details.recipient.address.address_line_1',
			'billing_address_2'   => 'fulfillments[].pickup_details.recipient.address.address_line_2',
			'billing_city'        => 'fulfillments[].pickup_details.recipient.address.locality',
			'billing_state'       => 'fulfillments[].pickup_details.recipient.address.administrative_district_level_1',
			'billing_postcode'    => 'fulfillments[].pickup_details.recipient.address.postal_code',
			'billing_country'     => 'fulfillments[].pickup_details.recipient.address.country',
			'billing_email'       => 'metadata.billing_email',
			'billing_phone'       => 'fulfillments[].pickup_details.recipient.phone_number',
			'shipping_first_name' => 'fulfillments[].shipment_details.recipient.display_name',
			'shipping_last_name'  => 'fulfillments[].shipment_details.recipient.display_name',
			'shipping_company'    => 'fulfillments[].shipment_details.recipient.address.company',
			'shipping_address_1'  => 'fulfillments[].shipment_details.recipient.address.address_line_1',
			'shipping_address_2'  => 'fulfillments[].shipment_details.recipient.address.address_line_2',
			'shipping_city'       => 'fulfillments[].shipment_details.recipient.address.locality',
			'shipping_state'      => 'fulfillments[].shipment_details.recipient.address.administrative_district_level_1',
			'shipping_postcode'   => 'fulfillments[].shipment_details.recipient.address.postal_code',
			'shipping_country'    => 'fulfillments[].shipment_details.recipient.address.country',
			'shipping_phone'      => 'fulfillments[].shipment_details.recipient.phone_number',
		);
	}

	/**
	 * Line Items Fields Mapping: Square ↔ WooCommerce
	 *
	 * @since x.x.x
	 * @return array
	 */
	public static function get_line_items_fields_mapping() {
		return array(
			'name'         => 'line_items[].name',
			'quantity'     => 'line_items[].quantity', // Quantity as string in Square
			'total'        => 'line_items[].total_money.amount', // Total in cents
			'subtotal'     => 'line_items[].gross_sales_money.amount', // Subtotal in cents
			'product_id'   => 'line_items[].catalog_object_id',
			'variation_id' => 'line_items[].variation_name',
			'sku'          => 'metadata.sku',
			'price'        => 'line_items[].base_price_money.amount', // Unit price in cents
		);
	}

	/**
	 * Tax Fields Mapping: Square ↔ WooCommerce
	 *
	 * @since x.x.x
	 * @return array
	 */
	public static function get_tax_fields_mapping() {
		return array(
			'tax_total'                => 'total_tax_money.amount', // Total tax in cents
			'tax_lines[].label'        => 'taxes[].name',
			'tax_lines[].rate_percent' => 'taxes[].percentage',
			'tax_lines[].total'        => 'taxes[].applied_money.amount', // Applied tax amount
		);
	}

	/**
	 * Discount Fields Mapping: Square ↔ WooCommerce
	 *
	 * @since x.x.x
	 * @return array
	 */
	public static function get_discount_fields_mapping() {
		return array(
			'discount_total'          => 'total_discount_money.amount', // Total discount in cents
			'coupon_lines[].code'     => 'discounts[].name',
			'coupon_lines[].discount' => 'discounts[].applied_money.amount', // Discount amount
			'coupon_lines[].type'     => 'discounts[].type', // FIXED_AMOUNT or FIXED_PERCENTAGE
		);
	}

	/**
	 * Shipping Fields Mapping: Square ↔ WooCommerce
	 *
	 * @since x.x.x
	 * @return array
	 */
	public static function get_shipping_fields_mapping() {
		return array(
			'shipping_total'                 => 'service_charges[].applied_money.amount', // Shipping as service charge
			'shipping_method'                => 'fulfillments[].type', // PICKUP, SHIPMENT, DELIVERY
			'shipping_lines[].method_title'  => 'fulfillments[].shipment_details.carrier',
			'shipping_lines[].total'         => 'service_charges[].applied_money.amount',
		);
	}

	/**
	 * Fulfillment Fields Mapping: Square ↔ WooCommerce
	 *
	 * @since x.x.x
	 * @return array
	 */
	public static function get_fulfillment_fields_mapping() {
		return array(
			'date_shipped'     => 'fulfillments[].shipment_details.shipped_at',
			'tracking_number'  => 'fulfillments[].shipment_details.tracking_number',
			'tracking_url'     => 'fulfillments[].shipment_details.tracking_url',
			'pickup_date'      => 'fulfillments[].pickup_details.pickup_at',
			'delivery_date'    => 'fulfillments[].delivery_details.deliver_at',
		);
	}

	/**
	 * WooCommerce Order Meta Fields (implemented in Order_Tagging class)
	 *
	 * @since x.x.x
	 * @return array
	 */
	public static function get_wc_order_meta_fields() {
		return array(
			'_square_order_id'           => 'Square order reference',
			'_square_sync_status'        => 'Current sync status',
			'_ordered_via_square'        => 'Boolean flag for Square-originated orders',
			'_square_fulfillment_id'     => 'Square fulfillment reference',
			'_square_sync_last_attempt'  => 'Timestamp of last sync attempt',
			'_square_sync_error'         => 'Error message if sync failed',
			'_square_location_id'        => 'Square location',
			'_square_version'            => 'Square plugin version',
			'_square_import_source'      => 'Import source (webhook, polling, manual)',
			'_square_import_date'        => 'Import date',
			'_square_reference_id'       => 'Square reference ID',
			'_square_sync_timestamp'     => 'Sync timestamp',
		);
	}

	/**
	 * Square Order Metadata Fields (for WooCommerce orders synced to Square)
	 *
	 * @since x.x.x
	 * @return array
	 */
	public static function get_square_metadata_fields() {
		return array(
			'orderedViaWoo'    => 'Order origin tracking',
			'wooOrderId'       => 'Cross-reference IDs',
			'wooOrderKey'      => 'WooCommerce order key',
			'syncVersion'      => 'Square plugin version',
			'syncTimestamp'    => 'Sync timestamp',
			'billing_company'  => 'Billing company',
			'billing_email'    => 'Billing email',
			'customer_note'    => 'Customer note',
			'payment_method'   => 'Payment method',
		);
	}

	/**
	 * Order Sync Status Constants
	 *
	 * @since x.x.x
	 * @return array
	 */
	public static function get_sync_status_constants() {
		return array(
			'pending'   => 'Sync operation pending',
			'completed' => 'Sync operation completed successfully',
			'failed'    => 'Sync operation failed',
			'syncing'   => 'Sync operation in progress',
			'conflict'  => 'Sync conflict detected',
		);
	}

	/**
	 * Data Type Conversion Methods
	 *
	 * @since x.x.x
	 * @return array
	 */
	public static function get_data_conversion_methods() {
		return array(
			'money_conversion' => array(
				'square_to_wc' => 'Square uses cents (integer), WooCommerce uses decimal',
				'method'       => 'square_to_wc_money( $square_amount ) { return $square_amount / 100; }',
				'reverse'      => 'wc_to_square_money( $wc_amount ) { return (int) round( $wc_amount * 100 ); }',
			),
			'datetime_conversion' => array(
				'square_to_wc' => 'Square uses RFC 3339, WooCommerce uses MySQL datetime',
				'method'       => 'square_to_wc_datetime( $square_datetime ) { return date( "Y-m-d H:i:s", strtotime( $square_datetime ) ); }',
				'reverse'      => 'wc_to_square_datetime( $wc_datetime ) { return date( "c", strtotime( $wc_datetime ) ); }',
			),
			'address_mapping' => array(
				'description' => 'Square has structured address object',
				'method'      => 'map_wc_address_to_square( $wc_order, $type = "billing" )',
				'structure'   => array(
					'address_line_1'                     => 'billing_address_1',
					'address_line_2'                     => 'billing_address_2',
					'locality'                           => 'billing_city',
					'administrative_district_level_1'    => 'billing_state',
					'postal_code'                        => 'billing_postcode',
					'country'                            => 'billing_country',
				),
			),
		);
	}

	/**
	 * Fulfillment State Mapping: WooCommerce Status ↔ Square Fulfillment State
	 *
	 * @since x.x.x
	 * @return array
	 */
	public static function get_fulfillment_state_mapping() {
		return array(
			'pending'    => 'PROPOSED',
			'processing' => 'RESERVED',
			'on-hold'    => 'PROPOSED',
			'completed'  => 'COMPLETED',
			'cancelled'  => 'CANCELED',
			'refunded'   => 'CANCELED',
			'failed'     => 'FAILED',
		);
	}

	/**
	 * Square Fulfillment Types
	 *
	 * @since x.x.x
	 * @return array
	 */
	public static function get_square_fulfillment_types() {
		return array(
			'PICKUP'   => 'Customer picks up the order at a physical location',
			'SHIPMENT' => 'A shipping carrier ships the fulfillment to a recipient',
			'DELIVERY' => 'A courier delivers the fulfillment to the recipient',
		);
	}

	/**
	 * Implementation Classes and Methods Reference
	 *
	 * @since x.x.x
	 * @return array
	 */
	public static function get_implementation_reference() {
		return array(
			'Order_Mapper' => array(
				'file'        => 'includes/Sync/Order_Mapper.php',
				'description' => 'Main data mapping between Square and WooCommerce order formats',
				'methods'     => array(
					'square_to_woocommerce_data()'      => 'Converts Square order to WooCommerce data array',
					'woocommerce_to_square_data()'      => 'Converts WooCommerce order to Square data array',
					'map_square_line_items()'           => 'Maps Square line items to WooCommerce format',
					'map_wc_line_items_to_square()'     => 'Maps WooCommerce line items to Square format',
					'map_square_taxes()'                => 'Maps Square taxes to WooCommerce format',
					'map_wc_taxes_to_square()'          => 'Maps WooCommerce taxes to Square format',
					'map_square_discounts()'            => 'Maps Square discounts to WooCommerce format',
					'map_wc_discounts_to_square()'      => 'Maps WooCommerce discounts to Square format',
					'map_square_fulfillment_data()'     => 'Maps Square fulfillment data',
					'map_wc_fulfillment_to_square()'    => 'Maps WooCommerce fulfillment to Square',
					'square_to_wc_money()'              => 'Converts Square cents to WooCommerce decimal',
					'wc_to_square_money()'              => 'Converts WooCommerce decimal to Square cents',
					'square_to_wc_datetime()'           => 'Converts Square RFC 3339 to MySQL datetime',
					'wc_to_square_datetime()'           => 'Converts MySQL datetime to RFC 3339',
				),
			),
			'Order_Tagging' => array(
				'file'        => 'includes/Sync/Order_Tagging.php',
				'description' => 'Order tagging and metadata management',
				'methods'     => array(
					'tag_order_as_square_imported()'        => 'Tags WooCommerce order as imported from Square',
					'tag_order_as_synced_to_square()'       => 'Tags WooCommerce order as synced to Square',
					'update_order_sync_status()'            => 'Updates order sync status',
					'set_square_metadata()'                 => 'Sets Square metadata on order',
					'is_square_imported_order()'            => 'Checks if order was imported from Square',
					'is_order_synced_to_square()'           => 'Checks if order was synced to Square',
					'get_square_order_id()'                 => 'Gets Square order ID from WooCommerce order',
					'get_all_square_metadata()'             => 'Gets all Square metadata from order',
					'generate_square_metadata_for_wc_order()' => 'Generates Square metadata for WooCommerce order',
				),
				'constants'   => array(
					'WC_META_SQUARE_ORDER_ID'           => '_square_order_id',
					'WC_META_SQUARE_SYNC_STATUS'        => '_square_sync_status',
					'WC_META_ORDERED_VIA_SQUARE'        => '_ordered_via_square',
					'SQUARE_META_ORDERED_VIA_WOO'       => 'orderedViaWoo',
					'SQUARE_META_WOO_ORDER_ID'          => 'wooOrderId',
					'SYNC_STATUS_PENDING'               => 'pending',
					'SYNC_STATUS_COMPLETED'             => 'completed',
					'SYNC_STATUS_FAILED'                => 'failed',
				),
			),
		);
	}

	/**
	 * Usage Examples
	 *
	 * @since x.x.x
	 * @return array
	 */
	public static function get_usage_examples() {
		return array(
			'converting_square_order_to_wc' => array(
				'description' => 'Convert a Square order to WooCommerce data',
				'code'        => '
					$square_order = $api->retrieve_order( $square_order_id );
					$order_data = Order_Mapper::square_to_woocommerce_data( $square_order );
					$wc_order = self::create_wc_order_from_data( $order_data, $square_order );
				',
			),
			'converting_wc_order_to_square' => array(
				'description' => 'Convert a WooCommerce order to Square data',
				'code'        => '
					$wc_order = wc_get_order( $order_id );
					$square_data = Order_Mapper::woocommerce_to_square_data( $wc_order );
					$api->create_order( $location_id, $square_data );
				',
			),
			'tagging_imported_order' => array(
				'description' => 'Tag an order as imported from Square',
				'code'        => '
					Order_Tagging::tag_order_as_square_imported( $wc_order, $square_order_id, "webhook" );
				',
			),
			'checking_order_source' => array(
				'description' => 'Check if an order was imported from Square',
				'code'        => '
					if ( Order_Tagging::is_square_imported_order( $order ) ) {
						// Handle Square-imported order
					}
				',
			),
			'money_conversion' => array(
				'description' => 'Convert between Square cents and WooCommerce decimal',
				'code'        => '
					// Square to WooCommerce
					$wc_amount = Order_Mapper::square_to_wc_money( 1599 ); // $15.99
					
					// WooCommerce to Square
					$square_amount = Order_Mapper::wc_to_square_money( 15.99 ); // 1599
				',
			),
		);
	}

	/**
	 * Best Practices and Guidelines
	 *
	 * @since x.x.x
	 * @return array
	 */
	public static function get_best_practices() {
		return array(
			'data_integrity' => array(
				'Always validate data before mapping',
				'Use try-catch blocks for error handling',
				'Log mapping errors for debugging',
				'Verify required fields are present',
			),
			'performance' => array(
				'Cache frequently accessed mapping data',
				'Use batch operations when possible',
				'Avoid unnecessary API calls during mapping',
				'Optimize database queries for metadata',
			),
			'maintenance' => array(
				'Keep mapping constants in sync with API changes',
				'Document any custom mapping logic',
				'Test mappings with real-world data',
				'Monitor for mapping failures in production',
			),
			'error_handling' => array(
				'Gracefully handle missing fields',
				'Provide fallback values where appropriate',
				'Log detailed error information',
				'Implement retry mechanisms for transient failures',
			),
		);
	}
} 