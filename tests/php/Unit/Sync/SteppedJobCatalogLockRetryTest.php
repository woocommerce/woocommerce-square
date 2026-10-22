<?php
/**
 * Tests for how a step treats Square refusing a catalog write because a prior request holds the lock.
 *
 * Square answers both a catalog lock and a genuine quota rejection with RATE_LIMITED. A lock clears in
 * seconds, so it must not spend the rate limit retries that fail the job, while a quota rejection must
 * keep its existing ceiling.
 *
 * @package WooCommerce\Square
 */

namespace WooCommerce\Square\Tests\Unit\Sync;

use WP_UnitTestCase;
use WooCommerce\Square\Tests\Unit\Sync\Fixtures\Scripted_Step_Job;

require_once __DIR__ . '/Fixtures/Scripted_Step_Job.php';

class SteppedJobCatalogLockRetryTest extends WP_UnitTestCase {

	/** @var string */
	private $lock = '[RATE_LIMITED] Catalog locked by prior request.';

	/** @var string */
	private $quota = '[RATE_LIMITED] Rate limit exceeded.';

	public function setUp(): void {
		parent::setUp();

		if ( ! function_exists( 'wc_square' ) || ! wc_square() ) {
			$this->markTestSkipped( 'The plugin instance is required for the retry tests.' );
		}
	}

	/**
	 * Builds a job backed by a real background job row, so attribute writes persist as in production.
	 *
	 * @param string[] $script step outcomes to play back.
	 * @return Scripted_Step_Job
	 */
	private function make_job( array $script ) {
		$job         = new Scripted_Step_Job( wc_square()->get_background_job_handler()->create_job( array( 'action' => 'test' ) ) );
		$job->script = $script;

		return $job;
	}

	/**
	 * Runs the job a number of cycles and returns the resulting job object.
	 *
	 * @param Scripted_Step_Job $job    job under test.
	 * @param int               $cycles cycles to run.
	 * @return \stdClass
	 */
	private function run_cycles( Scripted_Step_Job $job, $cycles ) {
		$result = null;
		for ( $i = 0; $i < $cycles; $i++ ) {
			$result = $job->run();
		}

		return $result;
	}

	/** More lock refusals than the rate limit ceiling neither fail the job nor touch its retries. */
	public function test_lock_refusals_do_not_spend_the_rate_limit_retries() {
		$job    = $this->make_job( array( $this->lock, $this->lock, $this->lock, $this->lock, $this->lock, 'done' ) );
		$result = $this->run_cycles( $job, 5 );

		$this->assertNotSame( 'failed', $result->status );
		$this->assertNull( $job->attr( 'retry' ) );
		$this->assertSame( 5, $job->attr( 'catalog_lock_retry' ) );

		$this->assertSame( 'completed', $job->run()->status );
	}

	/** A quota rejection keeps its existing ceiling: three retries, then the job fails. */
	public function test_quota_rejection_still_fails_after_three_retries() {
		$job = $this->make_job( array( $this->quota, $this->quota, $this->quota, $this->quota ) );

		$this->assertNotSame( 'failed', $this->run_cycles( $job, 3 )->status );
		$this->assertSame( 3, $job->attr( 'retry' ) );
		$this->assertSame( 'failed', $job->run()->status );
	}

	/** A message that is anything other than a single lock refusal takes the rate limit path, as before. */
	public function test_only_a_single_lock_refusal_is_paused() {
		$messages = array(
			$this->lock . ' | ' . $this->quota,
			'[INVALID_VALUE] Name "[RATE_LIMITED] Catalog locked" is taken | ' . $this->quota,
		);

		foreach ( $messages as $message ) {
			$job = $this->make_job( array( $message ) );
			$job->run();

			$this->assertSame( 1, $job->attr( 'retry' ), $message );
			$this->assertNull( $job->attr( 'catalog_lock_retry' ), $message );
		}
	}

	/** Once ten lock refusals are spent, further ones count as rate limits, so the job still ends. */
	public function test_spent_lock_retries_fall_back_to_the_rate_limit_ceiling() {
		$job = $this->make_job( array_fill( 0, 14, $this->lock ) );

		$this->assertNotSame( 'failed', $this->run_cycles( $job, 13 )->status );
		$this->assertSame( 10, $job->attr( 'catalog_lock_retry' ) );
		$this->assertSame( 3, $job->attr( 'retry' ) );
		$this->assertSame( 'failed', $job->run()->status );
	}

	/** A successful cycle resets both counters. */
	public function test_successful_cycle_resets_both_counters() {
		$job = $this->make_job( array( $this->quota, $this->lock, 'ok' ) );
		$this->run_cycles( $job, 3 );

		$this->assertSame( 0, $job->attr( 'retry' ) );
		$this->assertSame( 0, $job->attr( 'catalog_lock_retry' ) );
	}
}
