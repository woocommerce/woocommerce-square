<?php
/**
 * Tests for how a step treats Square refusing a catalog write because a prior request holds the lock.
 *
 * Square answers both a catalog lock and a genuine quota rejection with RATE_LIMITED. A lock clears in
 * seconds, so it must not spend the rate limit retries that fail the job, while a quota rejection must
 * keep its existing ceiling. Both counters and the failure point are pinned here.
 *
 * @package WooCommerce\Square
 */

namespace WooCommerce\Square\Tests\Unit\Sync;

use WP_UnitTestCase;
use WooCommerce\Square\Sync\Stepped_Job;
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

	/** Only a message whose every RATE_LIMITED error is a lock counts as a lock refusal. */
	public function test_lock_refusal_is_told_apart_from_a_quota_rejection() {
		$job = $this->make_job( array() );

		$this->assertTrue( $job->lock_refusal( $this->lock ) );
		$this->assertTrue( $job->lock_refusal( $this->lock . ' | ' . $this->lock ) );
		$this->assertFalse( $job->lock_refusal( '[INVALID_VALUE] Name "[RATE_LIMITED] Catalog locked" is taken' ), 'Bracketed text inside a detail is not a code.' );
		$this->assertFalse( $job->lock_refusal( $this->quota ) );
		$this->assertFalse( $job->lock_refusal( $this->lock . ' | ' . $this->quota ), 'A quota error alongside a lock wants the long backoff.' );
		$this->assertFalse( $job->lock_refusal( '[INVALID_REQUEST_ERROR] Catalog locked by prior request.' ), 'The detail alone is not enough.' );
		$this->assertFalse( $job->lock_refusal( '' ) );
	}

	/** More lock refusals than the rate limit ceiling do not fail the job or touch its retries. */
	public function test_lock_refusals_do_not_spend_the_rate_limit_retries() {
		$job    = $this->make_job( array( $this->lock, $this->lock, $this->lock, $this->lock, $this->lock ) );
		$result = $this->run_cycles( $job, 5 );

		$this->assertNotSame( 'failed', $result->status );
		$this->assertNull( $job->attr( 'retry' ), 'A lock refusal never sets the rate limit retry.' );
		$this->assertSame( 5, $job->attr( 'catalog_lock_retry' ) );
	}

	/** Once the lock retries are spent, the next refusal counts as a rate limit and the lock budget refreshes. */
	public function test_spent_lock_retries_fall_through_to_the_rate_limit_retry() {
		$job = $this->make_job( array_fill( 0, Stepped_Job::MAX_CATALOG_LOCK_RETRIES + 1, $this->lock ) );
		$this->run_cycles( $job, Stepped_Job::MAX_CATALOG_LOCK_RETRIES + 1 );

		$this->assertSame( 1, $job->attr( 'retry' ) );
		$this->assertSame( 0, $job->attr( 'catalog_lock_retry' ) );
	}

	/** A quota rejection keeps its existing ceiling: three retries, then the job fails. */
	public function test_quota_rejection_still_fails_after_three_retries() {
		$job = $this->make_job( array( $this->quota, $this->quota, $this->quota, $this->quota ) );

		$result = $this->run_cycles( $job, 3 );
		$this->assertNotSame( 'failed', $result->status );
		$this->assertSame( 3, $job->attr( 'retry' ) );

		$result = $job->run();
		$this->assertSame( 'failed', $result->status );
	}

	/** A quota rejection after a lock clears the lock counter, so the runner uses the long backoff. */
	public function test_quota_rejection_clears_the_lock_counter() {
		$job    = $this->make_job( array( $this->lock, $this->quota ) );
		$result = $this->run_cycles( $job, 2 );

		$this->assertSame( 1, $job->attr( 'retry' ) );
		$this->assertSame( 0, $result->catalog_lock_retry );
	}

	/** A successful cycle resets both counters. */
	public function test_successful_cycle_resets_both_counters() {
		$job = $this->make_job( array( $this->quota, $this->lock, 'ok' ) );
		$this->run_cycles( $job, 3 );

		$this->assertSame( 0, $job->attr( 'retry' ) );
		$this->assertSame( 0, $job->attr( 'catalog_lock_retry' ) );
	}

	/** The job completes once the step finishes after riding out lock refusals. */
	public function test_job_completes_after_lock_refusals() {
		$job    = $this->make_job( array( $this->lock, $this->lock, $this->lock, $this->lock, 'done' ) );
		$result = $this->run_cycles( $job, 5 );

		$this->assertSame( 'completed', $result->status );
	}
}
