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
	 * Extra ID mappings the upsert reports ahead of the option's own, as Square does for values.
	 *
	 * @var array
	 */
	private $upsert_id_mappings = array();

	/**
	 * Exception every retrieve_catalog_object() call raises, or null to behave normally.
	 *
	 * @var \Exception|null
	 */
	private $retrieve_exception = null;

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
	 * Queues mappings reported BEFORE the option's own, standing in for created option values.
	 *
	 * Square returns a mapping per created object with no guaranteed ordering, and an option that
	 * already existed gets no mapping at all. That is the shape that makes a positional read of the
	 * mappings return a value ID where the caller expected an option ID.
	 *
	 * @param array $mappings List of arrays with `client_object_id` and `object_id` keys.
	 * @return self
	 */
	public function set_upsert_id_mappings( array $mappings ) {
		$this->upsert_id_mappings = $mappings;

		return $this;
	}

	/**
	 * Makes every retrieve fail, so a test can prove which failures are not treated as a miss.
	 *
	 * @param \Exception $exception Exception to raise.
	 * @return self
	 */
	public function set_retrieve_exception( \Exception $exception ) {
		$this->retrieve_exception = $exception;

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
	 * Returns a registered object, or throws the way Square does for an ID it does not hold.
	 *
	 * Handing back an empty response for an unknown ID is what this double used to do, and it is
	 * why a fatal in production passed as a green test: the real call runs the response through the
	 * error validator, which throws on NOT_FOUND before any data is returned. A double that is
	 * gentler than the API it stands in for cannot pin the behaviour that matters.
	 *
	 * @param string   $object_id               Square object ID.
	 * @param bool     $include_related_objects Unused.
	 * @param int|null $object_version          Unused.
	 * @return Catalog
	 * @throws \Exception When the object is unknown, or when a failure has been scripted.
	 */
	public function retrieve_catalog_object( $object_id, $include_related_objects = false, $object_version = null ) {
		$this->retrieved_object_ids[] = $object_id;

		if ( $this->retrieve_exception ) {
			throw $this->retrieve_exception;
		}

		if ( ! isset( $this->catalog_objects[ $object_id ] ) ) {
			// Matches the shape API::do_post_parse_response_validation() builds: "[CODE] detail".
			throw new \Exception( '[NOT_FOUND] Object not found.' );
		}

		$data = new RetrieveCatalogObjectResponse();
		$data->setObject( $this->catalog_objects[ $object_id ] );

		return new Catalog( $data );
	}

	/**
	 * Records the upserted object and reports the configured ID mappings.
	 *
	 * Any mappings queued through set_upsert_id_mappings() come first, so a test can reproduce
	 * Square putting a created value ahead of the option. The option's own mapping is keyed on the
	 * client ID it was actually sent under, which is what the real API echoes back.
	 *
	 * @param string        $idempotency_key Unused.
	 * @param CatalogObject $catalog_object  Object being upserted.
	 * @return Catalog
	 */
	public function upsert_catalog_object( $idempotency_key, $catalog_object ) {
		$this->upserted_objects[] = $catalog_object;

		$data     = new BatchUpsertCatalogObjectsResponse();
		$mappings = array();

		foreach ( $this->upsert_id_mappings as $queued_mapping ) {
			$mapping = new CatalogIdMapping();
			$mapping->setClientObjectId( isset( $queued_mapping['client_object_id'] ) ? $queued_mapping['client_object_id'] : null );
			$mapping->setObjectId( isset( $queued_mapping['object_id'] ) ? $queued_mapping['object_id'] : null );

			$mappings[] = $mapping;
		}

		if ( null !== $this->upsert_object_id ) {
			$mapping = new CatalogIdMapping();
			$mapping->setClientObjectId( $catalog_object->getId() );
			$mapping->setObjectId( $this->upsert_object_id );

			$mappings[] = $mapping;
		}

		if ( $mappings ) {
			$data->setIdMappings( $mappings );
		}

		return new Catalog( $data );
	}
}
