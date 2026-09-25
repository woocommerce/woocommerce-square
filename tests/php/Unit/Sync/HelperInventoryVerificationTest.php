<?php
/**
 * Tests for the zero inventory count verification in WooCommerce\Square\Sync\Helper.
 *
 * Square reports IN_STOCK 0 both for an item that genuinely sold out and for an item nobody has ever
 * counted, so a zero may only be written to WooCommerce when the inventory change history proves it
 * real. These tests pin that discriminator down, including the states that caused regressions during
 * review: a failed lookup must never read as "no history", and a response is only proof for the ids it
 * actually names.
 *
 * @package WooCommerce\Square
 */

namespace WooCommerce\Square\Tests\Unit\Sync;

use WP_UnitTestCase;
use WooCommerce\Square\Sync\Helper;
use WooCommerce\Square\Tests\Unit\Sync\Fixtures\Scripted_History_Helper;

require_once __DIR__ . '/Fixtures/Scripted_History_Helper.php';

class HelperInventoryVerificationTest extends WP_UnitTestCase {

	public function tearDown(): void {
		Scripted_History_Helper::reset( array() );
		parent::tearDown();
	}

	/** A zero count is collected from a plain id to quantity map. */
	public function test_zero_count_object_ids_reads_a_flat_map() {
		$zeros = Helper::zero_count_object_ids(
			array(
				'SOLD_OUT'   => 0,
				'IN_STOCK'   => 7,
				'ALSO_ZERO'  => '0.0',
				'FRACTIONAL' => '0.5',
			)
		);

		$this->assertSame( array( 'SOLD_OUT', 'ALSO_ZERO' ), $zeros );
	}

	/** The polling and manual sync paths hold counts as arrays, so the quantity key form is accepted. */
	public function test_zero_count_object_ids_reads_a_stats_map() {
		$zeros = Helper::zero_count_object_ids(
			array(
				'SOLD_OUT' => array( 'quantity' => 0 ),
				'IN_STOCK' => array( 'quantity' => 3 ),
				'NO_KEY'   => array( 'IN_STOCK' => true ),
			),
			'quantity'
		);

		$this->assertSame( array( 'SOLD_OUT' ), $zeros, 'An entry without the quantity key is not a zero.' );
	}

	/** Nothing to verify means no API calls at all. */
	public function test_no_ids_makes_no_requests() {
		Scripted_History_Helper::reset( array() );

		$this->assertSame( array(), Scripted_History_Helper::get_catalog_objects_with_inventory_history( array() ) );
		$this->assertSame( array(), Scripted_History_Helper::$asked );
	}

	/** One page with no further pages settles the whole batch: named ids are real, the rest are phantoms. */
	public function test_single_page_settles_the_batch() {
		Scripted_History_Helper::reset( array( Scripted_History_Helper::page( array( 'REAL_SELLOUT' ) ) ) );

		$verified = Scripted_History_Helper::get_catalog_objects_with_inventory_history(
			array( 'REAL_SELLOUT', 'NEVER_COUNTED', 'ALSO_NEVER_COUNTED' )
		);

		$this->assertSame( array( 'REAL_SELLOUT' ), $verified );
		$this->assertCount( 1, Scripted_History_Helper::$asked, 'A conclusive first page needs no follow up.' );
	}

	/** The unresolved ids are re-asked as one group, not one request each. */
	public function test_unresolved_ids_are_re_asked_as_a_group() {
		Scripted_History_Helper::reset(
			array(
				Scripted_History_Helper::page( array( 'BUSY_ITEM' ), true ),
				Scripted_History_Helper::page( array(), false ),
			)
		);

		$verified = Scripted_History_Helper::get_catalog_objects_with_inventory_history(
			array( 'BUSY_ITEM', 'QUIET_A', 'QUIET_B' )
		);

		$this->assertSame( array( 'BUSY_ITEM' ), $verified );
		$this->assertCount( 2, Scripted_History_Helper::$asked );
		$this->assertSame( array( 'QUIET_A', 'QUIET_B' ), Scripted_History_Helper::$asked[1] );
	}

	/** Each group pass narrows the set until the remainder is proven empty. */
	public function test_group_passes_narrow_until_settled() {
		Scripted_History_Helper::reset(
			array(
				Scripted_History_Helper::page( array( 'BUSY_ITEM' ), true ),
				Scripted_History_Helper::page( array( 'SECOND_REAL' ), true ),
				Scripted_History_Helper::page( array(), false ),
			)
		);

		$verified = Scripted_History_Helper::get_catalog_objects_with_inventory_history(
			array( 'BUSY_ITEM', 'SECOND_REAL', 'PHANTOM' )
		);

		sort( $verified );
		$this->assertSame( array( 'BUSY_ITEM', 'SECOND_REAL' ), $verified );
		$this->assertSame( array( 'PHANTOM' ), Scripted_History_Helper::$asked[2], 'The last pass narrows to the remainder.' );
	}

