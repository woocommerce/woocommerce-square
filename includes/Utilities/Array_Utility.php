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

namespace WooCommerce\Square\Utilities;

defined( 'ABSPATH' ) || exit;

/**
 * Helper for dealing with String values.
 *
 * @since 2.2.0
 */
class Array_Utility {
	/**
	 * Insert the given element after the given key in the array
	 *
	 * Sample usage:
	 *
	 * given
	 *
	 * array( 'item_1' => 'foo', 'item_2' => 'bar' )
	 *
	 * array_insert_after( $array, 'item_1', array( 'item_1.5' => 'w00t' ) )
	 *
	 * becomes
	 *
	 * array( 'item_1' => 'foo', 'item_1.5' => 'w00t', 'item_2' => 'bar' )
	 *
	 * @since 2.2.0
	 * @param array $array array to insert the given element into
	 * @param string $insert_key key to insert given element after
	 * @param array $element element to insert into array
	 * @return array
	 */
	public static function array_insert_after( Array $array, $insert_key, Array $element ) {

		$new_array = array();

		foreach ( $array as $key => $value ) {

			$new_array[ $key ] = $value;

			if ( $insert_key == $key ) {

				foreach ( $element as $k => $v ) {
					$new_array[ $k ] = $v;
				}
			}
		}

		return $new_array;
	}

	/**
	 * Lists an array as text.
	 *
	 * Takes an array and returns a list like "one, two, three, and four"
	 * with a (mandatory) oxford comma.
	 *
	 * @since 5.2.0
	 *
	 * @param array $items items to list
	 * @param string|null $conjunction coordinating conjunction, like "or" or "and"
	 * @param string $separator list separator, like a comma
	 * @return string
	 */
	public static function list_array_items( array $items, $conjunction = null, $separator = '' ) {

		if ( ! is_string( $conjunction ) ) {
			$conjunction = _x( 'and', 'coordinating conjunction for a list of items: a, b, and c', 'woocommerce-square' );
		}

		// append the conjunction to the last item
		if ( count( $items ) > 1 ) {

			$last_item = array_pop( $items );

			array_push( $items, trim( "{$conjunction} {$last_item}" ) );

			// only use a comma if needed and no separator was passed
			if ( count( $items ) < 3 ) {
				$separator = ' ';
			} elseif ( ! is_string( $separator ) || '' === $separator ) {
				$separator = ', ';
			}
		}

		return implode( $separator, $items );
	}
}
