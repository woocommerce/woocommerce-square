<?php
/**
 * Order Data Mapper.
 *
 * Maps data between Square and WooCommerce order formats.
 *
 * @since x.x.x
 */

namespace WooCommerce\Square\Sync;

/**
 * Class Order_Mapper
 *
 * Provides static methods to map order data between Square and WooCommerce.
 *
 * @since x.x.x
 */
class Order_Mapper {

    /**
     * Square order states to WooCommerce status mapping.
     *
     * @since x.x.x
     * @var array
     */
    private static $square_to_wc_status_map = array(
        'OPEN'      => 'processing',
        'COMPLETED' => 'completed',
        'CANCELED'  => 'cancelled',
        'DRAFT'     => 'pending',
    );

    /**
     * WooCommerce status to Square order states mapping.
     *
     * @since x.x.x
     * @var array
     */
    private static $wc_to_square_status_map = array(
        'pending'    => 'OPEN',
        'processing' => 'OPEN',
        'on-hold'    => 'OPEN',
        'completed'  => 'COMPLETED',
        'cancelled'  => 'CANCELED',
        'refunded'   => 'CANCELED',
        'failed'     => 'CANCELED',
        'draft'      => 'DRAFT',
    );

    /**
     * Converts a Square order object to WooCommerce-compatible data array.
     * 
     * This method only handles data transformation, not order creation.
     *
     * @since x.x.x
     *
     * @param \Square\Models\Order $square_order Square order object.
     * @return array|false Mapped order data array or false on failure.
     */
    public static function square_to_woocommerce_data( $square_order ) {
        try {
            $order_data = array();

            // Basic order information.
            $total_money = $square_order->getTotalMoney();
            $order_data['currency'] = $total_money ? ( $total_money->getCurrency() ?? get_woocommerce_currency() ) : get_woocommerce_currency();
            
            $created_at = $square_order->getCreatedAt();
            $order_data['created_at'] = $created_at ? self::square_to_wc_datetime( $created_at ) : '';

            // Map order status.
            $square_state = $square_order->getState();
            $order_data['status'] = self::map_square_state_to_wc_status( $square_state );

            // Map order totals.
            $order_data['total'] = $total_money ? self::square_to_wc_money( $total_money->getAmount() ) : 0;
            
            $tax_money = $square_order->getTotalTaxMoney();
            $order_data['tax_total'] = $tax_money ? self::square_to_wc_money( $tax_money->getAmount() ) : 0;
            
            $discount_money = $square_order->getTotalDiscountMoney();
            $order_data['discount_total'] = $discount_money ? self::square_to_wc_money( $discount_money->getAmount() ) : 0;

            // Map line items.
            $line_items = $square_order->getLineItems() ?? array();
            $order_data['line_items'] = self::map_square_line_items( $line_items );

            // Map taxes.
            $square_taxes = $square_order->getTaxes() ?? array();
            $order_data['taxes'] = self::map_square_taxes( $square_taxes );

            // Map discounts.
            $square_discounts = $square_order->getDiscounts() ?? array();
            $order_data['discounts'] = self::map_square_discounts( $square_discounts );

            // Map service charges (shipping).
            $square_service_charges = $square_order->getServiceCharges() ?? array();
            $order_data['service_charges'] = self::map_square_service_charges( $square_service_charges );

            // Map customer information.
            $customer_id = $square_order->getCustomerId();
            $order_data['customer'] = self::map_square_customer_data( $customer_id );

            // Map fulfillment/shipping data.
            $fulfillments = $square_order->getFulfillments() ?? array();
            $order_data['fulfillment'] = self::map_square_fulfillment_data( $fulfillments );

            // Square metadata and references.
            $order_data['square_order_id'] = $square_order->getId();
            $order_data['square_reference_id'] = $square_order->getReferenceId();
            $order_data['square_location_id'] = $square_order->getLocationId();
            
            // Map metadata.
            $metadata = $square_order->getMetadata() ?? array();
            $order_data['metadata'] = self::map_square_metadata( $metadata );

            return $order_data;

        } catch ( \Exception $e ) {
            wc_square()->log( 'Error in Order_Mapper::square_to_woocommerce_data: ' . $e->getMessage(), 'sync' );
            return false;
        }
    }

