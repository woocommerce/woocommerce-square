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

namespace WooCommerce\Square;

use WooCommerce\Square\Framework\Api\Base;
use WooCommerce\Square\API\Requests;
use WooCommerce\Square\API\Responses;
use Square\SquareClient;
use Square\Environment;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce Square API class
 *
 * @since 2.0.0
 */
class API extends Base {


	/** catalog request type */
	const REQUEST_TYPE_CATALOG = 'catalog';

	/** inventory request type */
	const REQUEST_TYPE_INVENTORY = 'inventory';

	/** tax type inclusive */
	const TAX_TYPE_INCLUSIVE = 'INCLUSIVE';

	/** tax type additive */
	const TAX_TYPE_ADDITIVE = 'ADDITIVE';


	/** @var \Square\SquareClient Square API client instance */
	protected $client;


	/**
	 * Constructs the main Square API wrapper class.
	 *
	 * @since 2.0.0
	 *
	 * @param string $access_token Square API access token
	 * @param bool   $is_sandbox   If sandbox access is desired
	 */
	public function __construct( $access_token, $is_sandbox = null ) {
		$this->client = new SquareClient(
			array(
				'accessToken' => $access_token,
				'environment' => $is_sandbox ? Environment::SANDBOX : Environment::PRODUCTION,
			)
		);
	}


	/** Catalog API Methods *******************************************************************************************/


	/**
	 * Batch-deletes an array of catalog objects.
	 *
	 * @since 2.0.0
	 *
	 * @param string[] $object_ids array of square catalog object IDs
	 * @return Responses\Catalog
	 * @throws \Exception
	 */
	public function batch_delete_catalog_objects( array $object_ids ) {

		$request = $this->get_catalog_request();
		$request->set_batch_delete_catalog_objects_data( $object_ids );

		return $this->perform_request( $request );
	}


	/**
	 * Batch-retrieves an array of catalog objects.
	 *
	 * @since 2.0.0
	 *
	 * @param string[] $object_ids array of square catalog object IDs
	 * @param bool $include_related_objects whether or not to include related objects in the response
	 * @return Responses\Catalog
	 * @throws \Exception
	 */
	public function batch_retrieve_catalog_objects( array $object_ids, $include_related_objects = false ) {

		$request = $this->get_catalog_request();
		$request->set_batch_retrieve_catalog_objects_data( $object_ids, (bool) $include_related_objects );

		return $this->perform_request( $request );
	}


	/**
	 * Batch-upserts an array of catalog objects.
	 *
	 * @since 2.0.0
	 *
	 * @param string $idempotency_key a UUID for this request
	 * @param array $batches an array of batches to upsert
	 * @return Responses\Catalog
	 * @throws \Exception
	 */
	public function batch_upsert_catalog_objects( $idempotency_key, array $batches ) {

		$request = $this->get_catalog_request();
		$request->set_batch_upsert_catalog_objects_data( $idempotency_key, $batches );

		return $this->perform_request( $request );
	}


	/**
	 * Returns info about the Catalog API, including helpful info like request size limits.
	 *
	 * @since 2.0.0
	 * @return Responses\Catalog
	 * @throws \Exception
	 */
	public function catalog_info() {

		$request = $this->get_catalog_request();
		$request->set_catalog_info_data();

		return $this->perform_request( $request );
	}


	/**
	 * Deletes an object from the Square catalog.
	 *
	 * @since 2.0.0
	 *
	 * @param string $object_id Square catalog object ID
	 * @return Responses\Catalog
	 * @throws \Exception
	 */
	public function delete_catalog_object( $object_id ) {

		$request = $this->get_catalog_request();
		$request->set_delete_catalog_object_data( $object_id );

		return $this->perform_request( $request );
	}


	/**
	 * Returns a list of Square catalog items.
	 *
	 * @since 2.0.0
	 *
	 * @param string $cursor the cursor to list from
	 * @param string[] $types the item types to filter by
	 * @return Responses\Catalog
	 * @throws \Exception
	 */
	public function list_catalog( $cursor = '', $types = array() ) {

		$request = $this->get_catalog_request();
		$request->set_list_catalog_data( $cursor, $types );

		return $this->perform_request( $request );
	}


