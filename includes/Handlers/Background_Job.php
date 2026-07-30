<?php
/**
 * WooCommerce Square
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is bundled with this package in the file license.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0 or later
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@woocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade WooCommerce Square to newer
 * versions in the future. If you wish to customize WooCommerce Square for your
 * needs please refer to https://docs.woocommerce.com/document/woocommerce-square/
 *
 */

namespace WooCommerce\Square\Handlers;

use WooCommerce\Square\Framework\Utilities\Background_Job_Handler;
use WooCommerce\Square\Sync\Job;
use WooCommerce\Square\Sync\Records;
use WooCommerce\Square\Sync\Interval_Polling;
use WooCommerce\Square\Sync\Manual_Synchronization;
use WooCommerce\Square\Sync\Product_Import;

defined( 'ABSPATH' ) || exit;

/**
 * Product and Inventory Synchronization handler class.
 *
 * This class handles manual and interval synchronization jobs.
 * It is a wrapper for the framework background handler and as such it only handles loopback business to keep the queue processing.
 * See the individual job implementations:
 *
 * @see Manual_Synchronization manual jobs re-process ALL synced products
 * @see Interval_Polling interval (polling) jobs perform API requests for ONLY the latest changes and update the associated products
 *
 * @since 2.0.0
 */
class Background_Job extends Background_Job_Handler {


	/**
	 * Initializes the background sync handler.
	 *
	 * @since 2.0.0
	 */
	public function __construct() {

		$this->prefix   = 'wc_square';
		$this->action   = 'background_sync';
		$this->data_key = 'product_ids';

		parent::__construct();

		add_action( "{$this->identifier}_job_complete", array( $this, 'job_complete' ) );
		add_action( "{$this->identifier}_job_failed", array( $this, 'job_failed' ) );
		add_filter( 'woocommerce_debug_tools', array( $this, 'add_debug_tool' ) );
		add_action( 'wc_square_job_runner', array( $this, 'handle' ) );

		// Sync healthcheck
		add_action( $this->cron_hook_identifier, array( $this, 'handle_sync_healthcheck' ) );

		// Safety net for sites where cron/Action Scheduler execution is broken (the reported
		// incident had the healthcheck actions themselves failing for a month): any admin page
		// load can also detect and recover a stalled sync. Throttled internally.
		add_action( 'admin_init', array( $this, 'maybe_recover_stuck_sync_from_admin' ) );
	}


	/**
	 * Creates a new job.
	 *
	 * @since 2.0.0
	 *
	 * @param array $attrs array of job attributes
	 * @return \stdClass|null
	 */
	public function create_job( $attrs ) {

		$sor = wc_square()->get_settings_handler()->get_system_of_record();

		return parent::create_job(
			wp_parse_args(
				$attrs,
				array(
					'action'                => '',      // job action
					'catalog_processed'     => false,   // whether the Square catalog has been processed
					'cursor'                => '',      // job advancement position
					'manual'                => false,   // whether it's a sync job triggered manually
					'percentage'            => 0,       // percentage completed
					'product_ids'           => array(), // products to process
					'processed_product_ids' => array(), // newly imported products processed
					'updated_product_ids'   => array(), // updated products processed
					'skipped_products'      => array(), // remote product IDs that were skipped
					'system_of_record'      => $sor,    // Sync setting used
				)
			)
		);
	}


	/**
	 * Handles job execution.
	 *
	 * Overridden to support our multi-step job structure. There are steps that can take a long time to process, so this
	 * ensures only one step is performed for each background request.
	 *
	 * @since 2.0.0
	 */
	public function handle() {

		// Schedule sync healthcheck event if not already scheduled.
		$this->schedule_event();

		$this->lock_process();

		// Get next job in the queue
		$job = $this->get_job();

		// handle PHP errors from here on out
		register_shutdown_function( array( $this, 'handle_shutdown' ), $job );

		// Start processing
		$this->process_job( $job );

		$this->unlock_process();

		// Start next job or complete process
		if ( ! $this->is_queue_empty() ) {
			// If the job has a retry count set, we'll retry the job after a delay.
			if ( isset( $job->retry ) && is_numeric( $job->retry ) && $job->retry > 0 ) {
				$base_delay = 30;  // Base delay in seconds for rate limit errors. 30 seconds.
				$delay      = $base_delay * ( pow( 2, $job->retry ) );
				wc_square()->log( "Retrying in {$delay} seconds." );
				as_schedule_single_action( time() + $delay, 'wc_square_job_runner' );
			} else {
				as_enqueue_async_action( 'wc_square_job_runner' );
			}
		} else {
			$this->complete();
		}
	}