    /**
     * Converts a WooCommerce order object to Square order data array.
     *
     * @since x.x.x
     *
     * @param \WC_Order $wc_order WooCommerce order object.
     * @return array Mapped Square order data.
     */
    public static function woocommerce_to_square_data( $wc_order ) {
        try {
            $square_order_data = array();

            // Basic order information.
            $square_order_data['reference_id'] = 'woo_order_' . $wc_order->get_id();
            $square_order_data['state'] = self::map_wc_status_to_square_state( $wc_order->get_status() );

            // Map line items.
            $square_order_data['line_items'] = self::map_wc_line_items_to_square( $wc_order );

            // Map taxes.
            $square_order_data['taxes'] = self::map_wc_taxes_to_square( $wc_order );

            // Map discounts/coupons.
            $square_order_data['discounts'] = self::map_wc_discounts_to_square( $wc_order );

            // Map shipping as service charges.
            $square_order_data['service_charges'] = self::map_wc_shipping_to_square( $wc_order );

            // Map customer data.
            $square_order_data['customer_id'] = self::get_square_customer_id( $wc_order );

            // Map fulfillment data.
            $square_order_data['fulfillments'] = self::map_wc_fulfillment_to_square( $wc_order );

            // Add WooCommerce metadata.
            $square_order_data['metadata'] = array(
                'orderedViaWoo' => 'true',
                'wooOrderId'    => (string) $wc_order->get_id(),
                'wooOrderKey'   => $wc_order->get_order_key(),
                'syncVersion'   => wc_square()->get_version(),
                'syncTimestamp' => current_time( 'c' ),
            );

            // Add custom metadata from WooCommerce.
            $custom_meta = self::get_wc_custom_metadata( $wc_order );
            $square_order_data['metadata'] = array_merge( $square_order_data['metadata'], $custom_meta );

            return $square_order_data;

        } catch ( \Exception $e ) {
            wc_square()->log( 'Error in Order_Mapper::woocommerce_to_square_data: ' . $e->getMessage(), 'sync' );
            return array();
        }
    }

    /**
     * Maps Square line items to WooCommerce format.
     *
     * @since x.x.x
     *
     * @param array $square_line_items Array of Square OrderLineItem objects.
     * @return array Mapped line items data.
     */
    private static function map_square_line_items( $square_line_items ) {
        $mapped_items = array();

        foreach ( $square_line_items as $line_item ) {
            if ( ! $line_item instanceof \Square\Models\OrderLineItem ) {
                continue;
            }

            $item_name = $line_item->getName() ?? __( 'Square Item', 'woocommerce-square' );
            $quantity  = (int) ( $line_item->getQuantity() ?? 1 );
            
            // Get price from line item.
            $base_price_money = $line_item->getBasePriceMoney();
            $price = 0;
            if ( $base_price_money ) {
                $price = self::square_to_wc_money( $base_price_money->getAmount() );
            }

            // Calculate line total.
            $total_money = $line_item->getTotalMoney();
            $line_total = $total_money ? self::square_to_wc_money( $total_money->getAmount() ) : ( $price * $quantity );

            $mapped_items[] = array(
                'name'              => $item_name,
                'quantity'          => $quantity,
                'price'             => $price,
                'subtotal'          => $price * $quantity,
                'total'             => $line_total,
                'catalog_object_id' => $line_item->getCatalogObjectId(),
                'variation_name'    => $line_item->getVariationName(),
                'applied_taxes'     => self::map_applied_taxes( $line_item->getAppliedTaxes() ?? array() ),
                'applied_discounts' => self::map_applied_discounts( $line_item->getAppliedDiscounts() ?? array() ),
                'modifiers'         => self::map_line_item_modifiers( $line_item->getModifiers() ?? array() ),
                'uid'               => $line_item->getUid(),
            );
        }

        return $mapped_items;
    }

