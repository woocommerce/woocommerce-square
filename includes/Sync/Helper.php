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
	 * Maximum group narrowing passes before falling back to one request per unresolved id.
	 *
	 * Bounds the worst case: without it a page that resolves a single id each time would issue one
	 * group request per id on top of the per id fallback.
	 *
	 * @since 5.5.0
	 * @var int
	 */
	const MAX_HISTORY_NARROWING_PASSES = 5;

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
	 * Collects the catalog object ids whose count is zero.
	 *
	 * Callers hold counts in two shapes: a plain id to quantity map, and an id to stats map where the
	 * quantity sits under a key. Both are accepted so the zero collection is written once.
	 *
	 * @since 5.5.0
	 *
	 * @param array $counts id keyed counts, values either a quantity or an array of stats
	 * @param string|null $quantity_key key holding the quantity when values are arrays
	 * @return string[] ids whose count is exactly zero
	 */
	public static function zero_count_object_ids( array $counts, $quantity_key = null ) {

		$zero_object_ids = array();

		foreach ( $counts as $object_id => $value ) {

			if ( null !== $quantity_key ) {
				if ( ! is_array( $value ) || ! isset( $value[ $quantity_key ] ) ) {
					continue;
				}
				$value = $value[ $quantity_key ];
			}

			if ( 0.0 === (float) $value ) {
				$zero_object_ids[] = $object_id;
			}
		}

		return $zero_object_ids;
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
	 * On an API failure this returns null, which callers MUST treat as "verification unavailable":
	 * never write the zero, and never advance a watermark or processed marker past the item, or a
	 * genuine sellout would be permanently skipped once the API recovers. Null is distinct from an
	 * empty array, which is a POSITIVE verification that none of the ids have history.
	 *
	 * @since 5.5.0
	 *
	 * @param string[] $catalog_object_ids catalog object (variation) IDs to check
	 * @return string[]|null IDs with at least one real inventory change, or null when Square could not be asked
	 */
	public static function get_catalog_objects_with_inventory_history( $catalog_object_ids ) {

		$catalog_object_ids = array_values( array_filter( (array) $catalog_object_ids ) );

		if ( empty( $catalog_object_ids ) ) {
			return array();
		}

		$with_history = array();

		try {
			foreach ( array_chunk( $catalog_object_ids, 100 ) as $chunk ) {

				// First page for the whole chunk. When Square reports no further pages, every id
				// missing from it is positively verified as having no history. When more pages
				// exist, walking them all would page through the busiest item's entire history
				// just to prove a quiet item empty, so unresolved ids are re-queried individually
				// instead (a single id with no records answers in one page).
				$page = static::fetch_inventory_changes_page( $chunk );
				if ( null === $page ) {
					return null;
				}

				foreach ( $page['object_ids'] as $object_id ) {
					$with_history[ $object_id ] = true;
				}

				$unresolved = array_diff( $chunk, array_keys( $with_history ) );

				if ( empty( $unresolved ) || empty( $page['cursor'] ) ) {
					continue;
				}

				// Re-ask for the unresolved ids as a group before falling back to one request each.
				// A shared page holds the OLDEST changes across every id in it, so one busy id can
				// fill the page and hide a quiet id's record; asking again with the busy ids removed
				// usually settles the whole remainder in a single request, and settles it positively
				// when the response comes back empty with no further pages.
				$remaining = array_values( $unresolved );
				$passes    = 0;

				while ( ! empty( $remaining ) && $passes < self::MAX_HISTORY_NARROWING_PASSES ) {

					++$passes;

					$group = static::fetch_inventory_changes_page( $remaining );
					if ( null === $group ) {
						return null;
					}

					$before = count( $remaining );

					foreach ( $group['object_ids'] as $object_id ) {
						$with_history[ $object_id ] = true;
					}

					$remaining = array_values( array_diff( $remaining, array_keys( $with_history ) ) );

					// No further pages, so every id still remaining has no history at all. That is a
					// positive verification, not an unknown, and the loop is done.
					if ( empty( $group['cursor'] ) ) {
						$remaining = array();
						break;
					}

					// More pages exist but this one named none of the remaining ids, so narrowing has
					// stalled and another group request would return the same page.
					if ( count( $remaining ) === $before ) {
						break;
					}
				}

				// Anything still unresolved is settled one id at a time, where an empty first page is
				// conclusive: pagination is oldest first, so a single id with any record shows it there.
				foreach ( $remaining as $object_id ) {
					$single = static::fetch_inventory_changes_page( array( $object_id ) );
					if ( null === $single ) {
						return null;
					}
					// Membership, not emptiness: a response is only proof for the id it actually names.
					if ( in_array( $object_id, $single['object_ids'], true ) ) {
						$with_history[ $object_id ] = true;
					}
				}
			}
		} catch ( \Exception $exception ) {
			wc_square()->log( 'Could not verify inventory history for zero counts: ' . $exception->getMessage() );
			return null;
		}

		return array_keys( $with_history );
	}

	/**
	 * Fetches one page of inventory changes for the given catalog object ids.
	 *
	 * @since 5.5.0
	 *
	 * @param string[] $catalog_object_ids ids to query
	 * @return array|null { object_ids: string[] with a change on this page, cursor: string|null } or null on failure
	 */
	protected static function fetch_inventory_changes_page( array $catalog_object_ids ) {

		$response = wc_square()->get_api()->batch_retrieve_inventory_changes(
			array(
				'catalog_object_ids' => $catalog_object_ids,
				'location_ids'       => array( wc_square()->get_settings_handler()->get_location_id() ),
			)
		);

		$data = $response->get_data();

		if ( ! $data instanceof \Square\Models\BatchRetrieveInventoryChangesResponse ) {
			wc_square()->log( 'Could not verify inventory history for zero counts: unexpected API response.' );
			return null;
		}

		$object_ids = array();

		foreach ( is_array( $data->getChanges() ) ? $data->getChanges() : array() as $change ) {
			$object_id = null;

			if ( $change->getPhysicalCount() ) {
				$object_id = $change->getPhysicalCount()->getCatalogObjectId();
			} elseif ( $change->getAdjustment() ) {
				$object_id = $change->getAdjustment()->getCatalogObjectId();
			} elseif ( $change->getTransfer() ) {
				$object_id = $change->getTransfer()->getCatalogObjectId();
			}

			if ( $object_id ) {
				$object_ids[ $object_id ] = true;
			}
		}

		$returned = array_keys( $object_ids );

		// Square honors catalog_object_ids only while at least one of the supplied ids exists. When
		// none of them exists it silently drops the filter and answers with the whole location's
		// change history, so a response naming ids we did not ask about means exactly one thing:
		// none of the ids in this request exist in Square any more (a stale local mapping).
		//
		// That is a conclusive answer, not an unknown. A catalog object that does not exist cannot
		// have sold out, so report no history and no further pages: the caller then treats these ids
		// as unverified zeros and leaves the products alone. Without this check the per id branch
		// below would read the unrelated history as proof and write the zero, which is the very bug
		// SQUARE-145 fixes, for exactly the products whose mappings are stale.
		$unexpected = array_diff( $returned, $catalog_object_ids );

		if ( ! empty( $unexpected ) ) {

			wc_square()->log(
				sprintf(
					'Square ignored the catalog object filter for %1$d id(s), which means none of them exist there any more; treating them as having no inventory history. First id: %2$s',
					count( $catalog_object_ids ),
					reset( $catalog_object_ids )
				)
			);

			return array(
				'object_ids' => array(),
				'cursor'     => null,
			);
		}

		return array(
			'object_ids' => $returned,
			'cursor'     => $data->getCursor(),
		);
	}


	/**
	 * Applies a Square IN_STOCK count to a WooCommerce product using the sync write policy.
	 *
	 * Policy (SQUARE-145 / SQUARE-359):
	 * - A positive count is trusted (a phantom is always zero): write the quantity and keep the
	 *   existing behavior of enabling stock management to mirror Square tracking.
	 * - A zero count never changes manage_stock. For a stock-managed product it is written only
	 *   when Square's change history proves a real count was ever recorded ($zero_verified);
	 *   a phantom zero from a never-counted item is skipped. For a product that does not manage
	 *   stock, counts are ignored entirely and only the stock status is reflected.
	 *
	 * @since 5.5.0
	 *
	 * @param \WC_Product $product the WooCommerce product or variation
	 * @param float $quantity the IN_STOCK quantity reported by Square
	 * @param bool $sold_out whether Square reports the item as sold out at the configured location
	 * @param bool $zero_verified whether a zero count is backed by real inventory history
	 * @return bool whether the product was modified (caller is responsible for saving)
	 */
	public static function apply_square_inventory_count( \WC_Product $product, $quantity, $sold_out, $zero_verified ) {

		$quantity = (float) $quantity;

		// A variation inheriting stock management reports the string 'parent': its quantity is
		// governed by the parent's pooled stock, so a quantity written to it is invisible until the
		// variation manages its own stock, and a stock status write is overridden by the pool.
		//
		// Who owns that decision depends on the system of record. Under WooCommerce SOR the pool is
		// merchant intent, so a per-variation Square count is not applicable data and is skipped.
		// Under Square SOR the authority is reversed: a positive count is applied and the variation
		// takes over its own stock, which is what the plugin did before this changeset.
		//
		// A zero or negative count is skipped in both modes. Those are the counts that wiped stock
		// (SQUARE-145), and writing one into a pool would move stock shared with sibling variations
		// on the strength of a single variation's reading.
		if ( 'parent' === $product->get_manage_stock() ) {

			$square_is_system_of_record = wc_square()->get_settings_handler()->is_system_of_record_square();

			if ( ! $square_is_system_of_record || $quantity <= 0 ) {
				wc_square()->log(
					sprintf(
						'Skipped writing a stock quantity to variation #%1$d: its stock is managed by the parent product pool%2$s.',
						$product->get_id(),
						$square_is_system_of_record ? ' and the count was not positive' : ''
					)
				);

				return false;
			}

			wc_square()->log(
				sprintf(
					'Variation #%1$d inherits parent stock, but Square is the system of record and reports %2$s in stock, so the variation now manages its own stock.',
					$product->get_id(),
					$quantity
				)
			);
		}

		if ( $quantity > 0 ) {
			$product->set_stock_quantity( $quantity );
			$product->set_manage_stock( true );

			return true;
		}

		// A negative count can only come from real inventory movement, because an item that was
		// never counted reads exactly zero, so it needs no history check. Square itself cannot hold
		// a negative quantity (which is why the push side clamps at zero) but WooCommerce can, and a
		// store that allows backorders uses it to record how deep it is oversold, so the value is
		// written through rather than flattened. manage_stock is still left alone: only a positive
		// count mirrors Square tracking onto that setting.
		if ( $quantity < 0 ) {

			if ( ! $product->get_manage_stock() ) {
				$product->set_stock_status( 'outofstock' );

				return true;
			}

			$product->set_stock_quantity( $quantity );

			return true;
		}

		// Zero count: never change the product's manage_stock setting in either direction.

		if ( ! $product->get_manage_stock() ) {

			// Not stock-managed in WooCommerce, so a quantity is never written. A zero still has to
			// stop the product selling, but only when it is a proven sellout: an unproven zero (a
			// tracked item that was never counted) must not mark a product the merchant keeps
			// permanently sellable as out of stock. A zero is also never a reason to force a
			// product back in stock, so this branch only ever writes out of stock.
			if ( $zero_verified ) {
				$product->set_stock_status( 'outofstock' );

				return true;
			}

			wc_square()->log(
				sprintf(
					'Skipped marking product #%d out of stock: Square has no inventory history for the item, so its zero count is not a proven sellout.',
					$product->get_id()
				)
			);

			return false;
		}

		if ( $zero_verified ) {
			$product->set_stock_quantity( 0 );

			return true;
		}

		wc_square()->log(
			sprintf(
				'Skipped writing a zero stock quantity to product #%d: Square has no inventory history for the item (phantom zero from an uncounted catalog object).',
				$product->get_id()
			)
		);

		return false;
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
