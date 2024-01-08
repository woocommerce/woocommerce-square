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
 * Synchronization Job abstract.
 *
 * Synchronization jobs should extend this parent method with their own synchronization logic.
 * @see \WooCommerce\Square\Handlers\Background_Job main handler should be responsible to set and create all Square background jobs
 *
 * @since 2.0.0
 */
class Job {


	/** @var \stdClass background job object */
	protected $job;

	/**
	 * Synchronization job constructor.
	 *
	 * @since 2.0.0
	 *
	 * @param null|\stdClass $job background synchronization job object
	 */
	public function __construct( $job = null ) {

		if ( null === $job ) {
			$job = new \stdClass();
		}

		$this->job = $job;
	}

	/**
	 * Gets an attribute from the underlying job object.
	 *
	 * @since 2.0.0
	 *
	 * @param string $attr_name the attribute name
	 * @param mixed $default_value value if attribute is not found
	 * @return mixed
	 */
	protected function get_attr( $attr_name, $default_value = null ) {
		return isset( $this->job->$attr_name ) ? $this->job->$attr_name : $default_value;
	}


	/**
	 * Sets an attribute on the underlying job object.
	 *
	 * @since 2.0.0
	 *
	 * @param string $attr_name the attribute name
	 * @param mixed $attr_value the attribute value
	 * @param bool $update whether to update the job object (defaults to true)
	 */
	protected function set_attr( $attr_name, $attr_value, $update = true ) {

		$this->job->$attr_name = $attr_value;

		if ( true === $update ) {
			wc_square()->get_background_job_handler()->update_job( $this->job );
		}
	}


	/**
	 * Checks if the job is currently locked.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	protected function is_job_locked() {

		return (bool) $this->get_attr( 'locked' );
	}


	/**
	 * Locks the job.
	 *
	 * @since 2.0.0
	 */
	protected function lock_job() {

		$this->set_attr( 'locked', true );
	}


	/**
	 * Unlocks the job.
	 *
	 * @since 2.0.0
	 */
	protected function unlock_job() {

		$this->set_attr( 'locked', false );
	}


	/**
	 * Executes the job.
	 *
	 * Child implementation should override this method with their own job processing logic.
	 *
	 * @since 2.0.0
	 *
	 * @return \stdClass the job object
	 */
	public function run() {
		wp_set_current_user( $this->get_attr( 'created_by' ) );

		if ( ! defined( 'DOING_SQUARE_SYNC' ) || false === DOING_SQUARE_SYNC ) {
			define( 'DOING_SQUARE_SYNC', true );
		}

		return $this->job;
	}


	/**
	 * Completes the job.
	 *
	 * @since 2.0.0
	 *
	 * @return \stdClass the job object
	 */
	protected function complete() {
		return $this->job = wc_square()->get_background_job_handler()->complete_job( $this->job );
	}


	/**
	 * Fails the job.
	 *
	 * @since 2.0.0
	 *
	 * @param string $reason failure reason message (optional)
	 * @return \stdClass the job object
	 */
	protected function fail( $reason = '' ) {

		if ( ! empty( $reason ) ) {
			wc_square()->log( $reason );
		}

		return $this->job = wc_square()->get_background_job_handler()->fail_job( $this->job, $reason );
	}


	/**
	 * Checks if this job uses WooCommerce as the SOR.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function is_system_of_record_woocommerce() {

		return 'woocommerce' === $this->get_attr( 'system_of_record' );
	}


	/**
	 * Checks if this is job uses square as the SOR.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function is_system_of_record_square() {

		return 'square' === $this->get_attr( 'system_of_record' );
	}
}