    /**
     * Maps WooCommerce line items to Square format.
     *
     * @since x.x.x
     *
     * @param \WC_Order $wc_order WooCommerce order object.
     * @return array Mapped Square line items.
     */
    private static function map_wc_line_items_to_square( $wc_order ) {
        $square_line_items = array();

        foreach ( $wc_order->get_items() as $item_id => $item ) {
            $product = $item->get_product();
            
            $line_item_data = array(
                'name'     => $item->get_name(),
                'quantity' => (string) $item->get_quantity(),
            );

            // Set price.
            $unit_price = $item->get_subtotal() / $item->get_quantity();
            $line_item_data['base_price_money'] = array(
                'amount'   => self::wc_to_square_money( $unit_price ),
                'currency' => $wc_order->get_currency(),
            );

            // Add catalog object ID if available.
            if ( $product ) {
                $square_item_id = $product->get_meta( '_square_item_id' );
                if ( $square_item_id ) {
                    $line_item_data['catalog_object_id'] = $square_item_id;
                }
            }

            // Add metadata.
            $line_item_data['metadata'] = array(
                'woo_item_id'   => (string) $item_id,
                'woo_product_id' => (string) $item->get_product_id(),
            );

            if ( $item->get_variation_id() ) {
                $line_item_data['metadata']['woo_variation_id'] = (string) $item->get_variation_id();
            }

            $square_line_items[] = $line_item_data;
        }

        return $square_line_items;
    }

    /**
     * Maps Square taxes to WooCommerce format.
     *
     * @since x.x.x
     *
     * @param array $square_taxes Array of Square OrderLineItemTax objects.
     * @return array Mapped tax data.
     */
    private static function map_square_taxes( $square_taxes ) {
        $mapped_taxes = array();

        foreach ( $square_taxes as $square_tax ) {
            if ( ! $square_tax instanceof \Square\Models\OrderLineItemTax ) {
                continue;
            }

            $tax_percentage = (float) $square_tax->getPercentage();
            $tax_name       = $square_tax->getName() ?? __( 'Square Tax', 'woocommerce-square' );

            $applied_money = $square_tax->getAppliedMoney();
            $tax_amount = $applied_money ? self::square_to_wc_money( $applied_money->getAmount() ) : 0;

            $mapped_taxes[] = array(
                'name'            => $tax_name,
                'rate_percent'    => $tax_percentage,
                'uid'             => $square_tax->getUid(),
                'type'            => $square_tax->getType(),
                'scope'           => $square_tax->getScope(),
                'amount'          => $tax_amount,
                'shipping_amount' => 0, // Square doesn't separate shipping tax.
            );
        }

        return $mapped_taxes;
    }

    /**
     * Maps WooCommerce taxes to Square format.
     *
     * @since x.x.x
     *
     * @param \WC_Order $wc_order WooCommerce order object.
     * @return array Mapped Square taxes.
     */
    private static function map_wc_taxes_to_square( $wc_order ) {
        $square_taxes = array();

        foreach ( $wc_order->get_tax_totals() as $tax_code => $tax ) {
            $square_taxes[] = array(
                'name'       => $tax->label,
                'percentage' => (string) WC_Tax::get_rate_percent_value( $tax_code ),
                'scope'      => 'ORDER',
                'type'       => 'ADDITIVE',
                'uid'        => 'woo_tax_' . $tax_code,
            );
        }

        return $square_taxes;
    }

