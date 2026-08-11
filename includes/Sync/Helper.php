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
	 * On an API failure this returns null, which callers MUST treat as "verification unavailable":
	 * never write the zero, and never advance a watermark or processed marker past the item, or a
	 * genuine sellout would be permanently skipped once the API recovers. Null is distinct from an
	 * empty array, which is a POSITIVE verification that none of the ids have history.
	 *
	 * @since x.x.x
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
				$page = self::fetch_inventory_changes_page( $chunk );
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

				foreach ( $unresolved as $object_id ) {
					$single = self::fetch_inventory_changes_page( array( $object_id ) );
					if ( null === $single ) {
						return null;
					}
					if ( ! empty( $single['object_ids'] ) ) {
						$with_history[ $object_id ] = true;
					}
					// Empty first page for a single id means no history: pagination is oldest
					// first per id, so any record would appear on the first page.
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
	 * @since x.x.x
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

		return array(
			'object_ids' => array_keys( $object_ids ),
			'cursor'     => $data->getCursor(),
		);
	}


	/**
	 * Remembers catalog objects whose zero count could not be verified, for a later retry.
	 *
	 * Using the read window as the retry mechanism forces a choice between a window that grows on
	 * every failed poll and a window that is eventually dropped, which would skip a genuine sellout
	 * permanently. Remembering the specific objects avoids both: the watermark advances normally and
	 * these objects are re-checked by id until they resolve.
	 *
	 * @since x.x.x
	 *
	 * @param string[] $catalog_object_ids objects whose zero count is still unverified
	 */
	public static function remember_unverified_zero_counts( $catalog_object_ids ) {

		$catalog_object_ids = array_filter( (array) $catalog_object_ids );

		if ( empty( $catalog_object_ids ) ) {
			return;
		}

		$pending = (array) get_option( 'wc_square_unverified_zero_counts', array() );
		$now     = time();

		foreach ( $catalog_object_ids as $catalog_object_id ) {
			if ( ! isset( $pending[ $catalog_object_id ] ) ) {
				$pending[ $catalog_object_id ] = $now;
			}
		}

		// Drop anything that has sat here for a week, and keep the list bounded so a long incident
		// cannot grow it without limit.
		foreach ( $pending as $catalog_object_id => $first_seen ) {
			if ( $now - (int) $first_seen > WEEK_IN_SECONDS ) {
				unset( $pending[ $catalog_object_id ] );
			}
		}

		if ( count( $pending ) > 1000 ) {
			$pending = array_slice( $pending, -1000, null, true );
		}

		update_option( 'wc_square_unverified_zero_counts', $pending, false );
	}


	/**
	 * Gets the catalog object IDs waiting for their zero count to be verified.
	 *
	 * @since x.x.x
	 *
	 * @return string[]
	 */
	public static function get_unverified_zero_counts() {

		return array_keys( (array) get_option( 'wc_square_unverified_zero_counts', array() ) );
	}


	/**
	 * Forgets catalog objects whose zero count no longer needs a retry.
	 *
	 * @since x.x.x
	 *
	 * @param string[] $catalog_object_ids resolved objects
	 */
	public static function forget_unverified_zero_counts( $catalog_object_ids ) {

		$pending = (array) get_option( 'wc_square_unverified_zero_counts', array() );

		foreach ( (array) $catalog_object_ids as $catalog_object_id ) {
			unset( $pending[ $catalog_object_id ] );
		}

		update_option( 'wc_square_unverified_zero_counts', $pending, false );
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
	 * @since x.x.x
	 *
	 * @param \WC_Product $product the WooCommerce product or variation
	 * @param float $quantity the IN_STOCK quantity reported by Square
	 * @param bool $sold_out whether Square reports the item as sold out at the configured location
	 * @param bool $zero_verified whether a zero count is backed by real inventory history
	 * @return bool whether the product was modified (caller is responsible for saving)
	 */
	public static function apply_square_inventory_count( \WC_Product $product, $quantity, $sold_out, $zero_verified ) {

		$quantity = (float) $quantity;

		// A variation inheriting stock management reports the string 'parent': its quantity and
		// availability are governed by the parent's pooled stock, a quantity written to it is
		// invisible (reads come from the parent) and a stock status write is overridden by the
		// pool. A per-variation Square count, positive or zero, is not applicable data here; the
		// pool is merchant intent and one variation's count must not alter stock shared by its
		// siblings or convert the variation to its own management.
		if ( 'parent' === $product->get_manage_stock() ) {
			wc_square()->log(
				sprintf(
					'Skipped writing a stock quantity to variation #%d: its stock is managed by the parent product pool.',
					$product->get_id()
				)
			);

			return false;
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