	/**
	 * Retrieves a single catalog object.
	 *
	 * @since 2.0.0
	 *
	 * @param string $object_id the Square catalog object ID
	 * @param bool $include_related_objects whether or not to include related objects (such as categories)
	 * @return Responses\Catalog
	 * @throws \Exception
	 */
	public function retrieve_catalog_object( $object_id, $include_related_objects = false ) {

		$request = $this->get_catalog_request();
		$request->set_retrieve_catalog_object_data( $object_id, $include_related_objects );

		return $this->perform_request( $request );
	}


	/**
	 * Searches the catalog for objects.
	 *
	 * @since 2.0.0
	 *
	 * @param array $args see Catalog::set_search_catalog_objects_data() for list of args
	 * @return Responses\Catalog
	 * @throws \Exception
	 */
	public function search_catalog_objects( $args = array() ) {

		$request = $this->get_catalog_request();
		$request->set_search_catalog_objects_data( $args );

		return $this->perform_request( $request );
	}


	/**
	 * Updates the modifier lists that apply to given items.
	 *
	 * @since 2.0.0
	 *
	 * @param string[] $item_ids array of Square catalog item IDs
	 * @param string[] $modifier_lists_to_enable (optional) modifier list IDs to enable
	 * @param string[] $modifier_lists_to_disable (optional) modifier list IDs to disable
	 * @return Responses\Catalog
	 * @throws \Exception
	 */
	public function update_item_modifier_lists( array $item_ids, array $modifier_lists_to_enable = array(), array $modifier_lists_to_disable = array() ) {

		$request = $this->get_catalog_request();
		$request->set_update_item_modifier_lists_data( $item_ids, $modifier_lists_to_enable, $modifier_lists_to_disable );

		return $this->perform_request( $request );
	}


	/**
	 * Updates an item's applied taxes.
	 *
	 * @since 2.0.0
	 *
	 * @param string[] $item_ids array of Square catalog item IDs
	 * @param string[] $taxes_to_enable (optional) tax IDs to enable
	 * @param string[] $taxes_to_disable (optional) tax IDs to disable
	 * @return Responses\Catalog
	 * @throws \Exception
	 */
	public function update_item_taxes( array $item_ids, array $taxes_to_enable = array(), array $taxes_to_disable = array() ) {

		$request = $this->get_catalog_request();
		$request->set_update_item_taxes_data( $item_ids, $taxes_to_enable, $taxes_to_disable );

		return $this->perform_request( $request );
	}


	/**
	 * Upserts an object into the catalog.
	 *
	 * @since 2.0.0
	 *
	 * @param string $idempotency_key UUID for this request
	 * @param \Square\Models\CatalogObject $object the object to upsert
	 * @return Responses\Catalog
	 * @throws \Exception
	 */
	public function upsert_catalog_object( $idempotency_key, $object ) {

		$request = $this->get_catalog_request();
		$request->set_upsert_catalog_object_data( $idempotency_key, $object );

		return $this->perform_request( $request );
	}