    /**
     * Maps Square discounts to WooCommerce format.
     *
     * @since x.x.x
     *
     * @param array $square_discounts Array of Square OrderLineItemDiscount objects.
     * @return array Mapped discount data.
     */
    private static function map_square_discounts( $square_discounts ) {
        $mapped_discounts = array();

        foreach ( $square_discounts as $square_discount ) {
            if ( ! $square_discount instanceof \Square\Models\OrderLineItemDiscount ) {
                continue;
            }

            $discount_name   = $square_discount->getName() ?? __( 'Square Discount', 'woocommerce-square' );
            $discount_amount = 0;

            // Get discount amount.
            $amount_money = $square_discount->getAmountMoney();
            $applied_money = $square_discount->getAppliedMoney();
            
            if ( $applied_money ) {
                $discount_amount = self::square_to_wc_money( $applied_money->getAmount() );
            } elseif ( $amount_money ) {
                $discount_amount = self::square_to_wc_money( $amount_money->getAmount() );
            }

            $mapped_discounts[] = array(
                'code'       => $discount_name,
                'name'       => $discount_name,
                'amount'     => $discount_amount,
                'type'       => $square_discount->getType(),
                'scope'      => $square_discount->getScope(),
                'percentage' => $square_discount->getPercentage(),
                'uid'        => $square_discount->getUid(),
            );
        }

        return $mapped_discounts;
    }

    /**
     * Maps WooCommerce discounts/coupons to Square format.
     *
     * @since x.x.x
     *
     * @param \WC_Order $wc_order WooCommerce order object.
     * @return array Mapped Square discounts.
     */
    private static function map_wc_discounts_to_square( $wc_order ) {
        $square_discounts = array();

        foreach ( $wc_order->get_coupon_codes() as $coupon_code ) {
            $coupon = new \WC_Coupon( $coupon_code );
            $discount_amount = $wc_order->get_discount_total();

            $discount_data = array(
                'name'  => $coupon_code,
                'scope' => 'ORDER',
                'uid'   => 'woo_coupon_' . sanitize_key( $coupon_code ),
            );

            if ( $coupon->get_discount_type() === 'percent' ) {
                $discount_data['type'] = 'FIXED_PERCENTAGE';
                $discount_data['percentage'] = (string) $coupon->get_amount();
            } else {
                $discount_data['type'] = 'FIXED_AMOUNT';
                $discount_data['amount_money'] = array(
                    'amount'   => self::wc_to_square_money( $discount_amount ),
                    'currency' => $wc_order->get_currency(),
                );
            }

            $square_discounts[] = $discount_data;
        }

        return $square_discounts;
    }

    /**
     * Maps Square service charges to WooCommerce format.
     *
     * @since x.x.x
     *
     * @param array $square_service_charges Array of Square OrderServiceCharge objects.
     * @return array Mapped service charge data.
     */
    private static function map_square_service_charges( $square_service_charges ) {
        $mapped_charges = array();

        foreach ( $square_service_charges as $square_service_charge ) {
            if ( ! $square_service_charge instanceof \Square\Models\OrderServiceCharge ) {
                continue;
            }

            $charge_name   = $square_service_charge->getName() ?? __( 'Square Service Charge', 'woocommerce-square' );
            $charge_amount = 0;

            // Get service charge amount.
            $applied_money = $square_service_charge->getAppliedMoney();
            if ( $applied_money ) {
                $charge_amount = self::square_to_wc_money( $applied_money->getAmount() );
            }

            $mapped_charges[] = array(
                'name'   => $charge_name,
                'amount' => $charge_amount,
                'type'   => $square_service_charge->getType(),
                'uid'    => $square_service_charge->getUid(),
            );
        }

        return $mapped_charges;
    }

    /**
     * Maps WooCommerce shipping to Square service charges.
     *
     * @since x.x.x
     *
     * @param \WC_Order $wc_order WooCommerce order object.
     * @return array Mapped Square service charges.
     */
    private static function map_wc_shipping_to_square( $wc_order ) {
        $square_service_charges = array();

        foreach ( $wc_order->get_shipping_methods() as $shipping_method ) {
            $square_service_charges[] = array(
                'name'         => $shipping_method->get_name(),
                'type'         => 'AUTO_GRATUITY',
                'amount_money' => array(
                    'amount'   => self::wc_to_square_money( $shipping_method->get_total() ),
                    'currency' => $wc_order->get_currency(),
                ),
                'uid'          => 'woo_shipping_' . $shipping_method->get_id(),
                'metadata'     => array(
                    'woo_method_id' => (string) $shipping_method->get_method_id(),
                    'woo_instance_id' => (string) $shipping_method->get_instance_id(),
                ),
            );
        }

        return $square_service_charges;
    }

