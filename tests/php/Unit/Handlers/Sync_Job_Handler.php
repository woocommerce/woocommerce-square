<?php
/**
 * Background job handler double for the Sync handler tests.
 *
 * @package WooCommerce\Square
 */

namespace WooCommerce\Square\Tests\Unit\Handlers;

/**
 * Background job handler double that counts job lookups.
 */
class Sync_Job_Handler {

	/**
	 * Number of times get_job() ran.
	 *
	 * @var int
	 */
	public $lookups = 0;

	/**
	 * Job returned by get_job().
	 *
	 * @var \stdClass|null
	 */
	public $job = null;

	public function get_job() {
		++$this->lookups;

		return $this->job;
	}
}
