<?php
/**
 * Tests for resuming a full sync that stopped partway.
 *
 * A full sync that fails is resumed on its own, carrying the progress of the attempt that stopped so
 * the next one continues rather than starting over, and gives up once several attempts in a row
 * make no progress. Jobs are failed and completed through the background job handler, so the same
 * hooks fire as in production.
 *
 * @package WooCommerce\Square
 */

namespace WooCommerce\Square\Tests\Unit\Handlers;

use WP_UnitTestCase;
use WooCommerce\Square\Sync\Records;

class Sync_Resume_Test extends WP_UnitTestCase {

	/** @var int[] */
	private $product_ids = array( 101, 102, 103, 104, 105, 106, 107, 108, 109, 110 );

	public function setUp(): void {
		parent::setUp();

		if ( ! function_exists( 'wc_square' ) || ! wc_square() || ! function_exists( 'as_next_scheduled_action' ) ) {
			$this->markTestSkipped( 'The plugin instance and Action Scheduler are required.' );
		}

		wc_square()->get_settings_handler()->update_option( 'system_of_record', 'woocommerce' );
		add_filter( 'wc_square_access_token', array( $this, 'access_token' ) );

		wc_square()->get_background_job_handler()->clear_all_jobs();
		wc_square()->get_sync_handler()->clear_sync_resume();
		Records::clean_records();
	}

	public function tearDown(): void {
		remove_filter( 'wc_square_access_token', array( $this, 'access_token' ) );
		wc_square()->get_background_job_handler()->clear_all_jobs();
		wc_square()->get_sync_handler()->clear_sync_resume();
		as_unschedule_all_actions( 'wc_square_job_runner' );
		parent::tearDown();
	}

	public function access_token() {
		return 'test-token';
	}

	/**
	 * Creates a full sync job as start_manual_sync() would, with the given progress.
	 *
	 * @param array $attrs extra job attributes
	 * @return \stdClass
	 */
	private function create_full_sync( array $attrs = array() ) {
		return wc_square()->get_background_job_handler()->create_job(
			array_merge(
				array(
					'action'      => 'sync',
					'manual'      => true,
					'full_sync'   => true,
					'product_ids' => $this->product_ids,
				),
				$attrs
			)
		);
	}

	/**
	 * Fails a job through the handler, which fires the job_failed hook.
	 *
	 * @param \stdClass $job job
	 */
	private function fail_sync( $job ) {
		wc_square()->get_background_job_handler()->fail_job( $job, '[RATE_LIMITED] Catalog locked by prior request.' );
	}

	private function latest_record() {
		$records = Records::get_records();

		return reset( $records );
	}

	private function next_resume() {
		return as_next_scheduled_action( 'wc_square_resume_sync' );
	}

	/** A full sync that fails partway is scheduled to resume and keeps what it had done. */
	public function test_a_failed_full_sync_is_scheduled_to_resume_with_its_progress() {
		$job = $this->create_full_sync(
			array(
				'processed_product_ids'        => array( 101, 102, 103, 104 ),
				'inventory_push_product_ids'   => array( 104 ),
				'pull_inventory_variation_ids' => array( 'VAR1', 'VAR2' ),
			)
		);

		$this->fail_sync( $job );

		$state = wc_square()->get_sync_handler()->get_sync_resume_state();

		$this->assertNotNull( $state );
		$this->assertSame( 10, $state['total'] );
		$this->assertSame( 4, $state['done'] );
		$this->assertSame( 6, $state['remaining'] );
		$this->assertSame( 0, $state['attempts'] );
		$this->assertFalse( $state['needs_attention'] );
		$this->assertEqualsCanonicalizing( array( 101, 102, 103, 104 ), $state['processed_product_ids'] );
		$this->assertSame( array( 104 ), $state['inventory_push_product_ids'] );
		$this->assertSame( array( 'VAR1', 'VAR2' ), $state['pull_inventory_variation_ids'] );

		$next = $this->next_resume();
		$this->assertIsInt( $next, 'A resume must be scheduled.' );
		$this->assertSame( $state['next_at'], $next );
		$this->assertGreaterThanOrEqual( time() + 5 * MINUTE_IN_SECONDS - 5, $next, 'An attempt that made progress waits 5 minutes plus jitter.' );
		$this->assertLessThanOrEqual( time() + 6 * MINUTE_IN_SECONDS + 5, $next );

		$record = $this->latest_record();
		$this->assertSame( 'notice', $record->get_type() );
		$this->assertStringContainsString( 'this sync updated 4 of 10 products, 6 still to go', $record->get_message() );
		$this->assertStringContainsString( 'continue automatically', $record->get_message() );
	}

