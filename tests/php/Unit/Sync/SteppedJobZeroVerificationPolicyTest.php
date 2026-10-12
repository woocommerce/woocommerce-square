<?php
/**
 * Tests for the zero verification retry policy shared by every sync step.
 *
 * When Square's inventory history cannot be read, a step holds its progress and runs again a bounded
 * number of times, and once those attempts are spent it proceeds writing no zeros and says so. That
 * "says so" is what stops the interval poll advancing its window over a period it could not verify, so
 * the flag and the attempt counting are pinned here.
 *
 * @package WooCommerce\Square
 */

namespace WooCommerce\Square\Tests\Unit\Sync;

use WP_UnitTestCase;
use WooCommerce\Square\Sync\Records;
use WooCommerce\Square\Sync\Stepped_Job;
use WooCommerce\Square\Tests\Unit\Sync\Fixtures\Zero_Verification_Policy_Job;

require_once __DIR__ . '/Fixtures/Zero_Verification_Policy_Job.php';

class SteppedJobZeroVerificationPolicyTest extends WP_UnitTestCase {

	/** @var string */
	private $step = 'update_inventory_counts';

	public function setUp(): void {
		parent::setUp();

		if ( ! function_exists( 'wc_square' ) || ! wc_square() ) {
			$this->markTestSkipped( 'The plugin instance is required for the policy tests.' );
		}

		delete_transient( 'wc_square_zero_verification_alerted' );
		delete_option( 'wc_square_sync_records' );
	}

	public function tearDown(): void {
		delete_transient( 'wc_square_zero_verification_alerted' );
		parent::tearDown();
	}

	/**
	 * Builds a job backed by a real background job row, so attribute writes persist as in production.
	 *
	 * @return Zero_Verification_Policy_Job
	 */
	private function make_job() {
		$job = wc_square()->get_background_job_handler()->create_job( array( 'action' => 'poll' ) );

		return new Zero_Verification_Policy_Job( $job );
	}

	/** The step is held for a bounded number of attempts, then told to proceed. */
	public function test_the_step_is_held_for_a_bounded_number_of_attempts() {
		$job = $this->make_job();

		for ( $attempt = 1; $attempt <= Stepped_Job::MAX_ZERO_VERIFICATION_ATTEMPTS; $attempt++ ) {
			$this->assertTrue(
				$job->policy_should_retry( $this->step ),
				"Attempt {$attempt} should hold the step and run it again."
			);
		}

		$this->assertFalse(
			$job->policy_should_retry( $this->step ),
			'Once the attempts are spent the step must proceed rather than hold forever.'
		);
	}

	/** Exhaustion is reported separately, because an empty verified list does not mean failure. */
	public function test_exhaustion_is_reported_so_a_watermark_can_be_held() {
		$job = $this->make_job();

		$this->assertFalse( $job->policy_exhausted( $this->step ), 'A fresh job has verified nothing yet.' );

		$job->policy_set_attr( 'zero_verification_exhausted_' . $this->step, true );

		$this->assertTrue( $job->policy_exhausted( $this->step ) );
	}

	/**
	 * A successful verification clears both the flag and the attempt counter, so a later window is
	 * judged on its own answer rather than an earlier outage.
	 */
	public function test_a_successful_verification_clears_the_exhaustion_flag() {
		$job = $this->make_job();

		$job->policy_set_attr( 'zero_verification_exhausted_' . $this->step, true );
		$job->policy_set_attr( 'zero_verification_attempts_' . $this->step, 2 );

		// An empty id list is answered without asking Square, which is a successful verification of
		// nothing. The failure path itself is covered by the live outage run.
		$verified = $job->policy_resolve( $this->step, array() );

		$this->assertSame( array(), $verified );
		$this->assertFalse( $job->policy_exhausted( $this->step ) );
		$this->assertSame( 0, (int) $job->policy_get_attr( 'zero_verification_attempts_' . $this->step ) );
	}

	/** The merchant gets one alert per window, not one per batch during a sustained outage. */
	public function test_exhaustion_records_one_merchant_alert_per_window() {
		$first = $this->make_job();

		for ( $attempt = 1; $attempt <= Stepped_Job::MAX_ZERO_VERIFICATION_ATTEMPTS; $attempt++ ) {
			$first->policy_should_retry( $this->step );
		}
		$first->policy_should_retry( $this->step );

		$alerts = array_filter(
			Records::get_records(),
			static function ( $record ) {
				return false !== strpos( $record->get_message(), 'could not confirm which products are genuinely sold out' );
			}
		);

		$this->assertCount( 1, $alerts, 'Exhausting the attempts must tell the merchant once.' );

		// A second job in the same window must not add another record, or a sustained outage would
		// push every other sync record out of the capped list.
		$second = $this->make_job();
		for ( $attempt = 1; $attempt <= Stepped_Job::MAX_ZERO_VERIFICATION_ATTEMPTS + 1; $attempt++ ) {
			$second->policy_should_retry( $this->step );
		}

		$alerts_after = array_filter(
			Records::get_records(),
			static function ( $record ) {
				return false !== strpos( $record->get_message(), 'could not confirm which products are genuinely sold out' );
			}
		);

		$this->assertCount( 1, $alerts_after, 'A second job in the same window must not duplicate the alert.' );
	}
}
