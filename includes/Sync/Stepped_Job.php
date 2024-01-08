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

namespace WooCommerce\Square\Sync;

defined( 'ABSPATH' ) || exit;

/**
 * Stepped Job abstract.
 *
 * Adds multi-step management to the job class.
 *
 * @since 2.0.0
 */
abstract class Stepped_Job extends Job {


	/**
	 * Executes the next step of this job.
	 *
	 * @since 2.0.0
	 *
	 * @return \stdClass the job object
	 */
	public function run() {

		parent::run();

		if ( empty( $this->get_attr( 'next_steps' ) ) && empty( $this->get_attr( 'completed_steps' ) ) ) {
			$this->assign_next_steps();
		}

		$this->do_next_step();

		return $this->job;
	}


	/**
	 * Assigns the next steps needed for this sync job.
	 *
	 * Adds the next steps to the 'next_steps' attribute.
	 *
	 * @since 2.0.0
	 */
	abstract protected function assign_next_steps();


	/**
	 * Gets the next step in the sync process.
	 *
	 * @since 2.0.0
	 *
	 * @return string|null
	 */
	protected function get_next_step() {

		$next_steps = $this->get_next_steps();

		return isset( $next_steps[0] ) ? $next_steps[0] : null;
	}


	/**
	 * Gets the next steps for the sync process.
	 *
	 * @since 2.0.0
	 *
	 * @return string[]
	 */
	protected function get_next_steps() {

		return $this->get_attr( 'next_steps' );
	}


	/**
	 * Performs the next step in the sync process.
	 *
	 * @since 2.0.0
	 */
	protected function do_next_step() {
		$max_retry = 3;  // Maximum number of retries for rate limit errors.
		$retry     = $this->get_attr( 'retry', 0 ); // Number of retries for rate limit errors.

		$next_step = $this->get_next_step();

		if ( is_callable( array( $this, $next_step ) ) ) {

			$this->start_step_cycle( $next_step );

			try {

				$this->$next_step();
				$this->complete_step_cycle( $next_step );
				$this->set_attr( 'retry', 0 ); // Reset retry count to 0 after successful step cycle.
			} catch ( \Exception $exception ) {
				$error_message = $exception->getMessage();
				// If sync fail with rate limit error, retry the sync process after few seconds. (retry upto 3 times)
				if ( false !== strpos( $error_message, 'RATE_LIMITED' ) && $retry < $max_retry ) {
					wc_square()->log( 'Rate limit error detected, pausing sync process for few secs...' );
					$this->set_attr( 'retry', $retry + 1 );
					return;
				}

				$this->complete_step_cycle( $next_step, false, $exception->getMessage() );
				$this->fail( $exception->getMessage() );
				return;
			}
		}

		if ( ! $this->get_next_step() ) {

			$this->complete();
		}
	}


	/**
	 * Records the beginning of a new step cycle, meaning a new loop on the job for a given step.
	 *
	 * @since 2.0.0
	 *
	 * @param string $step_name the step name
	 */
	protected function start_step_cycle( $step_name ) {

		$current_step_cycle = array(
			'step_name'  => $step_name,
			'start_time' => microtime( true ),
		);

		wc_square()->log( "Starting step cycle: $step_name" );

		$this->set_attr( 'current_step_cycle', $current_step_cycle );
	}


	/**
	 * Records the completion of a step cycle.
	 *
	 * @since 2.0.0
	 *
	 * @param string $step_name the step name
	 * @param bool $is_successful (optional) whether the step completion is from a success or not
	 * @param string $error_message (optional) error message to include with failed step log
	 */
	protected function complete_step_cycle( $step_name, $is_successful = true, $error_message = '' ) {

		$current_step_cycle = $this->get_attr( 'current_step_cycle', array() );

		if ( ! empty( $current_step_cycle ) ) {

			$current_step_cycle['end_time'] = microtime( true );
			$current_step_cycle['runtime']  = number_format( $current_step_cycle['end_time'] - $current_step_cycle['start_time'], 2 ) . 's';
			$current_step_cycle['success']  = true === $is_successful;

			if ( true === $is_successful ) {

				wc_square()->log( "Completed step cycle: $step_name ({$current_step_cycle['runtime']})" );

			} else {

				wc_square()->log( "Failed step cycle: $step_name ({$current_step_cycle['runtime']}) - $error_message" );
			}

			$completed_cycles   = $this->get_attr( 'completed_step_cycles', array() );
			$completed_cycles[] = $current_step_cycle;
			$this->set_attr( 'completed_step_cycles', $completed_cycles );
		}
	}


