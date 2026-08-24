<?php
/**
 * Concrete Stepped_Job used to exercise the zero verification retry policy.
 *
 * @package WooCommerce\Square
 */

namespace WooCommerce\Square\Tests\Unit\Sync\Fixtures;

use WooCommerce\Square\Sync\Stepped_Job;

/**
 * Stepped_Job is abstract and its verification policy is protected, so this double supplies the one
 * abstract method and exposes the policy for assertions. Nothing else is overridden: the attempt
 * counting, the alert and the persistence all run as they do in production.
 */
class Zero_Verification_Policy_Job extends Stepped_Job {

	/**
	 * No steps are needed for policy tests.
	 */
	protected function assign_next_steps() {}

	/**
	 * @param string $step_name step name.
	 * @return bool
	 */
	public function policy_should_retry( $step_name ) {
		return $this->should_retry_unverified_zero_counts( $step_name );
	}

	/**
	 * @param string $step_name step name.
	 * @return bool
	 */
	public function policy_exhausted( $step_name ) {
		return $this->zero_verification_exhausted( $step_name );
	}

	/**
	 * @param string   $step_name       step name.
	 * @param string[] $zero_object_ids ids reporting a zero count.
	 * @return array|null
	 */
	public function policy_resolve( $step_name, array $zero_object_ids ) {
		return $this->resolve_zero_count_verification( $step_name, $zero_object_ids );
	}

	/**
	 * @param string $attr  attribute name.
	 * @param mixed  $value attribute value.
	 */
	public function policy_set_attr( $attr, $value ) {
		$this->set_attr( $attr, $value );
	}

	/**
	 * @param string $attr attribute name.
	 * @return mixed
	 */
	public function policy_get_attr( $attr ) {
		return $this->get_attr( $attr );
	}
}
