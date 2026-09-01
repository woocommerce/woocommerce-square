<?php
/**
 * Tests for WooCommerce\Square\Internal\Abilities\Domain\GetOrderPaymentStatus.
 *
 * @package WooCommerce\Square
 */

namespace WooCommerce\Square\Tests\Unit\Internal\Abilities\Domain;

use WP_UnitTestCase;
use WooCommerce\Square\Internal\Abilities\Abilities_Registrar;
use WooCommerce\Square\Internal\Abilities\Domain\GetOrderPaymentStatus;

class GetOrderPaymentStatusTest extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		if ( ! class_exists( '\\Automattic\\WooCommerce\\Internal\\Abilities\\AbilitiesLoader' ) ) {
			$this->markTestSkipped( 'WooCommerce 10.9 AbilitiesLoader required for these tests.' );
		}
		add_filter( 'woocommerce_square_abilities_enabled', '__return_true' );
		// init() runs once at plugin bootstrap with the feature flag
		// defaulting to false, so abilities are never registered there.
		// Re-run it now that we have flipped the flag so wp_get_ability()
		// can resolve woocommerce-square/* below.
		Abilities_Registrar::init();
	}

	public function tearDown(): void {
		remove_all_filters( 'woocommerce_square_abilities_enabled' );
		Abilities_Registrar::reset_initialized_for_testing();
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	public function test_registration_args_callbacks_are_wired_to_domain_class() {
		$args = GetOrderPaymentStatus::get_registration_args();

		$this->assertSame(
			array( GetOrderPaymentStatus::class, 'execute' ),
			$args['execute_callback']
		);
		$this->assertSame(
			array( Abilities_Registrar::class, 'can_manage_woocommerce_square' ),
			$args['permission_callback']
		);
	}

	public function test_execute_returns_wp_error_when_order_id_missing_or_invalid() {
		$result = GetOrderPaymentStatus::execute( array() );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'woocommerce_square_invalid_order_id', $result->get_error_code() );

		$result = GetOrderPaymentStatus::execute( array( 'order_id' => 0 ) );
		$this->assertInstanceOf( \WP_Error::class, $result );

		$result = GetOrderPaymentStatus::execute( array( 'order_id' => -3 ) );
		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	public function test_execute_returns_not_found_error_for_missing_order() {
		if ( ! function_exists( 'wc_get_order' ) ) {
			$this->markTestSkipped( 'WooCommerce required for this test.' );
		}
		$result = GetOrderPaymentStatus::execute( array( 'order_id' => 99999999 ) );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'woocommerce_square_order_not_found', $result->get_error_code() );
	}

	public function test_execute_returns_envelope_with_is_square_payment_false_for_non_square_order() {
		if ( ! function_exists( 'wc_create_order' ) ) {
			$this->markTestSkipped( 'WooCommerce required for this test.' );
		}
		$order = wc_create_order();
		$order->set_payment_method( 'cheque' );
		$order->set_total( 12.34 );
		$order->set_currency( 'USD' );
		$order->save();

		$result = GetOrderPaymentStatus::execute( array( 'order_id' => $order->get_id() ) );
		$this->assertIsArray( $result );
		$this->assertSame( $order->get_id(), $result['order_id'] );
		$this->assertSame( 'cheque', $result['payment_method'] );
		$this->assertFalse( $result['is_square_payment'] );
		$this->assertArrayNotHasKey( 'square', $result );
		$this->assertSame( array(), $result['refunds'] );
	}

	public function test_execute_returns_square_block_with_meta_for_square_order() {
		if ( ! function_exists( 'wc_create_order' ) ) {
			$this->markTestSkipped( 'WooCommerce required for this test.' );
		}
		$order = wc_create_order();
		$order->set_payment_method( 'square_credit_card' );
		$order->set_total( 100.0 );
		$order->set_currency( 'USD' );
		$order->update_meta_data( '_wc_square_credit_card_trans_id', 'tx_abc123' );
		$order->update_meta_data( '_wc_square_credit_card_trans_date', '2026-05-15 12:00:00' );
		$order->update_meta_data( '_wc_square_credit_card_charge_captured', 'yes' );
		$order->update_meta_data( '_wc_square_credit_card_capture_total', '100.00' );
		$order->update_meta_data( '_wc_square_credit_card_square_order_id', 'sq_order_xyz' );
		$order->update_meta_data( '_wc_square_credit_card_square_location_id', 'L123' );
		$order->save();

		$result = GetOrderPaymentStatus::execute( array( 'order_id' => $order->get_id() ) );
		$this->assertIsArray( $result );
		$this->assertTrue( $result['is_square_payment'] );
		$this->assertArrayHasKey( 'square', $result );

		$square = $result['square'];
		$this->assertSame( 'tx_abc123', $square['transaction_id'] );
		$this->assertSame( '2026-05-15 12:00:00', $square['transaction_date'] );
		$this->assertSame( 'yes', $square['charge_captured'] );
		$this->assertSame( 100.0, $square['capture_total'] );
		$this->assertSame( 'sq_order_xyz', $square['square_order_id'] );
		$this->assertSame( 'L123', $square['square_location_id'] );
		$this->assertTrue( $square['has_square_charge'] );
		$this->assertTrue( $square['is_fully_captured'] );
		$this->assertFalse( $square['is_partially_captured'] );
		$this->assertFalse( $square['gift_card']['is_split_payment'] );
	}

	public function test_execute_detects_gift_card_only_path_via_is_tender_type_gift_card() {
		if ( ! function_exists( 'wc_create_order' ) ) {
			$this->markTestSkipped( 'WooCommerce required for this test.' );
		}
		$order = wc_create_order();
		$order->set_payment_method( 'square_credit_card' );
		$order->set_total( 25.0 );
		$order->set_currency( 'USD' );
		// Mirror Payment_Gateway::update_order_meta( 'is_tender_type_gift_card', true )
		// — WC stores meta-bool true as the string '1'.
		$order->update_meta_data( '_wc_square_credit_card_is_tender_type_gift_card', '1' );
		$order->update_meta_data( '_wc_square_credit_card_gift_card_trans_id', 'gc_tx_123' );
		$order->update_meta_data( '_wc_square_credit_card_gift_card_partial_total', '25.00' );
		$order->save();

		$result = GetOrderPaymentStatus::execute( array( 'order_id' => $order->get_id() ) );
		$this->assertIsArray( $result );
		$this->assertTrue( $result['square']['gift_card']['is_split_payment'], 'Gift-card-only path must mark split_payment true.' );
		$this->assertSame( 'gc_tx_123', $result['square']['gift_card']['transaction_id'] );
		$this->assertSame( 25.0, $result['square']['gift_card']['partial_total'] );
		$this->assertSame( 0.0, $result['square']['gift_card']['refunded_total'] );
	}

	public function test_execute_detects_split_payment_via_partial_charge_type() {
		if ( ! function_exists( 'wc_create_order' ) ) {
			$this->markTestSkipped( 'WooCommerce required for this test.' );
		}
		$order = wc_create_order();
		$order->set_payment_method( 'square_credit_card' );
		$order->set_total( 50.0 );
		$order->set_currency( 'USD' );
		$order->update_meta_data( '_wc_square_credit_card_charge_type', 'PARTIAL' );
		$order->update_meta_data( '_wc_square_credit_card_trans_id', 'tx_cc_part' );
		$order->update_meta_data( '_wc_square_credit_card_gift_card_trans_id', 'gc_tx_part' );
		$order->update_meta_data( '_wc_square_credit_card_gift_card_partial_total', '20.00' );
		$order->save();

		$result = GetOrderPaymentStatus::execute( array( 'order_id' => $order->get_id() ) );
		$this->assertTrue( $result['square']['gift_card']['is_split_payment'] );
		$this->assertSame( 'gc_tx_part', $result['square']['gift_card']['transaction_id'] );
	}

	public function test_ability_is_registered_with_expected_shape() {
		if ( ! function_exists( 'wp_get_ability' ) ) {
			$this->markTestSkipped( 'Abilities API query functions not available in this WP version.' );
		}

		$ability = wp_get_ability( 'woocommerce-square/get-order-payment-status' );
		$this->assertNotNull( $ability, 'woocommerce-square/get-order-payment-status should be registered.' );
		$this->assertSame( Abilities_Registrar::CATEGORY_SLUG, $ability->get_category() );

		$meta        = $ability->get_meta();
		$annotations = $meta['annotations'] ?? array();
		$this->assertTrue( $annotations['readonly'] ?? false );
		$this->assertFalse( $annotations['destructive'] ?? true );
		$this->assertTrue( $annotations['idempotent'] ?? false );
		$this->assertTrue( $meta['show_in_rest'] ?? false );
		$this->assertTrue( $meta['mcp']['public'] ?? false );

		$subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );
		$this->assertFalse( $ability->check_permissions( array() ) );
	}
}
