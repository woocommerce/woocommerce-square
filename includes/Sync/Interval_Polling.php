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

use Square\Models\SearchCatalogObjectsResponse;
use Square\Models\BatchRetrieveInventoryCountsResponse;
use WooCommerce\Square\Handlers\Product;
use WooCommerce\Square\Handlers\Category;

defined( 'ABSPATH' ) || exit;

/**
 * Class to represent a synchronization job to poll latest product updates at intervals.
 *
 * @since 2.0.0
 */
class Interval_Polling extends Stepped_Job {

	/**
	 * Assigns the next steps needed for this sync job.
	 *
	 * Adds the next steps to the 'next_steps' attribute.
	 *
	 * @since 2.0.0
	 */
	protected function assign_next_steps() {

		$next_steps = array();

		if ( $this->is_system_of_record_square() ) {

			$next_steps = array(
				'update_category_data',
				'update_product_data',
			);
		}

		// only pull latest inventory if enabled
		if ( wc_square()->get_settings_handler()->is_inventory_sync_enabled() ) {
			$next_steps[] = 'update_inventory_tracking';
			$next_steps[] = 'update_inventory_counts';
		}

		$this->set_attr( 'next_steps', $next_steps );
	}

	/**
	 * Updates categories from Square.
	 *
	 * @since 2.0.8
	 *
	 * @throws \Exception
	 */
	protected function update_category_data() {
		$date = new \DateTime();
		$date->setTimestamp( $this->get_attr( 'catalog_last_synced_at', (int) wc_square()->get_sync_handler()->get_last_synced_at() ) );
		$date->setTimezone( new \DateTimeZone( 'UTC' ) );

		$count    = 0;
		$response = wc_square()->get_api()->search_catalog_objects(
			array(
				'object_types' => array( 'CATEGORY' ),
				'begin_time'   => $date->format( DATE_ATOM ),
			)
		);

		if ( $response->get_data() instanceof SearchCatalogObjectsResponse ) {
			$categories = $response->get_data()->getObjects();

			if ( $categories && is_array( $categories ) ) {
				foreach ( $categories as $category ) {
					Category::import_or_update( $category );
				}
				$count = count( $categories );

				Records::set_record(
					array(
						'type'    => 'info',
						'message' => sprintf(
							/* translators: Placeholder %d number of categories. */
							_n( 'Updated data for %d category.', 'Updated data for %d categories.', count( $categories ), 'woocommerce-square' ),
							count( $categories )
						),
					)
				);
			}
		} else {
			Records::set_record(
				array(
					'type'    => 'alert',
					'message' => esc_html__( 'Product category data could not be updated from Square. Invalid API response.', 'woocommerce-square' ),
				)
			);
		}

		$this->set_attr( 'update_category_data_count', $count );
		$this->complete_step( 'update_category_data' );
	}

	/**
	 * Updates products from Square.
	 *
	 * @since 2.0.0
	 *
	 * @throws \Exception
	 */
	protected function update_product_data() {
		$date = new \DateTime();
		$date->setTimestamp( $this->get_attr( 'catalog_last_synced_at', (int) wc_square()->get_sync_handler()->get_last_synced_at() ) );
		$date->setTimezone( new \DateTimeZone( 'UTC' ) );

		$products_updated = $this->get_attr( 'processed_product_ids', array() );
		$cursor           = $this->get_attr( 'update_product_data_cursor' );

		$response = wc_square()->get_api()->search_catalog_objects(
			array(
				'object_types'            => array( 'ITEM' ),
				'include_deleted_objects' => true,
				'begin_time'              => $date->format( DATE_ATOM ),
				'cursor'                  => $cursor,
			)
		);

		// store the timestamp after this API request was completed
		// we don't want to set it at the end, as counts may have changed in the time it takes to process the data
		if ( ! $cursor ) {
			wc_square()->get_sync_handler()->set_last_synced_at();
		}

		if ( $response->get_data() instanceof SearchCatalogObjectsResponse && is_array( $response->get_data()->getObjects() ) ) {

			foreach ( $response->get_data()->getObjects() as $object ) {

				// filter out objects that aren't at our configured location
				if ( ! $object->getPresentAtAllLocations() && ( ! is_array( $object->getPresentAtLocationIds() ) || ! in_array( wc_square()->get_settings_handler()->get_location_id(), $object->getPresentAtLocationIds(), true ) ) ) {
					continue;
				}

				$product = Product::get_product_by_square_id( $object->getId() );

				if ( $product instanceof \WC_Product ) {
					if ( ! in_array( $product->get_type(), wc_square()->get_sync_handler()->supported_product_types(), true ) ) {
						Records::set_record(
							array(
								'type'    => 'alert',
								'message' => sprintf(
									/* translators: %1$s - product edit page URL, %2$s - Product ID, %3$s - Product type. */
									__( 'Product <a href="%1$s">#%2$s</a> is excluded from sync as the product type "%3$s" is unsupported.', 'woocommerce-square' ),
									get_edit_post_link( $product->get_id() ),
									$product->get_id(),
									$product->get_type()
								),
							)
						);

						continue;
					}

					// deleted items won't have any data to set, so don't try and update the product
					if ( $object->getIsDeleted() ) {

						$record = array(
							'type'       => 'alert',
							'product_id' => $product->get_id(),
						);

						// if enabled, hide the product from the catalog
						if ( wc_square()->get_settings_handler()->hide_missing_square_products() ) {

							try {

								$product->set_catalog_visibility( 'hidden' );
								$product->save();

								$record['product_hidden'] = true;

							} catch ( \Exception $e ) {
								/* translators: Placeholder %1$s Product Name, %2$s Exception message */
								$record['message'] = sprintf( esc_html__( '%1$s was deleted in Square but could not be hidden in WooCommerce. %2$s.', 'woocommerce-square' ), '<a href="' . esc_url( get_edit_post_link( $product->get_id() ) ) . '">' . $product->get_formatted_name() . '</a>', $e->getMessage() );
							}
						}

						Records::set_record( $record );

					} else {

						try {
							$thumbnail_image_id = Product::get_catalog_item_thumbnail_id( $object );
							Product::update_from_square( $product, $object->getItemData(), false );

							Product::update_image_from_square( $product, $thumbnail_image_id );

							$products_updated[] = $product->get_id();

						} catch ( \Exception $exception ) {

							Records::set_record(
								array(
									'type'       => 'alert',
									'product_id' => $product->get_id(),
									/* translators: Placeholder %1$s Product Name, %2$s Exception message */
									'message'    => sprintf( esc_html__( 'Could not sync %1$s data from Square. %2$s.', 'woocommerce-square' ), '<a href="' . esc_url( get_edit_post_link( $product->get_id() ) ) . '">' . $product->get_formatted_name() . '</a>', $exception->getMessage() ),
								)
							);
						}
					}
				}
			}
		}

		$cursor = $response->get_data() instanceof SearchCatalogObjectsResponse ? $response->get_data()->getCursor() : null;

		$this->set_attr( 'update_product_data_cursor', $cursor );
		$this->set_attr( 'processed_product_ids', array_unique( $products_updated ) );
		$this->set_attr( 'update_product_data_count', count( array_unique( $products_updated ) ) );

		if ( ! $cursor ) {
			$this->complete_step( 'update_product_data' );
		}
	}

