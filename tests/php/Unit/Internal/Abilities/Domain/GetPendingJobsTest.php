<?php
/**
 * Tests for WooCommerce\Square\Internal\Abilities\Domain\GetPendingJobs.
 *
 * @package WooCommerce\Square
 */

namespace WooCommerce\Square\Tests\Unit\Internal\Abilities\Domain;

use WP_UnitTestCase;
use WooCommerce\Square\Internal\Abilities\Abilities_Registrar;
use WooCommerce\Square\Internal\Abilities\Domain\GetPendingJobs;

class GetPendingJobsTest extends WP_UnitTestCase {

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
		$args = GetPendingJobs::get_registration_args();

		$this->assertSame(
			array( GetPendingJobs::class, 'execute' ),
			$args['execute_callback']
		);
		$this->assertSame(
			array( Abilities_Registrar::class, 'can_manage_woocommerce_square' ),
			$args['permission_callback']
		);
	}

	public function test_execute_returns_empty_array_when_no_jobs_present() {
		$result = GetPendingJobs::execute( array() );
		$this->assertIsArray( $result );
		$this->assertSame( array(), $result );
	}

	public function test_execute_normalizes_job_object_fields() {
		$plugin = function_exists( 'wc_square' ) ? wc_square() : null;
		if ( ! $plugin || ! method_exists( $plugin, 'get_background_job_handler' ) ) {
			$this->markTestSkipped( 'Background_Job handler not available in this environment.' );
		}
		$handler = $plugin->get_background_job_handler();
		if ( ! $handler || ! method_exists( $handler, 'create_job' ) ) {
			$this->markTestSkipped( 'Background_Job::create_job() not available.' );
		}

		// Create a real job through the handler so the wp_options shape matches production.
		$job = $handler->create_job(
			array(
				'action'                => 'product_import',
				'percentage'            => 42.5,
				'product_ids'           => array( 1, 2, 3 ),
				'processed_product_ids' => array( 1 ),
				'manual'                => true,
			)
		);
		$this->assertNotNull( $job );

		$result = GetPendingJobs::execute( array() );
		$this->assertIsArray( $result );
		$this->assertNotEmpty( $result );

		$entry = $result[0];
		$this->assertSame( $job->id, $entry['id'] );
		$this->assertSame( 'queued', $entry['status'] );
		$this->assertSame( 'product_import', $entry['action'] );
		$this->assertSame( 42.5, $entry['percentage'] );
		$this->assertTrue( $entry['manual'] );
		$this->assertSame( 3, $entry['product_count'] );
		$this->assertSame( 1, $entry['processed_count'] );
		$this->assertSame( 0, $entry['updated_count'] );
		$this->assertSame( 0, $entry['skipped_count'] );

		// Cleanup so other tests don't see this job.
		if ( method_exists( $handler, 'delete_job' ) ) {
			$handler->delete_job( $job );
		}
	}

	public function test_ability_is_registered_with_expected_shape() {
		if ( ! function_exists( 'wp_get_ability' ) ) {
			$this->markTestSkipped( 'Abilities API query functions not available in this WP version.' );
		}

		$ability = wp_get_ability( 'woocommerce-square/get-pending-jobs' );
		$this->assertNotNull( $ability, 'woocommerce-square/get-pending-jobs should be registered.' );
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
