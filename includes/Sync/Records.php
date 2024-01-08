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

defined( 'ABSPATH' ) || exit;

use WooCommerce\Square\Sync\Records\Record;

/**
 * The sync records handler.
 *
 * @since 2.0.0
 */
class Records {


	/** @var string WordPress option key where sync records are stored */
	private static $records_option_key = 'wc_square_sync_records';


	/**
	 * Checks if there are existing records.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public static function has_records() {

		return ! empty( self::has_records() );
	}


	/**
	 * Gets a specific sync record.
	 *
	 * @since 2.0.0
	 *
	 * @param int $id the record's identifier
	 * @return null|Record
	 */
	public static function get_record( $id ) {

		$records = self::get_records( array( 'id' => $id ) );

		return ! empty( $records ) ? current( $records ) : null;
	}


	/**
	 * Gets an array of record objects.
	 *
	 * @since 2.0.0
	 *
	 * @param array $args associative array of arguments to query records
	 * @return Record[]
	 */
	public static function get_records( array $args = array() ) {

		$args = wp_parse_args(
			$args,
			array(
				'id'      => null,
				'type'    => null,
				'product' => null,
				'orderby' => 'date',
				'sort'    => 'DESC',
				'limit'   => 50,
			)
		);

		$records     = array();
		$raw_records = get_option( self::$records_option_key, array() );

		foreach ( $raw_records as $raw_record_data ) {

			$record = new Record( $raw_record_data );

			if ( ! empty( $args['id'] ) ) {

				$id = is_array( $args['id'] ) ? $args['id'] : explode( ',', $args['id'] );

				if ( ! in_array( $record->get_id(), $id, false ) ) {
					continue;
				}
			}

			if ( ! empty( $args['type'] ) ) {

				$type = is_array( $args['type'] ) ? $args['type'] : explode( ',', $args['type'] );

				if ( ! $record->is_type( $type ) ) {
					continue;
				}
			}

			if ( ! empty( $args['product'] ) ) {

				$product = is_array( $args['product'] ) ? $args['product'] : explode( ',', $args['product'] );

				if ( ! in_array( $record->get_product_id(), $product, false ) ) {
					continue;
				}
			}

			$records[ $record->get_id() ] = $record;
		}

		if ( ! empty( $records ) ) {

			switch ( $args['orderby'] ) {
				case 'date':
					uasort( $records, array( self::class, 'sort_records_by_date' ) );
					break;
				case 'type':
					uasort( $records, array( self::class, 'sort_records_by_type' ) );
					break;
			}

			if ( 'DESC' === $args['sort'] ) {
				$records = array_reverse( $records, true );
			}

			$records = array_slice( $records, 0, max( 50, absint( $args['limit'] ) ) );
		}

		return $records;
	}


	/**
	 * Compares two records for sorting by date.
	 *
	 * @see usort() callback
	 * @see Records::get_records()
	 *
	 * @since 2.0.0
	 *
	 * @param Record $record_1 first record
	 * @param Record $record_2 second record
	 * @return int should return 0, -1 or +1
	 */
	private static function sort_records_by_date( $record_1, $record_2 ) {

		$compare = 0;

		if ( $record_1 instanceof Record && $record_2 instanceof Record ) {

			$timestamp_1 = $record_1->get_timestamp();
			$timestamp_2 = $record_2->get_timestamp();

			if ( $timestamp_1 > $timestamp_2 ) {
				$compare = 1;
			} elseif ( $timestamp_1 < $timestamp_2 ) {
				$compare = -1;
			} else { // compare by ID as a fallback to keep order consistency
				$compare = strnatcmp( $record_1->get_id(), $record_2->get_id() );
			}
		}

		return $compare;
	}


