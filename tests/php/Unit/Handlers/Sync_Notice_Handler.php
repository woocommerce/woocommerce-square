<?php
/**
 * Admin notice handler double for the Sync handler tests.
 *
 * @package WooCommerce\Square
 */

namespace WooCommerce\Square\Tests\Unit\Handlers;

/**
 * Admin notice handler double that records notice IDs.
 */
class Sync_Notice_Handler {

	/**
	 * IDs of added notices.
	 *
	 * @var string[]
	 */
	public $notices = array();

	public function add_admin_notice( $message, $message_id ) {
		$this->notices[] = $message_id;
	}
}
