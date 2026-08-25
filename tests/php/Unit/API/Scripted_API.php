<?php
/**
 * Test double that scripts the Square catalog endpoints used by the option helpers.
 *
 * @package WooCommerce\Square
 */

namespace WooCommerce\Square\Tests\Unit\API;

use Square\Models\BatchUpsertCatalogObjectsResponse;
use Square\Models\CatalogIdMapping;
use Square\Models\CatalogItemOption;
use Square\Models\CatalogItemOptionValue;
use Square\Models\CatalogObject;
use Square\Models\ListCatalogResponse;
use Square\Models\RetrieveCatalogObjectResponse;
use WooCommerce\Square\API\Responses\Catalog;

/**
 * Replaces the three catalog calls the option helpers make with canned responses.
 *
 * The parent constructor is deliberately not called: building a SquareClient is
 * pointless here and leaving it unbuilt guarantees no test can reach the network.
 */
class Scripted_API extends \WooCommerce\Square\API {

	/**
	 * Cursors passed to list_catalog(), in call order.
	 *
	 * @var array
	 */
	public $list_catalog_cursors = array();

	/**
	 * Object IDs passed to retrieve_catalog_object(), in call order.
	 *
	 * @var array
	 */
	public $retrieved_object_ids = array();

	/**
	 * Objects handed to upsert_catalog_object(), in call order.
	 *
	 * @var array
	 */
	public $upserted_objects = array();

	/**
	 * Queued ListCatalog pages, each an array of `objects` and `cursor`.
	 *
	 * @var array
	 */
	private $catalog_pages = array();

	/**
	 * Catalog objects retrievable by ID. Anything absent responds with no data.
	 *
	 * @var array
	 */
	private $catalog_objects = array();

	/**
	 * Object ID an upsert reports back through its ID mappings, or null for none.
	 *
	 * @var string|null
	 */
	private $upsert_object_id = null;

	/**
	 * Skips the parent constructor so no Square client is ever built.
	 */
	public function __construct() {} // phpcs:ignore Generic.CodeAnalysis.UselessOverridingMethod.Found -- Deliberately replaces the parent constructor.

	/**
	 * Queues the pages ListCatalog should return, in order.
	 *
	 * @param array $pages List of arrays with `objects` and `cursor` keys.
	 * @return self
	 */
	public function queue_catalog_pages( array $pages ) {
		$this->catalog_pages = $pages;

		return $this;
	}

	/**
	 * Registers a catalog object retrievable by ID.
	 *
	 * @param string        $object_id      Square object ID.
	 * @param CatalogObject $catalog_object Object to hand back.
	 * @return self
	 */
	public function register_catalog_object( $object_id, CatalogObject $catalog_object ) {
		$this->catalog_objects[ $object_id ] = $catalog_object;

		return $this;
	}

	/**
	 * Sets the object ID an upsert reports through its ID mappings.
	 *
	 * @param string|null $object_id Object ID, or null to return no mappings.
	 * @return self
	 */
	public function set_upsert_object_id( $object_id ) {
		$this->upsert_object_id = $object_id;

		return $this;
	}

	/**
	 * Builds an ITEM_OPTION catalog object.
	 *
	 * @param string $object_id Option ID.
	 * @param string $name      Option name.
	 * @param array  $values    Map of value ID => value name.
	 * @return CatalogObject
	 */
	public static function make_option( $object_id, $name, array $values = array() ) {
		$catalog_object = new CatalogObject( 'ITEM_OPTION', $object_id );
		$option_data    = new CatalogItemOption();
		$option_data->setName( $name );

		$option_values = array();

		foreach ( $values as $value_id => $value_name ) {
			$value_object = new CatalogObject( 'ITEM_OPTION_VAL', $value_id );
			$value_data   = new CatalogItemOptionValue();
			$value_data->setName( $value_name );
			$value_object->setItemOptionValueData( $value_data );

			$option_values[] = $value_object;
		}

		$option_data->setValues( $option_values );
		$catalog_object->setItemOptionData( $option_data );

		return $catalog_object;
	}

	/**
	 * Returns the next queued page, or an empty final page once they run out.
	 *
	 * @param string $cursor Pagination cursor.
	 * @param array  $types  Requested object types.
	 * @return Catalog
	 */
	public function list_catalog( $cursor = '', $types = array() ) {
		$this->list_catalog_cursors[] = $cursor;

		$page = array_shift( $this->catalog_pages );

		if ( ! is_array( $page ) ) {
			$page = array(
				'objects' => array(),
				'cursor'  => null,
			);
		}

		$data = new ListCatalogResponse();
		$data->setObjects( isset( $page['objects'] ) ? $page['objects'] : array() );
		$data->setCursor( isset( $page['cursor'] ) ? $page['cursor'] : null );

		return new Catalog( $data );
	}

	/**
	 * Returns a registered object, or a response with no data at all.
	 *
	 * @param string   $object_id               Square object ID.
	 * @param bool     $include_related_objects Unused.
	 * @param int|null $object_version          Unused.
	 * @return Catalog
	 */
	public function retrieve_catalog_object( $object_id, $include_related_objects = false, $object_version = null ) {
		$this->retrieved_object_ids[] = $object_id;

		if ( ! isset( $this->catalog_objects[ $object_id ] ) ) {
			return new Catalog( null );
		}

		$data = new RetrieveCatalogObjectResponse();
		$data->setObject( $this->catalog_objects[ $object_id ] );

		return new Catalog( $data );
	}

	/**
	 * Records the upserted object and reports the configured ID mapping.
	 *
	 * @param string        $idempotency_key Unused.
	 * @param CatalogObject $catalog_object  Object being upserted.
	 * @return Catalog
	 */
	public function upsert_catalog_object( $idempotency_key, $catalog_object ) {
		$this->upserted_objects[] = $catalog_object;

		$data = new BatchUpsertCatalogObjectsResponse();

		if ( null !== $this->upsert_object_id ) {
			$mapping = new CatalogIdMapping();
			$mapping->setObjectId( $this->upsert_object_id );
			$data->setIdMappings( array( $mapping ) );
		}

		return new Catalog( $data );
	}
}
