<?php
/**
 * WooCommerce Square
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is bundled with this package in the file license.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0 or later
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@woocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade WooCommerce Square to newer
 * versions in the future. If you wish to customize WooCommerce Square for your
 * needs please refer to https://docs.woocommerce.com/document/woocommerce-square/
 *
 * @author    WooCommerce
 * @copyright Copyright: (c) 2019, Automattic, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0 or later
 */

namespace WooCommerce\Square\Sync;

use Square\Models\BatchRetrieveCatalogObjectsResponse;
use Square\Models\BatchRetrieveInventoryCountsResponse;

defined( 'ABSPATH' ) || exit;

/**
 * Square Sync Helper Class
 *
 * The purpose of this class is to centralize common sync utility functions.
 *
 * @since 3.8.2
 */
class Helper {

	/**
	 * Get the inventory tracking value for the given catalog object ids.
	 *
	 * @param array $catalog_object_ids The catalog object ids.
	 * @return array Array of inventory tracking for given catalog object ids.
	 */
	public static function get_catalog_objects_inventory_stats( $catalog_object_ids ) {
		if ( empty( $catalog_object_ids ) ) {
			return array();
		}

		$response = wc_square()->get_api()->batch_retrieve_inventory_counts(
			array(
				'catalog_object_ids' => $catalog_object_ids,
				'location_ids'       => array( wc_square()->get_settings_handler()->get_location_id() ),
				'states'             => array( 'IN_STOCK' ), // Get only in stock counts.
			)
		);

		if ( ! $response->get_data() instanceof BatchRetrieveInventoryCountsResponse ) {
			throw new \Exception( 'Response data missing or invalid' );
		}

		$inventory_hash = array();
		foreach ( $response->get_counts() as $inventory_count ) {
			$inventory_hash[ $inventory_count->getCatalogObjectId() ] = $inventory_count->getQuantity();
		}

		return $inventory_hash;
	}

	/**
	 * Get the inventory tracking value for the given catalog object ids.
	 *
	 * @param array $catalog_object_ids The catalog object ids.
	 * @return array Array of inventory tracking for given catalog object ids.
	 */
	public static function get_catalog_objects_tracking_stats( $catalog_object_ids ) {
		if ( empty( $catalog_object_ids ) ) {
			return array();
		}

		$catalog_response = wc_square()->get_api()->batch_retrieve_catalog_objects( $catalog_object_ids );
		if ( ! $catalog_response->get_data() instanceof BatchRetrieveCatalogObjectsResponse ) {
			throw new \Exception( 'Response data is missing' );
		}

		$objects = $catalog_response->get_data()->getObjects() ? $catalog_response->get_data()->getObjects() : array();

		return self::get_catalog_inventory_tracking( $objects );
	}

	/**
	 * Get the inventory tracking value for the given catalog objects.
	 *
	 * @param \Square\Models\CatalogObject[] $catalog_objects The catalog objects.
	 * @return array Array of inventory tracking for given catalog objects.
	 */
	public static function get_catalog_inventory_tracking( $catalog_objects ) {
		$catalog_objects_tracking = array();

		/** @var \Square\Models\CatalogObject $catalog_object */
		foreach ( $catalog_objects as $catalog_object ) {
			$variation_data     = $catalog_object->getItemVariationData();
			$location_overrides = $variation_data->getLocationOverrides();

			if ( ! empty( $location_overrides ) ) {
				foreach ( $location_overrides as $location_override ) {
					$location_id = $location_override->getLocationId();

					if ( wc_square()->get_settings_handler()->get_location_id() === $location_id ) {
						if ( ! is_null( $location_override->getTrackInventory() ) ) {
							$catalog_objects_tracking[ $catalog_object->getId() ] = $location_override->getTrackInventory();
						} else {
							$catalog_objects_tracking[ $catalog_object->getId() ] = $variation_data->getTrackInventory();
						}
					}
				}
			} else {
				$catalog_objects_tracking[ $catalog_object->getId() ] = $variation_data->getTrackInventory();
			}
		}

		return $catalog_objects_tracking;
	}
}
