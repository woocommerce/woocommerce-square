<?php
/**
 * Tests for how API::do_post_parse_response_validation() handles authorization errors.
 *
 * @package WooCommerce\Square
 */

namespace WooCommerce\Square\Tests\Unit\API;

use Square\Models\Error;
use WooCommerce\Square\API\Response;
use WP_UnitTestCase;

require_once __DIR__ . '/Recording_Connection_Handler.php';
require_once __DIR__ . '/Recording_Plugin.php';
require_once __DIR__ . '/Auth_Validation_API.php';

class Auth_Refresh_Test extends WP_UnitTestCase {

	/**
	 * Plugin double.
	 *
	 * @var Recording_Plugin
	 */
	private $plugin;

	public function setUp(): void {
		parent::setUp();

		$this->plugin = new Recording_Plugin();
	}

	/**
	 * Builds an API double whose last response carries the given error codes.
	 *
	 * @param string ...$codes Square error codes.
	 * @return Auth_Validation_API
	 */
	private function api_with_errors( ...$codes ) {
		$errors = array();

		foreach ( $codes as $code ) {
			$errors[] = new Error( 'AUTHENTICATION_ERROR', $code );
		}

		return new Auth_Validation_API( $this->plugin, new Response( $errors ) );
	}

	/**
	 * Runs the validator and returns the exception it raised.
	 *
	 * @param Auth_Validation_API $api API double.
	 * @return \Exception
	 */
	private function validation_exception( Auth_Validation_API $api ) {
		try {
			$api->validate();
		} catch ( \Exception $e ) {
			return $e;
		}

		$this->fail( 'An unauthorized response must never validate as a success.' );
	}

	/**
	 * A successful refresh keeps the store connected, but the triggering response still failed:
	 * returning it as valid hands callers an array of errors where they expect data.
	 *
	 * @dataProvider provide_refreshable_codes
	 */
	public function test_successful_refresh_keeps_connection_and_still_throws( $code ) {
		$this->plugin->connection_handler->refresh_result = true;

		$exception = $this->validation_exception( $this->api_with_errors( $code ) );

		$this->assertStringContainsString( "[{$code}]", $exception->getMessage() );
		$this->assertSame( 1, $this->plugin->connection_handler->refresh_calls );
		$this->assertSame( 0, $this->plugin->connection_handler->disconnect_calls, 'a refreshed token must not be cleared' );
		$this->assertContains( 'Connection successfully refreshed.', $this->plugin->logs );
	}

	/**
	 * A genuine refresh failure still disconnects. This also covers sandbox, where
	 * refresh_connection() is a no-op that reports false.
	 *
	 * @dataProvider provide_refreshable_codes
	 */
	public function test_failed_refresh_disconnects( $code ) {
		$this->plugin->connection_handler->refresh_result = false;

		$exception = $this->validation_exception( $this->api_with_errors( $code ) );

		$this->assertStringContainsString( "[{$code}]", $exception->getMessage() );
		$this->assertSame( 1, $this->plugin->connection_handler->refresh_calls );
		$this->assertSame( 1, $this->plugin->connection_handler->disconnect_calls );
		$this->assertNotContains( 'Connection successfully refreshed.', $this->plugin->logs );
	}

	/**
	 * The validator must branch on the refresh result, not on the failure option: the option is
	 * absent in sandbox and may be stale, so it does not say whether this refresh worked.
	 */
	public function test_failed_refresh_disconnects_even_when_failure_option_is_absent() {
		delete_option( 'wc_square_refresh_failed' );
		$this->plugin->connection_handler->refresh_result = false;

		$this->validation_exception( $this->api_with_errors( 'UNAUTHORIZED' ) );

		$this->assertSame( 1, $this->plugin->connection_handler->disconnect_calls );
	}

	/**
	 * Several auth errors in one response trigger one refresh, and once it succeeds the later
	 * errors must not disconnect the store.
	 */
	public function test_multiple_auth_errors_refresh_once_and_do_not_disconnect_after_success() {
		$this->plugin->connection_handler->refresh_result = true;

		$exception = $this->validation_exception( $this->api_with_errors( 'UNAUTHORIZED', 'ACCESS_TOKEN_EXPIRED' ) );

		$this->assertSame( '[UNAUTHORIZED] | [ACCESS_TOKEN_EXPIRED]', $exception->getMessage() );
		$this->assertSame( 1, $this->plugin->connection_handler->refresh_calls );
		$this->assertSame( 0, $this->plugin->connection_handler->disconnect_calls );
	}

	/**
	 * A revoked token cannot be refreshed, so it disconnects without attempting one.
	 */
	public function test_revoked_token_disconnects_without_refresh() {
		$this->validation_exception( $this->api_with_errors( 'ACCESS_TOKEN_REVOKED' ) );

		$this->assertSame( 0, $this->plugin->connection_handler->refresh_calls );
		$this->assertSame( 1, $this->plugin->connection_handler->disconnect_calls );
	}

	public function provide_refreshable_codes() {
		return array(
			'unauthorized'  => array( 'UNAUTHORIZED' ),
			'token expired' => array( 'ACCESS_TOKEN_EXPIRED' ),
		);
	}
}