	/**
	 * Compares two records for sorting by type.
	 *
	 * @see usort() callback
	 * @see Records::get_records()
	 *
	 * @since 2.0.0
	 *
	 * @param Record $record_1 first record
	 * @param Record $record_2 second record
	 * @return int should return 0, -1 or +1
	 */
	private static function sort_records_by_type( $record_1, $record_2 ) {

		$compare = 0;

		if ( $record_1 instanceof Record && $record_2 instanceof Record ) {

			$compare = strnatcmp( $record_1->get_type(), $record_2->get_type() );

			// if they are equal, sort by date within the same type group
			if ( 0 === $compare ) {

				$timestamp_1 = $record_1->get_timestamp();
				$timestamp_2 = $record_2->get_timestamp();

				if ( $timestamp_1 > $timestamp_2 ) {
					$compare = 1;
				} elseif ( $timestamp_1 < $timestamp_2 ) {
					$compare = -1;
				} else { // compare by ID as a fallback to keep order consistency
					$compare = strnatcmp( $record_1->get_id(), $record_2->get_id() );
				}
			}
		}

		return $compare;
	}


	/**
	 * Saves a new record.
	 *
	 * @since 2.0.0
	 *
	 * @param array|Record $data raw data or record object
	 * @return bool success
	 */
	public static function set_record( $data ) {

		if ( is_array( $data ) ) {
			$new_record = new Record( $data );
		} else {
			$new_record = $data;
		}

		if ( $new_record instanceof Record ) {

			// ensures there are never more than 50 records, leaving behind the older ones
			$existing_records = self::get_records( array( 'limit' => 49 ) );
			$raw_records      = array();

			foreach ( $existing_records as $existing_record ) {
				$raw_records[ $existing_record->get_id() ] = $existing_record->get_data();
			}

			$raw_records[ $new_record->get_id() ] = $new_record->get_data();

			$success = update_option( self::$records_option_key, $raw_records );

		} else {

			$success = false;
		}

		return $success;
	}


	/**
	 * Saves multiple records.
	 *
	 * @since 2.0.0
	 *
	 * @param array|Record[] $data array of raw record data or array of record objects
	 * @return bool success
	 */
	public static function set_records( array $data ) {

		$success     = false;
		$raw_records = array();

		foreach ( $data as $record ) {

			if ( is_array( $record ) ) {
				$record = new Record( $record );
			}

			if ( $record instanceof Record ) {
				$raw_records[ $record->get_id() ] = $record->get_data();
			}
		}

		if ( ! empty( $raw_records ) ) {

			$records = self::get_records( array( 'limit' => 50 - count( $raw_records ) ) );

			foreach ( $records as $record ) {
				$raw_records[ $record->get_id() ] = $record->get_data();
			}

			$success = update_option( self::$records_option_key, $raw_records );
		}

		return $success;
	}


	/**
	 * Removes a record permanently.
	 *
	 * @since 2.0.0
	 *
	 * @param int $id record identifier
	 * @return bool success
	 */
	public static function delete_record( $id ) {

		$success     = false;
		$raw_records = get_option( self::$records_option_key, array() );

		if ( array_key_exists( $id, $raw_records ) ) {

			unset( $raw_records[ $id ] );

			$success = update_option( self::$records_option_key, $raw_records );
		}

		return $success;
	}


	/**
	 * Delete multiple records.
	 *
	 * @since 2.0.0
	 *
	 * @param array $args query arguments
	 * @return int count of records removed
	 */
	public static function delete_records( array $args ) {

		$removed     = 0;
		$raw_records = get_option( self::$records_option_key, array() );

		foreach ( $raw_records as $raw_record ) {

			$record = new Record( $raw_record );

			if ( isset( $args['id'] ) ) {

				$id = is_array( $args['id'] ) ? $args['id'] : explode( ',', $args['id'] );

				if ( in_array( $record->get_id(), $id, false ) ) {
					unset( $raw_records[ $record->get_id() ] );
					$removed++;
				}
			}

			if ( isset( $args['type'] ) ) {

				$type = $args['type'];

				if ( $record->is_type( $type ) ) {
					unset( $raw_records[ $record->get_id() ] );
					$removed++;
				}
			}
		}

		if ( $removed > 0 ) {

			$success = update_option( self::$records_option_key, $raw_records );

			if ( ! $success ) {
				$removed = 0;
			}
		}

		return $removed;
	}


	/**
	 * Removes all records.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public static function clean_records() {

		return update_option( self::$records_option_key, array() );
	}


}
