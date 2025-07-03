<?php
/**
 * Multiple Payment Methods Reference.
 *
 * Explains how the WooCommerce Square plugin handles multiple payment methods
 * and how this differs from fulfillment splitting for order sync implementation.
 *
 * @since x.x.x
 */

namespace WooCommerce\Square\Sync;

/**
 * Class Multiple_Payment_Methods_Reference
 *
 * Documentation for multiple payment methods vs fulfillment splitting.
 *
 * @since x.x.x
 */
class Multiple_Payment_Methods_Reference {

	/**
	 * Explains the difference between fulfillment splitting and multiple payment methods.
	 *
	 * @since x.x.x
	 * @return array
	 */
	public static function get_concept_explanation() {
		return array(
			'fulfillment_splitting' => array(
				'definition'    => 'Dividing a single order into multiple shipments/deliveries based on line items',
				'api_support'   => 'NOT SUPPORTED by Square API',
				'example'       => array(
					'scenario' => 'Order with 3 items shipped separately',
					'items'    => array(
						'Item A (In Stock)' => 'Ship today',
						'Item B (Backorder)' => 'Ship next week',
						'Item C (Different warehouse)' => 'Ship separately',
					),
					'problem'  => 'Would require 3 OrderFulfillment objects for same order',
					'square_limitation' => 'Square API only allows 1 OrderFulfillment per order',
				),
				'workaround'    => 'Handle in WooCommerce only, sync final fulfillment status to Square',
			),
			'multiple_payment_methods' => array(
				'definition'    => 'Paying for a single order using multiple payment sources',
				'api_support'   => 'FULLY SUPPORTED by Square API',
				'example'       => array(
					'scenario' => 'Order paid with Gift Card + Credit Card',
					'breakdown' => array(
						'Order Total' => '$100',
						'Gift Card'   => '$30',
						'Credit Card' => '$70',
					),
					'result'   => 'One order with multiple payment/tender records',
				),
				'plugin_support' => 'Already implemented in WooCommerce Square plugin',
			),
		);
	}

	/**
	 * How the existing plugin handles multiple payment methods.
	 *
	 * @since x.x.x
	 * @return array
	 */
	public static function get_existing_plugin_implementation() {
		return array(
			'payment_flow' => array(
				'step_1' => 'Customer selects Gift Card + Credit Card',
				'step_2' => 'Plugin creates Square order',
				'step_3' => 'Plugin processes Gift Card payment first',
				'step_4' => 'Plugin processes Credit Card payment for remaining amount',
				'step_5' => 'Plugin calls pay_order() API to link payments to order',
				'step_6' => 'Order marked as paid with multiple tender records',
			),
			'key_methods' => array(
				'API::pay_order()' => array(
					'file'        => 'includes/Gateway/API.php:518',
					'description' => 'Performs payments when transaction uses multiple payment methods',
					'parameters'  => array(
						'$payment_ids' => 'Array of payment IDs (Gift Card + Credit Card)',
						'$order_id'    => 'Square order ID',
					),
					'returns'     => 'Create_PayOrder response with all tender details',
				),
				'Orders::set_pay_order_data()' => array(
					'file'        => 'includes/Gateway/API/Requests/Orders.php:172',
					'description' => 'Sets request data for multiple payment methods',
					'api_call'    => 'PayOrderRequest with payment IDs array',
				),
				'Order::is_tender_type_*()' => array(
					'file'        => 'includes/Handlers/Order.php:695-720',
					'methods'     => array(
						'is_tender_type_card()' => 'Check if order used Square credit card',
						'is_tender_type_gift_card()' => 'Check if order used Square gift card',
						'is_tender_type_cash_app_pay()' => 'Check if order used Cash App Pay',
					),
				),
			),
			'order_meta_tracking' => array(
				'is_tender_type_card'         => 'Boolean: Order used credit card',
				'is_tender_type_gift_card'    => 'Boolean: Order used gift card',
				'is_tender_type_cash_app_wallet' => 'Boolean: Order used Cash App Pay',
				'gift_card_charged_amount'    => 'Amount charged to gift card',
				'gift_card_partial_total'     => 'Gift card portion of split payment',
				'other_gateway_partial_total' => 'Credit card/Cash App portion',
				'charge_type'                 => 'CHARGE_TYPE_PARTIAL for split payments',
			),
		);
	}