	/** The interval poll clears every job row; the progress must survive that. */
	public function test_the_progress_survives_the_interval_poll_clearing_jobs() {
		$this->fail_sync( $this->create_full_sync( array( 'processed_product_ids' => array( 101, 102 ) ) ) );

		wc_square()->get_background_job_handler()->clear_all_jobs();

		$state = wc_square()->get_sync_handler()->get_sync_resume_state();
		$this->assertNotNull( $state );
		$this->assertSame( 2, $state['done'] );
		$this->assertIsInt( $this->next_resume() );
	}

	/** The resume starts a full sync seeded with the carried progress. */
	public function test_the_resume_starts_a_full_sync_that_skips_what_was_done() {
		$this->fail_sync(
			$this->create_full_sync(
				array(
					'processed_product_ids'        => array( 101, 102, 103 ),
					'inventory_push_product_ids'   => array( 103 ),
					'pull_inventory_variation_ids' => array( 'VAR1' ),
				)
			)
		);
		wc_square()->get_background_job_handler()->clear_all_jobs();

		wc_square()->get_sync_handler()->resume_sync();

		$job = wc_square()->get_background_job_handler()->get_job();

		$this->assertNotNull( $job, 'The resume must start a job.' );
		$this->assertTrue( $job->full_sync );
		$this->assertTrue( $job->resumed );
		$this->assertEqualsCanonicalizing( array( 101, 102, 103 ), $job->processed_product_ids );
		$this->assertSame( array( 103 ), $job->inventory_push_product_ids );
		$this->assertSame( array( 'VAR1' ), $job->pull_inventory_variation_ids );
	}

	/** A resumed attempt that pushed more products resets the ceiling. */
	public function test_an_attempt_that_made_progress_resets_the_ceiling() {
		$this->fail_sync( $this->create_full_sync( array( 'processed_product_ids' => array( 101 ) ) ) );
		$this->fail_sync(
			$this->create_full_sync(
				array(
					'resumed'               => true,
					'processed_product_ids' => array( 101 ),
				)
			)
		);

		$this->assertSame( 1, wc_square()->get_sync_handler()->get_sync_resume_state()['attempts'] );

		$this->fail_sync(
			$this->create_full_sync(
				array(
					'resumed'               => true,
					'processed_product_ids' => array( 101, 102, 103 ),
				)
			)
		);

		$state = wc_square()->get_sync_handler()->get_sync_resume_state();
		$this->assertSame( 0, $state['attempts'] );
		$this->assertSame( 3, $state['done'] );
	}

	/** Once every product is pushed, moving inventory still counts as progress. */
	public function test_inventory_progress_counts_once_every_product_is_pushed() {
		$this->fail_sync(
			$this->create_full_sync(
				array(
					'processed_product_ids'        => $this->product_ids,
					'pull_inventory_variation_ids' => array( 'VAR1', 'VAR2', 'VAR3' ),
				)
			)
		);
		$this->fail_sync(
			$this->create_full_sync(
				array(
					'resumed'                      => true,
					'processed_product_ids'        => $this->product_ids,
					'pull_inventory_variation_ids' => array( 'VAR3' ),
				)
			)
		);

		$this->assertSame( 0, wc_square()->get_sync_handler()->get_sync_resume_state()['attempts'] );
	}