	/**
	 * Updates the inventory tracking value from the latest in Square.
	 *
	 * Helper method, do not open to public.
	 *
	 * @since 3.8.2
	 *
	 * @throws \Exception
	 */
	protected function update_inventory_tracking() {
		$products_updated = $this->get_attr( 'processed_product_ids' );
		$cursor           = $this->get_attr( 'update_inventory_tracking_cursor', null );
		$last_synced_at   = $this->get_attr( 'inventory_last_synced_at' );
		$args             = array(
			'object_types' => array( 'ITEM_VARIATION' ),
			'limit'        => 100,
			'cursor'       => $cursor,
		);

		if ( $last_synced_at ) {
			$date = new \DateTime();
			$date->setTimestamp( $last_synced_at );
			$date->setTimezone( new \DateTimeZone( 'UTC' ) );
			$args['begin_time'] = $date->format( DATE_ATOM );
		}

		$search_result = wc_square()->get_api()->search_catalog_objects( $args );

		if ( ! $search_result->get_data() instanceof SearchCatalogObjectsResponse ) {
			throw new \Exception( 'API response data is invalid' );
		}

		$objects = $search_result->get_data()->getObjects() ? $search_result->get_data()->getObjects() : array();
		$cursor  = $search_result->get_data() instanceof SearchCatalogObjectsResponse ? $search_result->get_data()->getCursor() : null;

		$catalog_objects_tracking_stats = Helper::get_catalog_inventory_tracking( $objects );
		$catalog_objects_to_update      = array();

		foreach ( $catalog_objects_tracking_stats as $catalog_object_id => $is_tracking_inventory ) {
			$product = Product::get_product_by_square_variation_id( $catalog_object_id );
			if ( $product instanceof \WC_Product ) {
				$manage_stock = $product->get_manage_stock();
				// If Inventory tracking is the same as the product's manage stock setting, skip.
				if ( (bool) $is_tracking_inventory === (bool) $manage_stock ) {
					continue;
				}
				$catalog_objects_to_update[] = $catalog_object_id;
			}
		}

		if ( ! empty( $catalog_objects_to_update ) ) {
			// Catalog Inventory data.
			$inventory_hash = Helper::get_catalog_objects_inventory_stats( $catalog_objects_to_update );

			foreach ( $catalog_objects_to_update as $catalog_object_id ) {
				$product = Product::get_product_by_square_variation_id( $catalog_object_id );
				if ( $product instanceof \WC_Product ) {
					$is_tracking_inventory = isset( $catalog_objects_tracking_stats[ $catalog_object_id ] ) ?
						$catalog_objects_tracking_stats[ $catalog_object_id ] :
						true;

					/* If catalog object is tracked and has a quantity > 0 set in Square. */
					if ( $is_tracking_inventory && isset( $inventory_hash[ $catalog_object_id ] ) ) {
						$product->set_stock_quantity( (float) $inventory_hash[ $catalog_object_id ] );
						$product->set_manage_stock( true );

						/* If the catalog object is tracked but the quantity in Square is set to 0. */
					} elseif ( $is_tracking_inventory ) {
						$product->set_stock_quantity( 0 );
						$product->set_manage_stock( true );

						/* If the catalog object is not tracked in Square at all. */
					} else {
						$product->set_stock_status( 'instock' );
						$product->set_manage_stock( false );
					}

					$product->save();
					$products_updated[] = $product->get_id();
				}
			}
		}

		$this->set_attr( 'update_inventory_tracking_cursor', $cursor );
		$this->set_attr( 'processed_product_ids', array_unique( $products_updated ) );

		if ( ! $cursor ) {
			$this->complete_step( 'update_inventory_tracking' );
		}
	}