	/**
	 * How multiple payment methods appear in Square API.
	 *
	 * @since x.x.x
	 * @return array
	 */
	public static function get_square_api_structure() {
		return array(
			'order_object' => array(
				'id'           => 'SQUARE_ORDER_ID',
				'location_id'  => 'LOCATION_ID',
				'total_money'  => array(
					'amount'   => 10000, // $100.00 in cents
					'currency' => 'USD',
				),
				'tenders'      => array(
					array(
						'id'           => 'GIFT_CARD_TENDER_ID',
						'type'         => 'SQUARE_GIFT_CARD',
						'amount_money' => array(
							'amount'   => 3000, // $30.00 in cents
							'currency' => 'USD',
						),
						'gift_card_details' => array(
							'status' => 'CAPTURED',
						),
					),
					array(
						'id'           => 'CREDIT_CARD_TENDER_ID',
						'type'         => 'CARD',
						'amount_money' => array(
							'amount'   => 7000, // $70.00 in cents
							'currency' => 'USD',
						),
						'card_details' => array(
							'status' => 'CAPTURED',
							'card'   => array(
								'last_4' => '1111',
							),
						),
					),
				),
				'payment_ids'  => array(
					'GIFT_CARD_PAYMENT_ID',
					'CREDIT_CARD_PAYMENT_ID',
				),
			),
		);
	}

	/**
	 * How to handle multiple payment methods in order sync.
	 *
	 * @since x.x.x
	 * @return array
	 */
	public static function get_order_sync_considerations() {
		return array(
			'square_to_wc_sync' => array(
				'challenge' => 'WooCommerce orders typically have one payment method',
				'solution'  => array(
					'primary_payment_method' => 'Use the payment method with highest amount',
					'store_split_details'    => 'Store all tender details in order meta',
					'order_notes'           => 'Add note explaining split payment',
					'custom_display'        => 'Show split payment in admin order view',
				),
				'mapping_strategy' => array(
					'step_1' => 'Identify all tenders from Square order',
					'step_2' => 'Determine primary payment method (highest amount)',
					'step_3' => 'Set WooCommerce payment method to primary',
					'step_4' => 'Store all tender details in order meta',
					'step_5' => 'Add order note with payment breakdown',
				),
			),
			'wc_to_square_sync' => array(
				'challenge' => 'WooCommerce split payments need to be represented in Square',
				'solution'  => array(
					'check_existing_split' => 'Detect if WooCommerce order has split payment meta',
					'preserve_structure'   => 'Maintain split payment structure in Square',
					'payment_method_mapping' => 'Map WooCommerce payment methods to Square tender types',
				),
				'implementation' => array(
					'detect_split' => 'Check for charge_type === CHARGE_TYPE_PARTIAL',
					'extract_details' => 'Get gift_card_partial_total and other_gateway_partial_total',
					'create_square_payments' => 'Create separate payments for each method',
					'link_to_order' => 'Use pay_order() API to link all payments',
				),
			),
		);
	}

	/**
	 * Order meta fields used for multiple payment method tracking.
	 *
	 * @since x.x.x
	 * @return array
	 */
	public static function get_multiple_payment_meta_fields() {
		return array(
			'detection_fields' => array(
				'charge_type' => array(
					'values' => array(
						'CHARGE_TYPE_PARTIAL' => 'Order paid with multiple methods',
						'CHARGE_TYPE_FULL'    => 'Order paid with single method',
					),
					'usage'  => 'Primary field to detect split payments',
				),
			),
			'amount_tracking' => array(
				'gift_card_partial_total'     => 'Amount charged to gift card',
				'other_gateway_partial_total' => 'Amount charged to credit card/Cash App',
				'gift_card_charged_amount'    => 'Total gift card amount (legacy)',
				'credit_card_partial_total'   => 'Credit card amount (legacy, use other_gateway_partial_total)',
			),
			'tender_type_flags' => array(
				'is_tender_type_card'           => 'Boolean: Used credit card',
				'is_tender_type_gift_card'      => 'Boolean: Used gift card',
				'is_tender_type_cash_app_wallet' => 'Boolean: Used Cash App Pay',
			),
			'gift_card_details' => array(
				'gift_card_last4'         => 'Last 4 digits of gift card',
				'gift_card_refunded_amount' => 'Amount refunded to gift card',
			),
		);
	}

