<?php
/**
 * Class WC_REST_Square_Settings_Controller file.
 */

namespace WooCommerce\Square\Admin\Rest;

use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_REST_Square_Settings_Controller.
 *
 * @since 4.7.0
 */
class WC_REST_Square_Settings_Controller extends WC_Square_REST_Base_Controller {

	/**
	 * Square settings option name.
	 *
	 * @var string
	 */
	const SQUARE_GATEWAY_SETTINGS_OPTION_NAME = 'wc_square_settings';

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'wc_square/settings';

	/**
	 * Allowed parameters.
	 *
	 * @var array
	 */
	private $allowed_params;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->allowed_params = array(
			'enable_sandbox',
			'sandbox_application_id',
			'sandbox_token',
			'sandbox_location_id',
			'production_location_id',
			'system_of_record',
			'enable_inventory_sync',
			'override_product_images',
			'hide_missing_products',
			'sync_interval',
			'is_connected',
			'locations',
			'enable_customer_decline_messages',
			'debug_mode',
			'debug_logging_enabled',
		);

		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_settings' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'save_settings' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'enable_sandbox'                   => array(
						'description'       => __( 'Application ID for the Sandbox Application.', 'woocommerce-square' ),
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'sandbox_application_id'           => array(
						'description'       => __( 'Access Token for the Sandbox Test Account.', 'woocommerce-square' ),
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'sandbox_token'                    => array(
						'description'       => __( 'Square sandbox ID.', 'woocommerce-square' ),
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'debug_logging_enabled'            => array(
						'description'       => __( 'Log debug messages to the WooCommerce status log.', 'woocommerce-square' ),
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'sandbox_location_id'              => array(
						'description'       => __( 'Square location ID. (Sandbox)', 'woocommerce-square' ),
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'production_location_id'           => array(
						'description'       => __( 'Square location ID.', 'woocommerce-square' ),
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'system_of_record'                 => array(
						'description'       => __( 'Choose where data will be updated for synced products.', 'woocommerce-square' ),
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'enable_inventory_sync'            => array(
						'description'       => __( 'Enable to fetch inventory changes from Square.', 'woocommerce-square' ),
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'override_product_images'          => array(
						'description'       => __( 'Enable to override Product images from Square.', 'woocommerce-square' ),
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'hide_missing_products'            => array(
						'description'       => __( 'Hide synced products when not found in Square.', 'woocommerce-square' ),
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'sync_interval'                    => array(
						'description'       => __( 'Frequency for how regularly WooCommerce will sync products with Square.', 'woocommerce-square' ),
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'debug_mode'                       => array(
						'description'       => __( 'Type of debug mode.', 'woocommerce-square' ),
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'enable_customer_decline_messages' => array(
						'description'       => __( 'Enable detailed decline messages to the customer during checkout when possible, rather than a generic decline message.', 'woocommerce-square' ),
						'type'              => 'string',
						'sanitize_callback' => '',
					),
				),
			)
		);
	}

	/**
	 * Get the data.
	 *
	 * @return WP_REST_Response
	 */
	public function get_settings() {
		// Need to reload the new settings as the settings are cached
		// and won't refresh until the next page load.
		wc_square()->get_settings_handler()->init_settings();

		$square_settings   = get_option( self::SQUARE_GATEWAY_SETTINGS_OPTION_NAME, array() );
		$filtered_settings = array_intersect_key( $square_settings, array_flip( $this->allowed_params ) );

		// Generate disconnection URL.
		$action = 'wc_square_disconnect';
		$url    = add_query_arg( 'action', $action, admin_url() );

		// Add the connection parameters to the response.
		$filtered_settings['is_connected']           = wc_square()->get_gateway()->get_plugin()->get_settings_handler()->is_connected();
		$filtered_settings['access_tokens']          = wc_square()->get_gateway()->get_plugin()->get_settings_handler()->get_access_tokens();
		$filtered_settings['connection_url']         = wc_square()->get_gateway()->get_plugin()->get_connection_handler()->get_connect_url( false );
		$filtered_settings['connection_url_wizard']  = wc_square()->get_gateway()->get_plugin()->get_connection_handler()->get_connect_url( false, array( 'from' => 'wizard' ) );
		$filtered_settings['connection_url_sandbox'] = wc_square()->get_gateway()->get_plugin()->get_connection_handler()->get_connect_url( true, array( 'from' => 'wizard' ) );
		$filtered_settings['disconnection_url']      = html_entity_decode( wp_nonce_url( $url, $action ) );

		// Add locations to the response.
		if ( wc_square()->get_settings_handler()->is_connected() ) {
			$filtered_settings['locations'] = wc_square()->get_settings_handler()->get_locations();
		}

		return new WP_REST_Response( $filtered_settings );
	}

	/**
	 * Update the data.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function save_settings( WP_REST_Request $request ) {
		$settings     = array();
		$keys_to_skip = array(
			'is_connected',
			'access_tokens',
			'locations',
			'connection_url',
			'connection_url_wizard',
			'connection_url_sandbox',
			'disconnection_url',
		);

		foreach ( $this->allowed_params as $index => $key ) {
			if ( in_array( $key, $keys_to_skip, true ) ) {
				continue;
			}

			$new_value        = wc_clean( wp_unslash( $request->get_param( $key ) ) );
			$settings[ $key ] = $new_value;
		}

		$is_sandbox    = wc_clean( wp_unslash( $settings['enable_sandbox'] ) ?? '' );
		$sandbox_token = wc_clean( wp_unslash( $settings['sandbox_token'] ) ?? '' );

		update_option( self::SQUARE_GATEWAY_SETTINGS_OPTION_NAME, $settings );

		// Need to reload the new settings as the settings are cached
		// and won't refresh until the next page load.
		wc_square()->get_settings_handler()->init_settings();

		if ( 'yes' === $is_sandbox && ! empty( $sandbox_token ) ) {
			wc_square()->get_settings_handler()->update_access_token( $sandbox_token );
		}

		wp_send_json_success();
	}
}