    /**
     * Maps Square customer data to WooCommerce format.
     *
     * @since x.x.x
     *
     * @param string $square_customer_id Square customer ID.
     * @return array Mapped customer data.
     */
    private static function map_square_customer_data( $square_customer_id ) {
        if ( empty( $square_customer_id ) ) {
            return array();
        }

        // Try to find existing WooCommerce customer with Square ID.
        $users = get_users( array(
            'meta_key'     => 'wc_square_customer_id',
            'meta_value'   => $square_customer_id,
            'meta_compare' => '=',
            'number'       => 1,
        ) );

        if ( ! empty( $users ) ) {
            $user = $users[0];
            return array(
                'square_customer_id' => $square_customer_id,
                'wc_customer_id'     => $user->ID,
                'billing'            => array(
                    'first_name' => get_user_meta( $user->ID, 'billing_first_name', true ),
                    'last_name'  => get_user_meta( $user->ID, 'billing_last_name', true ),
                    'email'      => $user->user_email,
                    'phone'      => get_user_meta( $user->ID, 'billing_phone', true ),
                ),
            );
        }

        return array(
            'square_customer_id' => $square_customer_id,
            'wc_customer_id'     => 0,
            'billing'            => array(),
        );
    }

    /**
     * Gets Square customer ID for WooCommerce order.
     *
     * @since x.x.x
     *
     * @param \WC_Order $wc_order WooCommerce order object.
     * @return string Square customer ID or empty string.
     */
    private static function get_square_customer_id( $wc_order ) {
        $customer_id = $wc_order->get_customer_id();
        
        if ( $customer_id ) {
            $square_customer_id = get_user_meta( $customer_id, 'wc_square_customer_id', true );
            if ( $square_customer_id ) {
                return $square_customer_id;
            }
        }

        return '';
    }

    /**
     * Maps Square fulfillment data to WooCommerce format.
     *
     * @since x.x.x
     *
     * @param array $square_fulfillments Array of Square OrderFulfillment objects.
     * @return array Mapped fulfillment data.
     */
    private static function map_square_fulfillment_data( $square_fulfillments ) {
        $mapped_fulfillment = array();

        foreach ( $square_fulfillments as $fulfillment ) {
            if ( ! $fulfillment instanceof \Square\Models\OrderFulfillment ) {
                continue;
            }

            $fulfillment_type = $fulfillment->getType();
            $fulfillment_state = $fulfillment->getState();

            $mapped_fulfillment[] = array(
                'type'  => $fulfillment_type,
                'state' => $fulfillment_state,
                'uid'   => $fulfillment->getUid(),
                'details' => self::map_fulfillment_details( $fulfillment ),
            );
        }

        return $mapped_fulfillment;
    }

    /**
     * Maps WooCommerce fulfillment to Square format.
     *
     * @since x.x.x
     *
     * @param \WC_Order $wc_order WooCommerce order object.
     * @return array Mapped Square fulfillments.
     */
    private static function map_wc_fulfillment_to_square( $wc_order ) {
        $square_fulfillments = array();

        // Determine fulfillment type based on shipping method.
        $shipping_methods = $wc_order->get_shipping_methods();
        $fulfillment_type = 'SHIPMENT'; // Default to shipment.

        if ( empty( $shipping_methods ) ) {
            $fulfillment_type = 'PICKUP';
        }

        $fulfillment_data = array(
            'type'  => $fulfillment_type,
            'state' => self::map_wc_status_to_fulfillment_state( $wc_order->get_status() ),
            'uid'   => 'woo_fulfillment_' . $wc_order->get_id(),
        );

        // Add fulfillment details based on type.
        if ( 'SHIPMENT' === $fulfillment_type ) {
            $fulfillment_data['shipment_details'] = self::map_wc_shipping_details( $wc_order );
        } elseif ( 'PICKUP' === $fulfillment_type ) {
            $fulfillment_data['pickup_details'] = self::map_wc_pickup_details( $wc_order );
        }

        $square_fulfillments[] = $fulfillment_data;

        return $square_fulfillments;
    }

