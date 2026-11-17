<?php
/**
 * Tests that the Sync handler only looks up background jobs when product sync is on.
 *
 * @package WooCommerce\Square
 */

namespace WooCommerce\Square\Tests\Unit\Handlers;

use WooCommerce\Square\Handlers\Sync;
use WP_UnitTestCase;

require_once __DIR__ . '/Sync_Settings.php';
require_once __DIR__ . '/Sync_Job_Handler.php';
require_once __DIR__ . '/Sync_Notice_Handler.php';
require_once __DIR__ . '/Sync_Plugin.php';

class Sync_Job_Lookup_Test extends WP_UnitTestCase {

	/**
	 * Plugin double.
	 *
	 * @var Sync_Plugin
	 */
	private $plugin;

	/**
	 * Sync handler under test.
	 *
	 * @var Sync
	 */
	private $sync;

	public function setUp(): void {
		parent::setUp();

		$this->plugin = new Sync_Plugin();

		// The constructor requires the real Plugin class, so build the handler without it and inject the double.
		$reflection = new \ReflectionClass( Sync::class );
		$this->sync = $reflection->newInstanceWithoutConstructor();

		$plugin_property = $reflection->getProperty( 'plugin' );
		$plugin_property->setAccessible( true );
		$plugin_property->setValue( $this->sync, $this->plugin );

		$event_property = $reflection->getProperty( 'sync_scheduled_event_name' );
		$event_property->setAccessible( true );
		$event_property->setValue( $this->sync, 'wc_square_sync' );

		update_option( 'wc_square_last_synced_at', time() - DAY_IN_SECONDS );
	}

	public function tearDown(): void {
		as_unschedule_all_actions( 'wc_square_sync', array(), 'square' );
		delete_option( 'wc_square_last_synced_at' );

		parent::tearDown();
	}

	/**
	 * With product sync disabled, the init hook must not look up background jobs.
	 */
	public function test_schedule_sync_skips_job_lookup_when_product_sync_disabled() {
		$this->sync->schedule_sync();

		$this->assertSame( 0, $this->plugin->jobs->lookups );
		$this->assertFalse( as_next_scheduled_action( 'wc_square_sync', array(), 'square' ) );
	}

	/**
	 * With product sync disabled, the admin notice must not look up background jobs.
	 */
	public function test_import_warning_skips_job_lookup_when_product_sync_disabled() {
		$this->sync->render_import_no_navigation_warning();

		$this->assertSame( 0, $this->plugin->jobs->lookups );
		$this->assertSame( array(), $this->plugin->notices->notices );
	}

	/**
	 * A disconnected store must not look up background jobs either.
	 */
	public function test_skips_job_lookup_when_disconnected() {
		$this->plugin->settings->product_sync_enabled = true;
		$this->plugin->settings->connected            = false;

		$this->sync->schedule_sync();
		$this->sync->render_import_no_navigation_warning();

		$this->assertSame( 0, $this->plugin->jobs->lookups );
	}

	/**
	 * With product sync enabled and no job running, the interval sync is scheduled.
	 */
	public function test_schedule_sync_schedules_when_product_sync_enabled() {
		$this->plugin->settings->product_sync_enabled = true;

		$this->sync->schedule_sync();

		$this->assertSame( 1, $this->plugin->jobs->lookups );

		$next_run = as_next_scheduled_action( 'wc_square_sync', array(), 'square' );
		$this->assertNotFalse( $next_run );
		$this->assertEqualsWithDelta( time() + $this->plugin->settings->sync_interval, $next_run, 5 );
	}

	/**
	 * A sync that is already running stops a new interval sync from being scheduled.
	 */
	public function test_schedule_sync_bails_when_job_in_progress() {
		$this->plugin->settings->product_sync_enabled = true;
		$this->plugin->jobs->job                      = (object) array(
			'status' => 'processing',
			'action' => 'poll',
		);

		$this->sync->schedule_sync();

		$this->assertFalse( as_next_scheduled_action( 'wc_square_sync', array(), 'square' ) );
	}

	/**
	 * With product sync enabled, the warning shows while a product import runs.
	 */
	public function test_import_warning_shows_during_product_import() {
		$this->plugin->settings->product_sync_enabled = true;
		$this->plugin->jobs->job                      = (object) array(
			'status' => 'processing',
			'action' => 'product_import',
		);

		$this->sync->render_import_no_navigation_warning();

		$this->assertSame( array( 'wc-square-sync-in-progress-message' ), $this->plugin->notices->notices );
	}

	/**
	 * Once the import finishes, the warning is gone.
	 */
	public function test_import_warning_hidden_after_import_completes() {
		$this->plugin->settings->product_sync_enabled = true;
		$this->plugin->jobs->job                      = (object) array(
			'status' => 'completed',
			'action' => 'product_import',
		);

		$this->sync->render_import_no_navigation_warning();

		$this->assertSame( array(), $this->plugin->notices->notices );
	}
}
