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
            $order_data['created_at'] = $created_at ?? '';

            // Map order status.
            $square_state = $square_order->getState();
            $order_data['status'] = self::map_square_state_to_wc_status( $square_state );

            // Map line items.
            $line_items = $square_order->getLineItems() ?? array();
            $order_data['line_items'] = self::map_square_line_items( $line_items );

            // Map taxes.
            $square_taxes = $square_order->getTaxes() ?? array();
            $order_data['taxes'] = self::map_square_taxes( $square_taxes );

            // Map discounts.
            $square_discounts = $square_order->getDiscounts() ?? array();
            $order_data['discounts'] = self::map_square_discounts( $square_discounts );

            // Map service charges.
            $square_service_charges = $square_order->getServiceCharges() ?? array();
            $order_data['service_charges'] = self::map_square_service_charges( $square_service_charges );

            // Map customer information.
            $customer_id = $square_order->getCustomerId();
            $order_data['customer'] = self::map_square_customer_data( $customer_id );

            // Square metadata.
            $order_data['square_order_id'] = $square_order->getId();
            $order_data['square_reference_id'] = $square_order->getReferenceId();

            return $order_data;

        } catch ( \Exception $e ) {
            wc_square()->log( 'Error in Order_Mapper::square_to_woocommerce_data: ' . $e->getMessage(), 'sync' );
            return false;
        }
    }

    /**
     * Converts a WooCommerce order object to a Square order array/object.
     *
     * @since x.x.x
     *
     * @param \WC_Order $wc_order WooCommerce order object.
     * @return array Mapped Square order data.
     */
    public static function woocommerce_to_square( $wc_order ) {
        // TODO: Implement mapping logic.
        return [];
    }

    /**
     * Maps Square line items to WooCommerce format.
     *
     * @since x.x.x
     *
     * @param array $square_line_items Array of Square OrderLineItem objects.
     * @return array Mapped line items data.
     */
    public static function map_square_line_items( $square_line_items ) {
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
                $price = $base_price_money->getAmount() / 100; // Convert from cents.
            }

            // Calculate line total (may include discounts).
            $line_total = $price * $quantity;
            $total_money = $line_item->getTotalMoney();
            if ( $total_money ) {
                $line_total = $total_money->getAmount() / 100; // Convert from cents.
            }

            $mapped_items[] = array(
                'name'              => $item_name,
                'quantity'          => $quantity,
                'price'             => $price,
                'subtotal'          => $price * $quantity,
                'total'             => $line_total,
                'catalog_object_id' => $line_item->getCatalogObjectId(),
                'applied_taxes'     => self::map_applied_taxes( $line_item->getAppliedTaxes() ?? array() ),
            );
        }

        return $mapped_items;
    }

    /**
     * Maps Square taxes to WooCommerce format.
     *
     * @since x.x.x
     *
     * @param array $square_taxes Array of Square OrderLineItemTax objects.
     * @return array Mapped tax data.
     */
    public static function map_square_taxes( $square_taxes ) {
        $mapped_taxes = array();

        foreach ( $square_taxes as $square_tax ) {
            if ( ! $square_tax instanceof \Square\Models\OrderLineItemTax ) {
                continue;
            }

            $tax_percentage = (float) $square_tax->getPercentage();
            $tax_name       = $square_tax->getName() ?? __( 'Square Tax', 'woocommerce-square' );

            $mapped_taxes[] = array(
                'name'            => $tax_name,
                'percentage'      => $tax_percentage,
                'uid'             => $square_tax->getUid(),
                'type'            => $square_tax->getType(),
                'scope'           => $square_tax->getScope(),
                'amount'          => 0, // Will be calculated later.
                'shipping_amount' => 0, // Square doesn't separate shipping tax.
            );
        }

        return $mapped_taxes;
    }

    /**
     * Maps Square discounts to WooCommerce format.
     *
     * @since x.x.x
     *
     * @param array $square_discounts Array of Square OrderLineItemDiscount objects.
     * @return array Mapped discount data.
     */
    public static function map_square_discounts( $square_discounts ) {
        $mapped_discounts = array();

        foreach ( $square_discounts as $square_discount ) {
            if ( ! $square_discount instanceof \Square\Models\OrderLineItemDiscount ) {
                continue;
            }

            $discount_name   = $square_discount->getName() ?? __( 'Square Discount', 'woocommerce-square' );
            $discount_amount = 0;

            // Get discount amount.
            $amount_money = $square_discount->getAmountMoney();
            if ( $amount_money ) {
                $discount_amount = $amount_money->getAmount() / 100; // Convert from cents.
            }

            $mapped_discounts[] = array(
                'name'   => $discount_name,
                'amount' => $discount_amount,
                'type'   => $square_discount->getType(),
                'scope'  => $square_discount->getScope(),
            );
        }

        return $mapped_discounts;
    }

    /**
     * Maps Square service charges to WooCommerce format.
     *
     * @since x.x.x
     *
     * @param array $square_service_charges Array of Square OrderServiceCharge objects.
     * @return array Mapped service charge data.
     */
    public static function map_square_service_charges( $square_service_charges ) {
        $mapped_charges = array();

        foreach ( $square_service_charges as $square_service_charge ) {
            if ( ! $square_service_charge instanceof \Square\Models\OrderServiceCharge ) {
                continue;
            }

            $charge_name   = $square_service_charge->getName() ?? __( 'Square Service Charge', 'woocommerce-square' );
            $charge_amount = 0;

            // Get service charge amount.
            $amount_money = $square_service_charge->getAmountMoney();
            if ( $amount_money ) {
                $charge_amount = $amount_money->getAmount() / 100; // Convert from cents.
            }

            $mapped_charges[] = array(
                'name'   => $charge_name,
                'amount' => $charge_amount,
            );
        }

        return $mapped_charges;
    }

    /**
     * Maps Square customer data to WooCommerce format.
     *
     * @since x.x.x
     *
     * @param string $square_customer_id Square customer ID.
     * @return array Mapped customer data.
     */
    public static function map_square_customer_data( $square_customer_id ) {
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
     * Maps shipping/fulfillment data between systems.
     *
     * @since x.x.x
     *
     * @param mixed $source_fulfillment Source system fulfillment data.
     * @return array Mapped fulfillment data.
     */
    public static function map_fulfillment_data( $source_fulfillment ) {
        // TODO: Implement mapping logic.
        return array();
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

            $mapped_taxes[] = $applied_tax->getUid();
        }

        return $mapped_taxes;
    }



    /**
     * Map Square order state to WooCommerce order status.
     *
     * @since x.x.x
     * @param string $square_state Square order state.
     * @return string
     */
    private static function map_square_state_to_wc_status( $square_state ) {
        $mapping = array(
            'OPEN'      => 'processing',
            'COMPLETED' => 'completed',
            'CANCELED'  => 'cancelled',
        );

        return $mapping[ $square_state ] ?? 'processing';
    }
}
