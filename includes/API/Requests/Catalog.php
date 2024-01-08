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

namespace WooCommerce\Square\API\Requests;

use WooCommerce\Square\API\Request;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce Square Catalog API Request class.
 *
 * @since 2.0.0
 */
class Catalog extends Request {


	/**
	 * Initializes a new Catalog request.
	 *
	 * @since 2.0.0
	 *
	 * @param \Square\SquareClient $api_client the API client
	 */
	public function __construct( $api_client ) {
		$this->square_api = $api_client->getCatalogApi();
	}


	/**
	 * Sets the data for a batchDeleteCatalogObjects request.
	 *
	 * @see https://docs.connect.squareup.com/api/connect/v2#endpoint-catalog-batchdeletecatalogobjects
	 * @see \Square\Apis\CatalogApi::batchDeleteCatalogObjects()
	 *
	 * @since 2.0.0
	 *
	 * @param string[] $object_ids the square catalog object IDs to delete
	 */
	public function set_batch_delete_catalog_objects_data( array $object_ids ) {

		$this->square_api_method = 'batchDeleteCatalogObjects';
		$body                    = new \Square\Models\BatchDeleteCatalogObjectsRequest();
		$body->setObjectIds( $object_ids );
		$this->square_api_args = array( $body );
	}


	/**
	 * Sets the data for a batchRetrieveCatalogObjects request.
	 *
	 * @see https://docs.connect.squareup.com/api/connect/v2#endpoint-catalog-batchretrievecatalogobjects
	 * @see \Square\Apis\CatalogApi::batchRetrieveCatalogObjects()
	 *
	 * @since 2.0.0
	 *
	 * @param string[] $object_ids the square catalog object IDs to delete
	 * @param bool $include_related_objects whether or not to include related objects in the response
	 */
	public function set_batch_retrieve_catalog_objects_data( array $object_ids, $include_related_objects = false ) {

		$this->square_api_method = 'batchRetrieveCatalogObjects';
		$body                    = new \Square\Models\BatchRetrieveCatalogObjectsRequest( $object_ids );
		$body->setIncludeRelatedObjects( (bool) $include_related_objects );

		$this->square_api_args = array( $body );
	}


	/**
	 * Sets the data for a batchUpsertCatalogObjects request.
	 *
	 * @see https://docs.connect.squareup.com/api/connect/v2#endpoint-catalog-batchupsertcatalogobjects
	 * @see \Square\Apis\CatalogApi::batchUpsertCatalogObjects()
	 *
	 * @since 2.0.0
	 *
	 * @param string $idempotency_key the UUID for this request
	 * @param \Square\Models\CatalogObjectBatch[] $batches array of catalog object batches
	 */
	public function set_batch_upsert_catalog_objects_data( $idempotency_key, array $batches ) {

		$this->square_api_method = 'batchUpsertCatalogObjects';
		$this->square_api_args   = array(
			new \Square\Models\BatchUpsertCatalogObjectsRequest(
				$idempotency_key,
				$batches
			),
		);
	}


	/**
	 * Sets the data for a catalogInfo request.
	 *
	 * @see https://docs.connect.squareup.com/api/connect/v2#endpoint-catalog-cataloginfo
	 * @see \Square\Apis\CatalogApi::catalogInfo()
	 *
	 * @since 2.0.0
	 *
	 * @param string $cursor
	 * @param array $types
	 */
	public function set_catalog_info_data( $cursor = '', $types = array() ) {

		$this->square_api_method = 'catalogInfo';
	}


	/**
	 * Sets the data for a deleteCatalogObject request.
	 *
	 * @see https://docs.connect.squareup.com/api/connect/v2#endpoint-catalog-deletecatalogobject
	 * @see \Square\Apis\CatalogApi::deleteCatalogObject()
	 *
	 * @since 2.0.0
	 *
	 * @param string $object_id the Square catalog object ID to delete
	 */
	public function set_delete_catalog_object_data( $object_id ) {

		$this->square_api_method = 'deleteCatalogObject';
		$this->square_api_args   = array( $object_id );
	}


	/**
	 * Sets the data for a listCatalog request.
	 *
	 * @see https://docs.connect.squareup.com/api/connect/v2#endpoint-catalog-listcatalog
	 * @see \Square\Apis\CatalogApi::listCatalog()
	 *
	 * @since 2.0.0
	 *
	 * @param string $cursor (optional) the pagination cursor
	 * @param array $types (optional) the catalog item types to filter by
	 */
	public function set_list_catalog_data( $cursor = '', $types = array() ) {

		$this->square_api_method = 'listCatalog';
		$this->square_api_args   = array(
			'cursor' => $cursor,
			'types'  => is_array( $types ) ? implode( ',', array_map( 'strtoupper', $types ) ) : '',
		);
	}


