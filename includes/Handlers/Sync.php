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
 * @author    WooCommerce
 * @copyright Copyright: (c) 2019, Automattic, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0 or later
 */

namespace WooCommerce\Square\Handlers;

use WooCommerce\Square\Plugin;
use WooCommerce\Square\Sync\Records;

defined( 'ABSPATH' ) || exit;

/**
 * Synchronization handler class
 *
 * @since 2.0.0
 */
class Sync {


	/** @var string key of the option that stores a timestamp when the last sync job completed */
	private $last_synced_at_option_key = 'wc_square_last_synced_at';

	/** @var string name of the Action Scheduler event name for syncing with Square */
	private $sync_scheduled_event_name;

	/** @var Plugin plugin instance */
	private $plugin;


	/**
	 * Constructs the class.
	 *
	 * @since 2.0.0
	 *
	 * @param Plugin $plugin
	 */
	public function __construct( Plugin $plugin ) {

		$this->plugin = $plugin;

		$this->sync_scheduled_event_name = 'wc_square_sync';

		$this->add_hooks();
	}


	/**
	 * Adds the action & filter hooks.
	 *
	 * @since 2.0.0
	 */
	private function add_hooks() {

		// schedule the interval sync
		add_action( 'init', array( $this, 'schedule_sync' ) );

		// run the interval sync when fired by Action Scheduler
		add_action( $this->sync_scheduled_event_name, array( $this, 'start_interval_sync' ) );

		add_action( 'admin_notices', array( $this, 'render_import_no_navigation_warning' ) );
	}

	/**
	 * Returns array of post types supported for sync.
	 *
	 * Since 3.8.3
	 *
	 * @return array
	 */
	public function supported_product_types() {
		return array(
			'simple',
			'variable',
		);
	}

	/**
	 * Schedules the interval sync.
	 *
	 * @param bool $change_interval (optional) whether to change the interval
	 * @since 2.0.0
	 */
	public function schedule_sync( $change_interval = false ) {

		// bail if product sync is not enabled or there hasn't been a previous sync
		if ( $this->is_sync_in_progress() || ! $this->get_last_synced_at() || ! $this->get_plugin()->get_settings_handler()->is_connected() || ! $this->get_plugin()->get_settings_handler()->is_product_sync_enabled() ) {
			return;
		}

		$plugin_id = $this->get_plugin()->get_id();
		$interval  = wc_square()->get_settings_handler()->get_sync_interval();

		if ( false === as_next_scheduled_action( $this->sync_scheduled_event_name, array(), $plugin_id ) || $change_interval ) {
			as_unschedule_all_actions( $this->sync_scheduled_event_name, array(), $plugin_id );
			as_schedule_recurring_action( time() + $interval, $interval, $this->sync_scheduled_event_name, array(), $plugin_id );
		}
	}


	/**
	 * Unschedules the interval sync.
	 *
	 * @since 2.0.0
	 */
	public function unschedule_sync() {

		as_unschedule_action( $this->sync_scheduled_event_name, array(), 'square' );
	}

	/**
	 * Performs a product import from Square.
	 *
	 * @since 2.0.0
	 *
	 * @param bool $update_during_import whether the store manager has ticked to update products during an import
	 * @return \stdClass|null
	 */
	public function start_product_import( $update_during_import = false ) {

		$job = $this->get_plugin()->get_background_job_handler()->create_job(
			array(
				'action'                        => 'product_import',
				'update_products_during_import' => $update_during_import,
			)
		);

		if ( $job ) {
			as_enqueue_async_action( 'wc_square_job_runner' );
		}

		return $job;
	}

	/**
	 * Performs a manual sync.
	 *
	 * @since 2.0.0
	 *
	 * @param int[] $product_ids (optional) array of product IDs to sync
	 * @return \stdClass|null
	 */
	public function start_manual_sync( array $product_ids = array() ) {

		$product_ids = empty( $product_ids ) ? Product::get_products_synced_with_square() : $product_ids;

		$job = $this->get_plugin()->get_background_job_handler()->create_job(
			array(
				'action'      => 'sync',
				'manual'      => true,
				'product_ids' => $product_ids,
			)
		);

		if ( $job ) {
			as_enqueue_async_action( 'wc_square_job_runner' );
		}

		return $job;
	}

	/**
	 * Performs a manual product deletion.
	 *
	 * @since 2.0.0
	 *
	 * @param int[] $product_ids array of product IDs to delete
	 * @return \stdClass|null
	 */
	public function start_manual_deletion( array $product_ids ) {

		$job = $this->get_plugin()->get_background_job_handler()->create_job(
			array(
				'action'      => 'delete',
				'manual'      => true,
				'product_ids' => $product_ids,
			)
		);

		if ( $job ) {
			as_enqueue_async_action( 'wc_square_job_runner' );
		}

		return $job;
	}

	/**
	 * Performs an interval sync with Square.
	 *
	 * @since 2.0.0
	 */
	public function start_interval_sync() {

		// bail if there is already a sync in progress
		if ( ! $this->is_sync_enabled() || $this->is_sync_in_progress() ) {
			return;
		}

		// use this opportunity to clear old background jobs
		$this->get_plugin()->get_background_job_handler()->clear_all_jobs();

		$job = $this->get_plugin()->get_background_job_handler()->create_job(
			array(
				'action'                   => 'poll',
				'manual'                   => false,
				'catalog_last_synced_at'   => $this->get_last_synced_at(),
				'inventory_last_synced_at' => $this->get_inventory_last_synced_at(),
			)
		);

		if ( $job ) {
			as_enqueue_async_action( 'wc_square_job_runner' );
		}
	}


