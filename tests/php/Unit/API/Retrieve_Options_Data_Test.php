<?php
/**
 * Tests for WooCommerce\Square\API::retrieve_options_data().
 *
 * @package WooCommerce\Square
 */

namespace WooCommerce\Square\Tests\Unit\API;

use WooCommerce\Square\API;
use WP_UnitTestCase;

require_once __DIR__ . '/Scripted_API.php';

class Retrieve_Options_Data_Test extends WP_UnitTestCase {

	/**
	 * Scripted API under test.
	 *
	 * @var Scripted_API
	 */
	private $api;

	public function setUp(): void {
		parent::setUp();

		delete_transient( 'wc_square_options_data' );
		delete_transient( API::OPTIONS_DATA_PARTIAL_TRANSIENT );

		$this->api = new Scripted_API();
	}

	public function tearDown(): void {
		delete_transient( 'wc_square_options_data' );
		delete_transient( API::OPTIONS_DATA_PARTIAL_TRANSIENT );

		parent::tearDown();
	}

	/**
	 * The regression this covers: every page but the last used to be thrown away, because
	 * each call started from an empty array and only the final page reached the cache.
	 */
	public function test_paginated_read_keeps_every_page_in_the_finished_transient() {
		$this->api->queue_catalog_pages(
			array(
				array(
					'objects' => array(
						Scripted_API::make_option( 'OPT_A', 'Colour', array( 'VAL_A1' => 'Red' ) ),
						Scripted_API::make_option( 'OPT_B', 'Size', array( 'VAL_B1' => 'Small' ) ),
					),
					'cursor'  => 'CURSOR_PAGE_TWO',
				),
				array(
					'objects' => array(
						Scripted_API::make_option( 'OPT_C', 'Material', array( 'VAL_C1' => 'Cotton' ) ),
					),
					'cursor'  => null,
				),
			)
		);

		// First page: nothing is promoted to the finished cache yet.
		list( , $first_page_data, $first_cursor ) = $this->api->retrieve_options_data();

		$this->assertSame( 'CURSOR_PAGE_TWO', $first_cursor );
		$this->assertSame( array( 'OPT_A', 'OPT_B' ), array_keys( $first_page_data ) );
		$this->assertFalse( get_transient( 'wc_square_options_data' ), 'A half read catalogue must not land in the finished cache.' );
		$this->assertSame( array( 'OPT_A', 'OPT_B' ), array_keys( get_transient( API::OPTIONS_DATA_PARTIAL_TRANSIENT ) ) );

		// Second page: the cursor is exhausted, so the accumulated set is promoted.
		list( , $final_data, $final_cursor ) = $this->api->retrieve_options_data( $first_cursor );

		$this->assertNull( $final_cursor );
		$this->assertSame( array( 'OPT_A', 'OPT_B', 'OPT_C' ), array_keys( $final_data ) );

		$finished = get_transient( 'wc_square_options_data' );

		$this->assertSame( array( 'OPT_A', 'OPT_B', 'OPT_C' ), array_keys( $finished ) );
		$this->assertSame( 'Colour', $finished['OPT_A']['name'] );
		$this->assertSame( 'Material', $finished['OPT_C']['name'] );
		$this->assertSame( array( 'VAL_A1' => 'Red' ), $finished['OPT_A']['value_ids'] );
		$this->assertSame( array( 'Small' ), $finished['OPT_B']['values'] );

		$this->assertFalse( get_transient( API::OPTIONS_DATA_PARTIAL_TRANSIENT ), 'The partial cache must be cleared once the read completes.' );
		$this->assertSame( array( '', 'CURSOR_PAGE_TWO' ), $this->api->list_catalog_cursors );
	}

	/**
	 * Three pages, to prove the middle one is not the special case that survives.
	 */
	public function test_three_page_read_accumulates_all_pages() {
		$this->api->queue_catalog_pages(
			array(
				array(
					'objects' => array( Scripted_API::make_option( 'OPT_A', 'Colour' ) ),
					'cursor'  => 'CURSOR_2',
				),
				array(
					'objects' => array( Scripted_API::make_option( 'OPT_B', 'Size' ) ),
					'cursor'  => 'CURSOR_3',
				),
				array(
					'objects' => array( Scripted_API::make_option( 'OPT_C', 'Material' ) ),
					'cursor'  => null,
				),
			)
		);

		$cursor = '';

		do {
			list( , $options_data, $cursor ) = $this->api->retrieve_options_data( $cursor );
		} while ( $cursor );

		$this->assertSame( array( 'OPT_A', 'OPT_B', 'OPT_C' ), array_keys( $options_data ) );
		$this->assertSame( array( 'OPT_A', 'OPT_B', 'OPT_C' ), array_keys( get_transient( 'wc_square_options_data' ) ) );
	}

