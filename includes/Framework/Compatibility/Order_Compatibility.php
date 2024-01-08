<?php
/**
 * WooCommerce Plugin Framework
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is bundled with this package in the file license.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0 or later
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@skyverge.com so we can send you a copy immediately.
 *
 * @since     3.0.0
 * @author    WooCommerce / SkyVerge
 * @copyright Copyright (c) 2013-2019, SkyVerge, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0 or later
 *
 * Modified by WooCommerce on 01 December 2021.
 */

namespace WooCommerce\Square\Framework\Compatibility;

use WooCommerce\Square\WC_Order_Square;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce order compatibility class.
 *
 * @since 3.0.0
 */
class Order_Compatibility extends Data_Compatibility {

	/** @var array mapped compatibility properties, as `$new_prop => $old_prop` */
	protected static $compat_props = array(
		'date_completed' => 'completed_date',
		'date_paid'      => 'paid_date',
		'date_modified'  => 'modified_date',
		'date_created'   => 'order_date',
		'customer_id'    => 'customer_user',
		'discount'       => 'cart_discount',
		'discount_tax'   => 'cart_discount_tax',
		'shipping_total' => 'total_shipping',
		'type'           => 'order_type',
		'currency'       => 'order_currency',
		'version'        => 'order_version',
	);

	/**
	 * Gets an order date.
	 *
	 * This should only be used to retrieve WC core date properties.
	 *
	 * @since 3.0.0
	 *
	 * @param \WC_Order $order order object
	 * @param string $type type of date to get
	 * @param string $context if 'view' then the value will be filtered
	 *
	 * @return \WC_DateTime|null
	 */
	public static function get_date_prop( \WC_Order $order, $type, $context = 'edit' ) {

		$date = null;
		$prop = "date_{$type}";
		$date = is_callable( array( $order, "get_{$prop}" ) ) ? $order->{"get_{$prop}"}( $context ) : null;

		return $date;
	}


	/**
	 * Gets an order property.
	 *
	 * @since 3.0.0
	 * @param \WC_Order $object the order object
	 * @param string $prop the property name
	 * @param string $context if 'view' then the value will be filtered
	 * @return mixed
	 */
	public static function get_prop( $object, $prop, $context = 'edit', $compat_props = array() ) {

		return parent::get_prop( $object, $prop, $context, self::$compat_props );
	}


	/**
	 * Sets an order's properties.
	 *
	 * Note that this does not save any data to the database.
	 *
	 * @since 3.0.0
	 * @param \WC_Order $object the order object
	 * @param array $props the new properties as $key => $value
	 * @return \WC_Data|\WC_Order|WC_Order_Square
	 */
	public static function set_props( $object, $props, $compat_props = array() ) {

		return parent::set_props( $object, $props, self::$compat_props );
	}


	/**
	 * Order item CRUD compatibility method to add a coupon to an order.
	 *
	 * @since 3.0.0
	 * @param \WC_Order $order the order object
	 * @param array $code the coupon code
	 * @param int $discount the discount amount.
	 * @param int $discount_tax the discount tax amount.
	 * @return int the order item ID
	 */
	public static function add_coupon( \WC_Order $order, $code = array(), $discount = 0, $discount_tax = 0 ) {

		$item = new \WC_Order_Item_Coupon();

		$item->set_props(
			array(
				'code'         => $code,
				'discount'     => $discount,
				'discount_tax' => $discount_tax,
				'order_id'     => $order->get_id(),
			)
		);

		$item->save();
		$order->add_item( $item );

		return $item->get_id();
	}


	/**
	 * Order item CRUD compatibility method to add a fee to an order.
	 *
	 * @since 3.0.0
	 * @param \WC_Order $order the order object
	 * @param object $fee the fee to add
	 * @return int the order item ID
	 */
	public static function add_fee( \WC_Order $order, $fee ) {
		$item = new \WC_Order_Item_Fee();

		$item->set_props(
			array(
				'name'      => $fee->name,
				'tax_class' => $fee->taxable ? $fee->tax_class : 0,
				'total'     => $fee->amount,
				'total_tax' => $fee->tax,
				'taxes'     => array(
					'total' => $fee->tax_data,
				),
				'order_id'  => $order->get_id(),
			)
		);

		$item->save();
		$order->add_item( $item );

		return $item->get_id();
	}


	/**
	 * Order item CRUD compatibility method to add a shipping line to an order.
	 *
	 * @since 3.0.0
	 *
	 * @param \WC_Order $order order object
	 * @param \WC_Shipping_Rate $shipping_rate shipping rate to add
	 * @return int the order item ID
	 */
	public static function add_shipping( \WC_Order $order, $shipping_rate ) {
		$item = new \WC_Order_Item_Shipping();

		$item->set_props(
			array(
				'method_title' => $shipping_rate->label,
				'method_id'    => $shipping_rate->id,
				'total'        => wc_format_decimal( $shipping_rate->cost ),
				'taxes'        => $shipping_rate->taxes,
				'order_id'     => $order->get_id(),
			)
		);

		foreach ( $shipping_rate->get_meta_data() as $key => $value ) {
			$item->add_meta_data( $key, $value, true );
		}

		$item->save();
		$order->add_item( $item );

		return $item->get_id();
	}


	/**
	 * Order item CRUD compatibility method to add a tax line to an order.
	 *
	 * @since 3.0.0
	 *
	 * @param \WC_Order $order order object
	 * @param int $tax_rate_id tax rate ID
	 * @param float $tax_amount cart tax amount
	 * @param float $shipping_tax_amount shipping tax amount
	 * @return int order item ID
	 */
	public static function add_tax( \WC_Order $order, $tax_rate_id, $tax_amount = 0, $shipping_tax_amount = 0 ) {
		$item = new \WC_Order_Item_Tax();

		$item->set_props(
			array(
				'rate_id'            => $tax_rate_id,
				'tax_total'          => $tax_amount,
				'shipping_tax_total' => $shipping_tax_amount,
			)
		);

		$item->set_rate( $tax_rate_id );
		$item->set_order_id( $order->get_id() );
		$item->save();

		$order->add_item( $item );

		return $item->get_id();
	}

	/**
	 * Determines if an order has an available shipping address.
	 *
	 * WooCommerce 3.0+ no longer fills the shipping address with the billing if
	 * a shipping address was never set by the customer at checkout, as is the
	 * case with virtual orders. This method is helpful for gateways that may
	 * reject such transactions with blank shipping information.
	 *
	 * TODO: Remove when WC 3.0.4 can be required {CW 2017-04-17}
	 *
	 * @since 3.0.0
	 *
	 * @param \WC_Order $order order object
	 *
	 * @return bool
	 */
	public static function has_shipping_address( \WC_Order $order ) {

		return self::get_prop( $order, 'shipping_address_1' ) || self::get_prop( $order, 'shipping_address_2' );
	}
}
