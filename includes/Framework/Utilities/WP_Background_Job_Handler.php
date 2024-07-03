<?php
/**
 * WooCommerce Plugin Framework
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is bundled with this package in the file license.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0 or later
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@skyverge.com so we can send you a copy immediately.
 *
 * @since     3.0.0
 * @author    WooCommerce / SkyVerge / Delicious Brains
 * @copyright Copyright (c) 2015-2016 Delicious Brains Inc.
 * @copyright Copyright (c) 2013-2019, SkyVerge, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0 or later
 *
 * Modified by WooCommerce on 01 December 2021.
 */

namespace WooCommerce\Square\Framework\Utilities;

use WooCommerce\Square\Framework\Plugin_Compatibility;

defined( 'ABSPATH' ) or exit;

/**
 * Square WordPress Background Job Handler class
 *
 * Based on the wonderful WP_Background_Process class by deliciousbrains:
 * https://github.com/A5hleyRich/wp-background-processing
 *
 * Subclasses SV_WP_Async_Request. Instead of the concept of `batches` used in
 * the Delicious Brains' version, however, this takes a more object-oriented approach
 * of background `jobs`, allowing greater control over manipulating job data and
 * processing.
 *
 * A batch implicitly expected an array of items to process, whereas a job does
 * not expect any particular data structure (although it does default to
 * looping over job data) and allows subclasses to provide their own
 * processing logic.
 *
 * @since 3.0.0
 */
abstract class Background_Job_Handler {


	/** @var string async request prefix */
	protected $prefix = 'sv_wp';

	/** @var string async request action */
	protected $action = 'background_job';

	/** @var string data key */
	protected $data_key = 'data';

	/** @var int start time of current process */
	protected $start_time = 0;

	/** @var string cron hook identifier */
	protected $cron_hook_identifier;

	/** @var string cron interval identifier */
	protected $cron_interval_identifier;

	/** @var string debug message, used by the system status tool */
	protected $debug_message;

	/** @var string job identifier */
	protected $identifier;


	/**
	 * Initiate new background job handler
	 *
	 * @since 3.0.0
	 */
	public function __construct() {

		$this->identifier               = $this->prefix . '_' . $this->action;
		$this->cron_hook_identifier     = $this->identifier . '_cron';
		$this->cron_interval_identifier = $this->identifier . '_cron_interval';

		$this->add_hooks();
	}


	/**
	 * Adds the necessary action and filter hooks.
	 *
	 * @since 3.0.0
	 */
	protected function add_hooks() {

		// cron healthcheck
		add_filter( 'cron_schedules', array( $this, 'schedule_cron_healthcheck' ) );

		// debugging & testing
		add_filter( 'gettext', array( $this, 'translate_success_message' ), 10, 3 );
	}

