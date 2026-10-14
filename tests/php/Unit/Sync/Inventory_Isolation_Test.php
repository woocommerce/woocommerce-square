<?php
/**
 * Tests for the attribution gate that decides whether a failed inventory batch drops one change
 * or fails the whole job.
 *
 * @package WooCommerce\Square
 */

namespace WooCommerce\Square\Tests\Unit\Sync;

use WP_UnitTestCase;
use WooCommerce\Square\Sync\Manual_Synchronization;

/**
 * Square answers with the same error code for a catalog object that no longer exists and for a
 * location the account does not own. Only this gate separates them: an object the error names is
 * dropped and the rest of the batch is retried, while an error naming nothing in the batch is
 * unattributable and must fail loudly rather than silently discarding real stock updates.
 */
class Inventory_Isolation_Test extends WP_UnitTestCase {

	/** @var Manual_Synchronization */
	private $job;

	public function setUp(): void {
		parent::setUp();
		$this->job = new Manual_Synchronization( new \stdClass() );
	}

	/**
	 * Calls a protected method on the job under test.
	 *
	 * @param string $method method name.
	 * @param mixed  ...$args arguments.
	 * @return mixed
	 */
	private function call( $method, ...$args ) {
		$reflection = new \ReflectionMethod( Manual_Synchronization::class, $method );
		$reflection->setAccessible( true );
		return $reflection->invokeArgs( $this->job, $args );
	}

	/**
	 * Builds a physical count change for a catalog object, as the push path does.
	 *
	 * @param string $catalog_object_id Square catalog object ID.
	 * @return \Square\Models\InventoryChange
	 */
	private function change( $catalog_object_id ) {
		$count = new \Square\Models\InventoryPhysicalCount();
		$count->setCatalogObjectId( $catalog_object_id );
		$count->setQuantity( '5' );
		$count->setState( 'IN_STOCK' );

		$change = new \Square\Models\InventoryChange();
		$change->setType( 'PHYSICAL_COUNT' );
		$change->setPhysicalCount( $count );

		return $change;
	}

	/**
	 * Reduces a partition result to plain values for comparison.
	 *
	 * @param array $partition result of partition_inventory_changes_by_error().
	 * @return array
	 */
	private function summarize( array $partition ) {
		$remaining = array();

		foreach ( $partition['remaining'] as $change ) {
			$remaining[] = $change->getPhysicalCount()->getCatalogObjectId();
		}

		return array( $partition['named'], $remaining );
	}

	public function test_a_named_object_is_dropped_and_the_rest_retried() {
		$chunk = array( $this->change( 'AAAAAAAAAAAAAAAAAAAAAAAA' ), $this->change( 'BBBBBBBBBBBBBBBBBBBBBBBB' ) );

		list( $named, $remaining ) = $this->summarize(
			$this->call(
				'partition_inventory_changes_by_error',
				$chunk,
				'[NOT_FOUND] Object `AAAAAAAAAAAAAAAAAAAAAAAA` not found.'
			)
		);

		$this->assertSame( array( 'AAAAAAAAAAAAAAAAAAAAAAAA' ), $named );
		$this->assertSame( array( 'BBBBBBBBBBBBBBBBBBBBBBBB' ), $remaining, 'the change Square did not name must survive' );
	}

	public function test_every_named_object_is_dropped_in_a_single_round() {
		$chunk = array(
			$this->change( 'AAAAAAAAAAAAAAAAAAAAAAAA' ),
			$this->change( 'BBBBBBBBBBBBBBBBBBBBBBBB' ),
			$this->change( 'CCCCCCCCCCCCCCCCCCCCCCCC' ),
		);

		list( $named, $remaining ) = $this->summarize(
			$this->call(
				'partition_inventory_changes_by_error',
				$chunk,
				'[NOT_FOUND] Object `AAAAAAAAAAAAAAAAAAAAAAAA` not found. | [NOT_FOUND] Object `CCCCCCCCCCCCCCCCCCCCCCCC` not found.'
			)
		);

		$this->assertSame( array( 'AAAAAAAAAAAAAAAAAAAAAAAA', 'CCCCCCCCCCCCCCCCCCCCCCCC' ), $named );
		$this->assertSame( array( 'BBBBBBBBBBBBBBBBBBBBBBBB' ), $remaining );
	}

	/**
	 * The account level case. Square reports a location it does not own with the same NOT_FOUND
	 * code as a dead object, and names no object from the batch. Nothing may be dropped, which is
	 * what makes the caller fail loudly instead of discarding the batch.
	 */
	public function test_an_error_naming_no_object_in_the_batch_drops_nothing() {
		$chunk = array( $this->change( 'AAAAAAAAAAAAAAAAAAAAAAAA' ), $this->change( 'BBBBBBBBBBBBBBBBBBBBBBBB' ) );

		list( $named, $remaining ) = $this->summarize(
			$this->call(
				'partition_inventory_changes_by_error',
				$chunk,
				'[NOT_FOUND] This merchant does not have a location with the ID `L0000000FOREIGN`.'
			)
		);

		$this->assertSame( array(), $named, 'an unattributable failure must drop nothing' );
		$this->assertCount( 2, $remaining, 'the whole batch must survive for the caller to rethrow' );
	}

	/**
	 * Square quotes the JSON field path rather than the value often enough that parsing an ID out
	 * of the message is unsafe. Matching the batch's own IDs against the text handles both shapes.
	 */
	public function test_the_object_is_found_however_square_formats_the_message() {
		$chunk = array( $this->change( 'AAAAAAAAAAAAAAAAAAAAAAAA' ) );

		foreach (
			array(
				'[NOT_FOUND] Object `AAAAAAAAAAAAAAAAAAAAAAAA` not found.',
				"[NOT_FOUND] Catalog object 'AAAAAAAAAAAAAAAAAAAAAAAA' was not found",
				'[NOT_FOUND] Catalog object AAAAAAAAAAAAAAAAAAAAAAAA was not found',
				'[INVALID_VALUE] Value at `catalog_object_id` is invalid: `AAAAAAAAAAAAAAAAAAAAAAAA`',
				'[BAD_REQUEST] `changes[0].physical_count.catalog_object_id` bad: AAAAAAAAAAAAAAAAAAAAAAAA',
			) as $message
		) {
			list( $named ) = $this->summarize( $this->call( 'partition_inventory_changes_by_error', $chunk, $message ) );
			$this->assertSame( array( 'AAAAAAAAAAAAAAAAAAAAAAAA' ), $named, 'failed for: ' . $message );
		}
	}

	/**
	 * A location NOT_FOUND classifies as isolatable, exactly like a dead mapping. This pins the
	 * pairing so nobody removes NOT_FOUND from the allow list to fix the location case, which
	 * would stop stale catalog mappings from being isolated at all.
	 */
	public function test_not_found_stays_isolatable_so_the_gate_remains_the_discriminator() {
		$this->assertSame(
			'isolatable',
			$this->call( 'classify_sync_error', new \Exception( '[NOT_FOUND] This merchant does not have a location with the ID `L0000000FOREIGN`.' ) )
		);
	}
}
