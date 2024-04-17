<?php
/**
 * Class WC_REST_Square_Settings_Controller
 */

namespace WooCommerce\Square\Admin\Rest;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for saving Square's test/live account keys.
 *
 * This includes Live Publishable Key, Live Secret Key, Webhook Secret.
 *
 * @since 5.6.0
 */
class WC_REST_Square_Cash_App_Settings_Controller extends WC_Square_REST_Base_Controller {

	/**
	 * Square settings option name.
	 *
	 * @var string
	 */
	const SQUARE_CASH_APP_SETTINGS_OPTION_NAME = 'woocommerce_square_cash_app_pay_settings';

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'wc_square/cash_app_settings';

	/**
	 * Allowed parameters.
	 *
	 * @var array
	 */
	private $allowed_params;

	/**
	 * WC_REST_Square_Settings_Controller constructor.
	 */
	public function __construct() {
		$this->allowed_params  = array(
			'enabled',
			'title',
			'description',
			'transaction_type',
			'button_theme',
			'charge_virtual_orders',
			'enable_paid_capture',
			'button_shape',
			'debug_mode',
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
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_settings' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'save_settings' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [
					'enabled' => array(
						'description' => __( 'Enable Square payment gateway.', 'woocommerce-square' ),
						'type' => 'string',
						'sanitize_callback' => '',
					),
					'title' => array(
						'description' => __( 'Square payment gateway title.', 'woocommerce-square' ),
						'type' => 'string',
						'sanitize_callback' => '',
					),
					'description' => array(
						'description' => __( 'Square payment gateway description.', 'woocommerce-square' ),
						'type' => 'string',
						'sanitize_callback' => '',
					),
					'transaction_type' => array(
						'description' => __( 'The transaction type.', 'woocommerce-square' ),
						'type' => 'string',
						'sanitize_callback' => '',
					),
					'charge_virtual_orders' => array(
						'description' => __( 'If the order contains exclusively virtual items, enable this to immediately charge, rather than authorize, the transaction.', 'woocommerce-square' ),
						'type' => 'string',
						'sanitize_callback' => '',
					),
					'enable_paid_capture' => array(
						'description' => __( 'Automatically capture orders when they are changed to Processing or Completed.', 'woocommerce-square' ),
						'type' => 'string',
						'sanitize_callback' => '',
					),
					'button_theme' => array(
						'description' => __( 'Button Theme.', 'woocommerce-square' ),
						'type' => 'string',
						'sanitize_callback' => '',
					),
					'button_shape' => array(
						'description' => __( 'Button Shape.', 'woocommerce-square' ),
						'type' => 'string',
						'sanitize_callback' => '',
					),
					'debug_mode' => array(
						'description' => __( 'Debug mode type.', 'woocommerce-square' ),
						'type' => 'string',
						'sanitize_callback' => '',
					),
				],
			]
		);
	}

	/**
	 * Retrieve flag status.
	 *
	 * @return WP_REST_Response
	 */
	public function get_settings() {
		$square_settings   = get_option( self::SQUARE_CASH_APP_SETTINGS_OPTION_NAME, [] );
		$filtered_settings = array_intersect_key( $square_settings, array_flip( $this->allowed_params ) );

		return new WP_REST_Response( $filtered_settings );
	}

	/**
	 * Update the data.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function save_settings( WP_REST_Request $request ) {
		$settings = array();

		foreach ( $this->allowed_params as $index => $key ) {
			$new_value = wc_clean( wp_unslash( $request->get_param( $key ) ) );

			$settings[ $key ] = $new_value;
		}

		update_option( self::SQUARE_CASH_APP_SETTINGS_OPTION_NAME, $settings );
		wp_send_json_success();
	}
}