	/**
	 * Processes a background job.
	 *
	 * @since 2.0.0
	 *
	 * @param object|\stdClass $job
	 * @param null $items_per_batch
	 * @return false|object|\stdClass
	 */
	public function process_job( $job, $items_per_batch = null ) {

		if ( ! $job ) {
			return;
		}

		// Heartbeat: record activity on every processed step so a genuinely long running sync is
		// never mistaken for a stalled one. started_processing_at (below) is stamped only once, so
		// it cannot tell a slow large-catalog sync apart from a stuck job; last_activity_at can.
		$job->last_activity_at = time();

		// indicate that the job has started processing
		if ( 'processing' !== $job->status ) {

			$job->status                = 'processing';
			$job->started_processing_at = current_time( 'mysql' );
		}

		$job = $this->update_job( $job );

		// The row can be gone if the job was cleared concurrently (e.g. the Clear Square Sync tool).
		// This path now runs on every step, so guard before dereferencing the job below.
		if ( ! $job ) {
			return;
		}

		if ( 'poll' === $job->action ) {

			$job = new Interval_Polling( $job );

		} elseif ( 'product_import' === $job->action ) {

			$job = new Product_Import( $job );

		} elseif ( ! empty( $job->manual ) ) {

			$job = new Manual_Synchronization( $job );
		}

		if ( $job instanceof Job ) {
			$current_user_id = get_current_user_id();
			$job             = $job->run();
			wp_set_current_user( $current_user_id ); // phpcs:ignore Generic.PHP.ForbiddenFunctions.Discouraged -- required for background job processing
		}

		return $job;
	}


	/**
	 * Handles actions after a sync job is complete.
	 *
	 * @since 2.0.0
	 *
	 * @param $job
	 */
	public function job_complete( $job ) {

		wc_square()->get_sync_handler()->set_last_synced_at();

		wc_square()->get_sync_handler()->record_sync( $job->processed_product_ids, $job );

		wc_square()->get_email_handler()->get_sync_completed_email()->trigger( $job );
	}


	/**
	 * Handles actions after a sync job has failed.
	 *
	 * @since 2.0.0
	 *
	 * @param $job
	 */
	public function job_failed( $job ) {

		Records::set_record(
			array(
				'type'    => 'failed',
				'message' => __( 'Sync failed. Please try again', 'woocommerce-square' ),
			)
		);

		wc_square()->get_email_handler()->get_sync_completed_email()->trigger( $job );
	}


	/**
	 * No-op: implements framework parent abstract method.
	 *
	 * @since 2.0.0
	 *
	 * @param null $item
	 * @param \stdClass $job
	 */
	protected function process_item( $item, $job ) {}

	/**
	 * Adds some helpful debug tools.
	 *
	 * @since 2.0.0
	 *
	 * @param array $tools existing debug tools
	 * @return array
	 */
	public function add_debug_tool( $tools ) {

		// this key is not unique to the plugin to avoid duplicate tools
		$tools['wc_square_clear_background_jobs'] = array(
			'name'     => __( 'Clear Square Sync', 'woocommerce-square' ),
			'button'   => __( 'Clear', 'woocommerce-square' ),
			'desc'     => __( 'This tool will clear any ongoing Square product syncs.', 'woocommerce-square' ),
			'callback' => array( $this, 'run_clear_background_jobs' ),
		);

		return $tools;
	}


