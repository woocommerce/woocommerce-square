<?php
/**
 * Tests that the admin stalled-sync recovery only looks up background jobs when product sync is on.
 *
 * @package WooCommerce\Square
 */

namespace WooCommerce\Square\Tests\Unit\Handlers;

use WP_UnitTestCase;

class Admin_Recovery_Job_Lookup_Test extends WP_UnitTestCase {

	/**
	 * Queries that touched the background job option rows.
	 *
	 * @var string[]
	 */
	private $job_queries = array();

	/**
	 * The system of record setting before the test, restored in tearDown().
	 *
	 * @var mixed
	 */
	private $original_system_of_record;

	public function setUp(): void {
		parent::setUp();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		if ( ! current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
			$this->markTestSkipped( 'WooCommerce capabilities are not available in this environment.' );
		}

		$this->original_system_of_record = wc_square()->get_settings_handler()->get_option( 'system_of_record' );

		delete_transient( 'wc_square_admin_recovery_check' );
		delete_option( 'wc_square_failed_action_cleanup_at' );

		add_filter( 'query', array( $this, 'record_job_query' ) );
	}

	public function tearDown(): void {
		remove_filter( 'query', array( $this, 'record_job_query' ) );
		delete_transient( 'wc_square_admin_recovery_check' );
		delete_option( 'wc_square_failed_action_cleanup_at' );
		wc_square()->get_settings_handler()->update_option( 'system_of_record', $this->original_system_of_record );
		wp_set_current_user( 0 );

		parent::tearDown();
	}

	/**
	 * Records queries that read the background job option rows.
	 *
	 * @param string $query SQL query.
	 * @return string
	 */
	public function record_job_query( $query ) {
		if ( false !== strpos( $query, 'wc_square_background_sync_job_' ) ) {
			$this->job_queries[] = $query;
		}

		return $query;
	}

	/**
	 * With product sync disabled, admin page loads must not scan the options table for jobs.
	 */
	public function test_skips_job_lookup_when_product_sync_disabled() {
		wc_square()->get_settings_handler()->update_option( 'system_of_record', 'disabled' );

		wc_square()->get_background_job_handler()->maybe_recover_stuck_sync_from_admin();

		$this->assertSame( array(), $this->job_queries );
	}

	/**
	 * Failed action cleanup also covers order sync, so it must still run with product sync disabled.
	 */
	public function test_cleans_up_failed_actions_when_product_sync_disabled() {
		wc_square()->get_settings_handler()->update_option( 'system_of_record', 'disabled' );

		wc_square()->get_background_job_handler()->maybe_recover_stuck_sync_from_admin();

		$this->assertNotFalse( get_option( 'wc_square_failed_action_cleanup_at', false ) );
	}

	/**
	 * With product sync enabled, the recovery check still runs.
	 */
	public function test_runs_job_lookup_when_product_sync_enabled() {
		wc_square()->get_settings_handler()->update_option( 'system_of_record', 'square' );

		wc_square()->get_background_job_handler()->maybe_recover_stuck_sync_from_admin();

		$this->assertNotEmpty( $this->job_queries );
		$this->assertNotFalse( get_option( 'wc_square_failed_action_cleanup_at', false ) );
	}
}
