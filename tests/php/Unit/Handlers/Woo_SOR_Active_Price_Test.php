<?php
/**
 * Tests for WooCommerce\Square\Handlers\Product\Woo_SOR::get_active_price().
 *
 * This is the price sent to Square when WooCommerce is the system of record. A stored sale price
 * must only be sent while the sale is active: WooCommerce keeps it on the product after a
 * scheduled sale has ended, and stores it ahead of a sale that starts later.
 *
 * @package WooCommerce\Square
 */

namespace WooCommerce\Square\Tests\Unit\Handlers;

use WP_UnitTestCase;
use WooCommerce\Square\Handlers\Product\Woo_SOR;

class Woo_SOR_Active_Price_Test extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();

		if ( ! class_exists( '\WC_Product_Simple' ) ) {
			$this->markTestSkipped( 'WooCommerce is required for the active price tests.' );
		}
	}

	/**
	 * Builds an unsaved simple product.
	 *
	 * @param string      $regular_price regular price, '' for none.
	 * @param string      $sale_price    sale price, '' for none.
	 * @param string|null $sale_from     sale start, anything strtotime() accepts.
	 * @param string|null $sale_to       sale end, anything strtotime() accepts.
	 * @return \WC_Product_Simple
	 */
	private function make_product( $regular_price, $sale_price = '', $sale_from = null, $sale_to = null ) {
		$product = new \WC_Product_Simple();
		$product->set_name( 'Active price product' );
		$product->set_regular_price( $regular_price );
		$product->set_sale_price( $sale_price );

		if ( null !== $sale_from ) {
			$product->set_date_on_sale_from( strtotime( $sale_from ) );
		}

		if ( null !== $sale_to ) {
			$product->set_date_on_sale_to( strtotime( $sale_to ) );
		}

		return $product;
	}

	public function test_regular_price_is_sent_when_no_sale_price_is_stored() {
		$this->assertSame( '100', Woo_SOR::get_active_price( $this->make_product( '100' ) ) );
	}

	public function test_sale_price_is_sent_for_a_sale_with_no_dates() {
		$this->assertSame( '80', Woo_SOR::get_active_price( $this->make_product( '100', '80' ) ) );
	}

	public function test_sale_price_is_sent_inside_the_sale_window() {
		$this->assertSame( '80', Woo_SOR::get_active_price( $this->make_product( '100', '80', '-1 day', '+1 day' ) ) );
	}

	/**
	 * The reported bug: WooCommerce leaves the sale price stored after a scheduled sale has ended.
	 */
	public function test_regular_price_is_sent_after_a_scheduled_sale_has_ended() {
		$this->assertSame( '100', Woo_SOR::get_active_price( $this->make_product( '100', '80', null, '-1 day' ) ) );
	}

	public function test_regular_price_is_sent_before_a_scheduled_sale_has_started() {
		$this->assertSame( '100', Woo_SOR::get_active_price( $this->make_product( '100', '80', '+1 day', '+2 days' ) ) );
	}

	public function test_regular_price_is_sent_when_the_sale_price_is_not_lower() {
		$this->assertSame( '100', Woo_SOR::get_active_price( $this->make_product( '100', '120' ) ) );
	}

	public function test_sale_price_is_sent_when_it_is_the_only_price() {
		$this->assertSame( '80', Woo_SOR::get_active_price( $this->make_product( '', '80' ) ) );
	}

	public function test_zero_is_sent_when_the_product_has_no_price() {
		$this->assertSame( 0, Woo_SOR::get_active_price( $this->make_product( '' ) ) );
	}
}
