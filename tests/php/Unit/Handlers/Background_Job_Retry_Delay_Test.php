<?php
/**
 * Tests for the delay the job runner waits before running a paused sync step again.
 *
 * A catalog lock clears in seconds, so a lock pause runs again after a short fixed delay, while a rate
 * limit retry keeps its exponential backoff. Swapping the two would either hammer Square on a quota or
 * waste minutes on every lock, so the scheduled delay is pinned here.
 *
 * @package WooCommerce\Square
 */

namespace WooCommerce\Square\Tests\Unit\Handlers;

use WP_UnitTestCase;

class Background_Job_Retry_Delay_Test extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();

		if ( ! function_exists( 'wc_square' ) || ! wc_square() || ! function_exists( 'as_next_scheduled_action' ) ) {
			$this->markTestSkipped( 'The plugin and Action Scheduler are required for the job runner tests.' );
		}

		wc_square()->get_background_job_handler()->clear_all_jobs();
		as_unschedule_all_actions( 'wc_square_job_runner' );
	}

	public function tearDown(): void {
		wc_square()->get_background_job_handler()->clear_all_jobs();
		as_unschedule_all_actions( 'wc_square_job_runner' );
		parent::tearDown();
	}

	/**
	 * Runs the job runner once on a processing job carrying the given counters.
	 *
	 * The job's action is one the runner does not recognise, so no step runs and the counters reach the
	 * scheduling decision exactly as set.
	 *
	 * @param array $counters job attributes, such as the retry counters.
	 * @return int|bool seconds until the next runner, or true when it was enqueued to run now
	 */
	private function next_runner_after( array $counters ) {
		$handler = wc_square()->get_background_job_handler();
		$job     = $handler->create_job( array_merge( array( 'action' => 'retry_delay_test' ), $counters ) );

		$job->status = 'processing';
		$handler->update_job( $job );

		$handler->handle();

		$next = as_next_scheduled_action( 'wc_square_job_runner' );

		return is_int( $next ) ? $next - time() : $next;
	}

	/** A catalog lock pause runs again after the short fixed delay. */
	public function test_a_lock_pause_uses_the_short_delay() {
		$delay = $this->next_runner_after( array( 'catalog_lock_retry' => 1 ) );

		$this->assertIsInt( $delay, 'The runner must be scheduled for later, not enqueued now.' );
		$this->assertEqualsWithDelta( 10, $delay, 2 );
	}

	/** A rate limit retry the job is still carrying keeps its backoff, so the quota path is unchanged. */
	public function test_a_rate_limit_retry_wins_over_a_lock_pause() {
		$delay = $this->next_runner_after(
			array(
				'catalog_lock_retry' => 1,
				'retry'              => 2,
			)
		);

		$this->assertIsInt( $delay, 'The runner must be scheduled for later, not enqueued now.' );
		$this->assertEqualsWithDelta( 120, $delay, 2 );
	}

	/** A rate limit retry keeps the existing exponential backoff. */
	public function test_a_rate_limit_retry_keeps_the_backoff() {
		$delay = $this->next_runner_after( array( 'retry' => 2 ) );

		$this->assertIsInt( $delay, 'The runner must be scheduled for later, not enqueued now.' );
		$this->assertEqualsWithDelta( 120, $delay, 2 );
	}

	/** With nothing to wait for, the next step is enqueued to run now. */
	public function test_no_retry_enqueues_the_runner_now() {
		$this->assertTrue( $this->next_runner_after( array() ) );
	}
}
