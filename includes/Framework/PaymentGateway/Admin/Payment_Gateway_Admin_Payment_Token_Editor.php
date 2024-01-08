<?php
/**
 * WooCommerce Payment Gateway Framework
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is bundled with this package in the file license.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0 or later
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@skyverge.com so we can send you a copy immediately.
 *
 * @since     3.0.0
 * @author    WooCommerce / SkyVerge
 * @copyright Copyright (c) 2013-2019, SkyVerge, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0 or later
 *
 * Modified by WooCommerce on 01 December 2021.
 */

namespace WooCommerce\Square\Framework\PaymentGateway\Admin;

use WooCommerce\Square\Framework\PaymentGateway\Payment_Gateway_Direct;
use WooCommerce\Square\Plugin;
use WooCommerce\Square\Framework\Square_Helper;
use WooCommerce\Square\Framework\PaymentGateway\Payment_Gateway_Helper;
use WooCommerce\Square\Framework\PaymentGateway\PaymentTokens\Payment_Gateway_Payment_Token;
use WooCommerce\Square\Framework\PaymentGateway\PaymentTokens\Square_Credit_Card_Payment_Token;

defined( 'ABSPATH' ) || exit;

/**
 * The token editor.
 *
 * @since 3.0.0
 */
class Payment_Gateway_Admin_Payment_Token_Editor {


	/** @var Payment_Gateway_Direct the gateway object **/
	protected $gateway;


	/**
	 * Constructs the editor.
	 *
	 * @since 3.0.0
	 *
	 * @param Payment_Gateway_Direct the gateway object
	 */
	public function __construct( Payment_Gateway_Direct $gateway ) {

		$this->gateway = $gateway;

		// Load the editor scripts and styles
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts_styles' ) );

		// Display the tokens markup inside the editor
		add_action( 'wc_payment_gateway_' . $this->get_gateway()->get_id() . '_token_editor_tokens', array( $this, 'display_tokens' ) );

		/** AJAX actions */

		// Get the blank token markup via AJAX
		add_action( 'wp_ajax_wc_payment_gateway_' . $this->get_gateway()->get_id() . '_admin_get_blank_payment_token', array( $this, 'ajax_get_blank_token' ) );

		// Remove a token via AJAX
		add_action( 'wp_ajax_wc_payment_gateway_' . $this->get_gateway()->get_id() . '_admin_remove_payment_token', array( $this, 'ajax_remove_token' ) );

		// Refresh the tokens via AJAX
		add_action( 'wp_ajax_wc_payment_gateway_' . $this->get_gateway()->get_id() . '_admin_refresh_payment_tokens', array( $this, 'ajax_refresh_tokens' ) );
	}


