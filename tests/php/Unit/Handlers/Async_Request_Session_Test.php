<?php
/**
 * Tests that the add-to-cart inventory refresh never saves a stale shopper session.
 *
 * @package WooCommerce\Square
 */

namespace WooCommerce\Square\Tests\Unit\Handlers;

use WooCommerce\Square\Handlers\Async_Request;
use WooCommerce\Square\Handlers\Products;
use WP_UnitTestCase;

class Async_Request_Session_Test extends WP_UnitTestCase {

	/**
	 * Session in place before the test.
	 *
	 * @var \WC_Session|null
	 */
	private $original_session;

	public function setUp(): void {
		parent::setUp();

		if ( ! class_exists( Async_Request::class ) || ! class_exists( '\WC_Session_Handler' ) ) {
			$this->markTestSkipped( 'WooCommerce is not loaded.' );
		}

		$this->original_session = WC()->session;
		wp_set_current_user( self::factory()->user->create() );
	}

	public function tearDown(): void {
		if ( WC()->session ) {
			remove_action( 'shutdown', array( WC()->session, 'save_data' ), 20 );
		}
		WC()->session = $this->original_session;

		parent::tearDown();
	}

	/**
	 * Builds a session handler for the current user, loaded from the database now.
	 *
	 * @return \WC_Session_Handler
	 */
	private function load_session() {
		$session = new \WC_Session_Handler();
		$session->init();
		remove_action( 'shutdown', array( $session, 'save_data' ), 20 );

		return $session;
	}

	/**
	 * Runs the async request handler without dispatching an HTTP request.
	 */
	private function run_handler() {
		$handle = new \ReflectionMethod( Async_Request::class, 'handle' );
		$handle->setAccessible( true );
		$handle->invoke( new Async_Request() );
	}

	/**
	 * Runs the shutdown callbacks at WooCommerce's session save priority.
	 *
	 * Running the whole shutdown action would flush output buffers mid-test.
	 */
	private function run_session_save_callbacks() {
		global $wp_filter;

		$callbacks = isset( $wp_filter['shutdown'] ) ? ( $wp_filter['shutdown']->callbacks[20] ?? array() ) : array();
		foreach ( $callbacks as $callback ) {
			call_user_func( $callback['function'] );
		}
	}

	/**
	 * The async request unhooks WooCommerce's session save, so its copy is never persisted.
	 */
	public function test_handle_unhooks_session_save() {
		WC()->session = new \WC_Session_Handler();
		WC()->session->init();

		$this->assertSame( 20, has_action( 'shutdown', array( WC()->session, 'save_data' ) ) );

		$this->run_handler();

		$this->assertFalse( has_action( 'shutdown', array( WC()->session, 'save_data' ) ) );
	}

	/**
	 * A stale, dirty session in the async request does not overwrite a newer cart.
	 */
	public function test_stale_session_does_not_overwrite_newer_cart() {
		// The async request loads the session before the add-to-cart request saves it.
		$stale = new \WC_Session_Handler();
		$stale->init();

		// The add-to-cart request then saves the newer cart.
		$fresh = $this->load_session();
		$fresh->set( 'cart', array( 'newer-item' => array( 'quantity' => 1 ) ) );
		$fresh->save_data();

		// Something in the async request changes its stale session.
		WC()->session = $stale;
		WC()->session->set( 'unrelated_key', 'changed' );

		$this->run_handler();
		$this->run_session_save_callbacks();

		$stored = $this->load_session();
		$this->assertSame( array( 'newer-item' => array( 'quantity' => 1 ) ), $stored->get( 'cart' ) );
	}

	/**
	 * The inventory refresh is dispatched after WooCommerce saves the session at priority 20.
	 */
	public function test_inventory_refresh_dispatches_after_session_save() {
		$products = ( new \ReflectionClass( Products::class ) )->newInstanceWithoutConstructor();

		$add_hooks = new \ReflectionMethod( Products::class, 'add_product_sync_hooks' );
		$add_hooks->setAccessible( true );
		$add_hooks->invoke( $products );

		$this->assertSame( 99, has_action( 'shutdown', array( $products, 'maybe_sync_product_inventory' ) ) );

		remove_action( 'woocommerce_add_to_cart', array( $products, 'maybe_stage_products_for_sync_inventory' ) );
		remove_action( 'shutdown', array( $products, 'maybe_sync_staged_products' ) );
		remove_action( 'shutdown', array( $products, 'maybe_delete_staged_products' ) );
		remove_action( 'shutdown', array( $products, 'maybe_sync_product_inventory' ), 99 );
	}
}