	/**
	 * Code examples for handling multiple payment methods in order sync.
	 *
	 * @since x.x.x
	 * @return array
	 */
	public static function get_code_examples() {
		return array(
			'detect_multiple_payments' => '
				// Check if WooCommerce order has multiple payment methods
				$charge_type = wc_square()->get_gateway( $order->get_payment_method() )->get_order_meta( $order, "charge_type" );
				$is_split_payment = ( Payment_Gateway::CHARGE_TYPE_PARTIAL === $charge_type );
				
				if ( $is_split_payment ) {
					$gift_card_amount = (float) wc_square()->get_gateway( $order->get_payment_method() )->get_order_meta( $order, "gift_card_partial_total" );
					$other_amount = (float) wc_square()->get_gateway( $order->get_payment_method() )->get_order_meta( $order, "other_gateway_partial_total" );
				}
			',
			'sync_split_payment_to_square' => '
				// Create Square order with multiple payment methods
				if ( $is_split_payment ) {
					// Create individual payments
					$payment_ids = array();
					
					// Create gift card payment
					if ( $gift_card_amount > 0 ) {
						$gift_card_payment = $api->gift_card_charge( $order );
						$payment_ids[] = $gift_card_payment->get_transaction_id();
					}
					
					// Create credit card payment
					if ( $other_amount > 0 ) {
						$credit_card_payment = $api->credit_card_charge( $order );
						$payment_ids[] = $credit_card_payment->get_transaction_id();
					}
					
					// Link payments to order
					$pay_order_response = $api->pay_order( $payment_ids, $square_order_id );
				}
			',
			'sync_square_split_to_wc' => '
				// Handle Square order with multiple tenders
				$tenders = $square_order->getTenders();
				$payment_breakdown = array();
				$primary_tender = null;
				$highest_amount = 0;
				
				foreach ( $tenders as $tender ) {
					$amount = $tender->getAmountMoney()->getAmount() / 100; // Convert from cents
					$payment_breakdown[] = array(
						"type" => $tender->getType(),
						"amount" => $amount,
						"id" => $tender->getId(),
					);
					
					// Find primary payment method (highest amount)
					if ( $amount > $highest_amount ) {
						$highest_amount = $amount;
						$primary_tender = $tender;
					}
				}
				
				// Set WooCommerce payment method based on primary tender
				$wc_payment_method = self::map_square_tender_to_wc_payment_method( $primary_tender->getType() );
				
				// Store split payment details in order meta
				$wc_order->update_meta_data( "_square_split_payment_details", $payment_breakdown );
				$wc_order->update_meta_data( "_square_primary_tender_type", $primary_tender->getType() );
			',
		);
	}

	/**
	 * Fulfillment splitting workaround for order sync.
	 *
	 * @since x.x.x
	 * @return array
	 */
	public static function get_fulfillment_splitting_workaround() {
		return array(
			'problem' => 'Square API only allows one OrderFulfillment per order',
			'woocommerce_capability' => 'WooCommerce supports multiple shipments per order',
			'third_party_extensions' => 'Some extensions allow splitting fulfillments by line items',
			'recommended_approach' => array(
				'sync_strategy' => 'Sync only the final/overall fulfillment status to Square',
				'detailed_tracking' => 'Keep detailed fulfillment tracking in WooCommerce',
				'status_mapping' => array(
					'All items shipped' => 'COMPLETED in Square',
					'Partially shipped' => 'RESERVED in Square',
					'Not shipped' => 'PROPOSED in Square',
				),
				'implementation' => array(
					'detect_multiple_shipments' => 'Check if order has multiple tracking numbers',
					'calculate_overall_status' => 'Determine overall fulfillment state',
					'sync_to_square' => 'Update Square order with overall status only',
					'preserve_details' => 'Keep WooCommerce shipment details intact',
				),
			),
			'limitations_to_document' => array(
				'no_partial_fulfillment_sync' => 'Cannot sync partial shipments to Square',
				'overall_status_only' => 'Only overall fulfillment status syncs to Square',
				'woocommerce_primary' => 'WooCommerce remains source of truth for detailed fulfillment',
			),
		);
	}

	/**
	 * Implementation guidelines for order sync with multiple payment methods.
	 *
	 * @since x.x.x
	 * @return array
	 */
	public static function get_implementation_guidelines() {
		return array(
			'for_order_mapper' => array(
				'square_to_wc' => array(
					'detect_multiple_tenders' => 'Check if Square order has multiple tenders',
					'map_primary_payment' => 'Set WooCommerce payment method to primary tender type',
					'preserve_split_details' => 'Store all tender information in order meta',
					'add_order_notes' => 'Add note explaining payment breakdown',
				),
				'wc_to_square' => array(
					'detect_split_payment' => 'Check charge_type meta for CHARGE_TYPE_PARTIAL',
					'extract_payment_details' => 'Get amounts for each payment method',
					'create_separate_payments' => 'Create individual Square payments',
					'link_to_order' => 'Use pay_order API to associate payments',
				),
			),
			'for_order_tagging' => array(
				'meta_fields_to_preserve' => array(
					'_square_split_payment_details' => 'Array of all payment methods used',
					'_square_primary_tender_type' => 'Primary payment method type',
					'_square_payment_ids' => 'Array of Square payment IDs',
				),
				'display_enhancements' => array(
					'admin_order_view' => 'Show payment breakdown in order details',
					'order_notes' => 'Add notes explaining split payments',
					'payment_method_title' => 'Update title to show split payment info',
				),
			),
			'testing_scenarios' => array(
				'gift_card_plus_credit' => 'Gift Card ($30) + Credit Card ($70)',
				'gift_card_plus_cash_app' => 'Gift Card ($25) + Cash App Pay ($75)',
				'full_gift_card' => 'Gift Card ($100) only',
				'full_credit_card' => 'Credit Card ($100) only',
				'refund_scenarios' => 'Partial refunds to each payment method',
			),
		);
	}
} 