	/** Attempts that make no progress stop at the ceiling and ask for attention. */
	public function test_it_stops_and_asks_for_attention_after_the_ceiling() {
		reset_phpmailer_instance();

		$this->fail_sync( $this->create_full_sync( array( 'processed_product_ids' => array( 101, 102 ) ) ) );

		$previous_delay = 0;
		for ( $attempt = 1; $attempt < 5; $attempt++ ) {
			$this->fail_sync(
				$this->create_full_sync(
					array(
						'resumed'               => true,
						'processed_product_ids' => array( 101, 102 ),
					)
				)
			);

			$state = wc_square()->get_sync_handler()->get_sync_resume_state();
			$this->assertSame( $attempt, $state['attempts'] );
			$this->assertFalse( $state['needs_attention'] );

			$delay = $state['next_at'] - time();
			$this->assertGreaterThan( $previous_delay - MINUTE_IN_SECONDS, $delay, 'The wait grows with each attempt that made no progress.' );
			$this->assertLessThanOrEqual( 31 * MINUTE_IN_SECONDS + 5, $delay, 'The wait is capped.' );
			$previous_delay = $delay;
		}

		$this->assertSame( 0, count( tests_retrieve_phpmailer_instance()->mock_sent ), 'No email while the sync is still resuming on its own.' );

		$this->fail_sync(
			$this->create_full_sync(
				array(
					'resumed'               => true,
					'processed_product_ids' => array( 101, 102 ),
				)
			)
		);

		$state = wc_square()->get_sync_handler()->get_sync_resume_state();
		$this->assertSame( 5, $state['attempts'] );
		$this->assertTrue( $state['needs_attention'] );
		$this->assertSame( 0, $state['next_at'] );
		$this->assertFalse( $this->next_resume(), 'Nothing is scheduled once it gives up.' );

		// Records written in the same second have no defined order, so look the failure up by type.
		$failed = array_values(
			array_filter(
				Records::get_records(),
				function ( $record ) {
					return 'failed' === $record->get_type();
				}
			)
		);
		$this->assertCount( 1, $failed, 'Only the attempt that gave up is recorded as a failure.' );
		$record = $failed[0];
		$this->assertStringContainsString( 'this sync updated 2 of 10 products, 8 still to go', $record->get_message() );
		$this->assertStringContainsString( 'needs attention', $record->get_message() );

		// A late resume event does nothing once it has given up.
		wc_square()->get_sync_handler()->resume_sync();
		$this->assertNull( wc_square()->get_background_job_handler()->get_job() );
	}

	/** The merchant clicking Sync now starts a fresh full sync, as before, and drops saved progress. */
	public function test_sync_now_starts_fresh_and_drops_saved_progress() {
		$this->fail_sync( $this->create_full_sync( array( 'processed_product_ids' => array( 101, 102 ) ) ) );
		wc_square()->get_background_job_handler()->clear_all_jobs();

		$job = wc_square()->get_sync_handler()->start_manual_sync();

		$this->assertFalse( isset( $job->resumed ) );
		$this->assertSame( array(), $job->processed_product_ids );
		$this->assertNull( wc_square()->get_sync_handler()->get_sync_resume_state() );
		$this->assertFalse( $this->next_resume() );
	}

	/** A resume that finds another sync running waits for it without counting an attempt. */
	public function test_the_resume_waits_for_a_running_sync_without_counting() {
		$this->fail_sync( $this->create_full_sync( array( 'processed_product_ids' => array( 101 ) ) ) );
		wc_square()->get_background_job_handler()->clear_all_jobs();

		$poll = wc_square()->get_background_job_handler()->create_job( array( 'action' => 'poll' ) );

		wc_square()->get_sync_handler()->resume_sync();

		$this->assertSame( $poll->id, wc_square()->get_background_job_handler()->get_job()->id, 'No second job is queued.' );

		$state = wc_square()->get_sync_handler()->get_sync_resume_state();
		$this->assertSame( 0, $state['attempts'] );
		$this->assertIsInt( $this->next_resume() );
		$this->assertLessThanOrEqual( time() + 2 * MINUTE_IN_SECONDS + 5, $this->next_resume() );
	}

	/** A completed full sync clears the resume state and any scheduled resume. */
	public function test_completing_a_full_sync_clears_the_resume() {
		$this->fail_sync( $this->create_full_sync( array( 'processed_product_ids' => array( 101 ) ) ) );

		wc_square()->get_background_job_handler()->complete_job( $this->create_full_sync( array( 'resumed' => true ) ) );

		$this->assertNull( wc_square()->get_sync_handler()->get_sync_resume_state() );
		$this->assertFalse( $this->next_resume() );
	}