	/**
	 * A later page holding a newer copy of an option must win over the earlier page.
	 */
	public function test_later_page_wins_for_a_repeated_option_id() {
		$this->api->queue_catalog_pages(
			array(
				array(
					'objects' => array( Scripted_API::make_option( 'OPT_A', 'Colour', array( 'VAL_A1' => 'Red' ) ) ),
					'cursor'  => 'CURSOR_2',
				),
				array(
					'objects' => array(
						Scripted_API::make_option(
							'OPT_A',
							'Colour',
							array(
								'VAL_A1' => 'Red',
								'VAL_A2' => 'Blue',
							)
						),
					),
					'cursor'  => null,
				),
			)
		);

		list( , , $cursor ) = $this->api->retrieve_options_data();
		$this->api->retrieve_options_data( $cursor );

		$finished = get_transient( 'wc_square_options_data' );

		$this->assertSame( array( 'Red', 'Blue' ), $finished['OPT_A']['values'] );
	}

	/**
	 * A read abandoned part way through leaves a partial cache behind for up to an hour.
	 * A fresh read starts at an empty cursor and must not inherit it.
	 */
	public function test_fresh_read_ignores_a_partial_left_by_an_abandoned_read() {
		set_transient(
			API::OPTIONS_DATA_PARTIAL_TRANSIENT,
			array(
				'OPT_STALE' => array(
					'name'      => 'Abandoned',
					'values'    => array(),
					'value_ids' => array(),
				),
			),
			HOUR_IN_SECONDS
		);

		$this->api->queue_catalog_pages(
			array(
				array(
					'objects' => array( Scripted_API::make_option( 'OPT_A', 'Colour' ) ),
					'cursor'  => null,
				),
			)
		);

		list( , $options_data ) = $this->api->retrieve_options_data();

		$this->assertSame( array( 'OPT_A' ), array_keys( $options_data ) );
		$this->assertSame( array( 'OPT_A' ), array_keys( get_transient( 'wc_square_options_data' ) ) );
		$this->assertFalse( get_transient( API::OPTIONS_DATA_PARTIAL_TRANSIENT ) );
	}

	/**
	 * A complete cache still short circuits the whole walk.
	 */
	public function test_finished_cache_short_circuits_without_touching_the_api() {
		$cached = array(
			'OPT_A' => array(
				'name'      => 'Colour',
				'values'    => array( 'Red' ),
				'value_ids' => array( 'VAL_A1' => 'Red' ),
			),
		);

		set_transient( 'wc_square_options_data', $cached, DAY_IN_SECONDS );

		list( $response, $options_data ) = $this->api->retrieve_options_data();

		$this->assertSame( '', $response );
		$this->assertSame( $cached, $options_data );
		$this->assertSame( array(), $this->api->list_catalog_cursors );
	}

	/**
	 * A cache carrying a nameless option is refetched rather than trusted, and the
	 * refetch is still allowed to paginate.
	 */
	public function test_nameless_cache_is_refetched_and_may_paginate() {
		set_transient(
			'wc_square_options_data',
			array(
				'OPT_OLD' => array(
					'name'      => '',
					'values'    => array(),
					'value_ids' => array(),
				),
			),
			DAY_IN_SECONDS
		);

		$this->api->queue_catalog_pages(
			array(
				array(
					'objects' => array( Scripted_API::make_option( 'OPT_A', 'Colour' ) ),
					'cursor'  => 'CURSOR_2',
				),
				array(
					'objects' => array( Scripted_API::make_option( 'OPT_B', 'Size' ) ),
					'cursor'  => null,
				),
			)
		);

		list( , , $cursor ) = $this->api->retrieve_options_data();
		$this->api->retrieve_options_data( $cursor );

		$finished = get_transient( 'wc_square_options_data' );

		$this->assertSame( array( 'OPT_A', 'OPT_B' ), array_keys( $finished ) );
		$this->assertArrayNotHasKey( 'OPT_OLD', $finished );
	}
}
