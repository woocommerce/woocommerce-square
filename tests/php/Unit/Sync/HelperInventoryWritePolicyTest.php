<?php
/**
 * Tests for the stock write policy in WooCommerce\Square\Sync\Helper::apply_square_inventory_count().
 *
 * This is the decision that wiped a merchant's catalogue: what a Square count is allowed to change on
 * a WooCommerce product. Every branch is pinned here, in particular the ones where the correct answer
 * is to change nothing at all.
 *
 * @package WooCommerce\Square
 */

namespace WooCommerce\Square\Tests\Unit\Sync;

use WP_UnitTestCase;
use WooCommerce\Square\Sync\Helper;

class HelperInventoryWritePolicyTest extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();

		if ( ! class_exists( '\WC_Product_Simple' ) ) {
			$this->markTestSkipped( 'WooCommerce is required for the stock write policy tests.' );
		}

		$this->set_system_of_record( 'woocommerce' );
	}

	public function tearDown(): void {
		$this->set_system_of_record( 'woocommerce' );

		parent::tearDown();
	}

	/**
	 * Pins the system of record for a test.
	 *
	 * Written through the settings handler rather than the option row, because the handler caches
	 * its settings for the life of the process and would otherwise keep answering with the old value.
	 *
	 * @param string $value 'woocommerce' or 'square'.
	 */
	private function set_system_of_record( $value ) {
		wc_square()->get_settings_handler()->update_option( 'system_of_record', $value );
	}

	/**
	 * Builds a saved simple product.
	 *
	 * @param bool     $manage_stock whether the product manages its own stock.
	 * @param int|null $quantity     starting quantity, when managed.
	 * @param string   $status       starting stock status.
	 * @return \WC_Product_Simple
	 */
	private function make_product( $manage_stock, $quantity = null, $status = 'instock' ) {
		$product = new \WC_Product_Simple();
		$product->set_name( 'Write policy product' );
		$product->set_regular_price( '10' );
		$product->set_manage_stock( $manage_stock );

		if ( null !== $quantity ) {
			$product->set_stock_quantity( $quantity );
		}

		$product->set_stock_status( $status );
		$product->save();

		return $product;
	}

	/** A positive count is written, and turns stock management on. */
	public function test_a_positive_count_is_written() {
		$product = $this->make_product( true, 4 );

		$this->assertTrue( Helper::apply_square_inventory_count( $product, 9, false, false ) );
		$this->assertEquals( 9, $product->get_stock_quantity() );
		$this->assertTrue( $product->get_manage_stock() );
	}

	/** A zero backed by inventory history is a real sellout, so it is written. */
	public function test_a_verified_zero_is_written_to_a_managed_product() {
		$product = $this->make_product( true, 12 );

		$this->assertTrue( Helper::apply_square_inventory_count( $product, 0, true, true ) );
		$this->assertEquals( 0, $product->get_stock_quantity() );
	}

	/**
	 * The reported bug. An unverified zero comes from an item Square never counted, so the product is
	 * left exactly as the merchant left it.
	 */
	public function test_an_unverified_zero_leaves_a_managed_product_untouched() {
		$product = $this->make_product( true, 12 );

		$this->assertFalse( Helper::apply_square_inventory_count( $product, 0, true, false ) );
		$this->assertEquals( 12, $product->get_stock_quantity(), 'An unproven zero must not overwrite the quantity.' );
		$this->assertTrue( $product->get_manage_stock(), 'An unproven zero must not switch stock management off.' );
	}

	/** A product that does not manage stock gets availability only, never a quantity. */
	public function test_a_verified_zero_marks_an_unmanaged_product_out_of_stock() {
		$product = $this->make_product( false );

		$this->assertTrue( Helper::apply_square_inventory_count( $product, 0, true, true ) );
		$this->assertSame( 'outofstock', $product->get_stock_status() );
		$this->assertFalse( $product->get_manage_stock(), 'Availability changes must not turn stock management on.' );
		$this->assertNull( $product->get_stock_quantity() );
	}

	/** An unproven zero must not push an unmanaged product out of stock either: that is lost sales. */
	public function test_an_unverified_zero_leaves_an_unmanaged_product_in_stock() {
		$product = $this->make_product( false );

		$this->assertFalse( Helper::apply_square_inventory_count( $product, 0, true, false ) );
		$this->assertSame( 'instock', $product->get_stock_status() );
	}

	/**
	 * Under WooCommerce as the system of record the pool is merchant intent, so a variation
	 * inheriting it is skipped for every count: a per variation write is either invisible or would
	 * convert the variation to its own stock management.
	 */
	public function test_a_pooled_variation_is_skipped_for_every_count_under_woocommerce_system_of_record() {
		$variable = new \WC_Product_Variable();
		$variable->set_name( 'Pooled parent' );
		$variable->set_manage_stock( true );
		$variable->set_stock_quantity( 50 );
		$variable->save();

		$variation = new \WC_Product_Variation();
		$variation->set_parent_id( $variable->get_id() );
		$variation->set_regular_price( '10' );
		$variation->set_manage_stock( false );
		$variation->save();

		// A variation reports 'parent' only once it is read back with the parent's data attached: the
		// value is derived from the parent managing stock while the variation does not.
		$variation = wc_get_product( $variation->get_id() );

		$this->assertSame( 'parent', $variation->get_manage_stock(), 'Fixture must actually inherit the pool.' );

		foreach ( array( 6, 0, -2 ) as $count ) {
			$this->assertFalse(
				Helper::apply_square_inventory_count( $variation, $count, false, true ),
				"A pooled variation must be skipped for a count of {$count}."
			);
			$this->assertSame( 'parent', $variation->get_manage_stock() );
		}

		$this->assertEquals( 50, wc_get_product( $variable->get_id() )->get_stock_quantity(), 'The pool must be untouched.' );
	}

	/**
	 * Under Square as the system of record the authority is reversed, so a positive count is applied
	 * and the variation takes over its own stock, which is what the plugin did before this changeset.
	 * A zero or negative count is still refused, because writing one into a pool would move stock
	 * shared with sibling variations on one variation's reading.
	 */
	public function test_a_pooled_variation_takes_over_its_stock_for_a_positive_count_under_square_system_of_record() {
		$this->set_system_of_record( 'square' );

		$variable = new \WC_Product_Variable();
		$variable->set_name( 'Pooled parent under Square SOR' );
		$variable->set_manage_stock( true );
		$variable->set_stock_quantity( 50 );
		$variable->save();

		$variation = new \WC_Product_Variation();
		$variation->set_parent_id( $variable->get_id() );
		$variation->set_regular_price( '10' );
		$variation->set_manage_stock( false );
		$variation->save();

		$variation = wc_get_product( $variation->get_id() );

		$this->assertSame( 'parent', $variation->get_manage_stock(), 'Fixture must actually inherit the pool.' );

		$this->assertTrue(
			Helper::apply_square_inventory_count( $variation, 6, false, true ),
			'A positive count must be applied under Square SOR.'
		);
		$this->assertTrue( $variation->get_manage_stock(), 'The variation must take over its own stock management.' );
		$this->assertEquals( 6, $variation->get_stock_quantity() );
		$this->assertEquals( 50, wc_get_product( $variable->get_id() )->get_stock_quantity(), 'The parent pool must be left as it was.' );

		// A fresh pooled variation, to prove the non positive counts are still refused.
		$pooled = new \WC_Product_Variation();
		$pooled->set_parent_id( $variable->get_id() );
		$pooled->set_regular_price( '10' );
		$pooled->set_manage_stock( false );
		$pooled->save();

		$pooled = wc_get_product( $pooled->get_id() );

		foreach ( array( 0, -2 ) as $count ) {
			$this->assertFalse(
				Helper::apply_square_inventory_count( $pooled, $count, false, true ),
				"A pooled variation must still be skipped for a count of {$count} under Square SOR."
			);
			$this->assertSame( 'parent', $pooled->get_manage_stock() );
		}
	}

	/** WooCommerce holds negative stock as backorder depth, and Square cannot, so it is passed through. */
	public function test_a_negative_count_is_passed_through_for_a_managed_product() {
		$product = $this->make_product( true, 4 );

		$this->assertTrue( Helper::apply_square_inventory_count( $product, -3, false, false ) );
		$this->assertEquals( -3, $product->get_stock_quantity(), 'Clamping a negative to zero loses backorder depth.' );
	}

	/** A negative count needs no history check, because a never counted item reads exactly zero. */
	public function test_a_negative_count_marks_an_unmanaged_product_out_of_stock() {
		$product = $this->make_product( false );

		$this->assertTrue( Helper::apply_square_inventory_count( $product, -1, false, false ) );
		$this->assertSame( 'outofstock', $product->get_stock_status() );
	}
}
