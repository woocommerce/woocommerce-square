<?php
/**
 * Scripted stand in for the Square catalog API used by the sync upsert tests.
 *
 * @package WooCommerce\Square
 */

namespace WooCommerce\Square\Tests\Unit\Sync\Fixtures;

use Square\Models\BatchUpsertCatalogObjectsResponse;
use Square\Models\CatalogIdMapping;
use Square\Models\CatalogObjectBatch;
use Square\Models\SearchCatalogObjectsResponse;
use WooCommerce\Square\API\Response;

/**
 * Records every catalog request and answers it from a script. An upsert with no scripted answer
 * succeeds the way Square does: every temporary "#" ID is given a permanent ID, returned in the
 * ID mappings, and the objects come back carrying those IDs. A search with no scripted answer
 * finds nothing. Any other API call fails the test loudly.
 */
class Scripted_Catalog_Api {

	/** @var array[] recorded upserts, each { key: string, batches: CatalogObjectBatch[] } */
	public $upserts = array();

	/** @var array[] recorded search arguments */
	public $searches = array();

	/** @var callable[] answers for the next upserts, in order; each gets ( $key, $batches ) */
	public $upsert_script = array();

	/** @var callable[] answers for the next searches, in order; each gets ( $args ) */
	public $search_script = array();

	/**
	 * @param string               $idempotency_key the key sent with the request.
	 * @param CatalogObjectBatch[] $batches         the batches sent.
	 * @return Response
	 */
	public function batch_upsert_catalog_objects( $idempotency_key, array $batches ) {
		$this->upserts[] = array(
			'key'     => $idempotency_key,
			'batches' => $batches,
		);

		if ( ! empty( $this->upsert_script ) ) {
			$answer = array_shift( $this->upsert_script );
			$result = $answer( $idempotency_key, $batches );

			if ( null !== $result ) {
				return $result;
			}
		}

		return self::created( $batches );
	}

	/**
	 * @param array $args search arguments.
	 * @return Response
	 */
	public function search_catalog_objects( $args = array() ) {
		$this->searches[] = $args;

		if ( ! empty( $this->search_script ) ) {
			$answer = array_shift( $this->search_script );
			return $answer( $args );
		}

		return new Response( new SearchCatalogObjectsResponse() );
	}

	/**
	 * @param string $name method name.
	 * @param array  $args arguments.
	 * @throws \LogicException always.
	 */
	public function __call( $name, $args ) {
		throw new \LogicException( 'Unexpected Square API call in test: ' . $name ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
	}

	/**
	 * Builds the response Square gives when every batch is applied.
	 *
	 * @param CatalogObjectBatch[] $batches the batches sent.
	 * @return Response
	 */
	public static function created( array $batches ) {
		$mappings = array();
		$objects  = array();

		foreach ( $batches as $batch ) {
			foreach ( $batch->getObjects() as $object ) {
				$object = clone $object;

				if ( 0 === strpos( $object->getId(), '#' ) ) {
					$mappings[] = self::mapping( $object->getId() );
					$object->setId( self::permanent_id( $object->getId() ) );
				}

				$object->setVersion( 1 );

				if ( $object->getItemData() && is_array( $object->getItemData()->getVariations() ) ) {
					$variations = array();

					foreach ( $object->getItemData()->getVariations() as $variation ) {
						$variation = clone $variation;

						if ( 0 === strpos( $variation->getId(), '#' ) ) {
							$mappings[] = self::mapping( $variation->getId() );
							$variation->setId( self::permanent_id( $variation->getId() ) );
						}

						$variation->setVersion( 1 );
						$variations[] = $variation;
					}

					$item_data = clone $object->getItemData();
					$item_data->setVariations( $variations );
					$object->setItemData( $item_data );
				}

				$objects[] = $object;
			}
		}

		$data = new BatchUpsertCatalogObjectsResponse();
		$data->setObjects( $objects );
		$data->setIdMappings( $mappings );

		return new Response( $data );
	}

	/**
	 * The permanent ID this fake assigns to a temporary ID.
	 *
	 * @param string $client_id temporary "#" ID.
	 * @return string
	 */
	public static function permanent_id( $client_id ) {
		return 'SQ' . strtoupper( substr( md5( $client_id ), 0, 22 ) );
	}

	/**
	 * @param string $client_id temporary "#" ID.
	 * @return CatalogIdMapping
	 */
	private static function mapping( $client_id ) {
		$mapping = new CatalogIdMapping();
		$mapping->setClientObjectId( $client_id );
		$mapping->setObjectId( self::permanent_id( $client_id ) );

		return $mapping;
	}
}
