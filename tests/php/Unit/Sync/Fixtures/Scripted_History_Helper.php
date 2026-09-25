<?php
/**
 * Test double for the Square inventory change history lookup.
 *
 * @package WooCommerce\Square
 */

namespace WooCommerce\Square\Tests\Unit\Sync\Fixtures;

use WooCommerce\Square\Sync\Helper;

/**
 * Helper with a scripted page fetch, so the zero verification logic can be exercised without the
 * Square API.
 *
 * Helper::get_catalog_objects_with_inventory_history() reaches the page fetch through static::, so
 * this override is what runs.
 */
class Scripted_History_Helper extends Helper {

	/**
	 * Pages to return, in order. Each entry is a page array or null for a failed request.
	 *
	 * @var array
	 */
	public static $pages = array();

	/**
	 * The ids asked about, one entry per request.
	 *
	 * @var array
	 */
	public static $asked = array();

	/**
	 * Arms the double with a script and clears the recorded requests.
	 *
	 * @param array $pages pages to return, in order.
	 */
	public static function reset( array $pages ) {
		self::$pages = $pages;
		self::$asked = array();
	}

	/**
	 * Builds a page naming the given catalog object ids.
	 *
	 * @param array $object_ids ids named by the page.
	 * @param bool  $has_more   whether Square reports a further page.
	 * @return array
	 */
	public static function page( array $object_ids, $has_more = false ) {
		return array(
			'object_ids' => $object_ids,
			'cursor'     => $has_more ? 'next-page-cursor' : null,
		);
	}

	/**
	 * Returns the next scripted page instead of calling Square.
	 *
	 * @param array $catalog_object_ids ids being asked about.
	 * @return array|null
	 */
	protected static function fetch_inventory_changes_page( array $catalog_object_ids ) {
		self::$asked[] = array_values( $catalog_object_ids );
		return array_shift( self::$pages );
	}
}