	/**
	 * Load the editor scripts and styles.
	 *
	 * @since 3.0.0
	 */
	public function enqueue_scripts_styles() {

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		// Stylesheet
		wp_enqueue_style( 'payment-gateway-token-editor', $this->get_gateway()->get_plugin()->get_plugin_url() . '/assets/css/admin/wc-square-payment-gateway-token-editor.min.css', array(), Plugin::VERSION );

		// Main editor script
		wp_enqueue_script( 'payment-gateway-token-editor', $this->get_gateway()->get_plugin()->get_plugin_url() . '/assets/js/admin/wc-square-payment-gateway-token-editor.min.js', array( 'jquery' ), Plugin::VERSION, true );

		wp_localize_script(
			'payment-gateway-token-editor',
			'wc_payment_gateway_token_editor',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'actions'  => array(
					'remove_token' => array(
						'ays'   => esc_html__( 'Are you sure you want to remove this token?', 'woocommerce-square' ),
						'nonce' => wp_create_nonce( 'wc_payment_gateway_admin_remove_payment_token' ),
					),
					'add_token'    => array(
						'nonce' => wp_create_nonce( 'wc_payment_gateway_admin_get_blank_payment_token' ),
					),
					'get_token'    => array(
						'nonce' => wp_create_nonce( 'wc_payment_gateway_admin_get_customer_token' ),
					),
					'refresh'      => array(
						'nonce' => wp_create_nonce( 'wc_payment_gateway_admin_refresh_payment_tokens' ),
					),
					'save'         => array(
						'error' => esc_html__( 'Invalid token data', 'woocommerce-square' ),
					),
				),
				'i18n'     => array(
					'general_error' => esc_html__( 'An error occurred. Please try again.', 'woocommerce-square' ),
				),
			)
		);
	}


	/**
	 * Display the token editor.
	 *
	 * @since 3.0.0
	 * @param int $user_id the user ID
	 */
	public function display( $user_id ) {

		$id      = $this->get_gateway()->get_id();
		$title   = $this->get_title();
		$columns = $this->get_columns();
		$actions = $this->get_actions();

		include $this->get_gateway()->get_plugin()->get_payment_gateway_framework_path() . '/Admin/views/html-user-payment-token-editor.php';
	}


	/**
	 * Display the tokens.
	 *
	 * @since 3.0.0
	 * @param int $user_id the user ID
	 */
	public function display_tokens( $user_id ) {

		$tokens = $this->get_tokens( $user_id );

		$fields     = $this->get_fields();
		$input_name = $this->get_input_name();
		$actions    = $this->get_token_actions();
		$type       = $this->get_payment_type();

		$index = 0;

		foreach ( $tokens as $token ) {

			include $this->get_gateway()->get_plugin()->get_payment_gateway_framework_path() . '/Admin/views/html-user-payment-token-editor-token.php';

			$index++;
		}
	}


	/**
	 * Save the token editor.
	 *
	 * @since 3.0.0
	 * @param int $user_id the user ID
	 */
	public function save( $user_id ) {
		$token_key = $this->get_input_name();
		$tokens    = filter_input( INPUT_POST, $token_key, FILTER_DEFAULT, FILTER_REQUIRE_ARRAY ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$tokens    = is_array( $tokens ) ? $tokens : null;

		if ( is_null( $tokens ) ) {
			return;
		}

		$sanitized_tokens = array();

		foreach ( $tokens as $token ) {
			if ( ! is_array( $token ) ) {
				continue;
			}

			$sanitized_tokens[] = array_map( 'sanitize_text_field', $token );
		}

		$customer_tokens = \WC_Payment_Tokens::get_customer_tokens( $user_id, wc_square()->get_gateway()->get_id() );

		foreach ( $sanitized_tokens as $data ) {

			$token_id = $data['id'];

			unset( $data['id'] );

			if ( ! $token_id ) {
				continue;
			}

			if ( 'credit_card' === $data['type'] ) {
				$data = $this->prepare_expiry_date( $data );
			}

			// Set the default method
			$data['default'] = Square_Helper::get_post( $this->get_input_name() . '_default' ) === $token_id;
			$data            = $this->validate_token_data( $token_id, $data );

			if ( $data ) {
				$token_found           = false;
				$matched_payment_token = null;
				$token_obj             = false;

				foreach ( $customer_tokens as $payment_token => $customer_token ) {
					if ( $customer_token->get_token() === $token_id ) {
						$token_found           = true;
						$matched_payment_token = $payment_token;
						break;
					}
				}

				if ( $token_found ) {
					$token_obj = \WC_Payment_Tokens::get( $matched_payment_token );
					$token_obj = new Square_Credit_Card_Payment_Token( $token_obj );
				} else {
					$token_obj = new Square_Credit_Card_Payment_Token();
				}

				$token_obj->set_last4( $data['last_four'] );
				$token_obj->set_expiry_year( $data['exp_year'] );
				$token_obj->set_expiry_month( $data['exp_month'] );
				$token_obj->set_card_type( $data['card_type'] );
				$token_obj->set_default( $data['default'] );
				$token_obj->save();
			}
		}
	}


	/**
	 * Add a token via AJAX.
	 *
	 * @since 3.0.0
	 */
	public function ajax_get_blank_token() {

		check_ajax_referer( 'wc_payment_gateway_admin_get_blank_payment_token', 'security' );

		$index = Square_Helper::get_request( 'index' );

		if ( $index ) {

			$fields     = $this->get_fields();
			$input_name = $this->get_input_name();
			$actions    = $this->get_token_actions();
			$type       = $this->get_payment_type();
			$user_id    = 0;

			$token            = array_fill_keys( array_keys( $fields ), '' );
			$token['id']      = '';
			$token['expiry']  = '';
			$token['default'] = false;

			ob_start();

			include $this->get_gateway()->get_plugin()->get_payment_gateway_framework_path() . '/Admin/views/html-user-payment-token-editor-token.php';

			$html = ob_get_clean();

			wp_send_json_success( $html );

		} else {

			wp_send_json_error();
		}
	}


	/**
	 * Remove a token via AJAX.
	 *
	 * @since 3.0.0
	 */
	public function ajax_remove_token() {

		try {

			if ( ! current_user_can( 'manage_woocommerce' ) || ! check_ajax_referer( 'wc_payment_gateway_admin_remove_payment_token', 'security', false ) ) {
				throw new \Exception( 'Invalid permissions' );
			}

			$user_id          = Square_Helper::get_request( 'user_id' );
			$token_id         = Square_Helper::get_request( 'token_id' );
			$payment_token_id = Square_Helper::get_request( 'payment_token_id' );

			if ( ! $user_id ) {
				throw new \Exception( 'User ID is missing' );
			}

			if ( ! $token_id ) {
				throw new \Exception( 'Token ID is missing' );
			}

			$token      = \WC_Payment_Tokens::get( $payment_token_id );
			$is_deleted = $token->delete();

			if ( $is_deleted ) {
				wp_send_json_success();
			} else {
				throw new \Exception( 'Could not remove token' );
			}
		} catch ( \Exception $e ) {

			wp_send_json_error( $e->getMessage() );
		}
	}


	/**
	 * Refresh the tokens list via AJAX.
	 *
	 * @since 3.0.0
	 */
	public function ajax_refresh_tokens() {

		try {

			if ( ! current_user_can( 'manage_woocommerce' ) || ! check_ajax_referer( 'wc_payment_gateway_admin_refresh_payment_tokens', 'security', false ) ) {
				throw new \Exception( 'Invalid permissions' );
			}

			$user_id = Square_Helper::get_request( 'user_id' );

			if ( ! $user_id ) {
				throw new \Exception( 'User ID is missing' );
			}

			ob_start();

			$this->display_tokens( $user_id );

			$html = ob_get_clean();

			wp_send_json_success( trim( $html ) );

		} catch ( \Exception $e ) {

			wp_send_json_error( $e->getMessage() );
		}
	}


	/**
	 * Build a token object from data saved in the admin.
	 *
	 * This method allows concrete gateways to add special token data.
	 * See Authorize.net CIM for an example.
	 *
	 * @since 3.0.0
	 *
	 * @param int    $user_id the user ID
	 * @param string $token_id the token ID
	 * @param array  $data the token data
	 * @return Payment_Gateway_Payment_Token the payment token object
	 */
	protected function build_token( $user_id, $token_id, $data ) {
		return $this->get_gateway()->get_payment_tokens_handler()->build_token( $token_id, $data );
	}


	/**
	 * Update the user's token data.
	 *
	 * @since 3.0.0
	 * @param int                     $user_id the user ID
	 * @param array the token objects
	 */
	protected function update_tokens( $user_id, $tokens ) {

		$this->get_gateway()->get_payment_tokens_handler()->update_tokens( $user_id, $tokens, $this->get_gateway()->get_environment() );
	}

	/**
	 * Validate a token's data before saving.
	 *
	 * Concrete gateways can override this to provide their own validation.
	 *
	 * @since 3.0.0
	 * @param array $data the token data
	 * @return array|bool the validated token data or false if the token should not be saved
	 */
	protected function validate_token_data( $token_id, $data ) {

		/**
		 * Filter the validated token data.
		 *
		 * @since 3.0.0
		 * @param array $data the validated token data
		 * @param string $token_id the token ID
		 * @param Payment_Gateway_Admin_Payment_Token_Editor the token editor instance
		 * @return array the validated token data
		 */
		return apply_filters( 'wc_payment_gateway_' . $this->get_gateway()->get_id() . '_token_editor_validate_token_data', $data, $token_id, $this );
	}


	/**
	 * Correctly format a credit card expiration date for storage.
	 *
	 * @since 3.0.0
	 * @param array $data
	 * @return array
	 */
	protected function prepare_expiry_date( $data ) {

		// expiry date must be present, include a forward slash and be 5 characters (MM/YY)
		if ( ! $data['expiry'] || ! Square_Helper::str_exists( $data['expiry'], '/' ) || 7 !== strlen( $data['expiry'] ) ) {
			unset( $data['expiry'] );
			return $data;
		}

		list( $data['exp_month'], $data['exp_year'] ) = explode( '/', $data['expiry'] );

		unset( $data['expiry'] );

		return $data;
	}


	/**
	 * Get the stored tokens for a user.
	 *
	 * @since 3.0.0
	 * @param int $user_id the user ID
	 * @return array the tokens in db format
	 */
	protected function get_tokens( $user_id ) {

		// Clear any cached tokens
		$this->get_gateway()->get_payment_tokens_handler()->clear_transient( $user_id );

		// get the customer ID separately so it's never auto-created from the admin
		$customer_id = $this->get_gateway()->get_customer_id(
			$user_id,
			array(
				'autocreate' => false,
			)
		);

		$stored_tokens = $this->get_gateway()->get_payment_tokens_handler()->get_tokens(
			$user_id,
			array(
				'customer_id' => $customer_id,
			)
		);

		return $stored_tokens;
	}


	/**
	 * Get the editor title.
	 *
	 * @since 3.0.0
	 * @return string
	 */
	protected function get_title() {

		$title = $this->get_gateway()->get_title();

		// Append the environment name if there are multiple
		if ( $this->get_gateway()->get_plugin()->get_admin_user_handler()->has_multiple_environments() ) {
			// translators: environment name in brackets
			$title .= ' ' . sprintf( esc_html__( '(%s)', 'woocommerce-square' ), $this->get_gateway()->get_environment_name() );
		}

		/**
		 * Filters the token editor name.
		 *
		 * @since 3.0.0
		 *
		 * @param string $title the editor title
		 * @param Payment_Gateway_Admin_Payment_Token_Editor $editor the editor object
		 */
		return apply_filters( 'wc_payment_gateway_' . $this->get_gateway()->get_id() . '_token_editor_title', $title, $this );
	}


	/**
	 * Get the editor columns.
	 *
	 * @since 3.0.0
	 * @return array
	 */
	protected function get_columns() {

		$fields  = $this->get_fields();
		$columns = array();

		foreach ( $fields as $field_id => $field ) {
			$columns[ $field_id ] = isset( $field['label'] ) ? $field['label'] : '';
		}

		$columns['default'] = esc_html__( 'Default', 'woocommerce-square' );
		$columns['actions'] = '';

		/**
		 * Filters the admin token editor columns.
		 *
		 * @since 3.0.0
		 *
		 * @param array $columns
		 * @param Payment_Gateway_Admin_Payment_Token_Editor $editor the editor object
		 */
		$columns = apply_filters( 'wc_payment_gateway_' . $this->get_gateway()->get_id() . '_token_editor_columns', $columns, $this );

		return $columns;
	}


	/**
	 * Get the editor fields.
	 *
	 * @since 3.0.0
	 * @return array
	 */
	protected function get_fields( $type = '' ) {

		if ( ! $type ) {
			$type = $this->get_gateway()->get_payment_type();
		}

		switch ( $type ) {

			case 'credit-card':
				// Define the credit card fields
				$fields = array(
					'id'        => array(
						'label'    => esc_html__( 'Token ID', 'woocommerce-square' ),
						'editable' => ! $this->get_gateway()->get_api()->supports_get_tokenized_payment_methods(),
						'required' => true,
					),
					'card_type' => array(
						'label'   => esc_html__( 'Card Type', 'woocommerce-square' ),
						'type'    => 'select',
						'options' => $this->get_card_type_options(),
					),
					'last_four' => array(
						'label'      => esc_html__( 'Last Four', 'woocommerce-square' ),
						'attributes' => array(
							'pattern'   => '[0-9]{4}',
							'maxlength' => 4,
						),
					),
					'expiry'    => array(
						'label'      => esc_html__( 'Expiration (MM/YYYY)', 'woocommerce-square' ),
						'attributes' => array(
							'placeholder' => 'MM/YYYY',
							'pattern'     => '^(0[1-9]|1[0-2])\/?([0-9]{4})$',
							'maxlength'   => 7,
						),
					),
				);

				break;

			default:
				$fields = array();
		}

		// Parse each field against the defaults
		foreach ( $fields as $field_id => $field ) {

			$fields[ $field_id ] = wp_parse_args(
				$field,
				array(
					'label'      => '',
					'type'       => 'text',
					'attributes' => array(),
					'editable'   => true,
					'required'   => false,
				)
			);
		}

		/**
		 * Filters the admin token editor fields.
		 *
		 * @since 3.0.0
		 *
		 * @param array $fields
		 * @param Payment_Gateway_Admin_Payment_Token_Editor $editor the editor object
		 */
		$fields = apply_filters( 'wc_payment_gateway_' . $this->get_gateway()->get_id() . '_token_editor_fields', $fields, $this );

		return $fields;
	}


	/**
	 * Get the token payment type.
	 *
	 * @since 3.0.0
	 * @return string
	 */
	protected function get_payment_type() {

		return str_replace( '-', '_', $this->get_gateway()->get_payment_type() );
	}


	/**
	 * Get the credit card type field options.
	 *
	 * @since 3.0.0
	 * @return array
	 */
	protected function get_card_type_options() {

		$card_types = $this->get_gateway()->get_card_types();
		$options    = array();

		foreach ( $card_types as $card_type ) {

			$card_type = Payment_Gateway_Helper::normalize_card_type( $card_type );

			$options[ $card_type ] = Payment_Gateway_Helper::payment_type_to_name( $card_type );
		}

		return $options;
	}


	/**
	 * Get the HTML name for the token fields.
	 *
	 * @since 3.0.0
	 * @return string
	 */
	protected function get_input_name() {

		return 'wc_payment_gateway_' . $this->get_gateway()->get_id() . '_tokens';
	}


	/**
	 * Get the available editor actions.
	 *
	 * @since 3.0.0
	 * @return array
	 */
	protected function get_actions() {

		$actions = array();

		if ( $this->get_gateway()->get_api()->supports_get_tokenized_payment_methods() ) {
			$actions['refresh'] = esc_html__( 'Refresh', 'woocommerce-square' );
		} else {
			$actions['add-new'] = esc_html__( 'Add New', 'woocommerce-square' );
		}

		$actions['save'] = esc_html__( 'Save', 'woocommerce-square' );

		/**
		 * Filters the payment token editor actions.
		 *
		 * @since 3.0.0
		 *
		 * @param array $actions the actions
		 * @param Payment_Gateway_Admin_Payment_Token_Editor $editor the editor object
		 */
		return apply_filters( 'wc_payment_gateway_' . $this->get_gateway()->get_id() . '_token_editor_actions', $actions, $this );
	}


	/**
	 * Get the available token actions.
	 *
	 * @since 3.0.0
	 * @return array
	 */
	protected function get_token_actions() {

		$actions = array(
			'remove' => esc_html__( 'Remove', 'woocommerce-square' ),
		);

		/**
		 * Filters the token actions.
		 *
		 * @since 3.0.0
		 *
		 * @param array $actions the token actions
		 * @param Payment_Gateway_Admin_Payment_Token_Editor $editor the editor object
		 */
		return apply_filters( 'wc_payment_gateway_' . $this->get_gateway()->get_id() . '_token_editor_token_actions', $actions, $this );
	}


	/**
	 * Gets the gateway object.
	 *
	 * @since 3.0.0
	 *
	 * @return Payment_Gateway_Direct the gateway object
	 */
	protected function get_gateway() {

		return $this->gateway;
	}
}
