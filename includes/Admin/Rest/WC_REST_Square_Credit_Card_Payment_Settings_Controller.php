<?php
/**
 * Class WC_REST_Square_Credit_Card_Payment_Settings_Controller file.
 */

namespace WooCommerce\Square\Admin\Rest;

use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_REST_Square_Credit_Card_Payment_Settings_Controller.
 *
 * @since 4.7.0
 */
class WC_REST_Square_Credit_Card_Payment_Settings_Controller extends WC_Square_REST_Base_Controller {

	/**
	 * Square settings option name.
	 *
	 * @var string
	 */
	const SQUARE_PAYMENT_SETTINGS_OPTION_NAME = 'woocommerce_square_credit_card_settings';

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'wc_square/payment_settings';

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
			'transaction_type',
			'charge_virtual_orders',
			'enable_paid_capture',
			'card_types',
			'tokenization',
			'digital_wallet_settings',
			'enable_digital_wallets',
			'digital_wallets_button_type',
			'digital_wallets_apple_pay_button_color',
			'digital_wallets_google_pay_button_color',
			'digital_wallets_hide_button_options',
			'gift_card_settings',
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
					'enabled'                             => array(
						'description'       => __( 'Enable Square payment gateway.', 'woocommerce-square' ),
						'type'              => 'string',
						'sanitize_callback' => '',
					),
					'title'                               => array(
						'description'       => __( 'Square payment gateway title.', 'woocommerce-square' ),
						'type'              => 'string',
						'sanitize_callback' => '',
					),
					'description'                         => array(
						'description'       => __( 'Square payment gateway description.', 'woocommerce-square' ),
						'type'              => 'string',
						'sanitize_callback' => '',
					),
					'transaction_type'                    => array(
						'description'       => __( 'The transaction type.', 'woocommerce-square' ),
						'type'              => 'string',
						'sanitize_callback' => '',
					),
					'charge_virtual_orders'               => array(
						'description'       => __( 'If the order contains exclusively virtual items, enable this to immediately charge, rather than authorize, the transaction.', 'woocommerce-square' ),
						'type'              => 'string',
						'sanitize_callback' => '',
					),
					'enable_paid_capture'                 => array(
						'description'       => __( 'Automatically capture orders when they are changed to Processing or Completed.', 'woocommerce-square' ),
						'type'              => 'string',
						'sanitize_callback' => '',
					),
					'card_types'                          => array(
						'description'       => __( 'Array of card type logos.', 'woocommerce-square' ),
						'type'              => 'string',
						'sanitize_callback' => '',
					),
					'tokenization'                        => array(
						'description'       => __( 'Enable tokenization and allow customers to securely save their payment details for future checkout.', 'woocommerce-square' ),
						'type'              => 'string',
						'sanitize_callback' => '',
					),
					'enable_digital_wallets'              => array(
						'description'       => __( 'Allow customers to pay with Apple Pay or Google Pay from your Product, Cart and Checkout pages', 'woocommerce-square' ),
						'type'              => 'string',
						'sanitize_callback' => '',
					),
					'digital_wallets_button_type'         => array(
						'description'       => __( 'This setting only applies to the Apple Pay button. When Google Pay is available, the Google Pay button will always have the "Buy with" button text.', 'woocommerce-square' ),
						'type'              => 'string',
						'sanitize_callback' => '',
					),
					'digital_wallets_apple_pay_button_color' => array(
						'description'       => __( 'Color of the Apple Pay button.', 'woocommerce-square' ),
						'type'              => 'string',
						'sanitize_callback' => '',
					),
					'digital_wallets_google_pay_button_color' => array(
						'description'       => __( 'Color of the GPay button.', 'woocommerce-square' ),
						'type'              => 'string',
						'sanitize_callback' => '',
					),
					'digital_wallets_hide_button_options' => array(
						'description'       => __( 'Array of digital wallet buttons to hide', 'woocommerce-square' ),
						'type'              => 'array',
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
		$square_settings   = get_option( self::SQUARE_PAYMENT_SETTINGS_OPTION_NAME, array() );
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

		update_option( self::SQUARE_PAYMENT_SETTINGS_OPTION_NAME, $settings );
		wp_send_json_success();
	}
}
