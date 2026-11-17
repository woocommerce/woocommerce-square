<?php
/**
 * Tests for resolving line item tax rates against the order's taxes when building a Square order.
 *
 * @package WooCommerce\Square
 */

namespace WooCommerce\Square\Tests\Unit\Gateway;

use WP_UnitTestCase;
use WooCommerce\Square\Tests\Unit\Gateway\Fixtures\Orders_Tax_Lookup_Test_Request;

require_once __DIR__ . '/Fixtures/Orders_Tax_Lookup_Test_Request.php';

/**
 * Tax plugins such as Avalara AvaTax and TaxJar can leave a line item referencing a tax rate ID
 * that has no matching tax item on the order. Subscription renewals, classic checkout and block
 * checkout all build the Square order through the same line item code.
 */
class Orders_Tax_Lookup_Test extends WP_UnitTestCase {

	/** @var Orders_Tax_Lookup_Test_Request */
	private $request;

	public function setUp(): void {
		parent::setUp();

		if ( ! class_exists( 'WC_Order' ) ) {
			$this->markTestSkipped( 'WooCommerce is required.' );
		}

		$this->request = new Orders_Tax_Lookup_Test_Request();
	}

	/**
	 * Builds an unsaved order with one product line item.
	 *
	 * @param array          $line_taxes     line item taxes, keyed by rate ID
	 * @param array          $order_rate_ids rate IDs to add as order tax items
	 * @param \WC_Order|null $order          optional empty order to fill, defaults to a new \WC_Order
	 * @return \WC_Order
	 */
	private function build_order( array $line_taxes, array $order_rate_ids, $order = null ) {
		$order = $order ? $order : new \WC_Order();
		$order->set_currency( 'USD' );

		$item = new \WC_Order_Item_Product();
		$item->set_name( 'Test product' );
		$item->set_quantity( 1 );
		$item->set_subtotal( 10 );
		$item->set_total( 10 );
		$item->set_taxes(
			array(
				'total'    => $line_taxes,
				'subtotal' => $line_taxes,
			)
		);
		$order->add_item( $item );

		foreach ( $order_rate_ids as $rate_id ) {
			$tax = new \WC_Order_Item_Tax();
			$tax->set_rate_id( $rate_id );
			$tax->set_name( 'Tax ' . $rate_id );
			$tax->set_rate_percent( 8 );
			$order->add_item( $tax );
		}

		return $order;
	}

	/**
	 * Runs the order through the line item builder.
	 *
	 * @param \WC_Order $order order
	 * @return array{0: \Square\Models\OrderLineItemTax[], 1: \Square\Models\OrderLineItem} order taxes and the product line item
	 */
	private function build_line_item( \WC_Order $order ) {
		$taxes     = $this->request->call( 'get_order_taxes', $order );
		$line_item = null;

		foreach ( $this->request->call( 'get_api_line_items', $order, $order->get_items(), $taxes ) as $api_item ) {
			if ( $api_item instanceof \Square\Models\OrderLineItem ) {
				$line_item = $api_item;
			}
		}

		return array( $taxes, $line_item );
	}

	/**
	 * Gets the tax UIDs applied to a Square line item.
	 *
	 * @param \Square\Models\OrderLineItem $line_item line item
	 * @return string[]
	 */
	private function applied_tax_uids( $line_item ) {
		return array_map(
			function ( $applied_tax ) {
				return $applied_tax->getTaxUid();
			},
			(array) $line_item->getAppliedTaxes()
		);
	}

	public function test_line_item_tax_with_missing_rate_is_skipped_without_fatal() {
		$order = $this->build_order( array( 999 => 0.8 ), array( 1 ) );

		list( , $line_item ) = $this->build_line_item( $order );

		$this->assertInstanceOf( \Square\Models\OrderLineItem::class, $line_item );
		$this->assertSame( array(), $this->applied_tax_uids( $line_item ) );
	}

	public function test_missing_rate_is_logged_with_the_rate_id() {
		$order = $this->build_order( array( 999 => 0.8 ), array( 1 ) );

		$this->build_line_item( $order );

		$this->assertSame( array( 999 ), $this->request->missing_rate_ids );
	}