	/** A group pass that names none of the remaining ids falls back to one request per id. */
	public function test_a_stalled_group_pass_falls_back_to_one_request_per_id() {
		Scripted_History_Helper::reset(
			array(
				Scripted_History_Helper::page( array( 'BUSY_ITEM' ), true ),
				Scripted_History_Helper::page( array(), true ),
				Scripted_History_Helper::page( array( 'HAS_HISTORY' ), false ),
				Scripted_History_Helper::page( array(), false ),
			)
		);

		$verified = Scripted_History_Helper::get_catalog_objects_with_inventory_history(
			array( 'BUSY_ITEM', 'HAS_HISTORY', 'PHANTOM' )
		);

		sort( $verified );
		$this->assertSame( array( 'BUSY_ITEM', 'HAS_HISTORY' ), $verified );
		$this->assertCount( 1, Scripted_History_Helper::$asked[2], 'The fallback asks about a single id.' );
		$this->assertCount( 1, Scripted_History_Helper::$asked[3] );
	}

	/**
	 * A response is proof only for the ids it names.
	 *
	 * Square drops the catalog object id filter when none of the supplied ids exists, answering with
	 * unrelated history. Reading a non empty response as proof wrote a phantom zero for products whose
	 * Square mapping had gone stale, which is the bug this ticket exists to fix.
	 */
	public function test_a_response_naming_other_objects_is_not_proof() {
		Scripted_History_Helper::reset(
			array(
				Scripted_History_Helper::page( array( 'BUSY_ITEM' ), true ),
				Scripted_History_Helper::page( array(), true ),
				Scripted_History_Helper::page( array( 'SOMEONE_ELSE' ), true ),
			)
		);

		$verified = Scripted_History_Helper::get_catalog_objects_with_inventory_history(
			array( 'BUSY_ITEM', 'STALE_MAPPING' )
		);

		$this->assertNotContains( 'STALE_MAPPING', $verified );
	}

	/** A failed lookup is null, which is distinct from a verified empty history. */
	public function test_a_failure_returns_null_rather_than_an_empty_verification() {
		foreach ( array(
			'on the first page'   => array( null ),
			'during a group pass' => array( Scripted_History_Helper::page( array( 'BUSY_ITEM' ), true ), null ),
			'during the fallback' => array(
				Scripted_History_Helper::page( array( 'BUSY_ITEM' ), true ),
				Scripted_History_Helper::page( array(), true ),
				null,
			),
		) as $label => $pages ) {
			Scripted_History_Helper::reset( $pages );

			$this->assertNull(
				Scripted_History_Helper::get_catalog_objects_with_inventory_history( array( 'BUSY_ITEM', 'UNKNOWN' ) ),
				"A failure {$label} must return null."
			);
		}
	}

	/** Group narrowing is capped, then the remainder is settled one id at a time. */
	public function test_group_narrowing_is_capped() {
		$pages = array( Scripted_History_Helper::page( array( 'BUSY_ITEM' ), true ) );
		$ids   = array( 'BUSY_ITEM' );

		for ( $i = 1; $i <= Helper::MAX_HISTORY_NARROWING_PASSES; $i++ ) {
			$ids[]   = "RESOLVES_{$i}";
			$pages[] = Scripted_History_Helper::page( array( "RESOLVES_{$i}" ), true );
		}
		$ids[]   = 'LEFTOVER';
		$pages[] = Scripted_History_Helper::page( array(), false );

		Scripted_History_Helper::reset( $pages );

		$verified = Scripted_History_Helper::get_catalog_objects_with_inventory_history( $ids );

		$this->assertNotContains( 'LEFTOVER', $verified, 'The leftover id has no history.' );
		$this->assertSame(
			array( 'LEFTOVER' ),
			end( Scripted_History_Helper::$asked ),
			'After the cap the remainder is asked about one id at a time.'
		);
		$this->assertCount(
			Helper::MAX_HISTORY_NARROWING_PASSES + 2,
			Scripted_History_Helper::$asked,
			'One first page, the capped group passes, then the per id fallback.'
		);
	}

	/** Ids are chunked so a large set of zeros cannot exceed the API limit in one request. */
	public function test_ids_are_chunked_at_one_hundred_per_request() {
		$ids = array();
		for ( $i = 1; $i <= 150; $i++ ) {
			$ids[] = 'OBJECT_' . $i;
		}

		Scripted_History_Helper::reset(
			array(
				Scripted_History_Helper::page( array( 'OBJECT_1' ) ),
				Scripted_History_Helper::page( array( 'OBJECT_101' ) ),
			)
		);

		$verified = Scripted_History_Helper::get_catalog_objects_with_inventory_history( $ids );

		sort( $verified );
		$this->assertSame( array( 'OBJECT_1', 'OBJECT_101' ), $verified );
		$this->assertCount( 2, Scripted_History_Helper::$asked );
		$this->assertCount( 100, Scripted_History_Helper::$asked[0] );
		$this->assertCount( 50, Scripted_History_Helper::$asked[1] );
	}
}