	/**
	 * Updates the inventory counts from the latest in Square.
	 *
	 * Helper method, do not open to public.
	 *
	 * @since 2.0.0
	 *
	 * @throws \Exception
	 */
	protected function update_inventory_counts() {
		$products_updated = $this->get_attr( 'processed_product_ids' );
		$cursor           = $this->get_attr( 'update_inventory_counts_cursor' );
		$update_count     = $this->get_attr( 'update_inventory_counts_count', 0 );

		$args = array(
			'location_ids' => array( wc_square()->get_settings_handler()->get_location_id() ),
			'cursor'       => $cursor,
		);

		$last_synced_at = $this->get_attr( 'inventory_last_synced_at' );

		if ( $last_synced_at ) {

			$date = new \DateTime();
			$date->setTimestamp( $last_synced_at );
			$date->setTimezone( new \DateTimeZone( 'UTC' ) );

			$args['updated_after'] = $date->format( DATE_ATOM );
		}

		$response = wc_square()->get_api()->batch_retrieve_inventory_counts( $args );
		$cursor   = $response->get_data() instanceof BatchRetrieveInventoryCountsResponse ? $response->get_data()->getCursor() : null;

		// store the start timestamp after the first API request was completed but do not save it now
		// if cursor is present, then it is not the last page. So, use the inventory_last_synced_at time
		// else use the current time
		$last_sync_timestamp = $cursor ? $last_synced_at : current_time( 'timestamp', true ); // phpcs:disable WordPress.DateTime.CurrentTimeTimestamp.RequestedUTC

		$catalog_objects_inventory_stats = array();

		foreach ( $response->get_counts() as $count ) {
			// If catalog stats array already contains the catalog object marked as IN_STOCK, then continue.
			if ( isset( $catalog_objects_inventory_stats[ $count->getCatalogObjectId() ] ) && $catalog_objects_inventory_stats[ $count->getCatalogObjectId() ]['IN_STOCK'] ) {
				continue;
				// Else if the catalog object is IN_STOCK, then mark IN_STOCK as true and set the quantity for later use.
			} elseif ( 'IN_STOCK' === $count->getState() ) {
				$catalog_objects_inventory_stats[ $count->getCatalogObjectId() ] = array(
					'IN_STOCK' => true,
					'quantity' => $count->getQuantity(),
				);
				// Else if the catalog object doesn't have an IN_STOCK status, then mark IN_STOCK as false and set the quantity as 0 for later use.
			} else {
				$catalog_objects_inventory_stats[ $count->getCatalogObjectId() ] = array(
					'IN_STOCK' => false,
					'quantity' => 0,
				);
			}
		}

		// Get the inventory tracking for catalog objects.
		$catalog_objects_tracking_stats = Helper::get_catalog_objects_tracking_stats(
			array_keys( $catalog_objects_inventory_stats )
		);

		foreach ( $catalog_objects_inventory_stats as $catalog_object_id => $stats ) {

			$product = Product::get_product_by_square_variation_id( $catalog_object_id );

			// Square can return multiple "types" of counts, WooCommerce only distinguishes whether a product is in stock or not
			if ( $product instanceof \WC_Product ) {
				$is_tracking_inventory = isset( $catalog_objects_tracking_stats[ $catalog_object_id ] ) ?
					$catalog_objects_tracking_stats[ $catalog_object_id ] :
					true;

				if ( $is_tracking_inventory ) {
					$product->set_manage_stock( true );
					$product->set_stock_quantity( $stats['quantity'] );
				} else {
					$product->set_stock_status( 'instock' );
					$product->set_manage_stock( false );
				}

				$product->save();

				$products_updated[] = $product->get_id();
			}
		}

		$this->set_attr( 'update_inventory_counts_cursor', $cursor );
		$this->set_attr( 'processed_product_ids', array_unique( $products_updated ) );
		$this->set_attr( 'update_inventory_counts_count', $update_count + count( $catalog_objects_inventory_stats ) );

		if ( ! $cursor ) {
			// When all the inventory counts are synced then set the last sync time to the start time that was stored
			wc_square()->get_sync_handler()->set_inventory_last_synced_at( $last_sync_timestamp );
			$this->complete_step( 'update_inventory_counts' );
		}
	}
}
