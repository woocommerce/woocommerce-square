<?php
/**
 * Tests for the result Handlers\Connection::refresh_connection() reports.
 *
 * @package WooCommerce\Square
 */

namespace WooCommerce\Square\Tests\Unit\Handlers;

use WP_UnitTestCase;

require_once __DIR__ . '/Memory_Settings.php';
require_once __DIR__ . '/Memory_Plugin.php';
require_once __DIR__ . '/Testable_Connection.php';

class Refresh_Connection_Test extends WP_UnitTestCase {

	/**
	 * Plugin double.
	 *
	 * @var Memory_Plugin
	 */
	private $plugin;

	public function setUp(): void {
		parent::setUp();

		$this->plugin = new Memory_Plugin();
		update_option( 'wc_square_refresh_failed', 'yes' );
	}

	public function tearDown(): void {
		remove_all_filters( 'pre_http_request' );
		delete_option( 'wc_square_refresh_failed' );

		parent::tearDown();
	}

	/**
	 * Answers the refresh proxy with the given JSON body.
	 *
	 * @param array $body Response body.
	 */
	private function respond_with( array $body ) {
		add_filter(
			'pre_http_request',
			static function () use ( $body ) {
				return array(
					'headers'  => array(),
					'body'     => wp_json_encode( $body ),
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'cookies'  => array(),
				);
			}
		);
	}

	/**
	 * A stored new token is reported as a success, so the API keeps the connection.
	 */
	public function test_returns_true_when_new_token_is_stored() {
		$this->respond_with(
			array(
				'access_token'  => 'new-access-token',
				'refresh_token' => 'new-refresh-token',
			)
		);

		$this->assertTrue( ( new Testable_Connection( $this->plugin ) )->refresh_connection() );
		$this->assertSame( 'new-access-token', $this->plugin->settings->access_token );
		$this->assertFalse( get_option( 'wc_square_refresh_failed', false ) );
	}

	/**
	 * Square may keep the same refresh token, which must still count as a success.
	 */
	public function test_returns_true_when_only_access_token_is_returned() {
		$this->respond_with( array( 'access_token' => 'new-access-token' ) );

		$this->assertTrue( ( new Testable_Connection( $this->plugin ) )->refresh_connection() );
		$this->assertSame( 'refresh-token', $this->plugin->settings->refresh_token );
	}

	/**
	 * Sandbox never refreshes. Before refresh_connection() reported a result, the API read the
	 * failure option instead, which sandbox never sets, and took the no-op for a success.
	 */
	public function test_returns_false_in_sandbox_without_making_a_request() {
		$this->plugin->settings->sandbox = true;
		$requested                       = false;

		add_filter(
			'pre_http_request',
			static function () use ( &$requested ) {
				$requested = true;
				return new \WP_Error( 'unexpected', 'No request expected in sandbox.' );
			}
		);

		$this->assertFalse( ( new Testable_Connection( $this->plugin ) )->refresh_connection() );
		$this->assertFalse( $requested );
		$this->assertSame( 'old-access-token', $this->plugin->settings->access_token );
	}
}
