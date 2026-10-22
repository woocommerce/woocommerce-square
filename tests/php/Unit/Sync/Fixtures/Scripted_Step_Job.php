<?php
/**
 * Concrete Stepped_Job whose single step plays back a scripted sequence of outcomes.
 *
 * @package WooCommerce\Square
 */

namespace WooCommerce\Square\Tests\Unit\Sync\Fixtures;

use WooCommerce\Square\Sync\Stepped_Job;

/**
 * Each run of the step takes the next entry from the script: 'ok' finishes the cycle without finishing
 * the step, 'done' finishes the step, and any other string is thrown as an exception message. Nothing
 * else is overridden, so the retry counting and persistence run as they do in production.
 */
class Scripted_Step_Job extends Stepped_Job {

	/** @var string[] outcomes still to play back */
	public $script = array();

	/**
	 * A single scripted step.
	 */
	protected function assign_next_steps() {
		$this->set_attr( 'next_steps', array( 'scripted_step' ) );
	}

	/**
	 * Plays back the next scripted outcome.
	 *
	 * @throws \Exception when the outcome is an error message.
	 */
	protected function scripted_step() {
		$outcome = array_shift( $this->script );

		if ( 'done' === $outcome ) {
			$this->complete_step( 'scripted_step' );
		} elseif ( 'ok' !== $outcome ) {
			throw new \Exception( $outcome ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- scripted test message, never output.
		}
	}

	/**
	 * Exposes the lock refusal check.
	 *
	 * @param string $error_message exception message.
	 * @return bool
	 */
	public function lock_refusal( $error_message ) {
		return $this->is_catalog_lock_refusal( $error_message );
	}

	/**
	 * Reads a job attribute.
	 *
	 * @param string $attr attribute name.
	 * @return mixed
	 */
	public function attr( $attr ) {
		return $this->get_attr( $attr );
	}
}
