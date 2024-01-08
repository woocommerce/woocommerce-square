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

namespace WooCommerce\Square\Handlers;

defined( 'ABSPATH' ) || exit;

/**
 * Category handler class.
 *
 * @since 2.0.0
 */
class Category {


	const CATEGORY_MAP_META_KEY = 'wc_square_category_map';

	const SQUARE_ID_META_KEY = 'square_cat_id';

	const SQUARE_VERSION_META_KEY = 'square_cat_version';


	/**
	 * Gets the full category map.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	public static function get_map() {

		return get_option( self::CATEGORY_MAP_META_KEY, array() );
	}


	/**
	 * Updates the full category map.
	 *
	 * @since 2.0.0
	 *
	 * @param array $map
	 */
	public static function update_map( $map ) {

		update_option( self::CATEGORY_MAP_META_KEY, $map );
	}


	/**
	 * Gets the mapping for a single category ID.
	 *
	 * @since 2.0.0
	 *
	 * @param int $category_id the category ID
	 * @return array
	 */
	public static function get_mapping( $category_id ) {

		$category_id = (int) $category_id;
		$map         = self::get_map();

		if ( isset( $map[ $category_id ] ) ) {

			return $map[ $category_id ];
		}

		return array();
	}


	/**
	 * Returns a Square ID if known, or a temporary ID to be used in API calls.
	 *
	 * @since 2.0.0
	 *
	 * @param int $category_id
	 * @return string
	 */
	public static function get_square_id( $category_id ) {

		$mapping = self::get_mapping( $category_id );

		return isset( $mapping['square_id'] ) ? $mapping['square_id'] : '#category_' . $category_id;
	}


	/**
	 * Gets the Square version for the given category (if known).
	 *
	 * @since 2.0.0
	 *
	 * @param int $category_id
	 * @return int
	 */
	public static function get_square_version( $category_id ) {

		$mapping = self::get_mapping( $category_id );

		return isset( $mapping['square_version'] ) ? (int) $mapping['square_version'] : 0;
	}


	/**
	 * Adds a mapping for a category.
	 *
	 * @since 2.0.0
	 *
	 * @param int $category_id the category ID
	 * @param string $square_id the Square Catalog Item ID
	 * @param string $square_version the Square Item version
	 *
	 * @return array the updated full map
	 */
	public static function update_mapping( $category_id, $square_id, $square_version ) {

		$map = self::get_map();

		$map[ $category_id ] = array(
			'square_id'      => $square_id,
			'square_version' => $square_version,
		);

		self::update_map( $map );

		return $map;
	}


	/**
	 * Imports or updates local category data for a remote CatalogObject.
	 *
	 * @since 2.0.0
	 *
	 * @param \Square\Models\CatalogObject $catalog_object the catalog object
	 * @return int|null the category ID, if found
	 */
	public static function import_or_update( $catalog_object ) {

		$id      = $catalog_object->getId();
		$version = $catalog_object->getVersion();
		$name    = $catalog_object->getCategoryData()->getName();

		// look for category ID by the square ID
		$category_id = self::get_category_id_by_square_id( $id );

		// if not found, search for the category by name
		if ( ! $category_id ) {

			if ( $category = get_term_by( 'name', $name, 'product_cat', ARRAY_A ) ) {

				$category_id = isset( $category['term_id'] ) ? absint( $category['term_id'] ) : null;
			}
		}

		// if still not found, create a new category
		if ( ! $category_id ) {

			$inserted_term = wp_insert_term( $name, 'product_cat' );

			$category_id = isset( $inserted_term['term_id'] ) ? $inserted_term['term_id'] : null;
		}

		if ( $category_id ) {
			wp_update_term( $category_id, 'product_cat', array( 'name' => $name ) );
			self::update_square_meta( $category_id, $id, $version );
		}

		return $category_id;
	}


	/**
	 * Updates a category's Square metadata.
	 *
	 * @since 2.0.0
	 *
	 * @param int $category_id the category ID
	 * @param string $square_id the square ID
	 * @param string $square_version the square version
	 */
	public static function update_square_meta( $category_id, $square_id, $square_version ) {

		update_term_meta( $category_id, self::SQUARE_ID_META_KEY, $square_id );
		update_term_meta( $category_id, self::SQUARE_VERSION_META_KEY, $square_version );
	}


	/**
	 * Gets a category ID from a known square ID.
	 *
	 * @since 2.0.0
	 *
	 * @param string $square_id the square ID
	 * @return int|null
	 */
	public static function get_category_id_by_square_id( $square_id ) {
		global $wpdb;

		$query = $wpdb->prepare(
			"
			SELECT t.term_id FROM {$wpdb->prefix}terms AS t
			LEFT JOIN {$wpdb->prefix}term_taxonomy AS tt ON t.term_id = tt.term_id
			LEFT JOIN {$wpdb->prefix}termmeta AS tm ON t.term_id = tm.term_id
			WHERE tt.taxonomy = 'product_cat'
			AND tm.meta_key = '%s'
			AND tm.meta_value = '%s'
			",
			self::SQUARE_ID_META_KEY,
			$square_id
		);

		return $wpdb->get_var( $query );
	}


}