	/** Conditional methods *******************************************************************************************/


	/**
	 * Determines whether a sync, scheduled or manual, is in progress.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function is_sync_in_progress() {

		return ( defined( 'DOING_SQUARE_SYNC' ) && true === DOING_SQUARE_SYNC )
			|| null !== $this->get_job_in_progress();
	}


	/**
	 * Determines if sync is enabled.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function is_sync_enabled() {

		return $this->get_plugin()->get_settings_handler()->is_product_sync_enabled();
	}


	/** Setter methods ************************************************************************************************/


	/**
	 * Records a successful sync.
	 *
	 * @since 2.0.0
	 *
	 * @param int[] $product_ids IDs of products synced
	 * @param null|\stdClass $job optional sync job, may be used to set the job ID to prevent duplicates
	 */
	public function record_sync( array $product_ids, $job = null ) {

		$products = count( $product_ids );

		// only add a record of some products were synced
		if ( $products ) {

			Records::set_record(
				array(
					'id'      => $job && isset( $job->id ) ? $job->id : null,
					'message' => sprintf(
						/* translators: Placeholder: %d number of products processed */
						_n( 'Updated data for %d product.', 'Updated data for %d products.', $products, 'woocommerce-square' ),
						$products
					),
				)
			);
		}

		/**
		 * Fires after a set of products are synced with square.
		 *
		 * @since 2.0.0
		 *
		 * @param int[] $product_ids IDs for products that were synced
		 */
		do_action( 'wc_square_products_synced', $product_ids );
	}


	/**
	 * Updates the time when the last sync job occurred.
	 *
	 * @since 2.0.0
	 *
	 * @param int|string|null $timestamp a valid timestamp in UTC (optional, will default to now)
	 * @return bool success
	 */
	public function set_last_synced_at( $timestamp = null ) {

		if ( null === $timestamp ) {
			$timestamp = time();
		}

		return is_numeric( $timestamp ) && update_option( $this->last_synced_at_option_key, (int) $timestamp );
	}


	/** Getter methods ************************************************************************************************/


	/**
	 * Gets a job that is currently in progress.
	 *
	 * @since 2.0.0
	 *
	 * @return null|\stdClass background job object or null if not found
	 */
	public function get_job_in_progress() {

		$handler = $this->get_plugin()->get_background_job_handler();

		try {
			$job = $handler->get_job();
		} catch ( \Exception $e ) {
			$job = null;
		}

		return $job && isset( $job->status ) && in_array( $job->status, array( 'created', 'queued', 'processing' ), true ) ? $job : null;
	}

	/**
	 * Gets the timestamp when the next sync job should start.
	 *
	 * @since 2.0.0
	 *
	 * @return int
	 */
	public function get_next_sync_at() {

		$timestamp = null;
		$scheduled = as_next_scheduled_action( $this->sync_scheduled_event_name );

		if ( $scheduled ) {
			$timestamp = $scheduled;
		}

		return (int) $timestamp > 1 ? $timestamp : null;
	}


	/**
	 * Gets the timestamp for when the last sync job completed.
	 *
	 * @since 2.0.0
	 *
	 * @return int
	 */
	public function get_last_synced_at() {

		$timestamp = get_option( $this->last_synced_at_option_key, null );

		return (int) $timestamp > 1 ? $timestamp : null;
	}


	/**
	 * Sets the timestamp for when the last inventory sync job started.
	 *
	 * @since 2.0.0
	 *
	 * @param int|string|null $timestamp a valid timestamp in UTC (optional, will default to now)
	 * @return bool success
	 */
	public function set_inventory_last_synced_at( $timestamp = null ) {

		if ( null === $timestamp ) {
			$timestamp = time();
		}

		return is_numeric( $timestamp ) && update_option( $this->last_synced_at_option_key . '_inventory', $timestamp );
	}


	/**
	 * Gets the timestamp for when the last inventory sync job completed.
	 *
	 * @since 2.0.0
	 *
	 * @return int
	 */
	public function get_inventory_last_synced_at() {

		$timestamp = get_option( $this->last_synced_at_option_key . '_inventory', null );

		return (int) $timestamp > 1 ? $timestamp : null;
	}


	/**
	 * Gets the plugin instance.
	 *
	 * @since 2.0.0
	 *
	 * @return Plugin
	 */
	private function get_plugin() {

		return $this->plugin;
	}

	/**
	 * Show warning not to close or navigate away from the current
	 * page when product import is in progress.
	 */
	public function render_import_no_navigation_warning() {
		$job_in_progress = wc_square()->get_sync_handler()->get_job_in_progress();

		if ( $job_in_progress && 'product_import' === $job_in_progress->action ) {
			wc_square()->get_admin_notice_handler()->add_admin_notice(
				__( 'Please do not close or navigate away from this page as the product import job is in progress. This page may load several times during the course of sync.', 'woocommerce-square' ),
				'wc-square-sync-in-progress-message',
				array(
					'notice_class' => 'notice-warning',
				)
			);
		}
	}
}