	public function test_resolved_rate_keeps_the_order_tax() {
		$order = $this->build_order( array( 1 => 0.8 ), array( 1 ) );

		list( $taxes, $line_item ) = $this->build_line_item( $order );

		$this->assertSame( array( $taxes[1]->getUid() ), $this->applied_tax_uids( $line_item ) );
		$this->assertSame( array(), $this->request->missing_rate_ids );
	}

	public function test_only_the_missing_rate_is_skipped() {
		$order = $this->build_order(
			array(
				1   => 0.8,
				999 => 0.5,
			),
			array( 1 )
		);

		list( $taxes, $line_item ) = $this->build_line_item( $order );

		$this->assertSame( array( $taxes[1]->getUid() ), $this->applied_tax_uids( $line_item ) );
		$this->assertSame( array( 999 ), $this->request->missing_rate_ids );
	}

	public function test_get_order_tax_for_rate_id_matches_int_and_string_ids() {
		$tax   = new \Square\Models\OrderLineItemTax();
		$taxes = array( 5 => $tax );

		$this->assertSame( $tax, $this->request->call( 'get_order_tax_for_rate_id', $taxes, 5 ) );
		$this->assertSame( $tax, $this->request->call( 'get_order_tax_for_rate_id', $taxes, '5' ) );
		$this->assertNull( $this->request->call( 'get_order_tax_for_rate_id', $taxes, 6 ) );
		$this->assertNull( $this->request->call( 'get_order_tax_for_rate_id', array( 5 => 'not a tax' ), 5 ) );

		// AvaTax string rate IDs must not collapse onto a tax stored under rate ID 0.
		$this->assertNull( $this->request->call( 'get_order_tax_for_rate_id', array( 0 => $tax ), 'AVATAX-CA-STATE-TAX' ) );
	}

	public function test_shipping_service_charge_resolves_rate_and_falls_back_to_first_tax() {
		$cases = array(
			1   => 1,
			999 => 1,
		);

		foreach ( $cases as $shipping_rate_id => $expected_rate_id ) {
			$order    = $this->build_order( array(), array( 1 ) );
			$shipping = new \WC_Order_Item_Shipping();
			$shipping->set_name( 'Flat rate' );
			$shipping->set_total( 5 );
			$shipping->set_taxes( array( 'total' => array( $shipping_rate_id => 0.4 ) ) );
			$order->add_item( $shipping );

			$taxes   = $this->request->call( 'get_order_taxes', $order );
			$charges = $this->request->call( 'get_order_service_charges_for_shipping', $order, $taxes );

			$this->assertCount( 1, $charges );
			$this->assertSame( $taxes[ $expected_rate_id ]->getUid(), $charges[0]->getAppliedTaxes()[0]->getTaxUid() );
		}
	}

	/**
	 * Classic checkout, block checkout and subscription renewals all build the Square order through
	 * set_create_order_data(). Uses an AvaTax-style string rate ID, as the reported cases did.
	 */
	public function test_create_order_request_skips_missing_string_rate_id() {
		// The gateway sets unique_transaction_ref on the order before payment. Declare it to avoid a dynamic property.
		$gateway_order = new class() extends \WC_Order {
			/** @var string */
			public $unique_transaction_ref = 'test-transaction-ref';
		};

		$order = $this->build_order(
			array(
				1                     => 0.8,
				'AVATAX-CA-STATE-TAX' => 0.5,
			),
			array( 1 ),
			$gateway_order
		);

		$this->request->set_create_order_data( 'test-location', $order );

		$square_order = $this->request->get_square_api_args()[0]->getOrder();
		$line_items   = $square_order->getLineItems();
		$order_taxes  = $square_order->getTaxes();

		$this->assertCount( 1, $line_items );
		$this->assertCount( 1, $order_taxes );
		$this->assertSame( array( $order_taxes[0]->getUid() ), $this->applied_tax_uids( $line_items[0] ) );
		$this->assertSame( array( 'AVATAX-CA-STATE-TAX' ), $this->request->missing_rate_ids );
	}

	public function test_apply_taxes_skips_missing_rate() {
		$order = $this->build_order( array( 999 => 0.8 ), array( 1 ) );
		$taxes = $this->request->call( 'get_order_taxes', $order );
		$items = $order->get_items();

		$this->assertNull( $this->request->call( 'apply_taxes', $taxes, reset( $items ) ) );
	}
}
