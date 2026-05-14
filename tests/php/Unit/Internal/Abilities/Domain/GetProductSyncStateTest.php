<?php
/**
 * Tests for WooCommerce\Square\Internal\Abilities\Domain\GetProductSyncState.
 *
 * @package WooCommerce\Square
 */

namespace WooCommerce\Square\Tests\Unit\Internal\Abilities\Domain;

use WP_UnitTestCase;
use WooCommerce\Square\Internal\Abilities\Abilities_Registrar;
use WooCommerce\Square\Internal\Abilities\Domain\GetProductSyncState;

class GetProductSyncStateTest extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		if ( ! class_exists( '\\Automattic\\WooCommerce\\Internal\\Abilities\\AbilitiesLoader' ) ) {
			$this->markTestSkipped( 'WooCommerce 10.9 AbilitiesLoader required for these tests.' );
		}
		add_filter( 'woocommerce_square_abilities_enabled', '__return_true' );
	}

	public function tearDown(): void {
		remove_all_filters( 'woocommerce_square_abilities_enabled' );
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	public function test_registration_args_callbacks_are_wired_to_domain_class() {
		$args = GetProductSyncState::get_registration_args();
		$this->assertSame( array( GetProductSyncState::class, 'execute' ), $args['execute_callback'] );
		$this->assertSame( array( Abilities_Registrar::class, 'can_manage_woocommerce_square' ), $args['permission_callback'] );
	}

	public function test_input_schema_requires_product_id() {
		$args = GetProductSyncState::get_registration_args();
		$this->assertSame( array( 'product_id' ), $args['input_schema']['required'] ?? array() );
	}

	public function test_ability_is_registered_with_expected_shape() {
		if ( ! function_exists( 'wp_get_ability' ) ) {
			$this->markTestSkipped( 'Abilities API query functions not available in this WP version.' );
		}

		$ability = wp_get_ability( 'woocommerce-square/get-product-sync-state' );
		$this->assertNotNull( $ability );
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

	public function test_execute_returns_wp_error_when_product_id_missing() {
		$result = GetProductSyncState::execute( array() );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'woocommerce_square_missing_product_id', $result->get_error_code() );
	}

	public function test_execute_returns_wp_error_when_product_id_invalid() {
		$result = GetProductSyncState::execute( array( 'product_id' => 0 ) );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'woocommerce_square_invalid_product_id', $result->get_error_code() );
	}
}
