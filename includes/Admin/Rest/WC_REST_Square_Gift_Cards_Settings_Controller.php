<?php
/**
 * Class WC_REST_Square_Gift_Cards_Settings_Controller file.
 */

namespace WooCommerce\Square\Admin\Rest;

use WooCommerce\Square\Gateway\Gift_Card;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_REST_Square_Gift_Cards_Settings_Controller.
 *
 * @since 4.7.0
 */
class WC_REST_Square_Gift_Cards_Settings_Controller extends WC_Square_REST_Base_Controller {

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'wc_square/gift_cards_settings';

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
			'enabled',
			'title',
			'description',
			'is_default_placeholder',
			'placeholder_id',
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
					'enabled'                => array(
						'description'       => __( 'Enable Square payment gateway.', 'woocommerce-square' ),
						'type'              => 'string',
						'sanitize_callback' => '',
					),
					'title'                  => array(
						'description'       => __( 'Square payment gateway title.', 'woocommerce-square' ),
						'type'              => 'string',
						'sanitize_callback' => '',
					),
					'description'            => array(
						'description'       => __( 'Square payment gateway description.', 'woocommerce-square' ),
						'type'              => 'string',
						'sanitize_callback' => '',
					),
					'is_default_placeholder' => array(
						'description'       => __( 'Indicates if a Gift card product should use the default placeholder provided by the plugin.', 'woocommerce-square' ),
						'type'              => 'string',
						'sanitize_callback' => '',
					),
					'placeholder_id'         => array(
						'description'       => __( 'ID of the placeholder media.', 'woocommerce-square' ),
						'type'              => 'integer',
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
		$square_settings   = get_option( Gift_Card::SQUARE_PAYMENT_SETTINGS_OPTION_NAME, array() );
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

		update_option( Gift_Card::SQUARE_PAYMENT_SETTINGS_OPTION_NAME, $settings );

		/**
		 * Action triggered when the Gift card payment settings are updated.
		 *
		 * @since 4.8.1
		 */
		do_action( 'wc_square_' . Gift_Card::SQUARE_PAYMENT_SETTINGS_OPTION_NAME . '_settings_updated', $settings );

		wp_send_json_success();
	}
}
