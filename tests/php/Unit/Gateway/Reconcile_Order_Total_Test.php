<?php
/**
 * Tests for WooCommerce\Square\Gateway\API::reconcile_order_total().
 *
 * @package WooCommerce\Square
 */

namespace WooCommerce\Square\Tests\Unit\Gateway;

use WooCommerce\Square\Gateway\API;
use WP_UnitTestCase;

class Reconcile_Order_Total_Test extends WP_UnitTestCase {

	/**
	 * Arguments of each adjust_order() call, in order.
	 *
	 * @var array[]
	 */
	private $adjust_calls = array();

	public function setUp(): void {
		parent::setUp();

		$this->adjust_calls = array();
	}

	/**
	 * Builds a Square order model with the given total and version.
	 *
	 * @param int $total   Total in cents.
	 * @param int $version Square order version.
	 * @return \Square\Models\Order
	 */
	private function make_square_order( $total, $version ) {
		$money = new \Square\Models\Money();
		$money->setAmount( $total );
		$money->setCurrency( 'USD' );

		$square_order = new \Square\Models\Order( 'LOCATION' );
		$square_order->setVersion( $version );
		$square_order->setTotalMoney( $money );

		return $square_order;
	}

	/**
	 * Builds an API whose adjust_order() returns the given Square order totals, one per call.
	 *
	 * @param int[] $totals_after_each_adjustment Square totals (cents) returned by successive adjust_order() calls.
	 * @return API
	 */
	private function make_api( array $totals_after_each_adjustment ) {
		$api = $this->getMockBuilder( API::class )
			->disableOriginalConstructor()
			->onlyMethods( array( 'adjust_order' ) )
			->getMock();

		$api->method( 'adjust_order' )->willReturnCallback(
			function ( $location_id, $order, $version, $amount, $square_order = null ) use ( &$totals_after_each_adjustment ) {
				$this->adjust_calls[] = array(
					'version'      => $version,
					'amount'       => $amount,
					'square_order' => $square_order,
				);

				return $this->make_square_order( array_shift( $totals_after_each_adjustment ), $version + 1 );
			}
		);

		return $api;
	}

	/**
	 * Runs reconcile_order_total() for a WooCommerce order with the given total.
	 *
	 * @param API                       $api                  API under test.
	 * @param float                     $wc_total             WooCommerce order total.
	 * @param \Square\Models\Order      $square_order         Square order before reconciliation.
	 * @param \Square\Models\Order|null $square_coupon_in_use Square order when redemption is used.
	 * @return \Square\Models\Order
	 */
	private function reconcile( $api, $wc_total, $square_order, $square_coupon_in_use = null ) {
		$order = new \WC_Order();
		$order->set_currency( 'USD' );
		$order->set_total( $wc_total );

		return $api->reconcile_order_total( 'LOCATION', $order, $square_order, $square_coupon_in_use );
	}

	/**
	 * SQUARE-429: a WooCommerce gift card between the pre-tax subtotal and the total ($335 + 5% tax, $350 card, $1.75 due).
	 * The order-level discount is capped at the subtotal and zeroes the tax, so the remainder must be a service charge;
	 * as a line item the unused discount absorbed it and Square was asked to charge $0.
	 */
	public function test_remainder_after_a_capped_discount_is_added_as_a_service_charge() {
		$api = $this->make_api( array( 0, 175 ) );

		$result = $this->reconcile( $api, 1.75, $this->make_square_order( 35175, 3 ) );

		$this->assertCount( 2, $this->adjust_calls );
		$this->assertSame( -35000, $this->adjust_calls[0]['amount'] );
		$this->assertNull( $this->adjust_calls[0]['square_order'], 'The downward adjustment is a discount.' );

		$this->assertSame( 175, $this->adjust_calls[1]['amount'] );
		$this->assertInstanceOf( \Square\Models\Order::class, $this->adjust_calls[1]['square_order'], 'The remainder must be a service charge, not a line item.' );
		$this->assertSame( 4, $this->adjust_calls[1]['square_order']->getVersion(), 'The service charge builds on the order returned by the discount.' );

		$this->assertSame( 175, $result->getTotalMoney()->getAmount() );
	}

	public function test_remainder_with_a_coupon_builds_on_the_discounted_order() {
		$api    = $this->make_api( array( 0, 175 ) );
		$coupon = $this->make_square_order( 35175, 3 );

		$this->reconcile( $api, 1.75, $coupon, $coupon );

		$this->assertCount( 2, $this->adjust_calls );
		$this->assertSame( $coupon, $this->adjust_calls[0]['square_order'] );
		$this->assertNotSame( $coupon, $this->adjust_calls[1]['square_order'], 'The top-up must not reuse the pre-discount order.' );
		$this->assertSame( 4, $this->adjust_calls[1]['square_order']->getVersion() );
	}

	public function test_matching_totals_are_not_adjusted() {
		$api    = $this->make_api( array() );
		$square = $this->make_square_order( 15175, 3 );

		$this->assertSame( $square, $this->reconcile( $api, 151.75, $square ) );
		$this->assertCount( 0, $this->adjust_calls );
	}

	public function test_upward_only_adjustment_stays_a_line_item_without_a_coupon() {
		$api = $this->make_api( array( 15175 ) );

		$this->reconcile( $api, 151.75, $this->make_square_order( 15000, 3 ) );

		$this->assertCount( 1, $this->adjust_calls );
		$this->assertSame( 175, $this->adjust_calls[0]['amount'] );
		$this->assertNull( $this->adjust_calls[0]['square_order'] );
	}

	public function test_upward_only_adjustment_stays_a_service_charge_with_a_coupon() {
		$api    = $this->make_api( array( 15175 ) );
		$coupon = $this->make_square_order( 15000, 3 );

		$this->reconcile( $api, 151.75, $coupon, $coupon );

		$this->assertCount( 1, $this->adjust_calls );
		$this->assertSame( $coupon, $this->adjust_calls[0]['square_order'] );
	}

	public function test_downward_adjustment_that_lands_exactly_needs_no_top_up() {
		$api = $this->make_api( array( 15175 ) );

		$this->reconcile( $api, 151.75, $this->make_square_order( 16000, 3 ) );

		$this->assertCount( 1, $this->adjust_calls );
		$this->assertSame( -825, $this->adjust_calls[0]['amount'] );
	}
}
