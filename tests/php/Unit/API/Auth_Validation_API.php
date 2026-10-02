<?php
/**
 * API double that runs the response validator against a canned response.
 *
 * @package WooCommerce\Square
 */

namespace WooCommerce\Square\Tests\Unit\API;

use WooCommerce\Square\API;
use WooCommerce\Square\API\Response;

/**
 * API double that validates a canned response against the plugin double.
 *
 * The parent constructor is deliberately not called so no Square client is built.
 */
class Auth_Validation_API extends API {

	/**
	 * Plugin double.
	 *
	 * @var Recording_Plugin
	 */
	public $plugin;

	/**
	 * Sets the plugin double and the response to validate.
	 *
	 * @param Recording_Plugin $plugin   Plugin double.
	 * @param Response         $response Response to validate.
	 */
	public function __construct( Recording_Plugin $plugin, Response $response ) {
		$this->plugin   = $plugin;
		$this->response = $response;
	}

	/**
	 * Returns the plugin double.
	 *
	 * @return Recording_Plugin
	 */
	public function get_plugin() {
		return $this->plugin;
	}

	/**
	 * Exposes the validator under test.
	 *
	 * @return bool
	 * @throws \Exception When the response cannot be validated.
	 */
	public function validate() {
		return $this->do_post_parse_response_validation();
	}
}
