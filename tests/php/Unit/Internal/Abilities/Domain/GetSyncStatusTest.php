<?php
/**
 * Tests for WooCommerce\Square\Internal\Abilities\Domain\GetSyncStatus.
 *
 * @package WooCommerce\Square
 */

namespace WooCommerce\Square\Tests\Unit\Internal\Abilities\Domain;

use WP_UnitTestCase;
use WooCommerce\Square\Internal\Abilities\Abilities_Registrar;
use WooCommerce\Square\Internal\Abilities\Domain\GetSyncStatus;

class GetSyncStatusTest extends WP_UnitTestCase {

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
		$args = GetSyncStatus::get_registration_args();

		$this->assertSame(
			array( GetSyncStatus::class, 'execute' ),
			$args['execute_callback'],
			'execute_callback must point to GetSyncStatus::execute, not the legacy registrar method.'
		);

		$this->assertSame(
			array( Abilities_Registrar::class, 'can_manage_woocommerce_square' ),
			$args['permission_callback'],
			'permission_callback must point to Abilities_Registrar::can_manage_woocommerce_square.'
		);
	}

	public function test_ability_is_registered_with_expected_shape() {
		if ( ! function_exists( 'wp_get_ability' ) ) {
			$this->markTestSkipped( 'Abilities API query functions not available in this WP version.' );
		}

		$ability = wp_get_ability( 'woocommerce-square/get-sync-status' );
		$this->assertNotNull( $ability, 'woocommerce-square/get-sync-status should be registered.' );
		$this->assertSame( Abilities_Registrar::CATEGORY_SLUG, $ability->get_category() );

		$meta = $ability->get_meta();
		$this->assertIsArray( $meta );
		$this->assertArrayHasKey( 'annotations', $meta );

		$annotations = $meta['annotations'];
		$this->assertTrue( $annotations['readonly'], 'get-sync-status should be readonly.' );
		$this->assertFalse( $annotations['destructive'], 'get-sync-status should not be destructive.' );
		$this->assertTrue( $annotations['idempotent'], 'get-sync-status should be idempotent.' );
		$this->assertTrue(
			$meta['show_in_rest'] ?? false,
			'get-sync-status must be exposed via show_in_rest for the REST bridge.'
		);
		$this->assertTrue(
			$meta['mcp']['public'] ?? false,
			'get-sync-status must be opted into MCP discovery.'
		);

		// Behavioural permission check via the registered ability's own
		// check_permissions(). Subscribers must not pass the merchant gate.
		$subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );
		$this->assertFalse(
			$ability->check_permissions( array() ),
			'Wired permission_callback must deny subscribers.'
		);
	}
}