	/**
	 * Clear all background jobs of any status.
	 *
	 * @since 2.0.0
	 */
	public function clear_all_jobs() {

		$jobs = $this->get_jobs();

		if ( is_array( $jobs ) ) {
			$this->delete_jobs( $jobs );
		}

		delete_transient( 'wc_square_background_sync_process_lock' );
	}


	/**
	 * Deletes a set of background jobs.
	 *
	 * @since 2.0.0
	 *
	 * @param object[] $jobs jobs to delete
	 */
	public function delete_jobs( $jobs ) {

		foreach ( $jobs as $job ) {
			$this->delete_job( $job );
		}
	}

	/**
	 * Runs the "Clear Square Sync" tool.
	 *
	 * Provides a way for merchants to clear any ongoing or stuck product syncs.
	 *
	 * @since 2.0.0
	 */
	public function run_clear_background_jobs() {

		$this->clear_all_jobs();

		$this->debug_message = esc_html__( 'Success! You can now sync your products.', 'woocommerce-square' );

		return true;
	}

	/**
	 * Runs the stalled-sync recovery check from admin page loads, throttled.
	 *
	 * The scheduled healthcheck is the primary trigger, but on sites where cron or Action
	 * Scheduler execution is broken (as in the reported incident, where the healthcheck actions
	 * themselves failed for a month) it never runs. Admin page loads are the one context such a
	 * site still exercises, so use them as a fallback trigger. Throttled to once per five minutes
	 * and restricted to users who can manage WooCommerce.
	 *
	 * @since x.x.x
	 */
	public function maybe_recover_stuck_sync_from_admin() {

		if ( ! current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
			return;
		}

		if ( get_transient( 'wc_square_admin_recovery_check' ) ) {
			return;
		}
		set_transient( 'wc_square_admin_recovery_check', 1, 5 * MINUTE_IN_SECONDS );

		$this->maybe_recover_stuck_sync();
	}

	/**
	 * Detects a sync job stalled in "processing" and recovers it so the queue can resume.
	 *
	 * A job is considered stalled when it has been in "processing" without any step activity for
	 * longer than a filterable threshold (measured against the per-step heartbeat, so a legitimately
	 * long sync is not affected). Recovery marks the job failed, releases the process lock, and
	 * clears the pending/failing wc_square_job_runner action cascade that would otherwise keep the
	 * queue non-empty and block a restart. A flag is stored so the admin notice can prompt a re-run.
	 *
	 * @since x.x.x
	 */
	protected function maybe_recover_stuck_sync() {

		$job = $this->get_job();

		if ( ! $job || ! isset( $job->status ) || 'processing' !== $job->status ) {
			return;
		}

		/**
		 * Filters how long (in seconds) a sync job may sit in "processing" without any step activity
		 * before it is treated as stalled and automatically recovered.
		 *
		 * @since x.x.x
		 *
		 * @param int $threshold threshold in seconds (default 15 minutes)
		 */
		$threshold = (int) apply_filters( 'wc_square_stuck_job_threshold', 15 * MINUTE_IN_SECONDS );

		$is_stalled = function ( $job ) use ( $threshold ) {
			if ( ! $job || 'processing' !== ( $job->status ?? '' ) ) {
				return false;
			}
			// Prefer the per-step heartbeat; fall back to the one-time start stamp for jobs created
			// before this change shipped. started_processing_at is a site-local mysql string, so
			// convert to GMT before comparing against the UTC epoch from time().
			if ( ! empty( $job->last_activity_at ) ) {
				$reference = (int) $job->last_activity_at;
			} elseif ( ! empty( $job->started_processing_at ) ) {
				$reference = (int) strtotime( get_gmt_from_date( $job->started_processing_at ) );
			} else {
				return false;
			}

			return $reference > 0 && ( time() - $reference ) > $threshold;
		};

		if ( ! $is_stalled( $job ) ) {
			return;
		}

		// A live worker still holds the process lock: the job is progressing, not stuck. Leave it.
		if ( $this->is_process_running() ) {
			return;
		}

		// Re-read immediately before acting: a concurrent step may have advanced the job in the
		// window since get_job() above, in which case we must not clobber it with a stale snapshot.
		$job = $this->get_job( $job->id );
		if ( ! $is_stalled( $job ) ) {
			return;
		}

		// Recover: fail the stalled job, release the lock, and clear the action cascade.
		$this->fail_job( $job, __( 'Sync job stalled and was automatically recovered.', 'woocommerce-square' ) );
		$this->unlock_process();

		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( 'wc_square_job_runner' );
		}