	/**
	 * Sets the data for a retrieveCatalogObject request.
	 *
	 * @see https://docs.connect.squareup.com/api/connect/v2#endpoint-catalog-retrievecatalogobject
	 * @see \Square\Apis\CatalogApi::retrieveCatalogObject()
	 *
	 * @since 2.0.0
	 *
	 * @param string $object_id the Square catalog object ID to retrieve
	 * @param bool whether or not to include related objects (such as categories)
	 */
	public function set_retrieve_catalog_object_data( $object_id, $include_related_objects = false ) {

		$this->square_api_method = 'retrieveCatalogObject';
		$this->square_api_args   = array( $object_id, (bool) $include_related_objects );
	}


	/**
	 * Sets the data for a searchCatalogObjects request.
	 *
	 * @see https://docs.connect.squareup.com/api/connect/v2#endpoint-catalog-searchcatalogobjects
	 * @see \Square\Apis\CatalogApi::searchCatalogObjects()
	 *
	 * @since 2.0.0
	 *
	 * @param array $args see Square documentation for full list of args allowed
	 */
	public function set_search_catalog_objects_data( array $args = array() ) {

		// convert object types to array
		if ( isset( $args['object_types'] ) && ! is_array( $args['object_types'] ) ) {
			$args['object_types'] = array( $args['object_types'] );
		}

		$defaults = array(
			'cursor'                  => null,
			'object_types'            => null,
			'include_deleted_objects' => null,
			'include_related_objects' => null,
			'begin_time'              => null,
			'query'                   => null,
			'limit'                   => null,
		);

		// apply defaults and remove any keys that aren't recognized
		$args = array_intersect_key( wp_parse_args( $args, $defaults ), $defaults );

		$body = new \Square\Models\SearchCatalogObjectsRequest();
		$body->setCursor( $args['cursor'] );
		$body->setObjectTypes( $args['object_types'] );
		$body->setIncludeDeletedObjects( $args['include_deleted_objects'] );
		$body->setIncludeRelatedObjects( $args['include_related_objects'] );
		$body->setBeginTime( $args['begin_time'] );
		$body->setQuery( $args['query'] );
		$body->setLimit( $args['limit'] );

		$this->square_api_method = 'searchCatalogObjects';
		$this->square_api_args   = array( $body );
	}


	/**
	 * Sets the data for a updateItemModifierLists request.
	 *
	 * @see https://docs.connect.squareup.com/api/connect/v2#endpoint-catalog-updateitemmodifierlists
	 * @see \Square\Apis\CatalogApi::updateItemModifierLists()
	 *
	 * @since 2.0.0
	 *
	 * @param string[] $item_ids array of item IDs to update
	 * @param string[] $modifier_lists_to_enable array of list IDs to enable
	 * @param string[] $modifier_lists_to_disable array of list IDs to disable
	 */
	public function set_update_item_modifier_lists_data( array $item_ids, array $modifier_lists_to_enable = array(), array $modifier_lists_to_disable = array() ) {

		$this->square_api_method = 'updateItemModifierLists';
		$body                    = new \Square\Models\UpdateItemModifierListsRequest( $item_ids );
		$body->setModifierListsToEnable( $modifier_lists_to_enable );
		$body->setModifierListsToDisable( $modifier_lists_to_disable );

		$this->square_api_args = array( $body );
	}


	/**
	 * Sets the data for an updateItemTaxes request.
	 *
	 * @see https://docs.connect.squareup.com/api/connect/v2#endpoint-catalog-updateitemtaxes
	 * @see \Square\Apis\CatalogApi::updateItemTaxes()
	 *
	 * @since 2.0.0
	 *
	 * @param string[] $item_ids array of item IDs to update
	 * @param string[] $taxes_to_enable array of catalog tax IDs to enable
	 * @param string[] $taxes_to_disable array of catalog tax IDs to disable
	 */
	public function set_update_item_taxes_data( array $item_ids, array $taxes_to_enable = array(), array $taxes_to_disable = array() ) {

		$this->square_api_method = 'updateItemTaxes';
		$body                    = new \Square\Models\UpdateItemTaxesRequest( $item_ids );
		$body->setTaxesToEnable( $taxes_to_enable );
		$body->setTaxesToDisable( $taxes_to_disable );
		$this->square_api_args   = array( $body );
	}


	/**
	 * Sets the data for an upsertCatalogObject request.
	 *
	 * @see https://docs.connect.squareup.com/api/connect/v2#endpoint-catalog-upsertcatalogobject
	 * @see \Square\Apis\CatalogApi::upsertCatalogObject()
	 *
	 * @since 2.0.0
	 *
	 * @param string $idempotency_key a UUID for this request
	 * @param \Square\Models\CatalogObject $object the object to update
	 */
	public function set_upsert_catalog_object_data( $idempotency_key, $object ) {

		$this->square_api_method = 'upsertCatalogObject';
		$this->square_api_args   = array(
			new \Square\Models\UpsertCatalogObjectRequest(
				$idempotency_key,
				$object
			),
		);
	}


}