	/**
	 * Completes the specified step (if it's the next step).
	 *
	 * @since 2.0.0
	 *
	 * @param string $step_name
	 */
	protected function complete_step( $step_name ) {

		$next_steps = $this->get_next_steps();

		if ( isset( $next_steps[0] ) && $step_name === $next_steps[0] ) {

			$this->add_completed_step( $step_name );
			array_shift( $next_steps );
			$this->set_attr( 'next_steps', $next_steps );
		}
	}


	/**
	 * Adds a step to the completed steps array.
	 *
	 * @since 2.0.0
	 *
	 * @param string $step_name
	 */
	protected function add_completed_step( $step_name ) {

		if ( empty( $step_name ) ) {
			return;
		}

		$completed_steps = $this->get_attr( 'completed_steps', array() );

		$completed_steps[] = array(
			'name'            => $step_name,
			'completion_time' => current_time( 'mysql' ),
		);

		$this->set_attr( 'completed_steps', $completed_steps );

		$update_data = $this->get_step_update_data( $step_name );

		wc_square()->log( 'Completed job step: ' . $step_name . $update_data );
	}

	/**
	 * Get step update data like count of synced products or categories.
	 *
	 * @param string $step_name Step name.
	 * @return string
	 */
	protected function get_step_update_data( $step_name ) {
		$update_data = '';
		$count       = $this->get_attr( $step_name . '_count', 0 );
		switch ( $step_name ) {
			// Product Import.
			case 'import_products':
				$imported    = count( $this->get_attr( 'processed_product_ids', array() ) );
				$updated     = count( $this->get_attr( 'updated_product_ids', array() ) );
				$skipped     = count( $this->get_attr( 'skipped_products', array() ) );
				$update_data = sprintf( ' (Imported products: %d, Updated products: %d, Skipped products: %d)', $imported, $updated, $skipped );
				break;

			case 'import_inventory':
				$update_data = sprintf( ' (Synced products: %d)', $count );
				break;

			// Manual Sync.
			case 'validate_products':
				$count       = count( $this->get_attr( 'validated_product_ids', array() ) );
				$update_data = sprintf( ' (Validated products: %d)', $count );
				break;

			case 'extract_category_ids':
				$count       = count( $this->get_attr( 'category_ids', array() ) );
				$update_data = sprintf( ' (Extracted categories: %d)', $count );
				break;

			case 'refresh_category_mappings':
				$mapped_cat   = count( $this->get_attr( 'mapped_categories', array() ) );
				$unmapped_cat = count( $this->get_attr( 'unmapped_categories', array() ) );
				$update_data  = sprintf( ' (Mapped categories: %d, Unmapped categories: %d)', $mapped_cat, $unmapped_cat );
				break;

			case 'query_unmapped_categories':
				$mapped_cat  = count( $this->get_attr( 'mapped_categories', array() ) );
				$update_data = sprintf( ' (Total mapped categories: %d)', $mapped_cat );
				break;

			case 'upsert_categories':
				$count       = count( $this->get_attr( 'category_ids', array() ) );
				$update_data = sprintf( ' (Upserted categories: %d)', $count );
				break;

			case 'update_matched_products':
				$count       = count( $this->get_attr( 'processed_product_ids', array() ) );
				$update_data = sprintf( ' (Synced matched products: %d)', $count );
				break;

			case 'search_matched_products':
			case 'square_sor_sync':
				$count       = count( $this->get_attr( 'processed_product_ids', array() ) );
				$update_data = sprintf( ' (Synced products: %d)', $count );
				break;

			case 'upsert_new_products':
				$count       = count( $this->get_attr( 'inventory_push_product_ids', array() ) );
				$update_data = sprintf( ' (Newly upserted products: %d)', $count );
				break;

			case 'push_inventory':
				$update_data = sprintf( ' (Synced products: %d)', $count );
				break;

			case 'pull_inventory':
				$count       = count( $this->get_attr( 'processed_square_variation_ids', array() ) );
				$update_data = sprintf( ' (Synced products: %d)', $count );
				break;

			// Interval Polling.
			case 'update_category_data':
				$update_data = sprintf( ' (Updated categories: %d)', $count );
				break;

			case 'update_product_data':
				$update_data = sprintf( ' (Updated products: %d)', $count );
				break;

			case 'update_inventory_counts':
				$update_data = sprintf( ' (Synced products: %d)', $count );
				break;

			default:
				break;
		}

		return $update_data;
	}

}
