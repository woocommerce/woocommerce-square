<?php
/**
 * Tests for the list of products a manual sync offers to its inventory push step.
 *
 * @package WooCommerce\Square
 */

namespace WooCommerce\Square\Tests\Unit\Sync;

use WP_UnitTestCase;
use WooCommerce\Square\Sync\Job;
use WooCommerce\Square\Sync\Manual_Synchronization;

/**
 * Under a WooCommerce system of record only the products this queue holds ever have their quantity
 * written to Square, and it used to hold nothing but the products the sync had just created.
 */
class InventoryPushQueueTest extends WP_UnitTestCase {

	/**
	 * Runs the queue helper against a job and returns the resulting queue.
	 *
	 * @param array $attrs attributes for the underlying job
	 * @param int[] $product_ids ids to queue
	 * @return int[]|null
	 */
	private function queue( array $attrs, array $product_ids ) {

		$job_object             = new \stdClass();
		$job_object->id         = 'inventory-push-queue-test';
		$job_object->next_steps = array( 'update_matched_products', 'upsert_new_products', 'push_inventory' );

		foreach ( $attrs as $name => $value ) {
			$job_object->$name = $value;
		}

		$job = new Manual_Synchronization( $job_object );

		$method = new \ReflectionMethod( Manual_Synchronization::class, 'queue_products_for_inventory_push' );
		$method->setAccessible( true );
		$method->invoke( $job, $product_ids );

		$property = new \ReflectionProperty( Job::class, 'job' );
		$property->setAccessible( true );

		return $property->getValue( $job )->inventory_push_product_ids ?? null;
	}

	/**
	 * A product that already exists in Square is handled by the matched steps, which create nothing,
	 * so this is the only way its quantity is ever pushed.
	 */
	public function test_products_are_queued() {

		$this->assertSame( array( 5, 6 ), $this->queue( array(), array( 5, 6 ) ) );
	}

	/**
	 * The matched steps run in batches and both feed the same queue, so a product can be offered
	 * twice and would then send the same physical count twice. Callers also pass the result of an
	 * array_diff, whose gapped keys must not survive into the queue the push step walks.
	 */
	public function test_the_queue_is_deduplicated_and_reindexed() {

		$queued = $this->queue(
			array( 'inventory_push_product_ids' => array( 5, 7 ) ),
			array(
				1 => 5,
				3 => 6,
			)
		);

		$this->assertSame( array( 5, 7, 6 ), $queued );
	}

	/**
	 * A deletion, and a store with inventory sync switched off, run the same matched steps but never
	 * schedule the push step, so nothing should be collected for it.
	 */
	public function test_nothing_is_queued_when_the_job_has_no_push_step() {

		$queued = $this->queue(
			array( 'next_steps' => array( 'update_matched_products', 'search_matched_products' ) ),
			array( 5, 6 )
		);

		$this->assertNull( $queued );
	}
}
