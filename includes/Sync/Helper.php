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
	 * Returns the subset of catalog object IDs that have real inventory history in Square.
	 *
	 * A tracked item that has never had a count recorded reports an IN_STOCK count of 0 that is
	 * indistinguishable from a genuine sellout by state alone. Square's inventory change history is
	 * the reliable discriminator: a never-counted item has an empty history, while any real count or
	 * sale leaves a PHYSICAL_COUNT / ADJUSTMENT record. Callers use this to decide whether a zero
	 * count may be written to WooCommerce (real) or must be ignored (phantom).
	 *
	 * On an API failure this returns an empty array, which callers must treat as "no zeros are
	 * verified" - failing closed so an outage can never cause a zero to be written.
	 *
	 * @since x.x.x
	 *
	 * @param string[] $catalog_object_ids catalog object (variation) IDs to check
	 * @return string[] IDs that have at least one real inventory change recorded
	 */
	public static function get_catalog_objects_with_inventory_history( $catalog_object_ids ) {

		$catalog_object_ids = array_values( array_filter( (array) $catalog_object_ids ) );

		if ( empty( $catalog_object_ids ) ) {
			return array();
		}

		$with_history = array();

		try {
			foreach ( array_chunk( $catalog_object_ids, 100 ) as $chunk ) {
				$remaining = array_flip( $chunk );
				$cursor    = null;

				// Results are paginated globally across the requested IDs (oldest first), so a
				// single page can be exhausted by high-activity items before a quiet item's only
				// record appears: the cursor must be followed or a real sellout is misread as a
				// phantom.
				do {
					// No `types` request filter: the API rejects TRANSFER as a filter value, and
					// filtering to the other two would misread an item whose only history is a
					// transfer as never counted. All change types are fetched and matched below.
					$response = wc_square()->get_api()->batch_retrieve_inventory_changes(
						array(
							'catalog_object_ids' => $chunk,
							'location_ids'       => array( wc_square()->get_settings_handler()->get_location_id() ),
							'cursor'             => $cursor,
						)
					);

					$data = $response->get_data();

					if ( ! $data instanceof \Square\Models\BatchRetrieveInventoryChangesResponse ) {
						wc_square()->log( 'Could not verify inventory history for zero counts, skipping zero writes: unexpected API response.' );
						return array();
					}

					if ( is_array( $data->getChanges() ) ) {
						foreach ( $data->getChanges() as $change ) {
							$object_id = null;

							if ( $change->getPhysicalCount() ) {
								$object_id = $change->getPhysicalCount()->getCatalogObjectId();
							} elseif ( $change->getAdjustment() ) {
								$object_id = $change->getAdjustment()->getCatalogObjectId();
							} elseif ( $change->getTransfer() ) {
								$object_id = $change->getTransfer()->getCatalogObjectId();
							}

							if ( $object_id ) {
								$with_history[ $object_id ] = true;
								unset( $remaining[ $object_id ] );
							}
						}
					}

					$cursor = $data->getCursor();
				} while ( $cursor && ! empty( $remaining ) );
			}
		} catch ( \Exception $exception ) {
			wc_square()->log( 'Could not verify inventory history for zero counts, skipping zero writes: ' . $exception->getMessage() );
			return array();
		}

		return array_keys( $with_history );
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
			$variation_data      = $catalog_object->getItemVariationData();
			$location_overrides  = $variation_data->getLocationOverrides();
			$configured_location = wc_square()->get_settings_handler()->get_location_id();

			$default_data = array(
				'track_inventory' => $variation_data->getTrackInventory(),
				'sold_out'        => false,
			);

			if ( ! empty( $location_overrides ) ) {
				$location_ids = array_map(
					function ( $location_override ) {
						return $location_override->getLocationId();
					},
					$location_overrides
				);

				if ( ! in_array( $configured_location, $location_ids, true ) ) {
					$catalog_objects_tracking[ $catalog_object->getId() ] = $default_data;
					continue;
				}

				foreach ( $location_overrides as $location_override ) {
					$location_id = $location_override->getLocationId();

					if ( $configured_location === $location_id ) {
						$sold_out = $location_override->getSoldOut() ?? false;
						if ( ! is_null( $location_override->getTrackInventory() ) ) {
							$catalog_objects_tracking[ $catalog_object->getId() ] = array(
								'track_inventory' => $location_override->getTrackInventory(),
								'sold_out'        => $sold_out,
							);
						} else {
							$catalog_objects_tracking[ $catalog_object->getId() ] = array(
								'track_inventory' => $variation_data->getTrackInventory(),
								'sold_out'        => $sold_out,
							);
						}
					}
				}
			} else {
				$catalog_objects_tracking[ $catalog_object->getId() ] = $default_data;
			}
		}

		return $catalog_objects_tracking;
	}
}