		// Recorded so the stale-sync admin notice can tell the merchant the sync was restarted.
		update_option( 'wc_square_sync_auto_recovered_at', time() );

		wc_square()->log( 'Auto-recovered a stalled sync job (' . ( isset( $job->id ) ? $job->id : 'unknown' ) . '). Cleared the job_runner queue for restart.' );
	}

	/**
	 * Deletes stale failed Square Action Scheduler actions to prevent table bloat.
	 *
	 * Runs at most once per day. Removes actions for the plugin's sync hooks that are in the failed
	 * state and older than a filterable retention window. Never touches pending or in-progress
	 * actions, and does not change Action Scheduler's own timeout handling.
	 *
	 * @since x.x.x
	 */
	protected function cleanup_stale_failed_actions() {

		$last_run = (int) get_option( 'wc_square_failed_action_cleanup_at', 0 );
		if ( $last_run && ( time() - $last_run ) < DAY_IN_SECONDS ) {
			return;
		}
		update_option( 'wc_square_failed_action_cleanup_at', time(), false );

		if ( ! function_exists( 'as_get_scheduled_actions' ) || ! class_exists( 'ActionScheduler_Store' ) ) {
			return;
		}

		/**
		 * Filters how many days a failed Square action is retained before automatic cleanup.
		 *
		 * @since x.x.x
		 *
		 * @param int $days retention in days (default 30)
		 */
		$retention_days = max( 1, (int) apply_filters( 'wc_square_failed_action_retention_days', 30 ) );
		$cutoff         = gmdate( 'Y-m-d H:i:s', time() - ( $retention_days * DAY_IN_SECONDS ) );

		$hooks = array( 'wc_square_job_runner', 'wc_square_background_sync_cron', 'wc_square_sync', 'wc_square_sync_orders' );

		try {
			$store = \ActionScheduler_Store::instance();

			foreach ( $hooks as $hook ) {
				$action_ids = as_get_scheduled_actions(
					array(
						'hook'         => $hook,
						'status'       => \ActionScheduler_Store::STATUS_FAILED,
						'date'         => $cutoff,
						'date_compare' => '<=',
						'per_page'     => 200,
						'orderby'      => 'none',
					),
					'ids'
				);

				foreach ( (array) $action_ids as $action_id ) {
					$store->delete_action( $action_id );
				}
			}
		} catch ( \Exception $e ) {
			wc_square()->log( 'Failed-action cleanup skipped: ' . $e->getMessage() );
		}
	}

	/**
	 * Handle Sync healthcheck
	 *
	 * Restart the background sync process if not already running
	 * and data exists in the queue.
	 *
	 * @since 3.8.2
	 */
	public function handle_sync_healthcheck() {

		// Auto-recover a stalled sync first, on purpose. A job stuck in "processing" (timeout, fatal,
		// worker kill) or a cascade of failing wc_square_job_runner actions keeps the queue
		// non-empty, so the as_has_scheduled_action() guard below would otherwise never let the sync
		// restart. Running recovery before the early returns is what breaks that deadlock.
		$this->maybe_recover_stuck_sync();

		// Housekeeping: prune old failed Square actions so the Action Scheduler store does not bloat
		// (the reported incident left 14,000+ failed actions behind). Throttled internally.
		$this->cleanup_stale_failed_actions();

		if ( $this->is_process_running() ) {
			// background process already running
			return;
		}

		if ( $this->is_queue_empty() ) {
			// no data to process
			return;
		}

		if ( as_has_scheduled_action( 'wc_square_job_runner' ) ) {
			// scheduled action for trigger sync is already exists
			return;
		}

		// Start the sync process
		as_enqueue_async_action( 'wc_square_job_runner' );
	}
}