    /**
     * Maps WooCommerce shipping details to Square shipment details.
     *
     * @since x.x.x
     *
     * @param \WC_Order $wc_order WooCommerce order object.
     * @return array Mapped shipment details.
     */
    private static function map_wc_shipping_details( $wc_order ) {
        $shipping_details = array(
            'recipient' => array(
                'display_name' => trim( $wc_order->get_shipping_first_name() . ' ' . $wc_order->get_shipping_last_name() ),
                'address'      => array(
                    'address_line_1'                     => $wc_order->get_shipping_address_1(),
                    'address_line_2'                     => $wc_order->get_shipping_address_2(),
                    'locality'                           => $wc_order->get_shipping_city(),
                    'administrative_district_level_1'    => $wc_order->get_shipping_state(),
                    'postal_code'                        => $wc_order->get_shipping_postcode(),
                    'country'                            => $wc_order->get_shipping_country(),
                ),
            ),
        );

        // Add phone if available.
        $shipping_phone = $wc_order->get_meta( '_shipping_phone' );
        if ( $shipping_phone ) {
            $shipping_details['recipient']['phone_number'] = $shipping_phone;
        }

        // Add tracking information if available.
        $tracking_number = $wc_order->get_meta( '_tracking_number' );
        if ( $tracking_number ) {
            $shipping_details['tracking_number'] = $tracking_number;
        }

        $tracking_url = $wc_order->get_meta( '_tracking_url' );
        if ( $tracking_url ) {
            $shipping_details['tracking_url'] = $tracking_url;
        }

        return $shipping_details;
    }

    /**
     * Maps WooCommerce pickup details.
     *
     * @since x.x.x
     *
     * @param \WC_Order $wc_order WooCommerce order object.
     * @return array Mapped pickup details.
     */
    private static function map_wc_pickup_details( $wc_order ) {
        return array(
            'recipient' => array(
                'display_name' => trim( $wc_order->get_billing_first_name() . ' ' . $wc_order->get_billing_last_name() ),
                'phone_number' => $wc_order->get_billing_phone(),
            ),
            'schedule_type' => 'ASAP',
            'pickup_at'     => self::wc_to_square_datetime( $wc_order->get_date_created()->format( 'Y-m-d H:i:s' ) ),
        );
    }

    /**
     * Maps Square metadata to WooCommerce format.
     *
     * @since x.x.x
     *
     * @param array $square_metadata Square order metadata.
     * @return array Mapped metadata.
     */
    private static function map_square_metadata( $square_metadata ) {
        $mapped_metadata = array();

        foreach ( $square_metadata as $key => $value ) {
            // Skip Square system metadata.
            if ( strpos( $key, 'square_' ) === 0 ) {
                continue;
            }

            $mapped_metadata[ $key ] = $value;
        }

        return $mapped_metadata;
    }

    /**
     * Gets custom metadata from WooCommerce order.
     *
     * @since x.x.x
     *
     * @param \WC_Order $wc_order WooCommerce order object.
     * @return array Custom metadata.
     */
    private static function get_wc_custom_metadata( $wc_order ) {
        $custom_metadata = array();

        // Add billing information that doesn't fit in Square's standard fields.
        if ( $wc_order->get_billing_company() ) {
            $custom_metadata['billing_company'] = $wc_order->get_billing_company();
        }

        if ( $wc_order->get_billing_email() ) {
            $custom_metadata['billing_email'] = $wc_order->get_billing_email();
        }

        // Add customer note.
        if ( $wc_order->get_customer_note() ) {
            $custom_metadata['customer_note'] = $wc_order->get_customer_note();
        }

        // Add payment method.
        if ( $wc_order->get_payment_method() ) {
            $custom_metadata['payment_method'] = $wc_order->get_payment_method();
        }

        return $custom_metadata;
    }