	/**
	 * Check whether job queue is empty or not
	 *
	 * @since 3.0.0
	 * @return bool True if queue is empty, false otherwise
	 */
	protected function is_queue_empty() {
		global $wpdb;

		$key = $this->identifier . '_job_%';

		// only queued or processing jobs count
		$queued     = '%"status":"queued"%';
		$processing = '%"status":"processing"%';

		$count = $wpdb->get_var( $wpdb->prepare( "
			SELECT COUNT(*)
			FROM {$wpdb->options}
			WHERE option_name LIKE %s
			AND ( option_value LIKE %s OR option_value LIKE %s )
		", $key, $queued, $processing ) );

		return ( $count > 0 ) ? false : true;
	}


	/**
	 * Check whether background process is running or not
	 *
	 * Check whether the current process is already running
	 * in a background process.
	 *
	 * @since 3.0.0
	 * @return bool True if processing is running, false otherwise
	 */
	protected function is_process_running() {

		// add a random artificial delay to prevent a race condition if 2 or more processes are trying to
		// process the job queue at the very same moment in time and neither of them have yet set the lock
		// before the others are calling this method
		usleep( wp_rand( 100000, 300000 ) );

		return (bool) get_transient( "{$this->identifier}_process_lock" );
	}


	/**
	 * Lock process
	 *
	 * Lock the process so that multiple instances can't run simultaneously.
	 * Override if applicable, but the duration should be greater than that
	 * defined in the time_exceeded() method.
	 *
	 * @since 3.0.0
	 */
	protected function lock_process() {

		// set start time of current process
		$this->start_time = time();

		// set lock duration to 1 minute by default
		$lock_duration = ( property_exists( $this, 'queue_lock_time' ) ) ? $this->queue_lock_time : 60;

		/**
		 * Filter the queue lock time
		 *
		 * @since 3.0.0
		 * @param int $lock_duration Lock duration in seconds
		 */
		$lock_duration = apply_filters( "{$this->identifier}_queue_lock_time", $lock_duration );

		set_transient( "{$this->identifier}_process_lock", microtime(), $lock_duration );
	}


	/**
	 * Unlock process
	 *
	 * Unlock the process so that other instances can spawn.
	 *
	 * @since 3.0.0
	 * @return Background_Job_Handler
	 */
	protected function unlock_process() {

		delete_transient( "{$this->identifier}_process_lock" );

		return $this;
	}


	/**
	 * Check if memory limit is exceeded
	 *
	 * Ensures the background job handler process never exceeds 90%
	 * of the maximum WordPress memory.
	 *
	 * @since 3.0.0
	 *
	 * @return bool True if exceeded memory limit, false otherwise
	 */
	protected function memory_exceeded() {

		$memory_limit   = $this->get_memory_limit() * 0.9; // 90% of max memory
		$current_memory = memory_get_usage( true );
		$return         = false;

		if ( $current_memory >= $memory_limit ) {
			$return = true;
		}

		/**
		 * Filter whether memory limit has been exceeded or not
		 *
		 * @since 3.0.0
		 *
		 * @param bool $exceeded
		 */
		return apply_filters( "{$this->identifier}_memory_exceeded", $return );
	}


	/**
	 * Get memory limit
	 *
	 * @since 3.0.0
	 *
	 * @return int memory limit in bytes
	 */
	protected function get_memory_limit() {

		if ( function_exists( 'ini_get' ) ) {
			$memory_limit = ini_get( 'memory_limit' );
		} else {
			// sensible default
			$memory_limit = '128M';
		}

		if ( ! $memory_limit || -1 === (int) $memory_limit ) {
			// unlimited, set to 32GB
			$memory_limit = '32G';
		}

		return Plugin_Compatibility::convert_hr_to_bytes( $memory_limit );
	}

	/**
	 * Create a background job
	 *
	 * Delicious Brains' versions alternative would be using ->data()->save().
	 * Allows passing in any kind of job attributes, which will be available at item data processing time.
	 * This allows sharing common options between items without the need to repeat
	 * the same information for every single item in queue.
	 *
	 * Instead of returning self, returns the job instance, which gives greater
	 * control over the job.
	 *
	 * @since 3.0.0
	 *
	 * @param array|mixed $attrs Job attributes.
	 * @return \stdClass|object|null
	 */
	public function create_job( $attrs ) {
		global $wpdb;

		if ( empty( $attrs ) ) {
			return null;
		}

		// generate a unique ID for the job
		$job_id = md5( microtime() . mt_rand() );

		/**
		 * Filter new background job attributes
		 *
		 * @since 3.0.0
		 *
		 * @param array $attrs Job attributes
		 * @param string $id Job ID
		 */
		$attrs = apply_filters( "{$this->identifier}_new_job_attrs", $attrs, $job_id );

		// ensure a few must-have attributes
		$attrs = wp_parse_args( array(
			'id'         => $job_id,
			'created_at' => current_time( 'mysql' ),
			'created_by' => get_current_user_id(),
			'status'     => 'queued',
		), $attrs );

		$wpdb->insert( $wpdb->options, array(
			'option_name'  => "{$this->identifier}_job_{$job_id}",
			'option_value' => wp_json_encode( $attrs ),
			'autoload'     => 'no'
		) );

		$job = new \stdClass();

		foreach ( $attrs as $key => $value ) {
			$job->{$key} = $value;
		}

		/**
		 * Runs when a job is created.
		 *
		 * @since 3.0.0
		 *
		 * @param \stdClass|object $job the created job
		 */
		do_action( "{$this->identifier}_job_created", $job );

		return $job;
	}


	/**
	 * Get a job (by default the first in the queue)
	 *
	 * @since 3.0.0
	 *
	 * @param string $id Optional. Job ID. Will return first job in queue if not
	 *                   provided. Will not return completed or failed jobs from queue.
	 * @return \stdClass|object|null The found job object or null
	 */
	public function get_job( $id = null ) {
		global $wpdb;

		if ( ! $id ) {

			$key        = $this->identifier . '_job_%';
			$queued     = '%"status":"queued"%';
			$processing = '%"status":"processing"%';

			$results = $wpdb->get_var( $wpdb->prepare( "
				SELECT option_value
				FROM {$wpdb->options}
				WHERE option_name LIKE %s
				AND ( option_value LIKE %s OR option_value LIKE %s )
				ORDER BY option_id ASC
				LIMIT 1
			", $key, $queued, $processing ) );

		} else {

			$results = $wpdb->get_var( $wpdb->prepare( "
				SELECT option_value
				FROM {$wpdb->options}
				WHERE option_name = %s
			", "{$this->identifier}_job_{$id}" ) );

		}

		if ( ! empty( $results ) ) {

			$job = new \stdClass();

			foreach ( json_decode( $results, true ) as $key => $value ) {
				$job->{$key} = $value;
			}

		} else {
			return null;
		}

		/**
		 * Filters the job as returned from the database.
		 *
		 * @since 3.0.0
		 *
		 * @param \stdClass|object $job
		 */
		return apply_filters( "{$this->identifier}_returned_job", $job );
	}


	/**
	 * Gets jobs.
	 *
	 * @since 3.0.0
	 *
	 * @param array $args {
	 *     Optional. An array of arguments
	 *
	 *     @type string|array $status Job status(es) to include
	 *     @type string $order ASC or DESC. Defaults to DESC
	 *     @type string $orderby Field to order by. Defaults to option_id
	 * }
	 * @return \stdClass[]|object[]|null Found jobs or null if none found
	 */
	public function get_jobs( $args = array() ) {
		global $wpdb;

		$args = wp_parse_args( $args, array(
			'order'   => 'DESC',
			'orderby' => 'option_id',
		) );

		$replacements = array( $this->identifier . '_job_%' );
		$status_query = '';

		// prepare status query
		if ( ! empty( $args['status'] ) ) {

			$statuses     = (array) $args['status'];
			$placeholders = array();

			foreach ( $statuses as $status ) {

				$placeholders[] = '%s';
				$replacements[] = '%"status":"' . sanitize_key( $status ) . '"%';
			}

			$status_query = 'AND ( option_value LIKE ' . implode( ' OR option_value LIKE ', $placeholders ) . ' )';
		}

		// prepare sorting vars
		$order   = sanitize_key( $args['order'] );
		$orderby = sanitize_key( $args['orderby'] );

		// put it all together now
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Data is already sanitized to direct use.
		$results = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_value
				FROM {$wpdb->options}
				WHERE option_name LIKE %s
				{$status_query}
				ORDER BY {$orderby} {$order}",
				$replacements
			)
		);
		// phpcs:enable

		if ( empty( $results ) ) {
			return null;
		}

		$jobs = array();

		foreach ( $results as $result ) {

			$job = new \stdClass();

			foreach ( json_decode( $result, true ) as $key => $value ) {
				$job->{$key} = $value;
			}

			/* This filter is documented above. */
			$job = apply_filters( "{$this->identifier}_returned_job", $job );

			$jobs[] = $job;
		}

		return $jobs;
	}

	/**
	 * Update job attrs
	 *
	 * @since 3.0.0
	 *
	 * @param \stdClass|object|string $job Job instance or ID
	 * @return \stdClass|object|false on failure
	 */
	public function update_job( $job ) {

		if ( is_string( $job ) ) {
			$job = $this->get_job( $job );
		}

		if ( ! $job ) {
			return false;
		}

		$job->updated_at = current_time( 'mysql' );

		$this->update_job_option( $job );

		/**
		 * Runs when a job is updated.
		 *
		 * @since 3.0.0
		 *
		 * @param \stdClass|object $job the updated job
		 */
		do_action( "{$this->identifier}_job_updated", $job );

		return $job;
	}


	/**
	 * Handles job completion.
	 *
	 * @since 3.0.0
	 *
	 * @param \stdClass|object|string $job Job instance or ID
	 * @return \stdClass|object|false on failure
	 */
	public function complete_job( $job ) {

		if ( is_string( $job ) ) {
			$job = $this->get_job( $job );
		}

		if ( ! $job ) {
			return false;
		}

		$job->status       = 'completed';
		$job->completed_at = current_time( 'mysql' );

		$this->update_job_option( $job );

		/**
		 * Runs when a job is completed.
		 *
		 * @since 3.0.0
		 *
		 * @param \stdClass|object $job the completed job
		 */
		do_action( "{$this->identifier}_job_complete", $job );

		return $job;
	}


	/**
	 * Handle job failure
	 *
	 * Default implementation does not call this method directly, but it's
	 * provided as a convenience method for subclasses that may call this to
	 * indicate that a particular job has failed for some reason.
	 *
	 * @since 3.0.0
	 *
	 * @param \stdClass|object|string $job Job instance or ID
	 * @param string $reason Optional. Reason for failure.
	 * @return \stdClass|false on failure
	 */
	public function fail_job( $job, $reason = '' ) {

		if ( is_string( $job ) ) {
			$job = $this->get_job( $job );
		}

		if ( ! $job ) {
			return false;
		}

		$job->status    = 'failed';
		$job->failed_at = current_time( 'mysql' );

		if ( $reason ) {
			$job->failure_reason = $reason;
		}

		$this->update_job_option( $job );

		/**
		 * Runs when a job is failed.
		 *
		 * @since 3.0.0
		 *
		 * @param \stdClass|object $job the failed job
		 */
		do_action( "{$this->identifier}_job_failed", $job );

		return $job;
	}


	/**
	 * Delete a job
	 *
	 * @since 3.0.0
	 *
	 * @param \stdClass|object|string $job Job instance or ID
	 * @return bool|void returns false on failure
	 */
	public function delete_job( $job ) {
		global $wpdb;

		if ( is_string( $job ) ) {
			$job = $this->get_job( $job );
		}

		if ( ! $job ) {
			return false;
		}

		$wpdb->delete( $wpdb->options, array( 'option_name' => "{$this->identifier}_job_{$job->id}" ) );

		/**
		* Runs after a job is deleted.
		*
		* @since 3.0.0
		*
		* @param \stdClass|object $job the job that was deleted from database
		*/
		do_action( "{$this->identifier}_job_deleted", $job );
	}


	/**
	 * Handle job queue completion
	 *
	 * Override if applicable, but ensure that the below actions are
	 * performed, or, call parent::complete().
	 *
	 * @since 3.0.0
	 */
	protected function complete() {

		// unschedule the cron healthcheck
		$this->clear_scheduled_event();
	}


	/**
	 * Schedule cron healthcheck
	 *
	 * @since 3.0.0
	 * @param array $schedules
	 * @return array
	 */
	public function schedule_cron_healthcheck( $schedules ) {

		$interval = property_exists( $this, 'cron_interval' ) ? $this->cron_interval : 5;

		/**
		 * Filter cron health check interval
		 *
		 * @since 3.0.0
		 * @param int $interval Interval in minutes
		 */
		$interval = apply_filters( "{$this->identifier}_cron_interval", $interval );

		// adds every 5 minutes to the existing schedules.
		$schedules[ $this->identifier . '_cron_interval' ] = array(
			'interval' => MINUTE_IN_SECONDS * $interval,
			'display'  => sprintf( esc_html__( 'Every %d Minutes', 'woocommerce-square' ), $interval ),
		);

		return $schedules;
	}

	/**
	 * Schedule cron health check event
	 *
	 * @since 3.0.0
	 */
	protected function schedule_event() {
		/**
		 * Filters the interval of sync healthcheck action.
		 *
		 * @since 3.8.2
		 *
		 * @param int $interval sync heathcheck interval in seconds (defaults to 5 minutes)
		 */
		$interval = apply_filters( 'wc_square_sync_healthcheck_interval', 300 );
		$group    = 'square';

		if ( false === as_next_scheduled_action( $this->cron_hook_identifier, array(), $group ) ) {
			// Schedule the health check to fire after 5 mins from now, and run every 5 mins until queue get empty.
			as_schedule_recurring_action( time() + $interval, $interval, $this->cron_hook_identifier, array(), $group );
		}
	}

	/**
	 * Clear scheduled health check event
	 *
	 * @since 3.0.0
	 */
	protected function clear_scheduled_event() {
		as_unschedule_all_actions( $this->cron_hook_identifier );
	}


	/**
	 * Process an item from job data
	 *
	 * Implement this method to perform any actions required on each
	 * item in job data.
	 *
	 * @since 3.0.0
	 *
	 * @param mixed $item Job data item to iterate over
	 * @param \stdClass|object $job Job instance
	 * @return mixed
	 */
	abstract protected function process_item( $item, $job );


	/**
	 * Handles PHP shutdown, say after a fatal error.
	 *
	 * @since 3.0.0
	 *
	 * @param \stdClass|object $job the job being processed
	 */
	public function handle_shutdown( $job ) {

		$error = error_get_last();

		// if shutting down because of a fatal error, fail the job
		if ( $error && E_ERROR === $error['type'] ) {

			$this->fail_job( $job, $error['message'] );

			$this->unlock_process();
		}
	}


	/**
	 * Update a job option in options database.
	 *
	 * @since 3.0.0
	 *
	 * @param \stdClass|object $job the job instance to update in database
	 * @return int|bool number of rows updated or false on failure, see wpdb::update()
	 */
	private function update_job_option( $job ) {
		global $wpdb;

		return $wpdb->update(
			$wpdb->options,
			array( 'option_value' => wp_json_encode( $job ) ),
			array( 'option_name'  => "{$this->identifier}_job_{$job->id}" )
		);
	}


	/** Debug & Testing Methods ***********************************************/

	/**
	 * Translate the tool success message.
	 *
	 * This can be removed in favor of returning the message string in `run_debug_tool()`
	 *  when WC 3.1 is required, though that means the message will always be "success" styled.
	 *
	 * @since 3.0.0
	 *
	 * @param string $translated the text to output
	 * @param string $original the original text
	 * @param string $domain the textdomain
	 * @return string the updated text
	 */
	public function translate_success_message( $translated, $original, $domain ) {

		if ( 'woocommerce' === $domain && ( 'Tool ran.' === $original || 'There was an error calling %s' === $original ) ) {
			$translated = $this->debug_message;
		}

		return $translated;
	}


	/** Helper Methods ********************************************************/


	/**
	 * Gets the job handler identifier.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public function get_identifier() {

		return $this->identifier;
	}
}