	/** The Clear Square Sync tool stops a pending resume. */
	public function test_the_clear_tool_stops_a_pending_resume() {
		$this->fail_sync( $this->create_full_sync( array( 'processed_product_ids' => array( 101 ) ) ) );

		wc_square()->get_background_job_handler()->run_clear_background_jobs();

		$this->assertNull( wc_square()->get_sync_handler()->get_sync_resume_state() );
		$this->assertFalse( $this->next_resume() );
	}

	/** Disconnecting Square stops a pending resume. */
	public function test_a_disconnected_store_does_not_resume() {
		$this->fail_sync( $this->create_full_sync( array( 'processed_product_ids' => array( 101 ) ) ) );
		wc_square()->get_background_job_handler()->clear_all_jobs();
		remove_filter( 'wc_square_access_token', array( $this, 'access_token' ) );
		add_filter( 'wc_square_access_token', '__return_empty_string' );

		wc_square()->get_sync_handler()->resume_sync();

		remove_filter( 'wc_square_access_token', '__return_empty_string' );

		$this->assertNull( wc_square()->get_background_job_handler()->get_job() );
		$this->assertNull( wc_square()->get_sync_handler()->get_sync_resume_state() );
	}

	/** A product save sync is not a full sync and keeps the existing behaviour. */
	public function test_a_subset_sync_failure_is_not_resumed() {
		$job = wc_square()->get_background_job_handler()->create_job(
			array(
				'action'      => 'sync',
				'manual'      => true,
				'full_sync'   => false,
				'product_ids' => array( 101 ),
			)
		);

		$this->fail_sync( $job );

		$this->assertNull( wc_square()->get_sync_handler()->get_sync_resume_state() );
		$this->assertFalse( $this->next_resume() );
		$this->assertSame( 'Sync failed. Please try again', $this->latest_record()->get_message() );
	}

	/** Sync now with an empty product list is flagged as a full sync; a product list is not. */
	public function test_start_manual_sync_flags_full_syncs_only() {
		$full   = wc_square()->get_sync_handler()->start_manual_sync();
		$subset = wc_square()->get_sync_handler()->start_manual_sync( array( 101 ) );

		$this->assertTrue( $full->full_sync );
		$this->assertFalse( $subset->full_sync );
		$this->assertFalse( isset( $full->resumed ), 'Nothing to resume, so the job starts fresh.' );
	}

	/** Failing the same job twice (fail(), then the shutdown handler) is counted once. */
	public function test_failing_the_same_job_twice_counts_once() {
		$this->fail_sync( $this->create_full_sync( array( 'processed_product_ids' => array( 101 ) ) ) );

		$job = $this->create_full_sync(
			array(
				'resumed'               => true,
				'processed_product_ids' => array( 101 ),
			)
		);
		$this->fail_sync( $job );
		Records::clean_records();
		$this->fail_sync( $job );

		$this->assertSame( 1, wc_square()->get_sync_handler()->get_sync_resume_state()['attempts'] );
		$this->assertEmpty( Records::get_records(), 'The second failure writes no record.' );
	}

	/** A resume due after the system of record moved to Square does not run, and drops the progress. */
	public function test_the_resume_stops_when_the_system_of_record_changes() {
		$this->fail_sync( $this->create_full_sync( array( 'processed_product_ids' => array( 101, 102 ) ) ) );
		wc_square()->get_background_job_handler()->clear_all_jobs();

		wc_square()->get_settings_handler()->update_option( 'system_of_record', 'square' );
		wc_square()->get_sync_handler()->resume_sync();

		$this->assertNull( wc_square()->get_background_job_handler()->get_job() );
		$this->assertNull( wc_square()->get_sync_handler()->get_sync_resume_state() );
	}

	/** Square as system of record keeps the existing failure behaviour. */
	public function test_a_square_system_of_record_failure_is_not_resumed() {
		wc_square()->get_settings_handler()->update_option( 'system_of_record', 'square' );

		$this->fail_sync( $this->create_full_sync( array( 'processed_product_ids' => array( 101 ) ) ) );

		$this->assertNull( wc_square()->get_sync_handler()->get_sync_resume_state() );
		$this->assertFalse( $this->next_resume() );
		$this->assertSame( 'Sync failed. Please try again', $this->latest_record()->get_message() );
	}
}