    /**
     * Maps fulfillment details based on type.
     *
     * @since x.x.x
     *
     * @param \Square\Models\OrderFulfillment $fulfillment Square fulfillment object.
     * @return array Mapped fulfillment details.
     */
    private static function map_fulfillment_details( $fulfillment ) {
        $details = array();

        switch ( $fulfillment->getType() ) {
            case 'PICKUP':
                $pickup_details = $fulfillment->getPickupDetails();
                if ( $pickup_details ) {
                    $details = array(
                        'pickup_at'      => $pickup_details->getPickupAt(),
                        'schedule_type'  => $pickup_details->getScheduleType(),
                        'recipient'      => self::map_fulfillment_recipient( $pickup_details->getRecipient() ),
                        'note'           => $pickup_details->getNote(),
                    );
                }
                break;

            case 'SHIPMENT':
                $shipment_details = $fulfillment->getShipmentDetails();
                if ( $shipment_details ) {
                    $details = array(
                        'carrier'         => $shipment_details->getCarrier(),
                        'tracking_number' => $shipment_details->getTrackingNumber(),
                        'tracking_url'    => $shipment_details->getTrackingUrl(),
                        'recipient'       => self::map_fulfillment_recipient( $shipment_details->getRecipient() ),
                    );
                }
                break;

            case 'DELIVERY':
                $delivery_details = $fulfillment->getDeliveryDetails();
                if ( $delivery_details ) {
                    $details = array(
                        'deliver_at'     => $delivery_details->getDeliverAt(),
                        'schedule_type'  => $delivery_details->getScheduleType(),
                        'recipient'      => self::map_fulfillment_recipient( $delivery_details->getRecipient() ),
                        'note'           => $delivery_details->getNote(),
                    );
                }
                break;
        }

        return $details;
    }

    /**
     * Maps fulfillment recipient data.
     *
     * @since x.x.x
     *
     * @param \Square\Models\OrderFulfillmentRecipient|null $recipient Square recipient object.
     * @return array Mapped recipient data.
     */
    private static function map_fulfillment_recipient( $recipient ) {
        if ( ! $recipient ) {
            return array();
        }

        $mapped_recipient = array(
            'display_name' => $recipient->getDisplayName(),
            'phone_number' => $recipient->getPhoneNumber(),
        );

        $address = $recipient->getAddress();
        if ( $address ) {
            $mapped_recipient['address'] = array(
                'address_line_1' => $address->getAddressLine1(),
                'address_line_2' => $address->getAddressLine2(),
                'locality'       => $address->getLocality(),
                'postal_code'    => $address->getPostalCode(),
                'country'        => $address->getCountry(),
                'state'          => $address->getAdministrativeDistrictLevel1(),
            );
        }

        return $mapped_recipient;
    }

    /**
     * Maps applied taxes from Square line items.
     *
     * @since x.x.x
     *
     * @param array $applied_taxes Array of Square OrderLineItemAppliedTax objects.
     * @return array Mapped applied tax data.
     */
    private static function map_applied_taxes( $applied_taxes ) {
        $mapped_taxes = array();

        foreach ( $applied_taxes as $applied_tax ) {
            if ( ! $applied_tax instanceof \Square\Models\OrderLineItemAppliedTax ) {
                continue;
            }

            $mapped_taxes[] = array(
                'uid'      => $applied_tax->getUid(),
                'tax_uid'  => $applied_tax->getTaxUid(),
                'amount'   => $applied_tax->getAppliedMoney() ? self::square_to_wc_money( $applied_tax->getAppliedMoney()->getAmount() ) : 0,
            );
        }

        return $mapped_taxes;
    }

    /**
     * Maps applied discounts from Square line items.
     *
     * @since x.x.x
     *
     * @param array $applied_discounts Array of Square OrderLineItemAppliedDiscount objects.
     * @return array Mapped applied discount data.
     */
    private static function map_applied_discounts( $applied_discounts ) {
        $mapped_discounts = array();

        foreach ( $applied_discounts as $applied_discount ) {
            if ( ! $applied_discount instanceof \Square\Models\OrderLineItemAppliedDiscount ) {
                continue;
            }

            $mapped_discounts[] = array(
                'uid'          => $applied_discount->getUid(),
                'discount_uid' => $applied_discount->getDiscountUid(),
                'amount'       => $applied_discount->getAppliedMoney() ? self::square_to_wc_money( $applied_discount->getAppliedMoney()->getAmount() ) : 0,
            );
        }

        return $mapped_discounts;
    }