	/**
	 * Creates an image in Square.
	 *
	 * Note that this method uses a custom request, since the Square SDK does not yet provide a method for image creation.
	 *
	 * @since 2.0.0
	 *
	 * @param $image_path
	 * @param string $square_item_id
	 * @param string $caption optional image caption
	 * @return string
	 * @throws \Exception
	 */
	public function create_image( $image_path, $square_item_id = '', $caption = '' ) {

		if ( ! is_readable( $image_path ) ) {
			throw new \Exception( 'Image file is not readable' );
		}

		$image = file_get_contents( $image_path );

		$headers = array(
			'accept'         => 'application/json',
			'content-type'   => 'multipart/form-data; boundary="boundary"',
			'Square-Version' => '2019-05-08',
			'Authorization'  => 'Bearer ' . wc_square()->get_settings_handler()->get_access_token(),
		);

		$body  = '--boundary' . "\r\n";
		$body .= 'Content-Disposition: form-data; name="request"' . "\r\n";
		$body .= 'Content-Type: application/json' . "\r\n\r\n";

		$request = array(
			'idempotency_key' => wc_square()->get_idempotency_key(),
			'image'           => array(
				'type'       => 'IMAGE',
				'id'         => '#TEMP_ID',
				'image_data' => array(
					'caption' => esc_attr( $caption ),
				),
			),
		);

		if ( $square_item_id ) {
			$request['object_id'] = $square_item_id;
		}

		$body .= json_encode( $request ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode

		$body .= "\r\n";

		$body .= '--boundary' . "\r\n";
		$body .= 'Content-Disposition: form-data; name="file"; filename="' . esc_attr( basename( $image_path ) ) . '"' . "\r\n";
		$body .= 'Content-Type: image/jpeg' . "\r\n\r\n";
		$body .= $image . "\r\n";
		$body .= '--boundary--';

		$url = $this->client->getBaseUri() . '/v2/catalog/images';

		$response = wp_remote_post(
			$url,
			array(
				'headers' => $headers,
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new \Exception( esc_html( $response->get_error_message() ) );
		}

		$body = wp_remote_retrieve_body( $response );
		$body = json_decode( $body, true );

		if ( ! is_array( $body ) ) {
			throw new \Exception( 'Response was malformed' );
		}

		if ( ! empty( $body['errors'] ) || empty( $body['image']['id'] ) ) {

			if ( ! empty( $body['errors'][0]['detail'] ) ) {
				$message = $body['errors'][0]['detail'];
			} else {
				$message = 'Unknown error';
			}

			throw new \Exception( esc_html( $message ) );
		}

		return $body['image']['id'];
	}


	/** Inventory API Methods *****************************************************************************************/


	/**
	 * Adds a count of inventory as "in-stock" to the given Square item variation ID as a result of a refund.
	 *
	 * @since 2.0.0
	 *
	 * @param string $square_id Square object ID
	 * @param int $amount amount of inventory to add
	 * @return Responses\Inventory
	 * @throws \Exception
	 */
	public function add_inventory_from_refund( $square_id, $amount ) {

		return $this->add_inventory( $square_id, $amount, 'NONE' );
	}


	/**
	 * Adds a count of inventory as "in-stock" to the given Square item variation ID.
	 *
	 * @since 2.0.0
	 *
	 * @param string $square_id Square object ID
	 * @param int $amount amount of inventory to add
	 * @param string $from_state the API state the inventory is coming from
	 * @return Responses\Inventory
	 * @throws \Exception
	 */
	public function add_inventory( $square_id, $amount, $from_state = 'NONE' ) {

		return $this->adjust_inventory( $square_id, $amount, $from_state, 'IN_STOCK' );
	}


	/**
	 * Removes a count of inventory as "in-stock" to the given Square item variation ID.
	 *
	 * @since 2.0.0
	 *
	 * @param string $square_id Square object ID
	 * @param int $amount amount of inventory to remove
	 * @return Responses\Inventory
	 * @throws \Exception
	 */
	public function remove_inventory( $square_id, $amount ) {

		return $this->adjust_inventory( $square_id, $amount, 'IN_STOCK', 'SOLD' );
	}


	/**
	 * Performs an inventory adjustment.
	 *
	 * @since 2.0.0
	 *
	 * @param string $square_id Square object ID
	 * @param int $amount amount of inventory to add
	 * @param string $from_state the API state the inventory is coming from
	 * @param string $to_state the API state the inventory is changing to
	 * @return Responses\Inventory
	 * @throws \Exception
	 */
	protected function adjust_inventory( $square_id, $amount, $from_state, $to_state ) {

		$date = new \DateTime();

		$change = new \Square\Models\InventoryChange();
		$change->setType( 'ADJUSTMENT' );

		$inventory_adjustment = new \Square\Models\InventoryAdjustment();
		$inventory_adjustment->setCatalogObjectId( $square_id );
		$inventory_adjustment->setLocationId( $this->get_plugin()->get_settings_handler()->get_location_id() );
		$inventory_adjustment->setQuantity( (string) absint( $amount ) );
		$inventory_adjustment->setFromState( $from_state );
		$inventory_adjustment->setToState( $to_state );
		$inventory_adjustment->setOccurredAt( $date->format( DATE_ATOM ) );

		$change->setAdjustment( $inventory_adjustment );

		return $this->batch_change_inventory(
			uniqid( '', false ),
			array(
				$change,
			)
		);
	}


	/**
	 * Performs a Batch Change Inventory request.
	 *
	 * @since 2.0.0
	 *
	 * @param string $idempotency_key UUID for this request
	 * @param \Square\Models\InventoryChange[] $changes array of Inventory Changes
	 * @param bool $ignore_unchanged_counts whether the current physical count should be ignored if the quantity is unchanged since the last physical count
	 * @return Responses\Inventory
	 * @throws \Exception
	 */
	public function batch_change_inventory( $idempotency_key, $changes, $ignore_unchanged_counts = true ) {

		$request = $this->get_inventory_request();
		$request->set_batch_change_inventory_data( $idempotency_key, $changes, $ignore_unchanged_counts );

		return $this->perform_request( $request );
	}


	/**
	 * Performs a Batch Retrieve Inventory Changes request.
	 *
	 * @since 2.0.0
	 *
	 * @param array $args see Requests\Inventory::set_batch_retrieve_inventory_changes_data() for accepted arguments
	 *
	 * @return Responses\Inventory
	 * @throws \Exception
	 */
	public function batch_retrieve_inventory_changes( array $args = array() ) {

		$request = $this->get_inventory_request();
		$request->set_batch_retrieve_inventory_changes_data( $args );

		return $this->perform_request( $request );
	}


	/**
	 * Performs a Batch Retrieve Inventory Counts request.
	 *
	 * @since 2.0.0
	 *
	 * @param array $args see Requests\Inventory::set_batch_retrieve_inventory_counts_data() for accepted arguments
	 *
	 * @return Responses\Inventory
	 * @throws \Exception
	 */
	public function batch_retrieve_inventory_counts( array $args = array() ) {

		$request = $this->get_inventory_request();
		$request->set_batch_retrieve_inventory_counts_data( $args );

		return $this->perform_request( $request );
	}


	/**
	 * Performs a Retrieve Inventory Adjustment request.
	 *
	 * @since 2.0.0
	 *
	 * @param string $adjustment_id the InventoryAdjustment ID to retrieve
	 *
	 * @return Responses\Inventory
	 * @throws \Exception
	 */
	public function retrieve_inventory_adjustment( $adjustment_id ) {

		$request = $this->get_inventory_request();
		$request->set_retrieve_inventory_adjustment_data( $adjustment_id );

		return $this->perform_request( $request );
	}


	/**
	 * Performs a Retrieve Inventory Changes request.
	 *
	 * @since 2.0.0
	 *
	 * @param string $catalog_object_id the CatalogObject ID to retrieve
	 *
	 * @return Responses\Inventory
	 * @throws \Exception
	 */
	public function retrieve_inventory_changes( $catalog_object_id ) {

		$request = $this->get_inventory_request();
		$request->set_retrieve_inventory_changes_data( $catalog_object_id );

		return $this->perform_request( $request );
	}


	/**
	 * Performs a Retrieve Inventory Count request.
	 *
	 * @since 2.0.0
	 *
	 * @param string $catalog_object_id the CatalogObject ID to retrieve
	 * @return Responses\Inventory
	 * @throws \Exception
	 */
	public function retrieve_inventory_count( $catalog_object_id ) {

		$request = $this->get_inventory_request();
		$request->set_retrieve_inventory_count_data( $catalog_object_id, $this->get_plugin()->get_settings_handler()->get_location_id() );

		return $this->perform_request( $request );
	}


	/**
	 * Performs a Retrieve Inventory Physical Count request.
	 *
	 * @since 2.0.0
	 *
	 * @param string $physical_count_id the InventoryPhysicalCount ID to retrieve
	 *
	 * @return Responses\Inventory
	 * @throws \Exception
	 */
	public function retrieve_inventory_physical_count( $physical_count_id ) {

		$request = $this->get_inventory_request();
		$request->set_retrieve_inventory_physical_count_data( $physical_count_id );

		return $this->perform_request( $request );
	}


	/** Locations methods *********************************************************************************************/


	/**
	 * Gets the available locations.
	 *
	 * @since 2.0.0
	 *
	 * @return \Square\Models\Location[]
	 * @throws \Exception
	 */
	public function get_locations() {

		$request = new API\Requests\Locations( $this->client );

		$request->set_list_locations_data();

		$this->set_response_handler( API\Responses\Locations::class );

		/* @type API\Responses\Locations $response */
		$response = $this->perform_request( $request );

		return $response->get_locations();
	}


	/** Customer methods **********************************************************************************************/


	/**
	 * Gets all customers.
	 *
	 * @since 2.0.0
	 *
	 * @param string $cursor pagination cursor
	 * @return API\Response
	 * @throws \Exception
	 */
	public function get_customers( $cursor = '' ) {

		$request = new API\Requests\Customers( $this->client );

		$request->set_get_customers_data( $cursor );

		$this->set_response_handler( API\Response::class );

		return $this->perform_request( $request );
	}


	/** Request Helper Methods ****************************************************************************************/


	/**
	 * Gets a new Catalog API request.
	 *
	 * @since 2.0.0
	 *
	 * @return Requests\Catalog
	 * @throws \Exception
	 */
	protected function get_catalog_request() {

		return $this->get_new_request( self::REQUEST_TYPE_CATALOG );
	}


	/**
	 * Gets a new Inventory API request.
	 *
	 * @since 2.0.0
	 *
	 * @return Requests\Inventory
	 * @throws \Exception
	 */
	protected function get_inventory_request() {

		return $this->get_new_request( self::REQUEST_TYPE_INVENTORY );
	}


	/**
	 * Gets a new request object.
	 *
	 * @since 2.0.0
	 *
	 * @param string $type desired request type
	 * @return Requests\Catalog|Requests\Inventory
	 * @throws \Exception
	 */
	protected function get_new_request( $type = '' ) {

		switch ( $type ) {

			case self::REQUEST_TYPE_CATALOG:
				$request          = new Requests\Catalog( $this->client );
				$response_handler = Responses\Catalog::class;
				break;

			case self::REQUEST_TYPE_INVENTORY:
				$request          = new Requests\Inventory( $this->client );
				$response_handler = Responses\Inventory::class;
				break;

			default:
				throw new \Exception( 'Invalid request type.' );
		}

		$this->set_response_handler( $response_handler );

		return $request;
	}


	/**
	 * Performs an API request.
	 *
	 * @see Base::perform_request()
	 *
	 * @since 2.0.0
	 *
	 * @param API\Request $request request object
	 * @return API\Response
	 * @throws \Exception
	 */
	protected function perform_request( $request ) {

		// ensure API is in its default state
		$this->reset_response();

		// save the request object
		$this->request = $request;

		$start_time = microtime( true );

		try {

			// set the request URI to the Square SDK method for better logging
			$this->request_uri    = $this->get_request()->get_square_api_method();
			$this->request_method = '';

			// add any query args to the logged request URI for easier debugging
			foreach ( $this->get_request()->get_square_api_args() as $arg ) {

				if ( is_string( $arg ) ) {
					$this->request_uri .= "/{$arg}";
				}
			}

			// perform the request
			$response = $this->do_square_request( $this->get_request()->get_square_api(), $this->get_request()->get_square_api_method(), $this->get_request()->get_square_api_args() );

			// calculate request duration
			$this->request_duration = round( microtime( true ) - $start_time, 5 );

			// parse & validate response
			$response = $this->handle_response( $response );

		} catch ( \Exception $e ) {

			// alert other actors that a request has been made
			$this->broadcast_request();

			throw $e;
		}

		return $response;
	}


	/**
	 * Handles and parses the response.
	 *
	 * @since 2.0.0
	 *
	 * @param array|\WP_Error $response response data
	 * @throws \Exception
	 * @return API_Response|object request class instance that implements API_Request
	 */
	protected function handle_response( $response ) {
		// parse the response body and tie it to the request
		$this->response = $this->get_parsed_response( $this->raw_response_body );

		// allow child classes to validate response after parsing -- this is useful
		// for checking error codes/messages included in a parsed response
		$this->do_post_parse_response_validation();

		// fire do_action() so other actors can act on request/response data,
		// primarily used for logging
		$this->broadcast_request();

		return $this->response;
	}


	/**
	 * Validates the response data after it's been parsed.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 * @throws \Exception
	 */
	protected function do_post_parse_response_validation() {

		if ( ! $this->get_response()->has_errors() ) {
			return true;
		}

		$errors = array();

		/** @var \Square\Models\Error $error */
		foreach ( $this->get_response()->get_errors() as $error ) {
			$error_code = $error->getCode();
			if ( empty( $error_code ) ) {
				continue;
			}

			$errors[] = trim( "[{$error_code}] {$error->getDetail()}" );

			// Last attempt to refresh access token.
			if ( in_array( $error_code, array( 'ACCESS_TOKEN_EXPIRED', 'UNAUTHORIZED' ), true ) ) {
				if ( 'ACCESS_TOKEN_EXPIRED' === $error_code ) {
					$this->get_plugin()->log( 'Access Token Expired, attempting a refresh.' );
				} else {
					$this->get_plugin()->log( 'Authorization error occurred, attempting a refresh.' );
				}

				$this->get_plugin()->get_connection_handler()->refresh_connection();

				$failure_value = get_option( 'wc_square_refresh_failed', 'yes' );

				if ( empty( $failure_value ) ) {
					// Successfully refreshed on the last attempt
					$this->get_plugin()->log( 'Connection successfully refreshed.' );
					return true;
				}
			}

			// if the error indicates that access token is bad, disconnect the plugin to prevent further attempts
			if ( in_array( $error_code, array( 'ACCESS_TOKEN_EXPIRED', 'ACCESS_TOKEN_REVOKED', 'UNAUTHORIZED' ), true ) ) {
				$this->get_plugin()->get_connection_handler()->disconnect();
				$this->get_plugin()->log( 'Disconnected due to invalid authorization. Please try connecting again.' );
			}
		}

		// At this point we could not validate the response and assume a failed attempt.
		throw new \Exception( esc_html( implode( ' | ', $errors ) ) );
	}

	/**
	 * Performs a remote request with the Square API class.
	 *
	 * @since 2.0.0
	 *
	 * @param Object $square_api the square API class instance
	 * @param string $method the class method to call
	 * @param array $args the args to send with the method call
	 * @throws \Exception
	 */
	protected function do_square_request( $square_api, $method, $args ) {

		if ( ! is_callable( array( $square_api, $method ) ) ) {
			throw new \Exception( 'Invalid API method' );
		}

		// perform the request
		$response = call_user_func_array( array( $square_api, $method ), $args );

		if ( $response instanceof \Square\Http\ApiResponse ) {
			$this->response_code    = $response->getStatusCode();
			$this->response_headers = $response->getHeaders();

			if ( $response->isSuccess() ) {
				$this->raw_response_body = $response->getResult();
			} else {
				$this->raw_response_body = $response->getErrors();
			}
		}
	}


	/**
	 * Gets the main plugin instance.
	 *
	 * @since 2.0.0
	 *
	 * @return \WooCommerce\Square\Plugin
	 */
	public function get_plugin() {

		return wc_square();
	}


}