    /**
     * Maps line item modifiers from Square.
     *
     * @since x.x.x
     *
     * @param array $modifiers Array of Square OrderLineItemModifier objects.
     * @return array Mapped modifier data.
     */
    private static function map_line_item_modifiers( $modifiers ) {
        $mapped_modifiers = array();

        foreach ( $modifiers as $modifier ) {
            if ( ! $modifier instanceof \Square\Models\OrderLineItemModifier ) {
                continue;
            }

            $base_price = $modifier->getBasePriceMoney();
            $total_price = $modifier->getTotalPriceMoney();

            $mapped_modifiers[] = array(
                'uid'                  => $modifier->getUid(),
                'catalog_object_id'    => $modifier->getCatalogObjectId(),
                'name'                 => $modifier->getName(),
                'base_price'           => $base_price ? self::square_to_wc_money( $base_price->getAmount() ) : 0,
                'total_price'          => $total_price ? self::square_to_wc_money( $total_price->getAmount() ) : 0,
            );
        }

        return $mapped_modifiers;
    }

    /**
     * Map Square order state to WooCommerce order status.
     *
     * @since x.x.x
     * @param string $square_state Square order state.
     * @return string WooCommerce order status.
     */
    private static function map_square_state_to_wc_status( $square_state ) {
        return self::$square_to_wc_status_map[ $square_state ] ?? 'processing';
    }

    /**
     * Map WooCommerce order status to Square order state.
     *
     * @since x.x.x
     * @param string $wc_status WooCommerce order status.
     * @return string Square order state.
     */
    private static function map_wc_status_to_square_state( $wc_status ) {
        return self::$wc_to_square_status_map[ $wc_status ] ?? 'OPEN';
    }

    /**
     * Map WooCommerce order status to Square fulfillment state.
     *
     * @since x.x.x
     * @param string $wc_status WooCommerce order status.
     * @return string Square fulfillment state.
     */
    private static function map_wc_status_to_fulfillment_state( $wc_status ) {
        $fulfillment_mapping = array(
            'pending'    => 'PROPOSED',
            'processing' => 'RESERVED',
            'on-hold'    => 'PROPOSED',
            'completed'  => 'COMPLETED',
            'cancelled'  => 'CANCELED',
            'refunded'   => 'CANCELED',
            'failed'     => 'FAILED',
        );

        return $fulfillment_mapping[ $wc_status ] ?? 'PROPOSED';
    }

    /**
     * Convert Square money amount (cents) to WooCommerce decimal.
     *
     * @since x.x.x
     * @param int $square_amount Amount in cents.
     * @return float Amount in decimal format.
     */
    private static function square_to_wc_money( $square_amount ) {
        return (float) ( $square_amount / 100 );
    }

    /**
     * Convert WooCommerce decimal amount to Square money (cents).
     *
     * @since x.x.x
     * @param float $wc_amount Amount in decimal format.
     * @return int Amount in cents.
     */
    private static function wc_to_square_money( $wc_amount ) {
        return (int) round( (float) $wc_amount * 100 );
    }

    /**
     * Convert Square datetime to WooCommerce datetime.
     *
     * @since x.x.x
     * @param string $square_datetime RFC 3339 datetime.
     * @return string MySQL datetime format.
     */
    private static function square_to_wc_datetime( $square_datetime ) {
        return date( 'Y-m-d H:i:s', strtotime( $square_datetime ) );
    }

    /**
     * Convert WooCommerce datetime to Square datetime.
     *
     * @since x.x.x
     * @param string $wc_datetime MySQL datetime format.
     * @return string RFC 3339 datetime format.
     */
    private static function wc_to_square_datetime( $wc_datetime ) {
        return date( 'c', strtotime( $wc_datetime ) );
    }
}
