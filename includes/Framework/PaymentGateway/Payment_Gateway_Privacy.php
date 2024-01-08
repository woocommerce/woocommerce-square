<?php
/**
 * WooCommerce Plugin Framework
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
 * Modified by WooCommerce on 15 December 2021.
 */

namespace WooCommerce\Square\Framework\PaymentGateway;

use \WooCommerce\Square\Framework\PaymentGateway\PaymentTokens\Square_Credit_Card_Payment_Token;

defined( 'ABSPATH' ) || exit;

/**
 * The payment gateway privacy handler class.
 *
 * @since 3.0.0
 */
class Payment_Gateway_Privacy extends \WC_Abstract_Privacy {

	/** @var Payment_Gateway_Plugin payment gateway plugin instance */
	private $plugin;


	/**
	 * Constructs the class.
	 *
	 * @since 3.0.0
	 *
	 * @param Payment_Gateway_Plugin payment gateway plugin instance
	 */
	public function __construct( Payment_Gateway_Plugin $plugin ) {

		$this->plugin = $plugin;

		parent::__construct( $plugin->get_plugin_name() );

		// add the action & filter hooks
		$this->add_hooks();

		// add the token exporters & erasers
		// translators: Placeholder %s - Plugin name
		$this->add_exporter( "wc-{$plugin->get_id_dasherized()}-customer-tokens", sprintf( esc_html__( '%s Payment Tokens', 'woocommerce-square' ), $plugin->get_plugin_name() ), array( $this, 'customer_tokens_exporter' ) );

		// translators: Placeholder %s - Plugin name
		$this->add_eraser( "wc-{$plugin->get_id_dasherized()}-customer-tokens", sprintf( esc_html__( '%s Payment Tokens', 'woocommerce-square' ), $plugin->get_plugin_name() ), array( $this, 'customer_tokens_eraser' ) );
	}


	/**
	 * Adds the action & filter hooks.
	 *
	 * @since 3.0.0
	 */
	protected function add_hooks() {

		// add the gateway data to customer data exports
		add_filter( 'woocommerce_privacy_export_customer_personal_data', array( $this, 'add_export_customer_data' ), 10, 2 );

		// removes the gateway data during a customer data erasure
		add_action( 'woocommerce_privacy_erase_personal_data_customer', array( $this, 'remove_customer_personal_data' ), 10, 2 );

		// add the gateway data to order data exports
		add_filter( 'woocommerce_privacy_export_order_personal_data', array( $this, 'add_export_order_data' ), 10, 2 );

		// removes the gateway data during an order data erasure
		add_action( 'woocommerce_privacy_remove_order_personal_data', array( $this, 'remove_order_personal_data' ) );
	}


	/** Customer methods ******************************************************/


	/**
	 * Adds the gateway data to customer data exports.
	 *
	 * @internal
	 *
	 * @since 3.0.0
	 *
	 * @param array $data customer personal data to export
	 * @param \WC_Customer $customer customer object
	 * @return array
	 */
	public function add_export_customer_data( $data, $customer ) {

		if ( $customer instanceof \WC_Customer ) {

			foreach ( $this->get_plugin()->get_gateways() as $gateway ) {

				// skip gateways that don't support customer ID
				if ( ! $gateway->supports_customer_id() ) {
					continue;
				}

				$customer_id = $gateway->get_customer_id( $customer->get_id(), array( 'autocreate' => false ) );

				if ( $customer_id ) {

					$data[] = array(
						// translators: Placeholder %s - Customer ID
						'name'  => sprintf( esc_html__( '%s Customer ID', 'woocommerce-square' ), $gateway->get_method_title() ),
						'value' => $customer_id,
					);
				}
			}
		}

		return $data;
	}


	/**
	 * Removes the gateway data during an order data erasure.
	 *
	 * @since 3.0.0
	 *
	 * @param array $response customer data erasure response
	 * @param \WC_Customer $customer customer object
	 * @return array
	 */
	public function remove_customer_personal_data( $response, $customer ) {

		if ( $customer instanceof \WC_Customer ) {

			foreach ( $this->get_plugin()->get_gateways() as $gateway ) {

				// skip gateways that don't support customer ID
				if ( ! $gateway->supports_customer_id() ) {
					continue;
				}

				$gateway->remove_customer_id( $customer->get_id() );
			}
		}

		return $response;
	}


	/**
	 * Handles the customer token exporter.
	 *
	 * @internal
	 *
	 * @param string $email_address email address for the user to export
	 * @param int $page page offset - unused as we don't page tokens
	 * @return array token export data
	 */
	public function customer_tokens_exporter( $email_address, $page ) {

		$data = array();
		$user = get_user_by( 'email', $email_address );

		if ( $user instanceof \WP_User ) {

			foreach ( $this->get_plugin()->get_gateways() as $gateway ) {

				// skip gateways that don't support tokenization
				if ( ! $gateway->supports_tokenization() ) {
					continue;
				}

				/** @var Square_Credit_Card_Payment_Token $token */
				foreach ( $gateway->get_payment_tokens_handler()->get_tokens( $user->ID ) as $token ) {

					$token_data = array();

					if ( $token->get_card_type() ) {

						$token_data[] = array(
							'name'  => __( 'Type', 'woocommerce-square' ),
							'value' => $token->get_card_type(),
						);
					}

					if ( $token->get_last4() ) {

						$token_data[] = array(
							'name'  => __( 'Last Four', 'woocommerce-square' ),
							'value' => $token->get_last4(),
						);
					}

					if ( $token->get_nickname() ) {

						$token_data[] = array(
							'name'  => __( 'Nickname', 'woocommerce-square' ),
							'value' => $token->get_nickname(),
						);
					}

					if ( ! empty( $token_data ) ) {

						$data[] = array(
							'group_id'    => 'wc_' . $gateway->get_id() . '_tokens',
							// translators: Placeholder %s - Payment token
							'group_label' => sprintf( esc_html__( '%s Payment Tokens', 'woocommerce-square' ), $gateway->get_method_title() ),
							'item_id'     => 'token-' . $token->get_id(),
							'data'        => $token_data,
						);
					}
				}
			}
		}

		return array(
			'data' => $data,
			'done' => true,
		);
	}


	/**
	 * Handles the customer token eraser.
	 *
	 * @internal
	 *
	 * @param string $email_address email address for the user to erase
	 * @param int $page page offset - unused as we don't page tokens
	 * @return array token eraser data
	 */
	public function customer_tokens_eraser( $email_address, $page ) {

		$removed  = false;
		$messages = array();

		$user = get_user_by( 'email', $email_address );

		if ( $user instanceof \WP_User ) {

			foreach ( $this->get_plugin()->get_gateways() as $gateway ) {

				// skip gateways that don't support tokenization
				if ( ! $gateway->supports_tokenization() ) {
					continue;
				}

				foreach ( $gateway->get_payment_tokens_handler()->get_tokens( $user->ID ) as $token ) {

					$gateway->get_payment_tokens_handler()->remove_token( null, $token );

					// translators: Placeholder %d - Payment token
					$messages[] = sprintf( esc_html__( 'Removed payment token "%d"', 'woocommerce-square' ), $token->get_id() );
					$removed    = true;
				}

				// completely remove the user meta in case there is an API failure
				delete_user_meta( $user->ID, $gateway->get_payment_tokens_handler()->get_user_meta_name() );

				$gateway->get_payment_tokens_handler()->clear_transient( $user->ID );
			}
		}

		return array(
			'items_removed'  => $removed,
			'items_retained' => false,
			'messages'       => $messages,
			'done'           => true,
		);
	}


	/** Order methods *********************************************************/


	/**
	 * Adds the gateway data to order data exports.
	 *
	 * @internal
	 *
	 * @since 3.0.0
	 *
	 * @param array $data order personal data to export
	 * @param \WC_Order $order order object
	 * @return array
	 */
	public function add_export_order_data( $data, $order ) {

		$order = wc_get_order( $order );

		// ensure we have a full order object and it belongs to the plugin's gateway
		if ( $order && $this->get_plugin()->has_gateway( $order->get_payment_method() ) ) {

			$gateway = $this->get_plugin()->get_gateway( $order->get_payment_method() );

			$meta_to_export = array(
				'account_four'     => __( 'Last Four', 'woocommerce-square' ),
				'account_type'     => __( 'Account Type', 'woocommerce-square' ),
				'card_type'        => __( 'Card Type', 'woocommerce-square' ),
				'card_expiry_date' => __( 'Expiry Date', 'woocommerce-square' ),
			);

			foreach ( $meta_to_export as $key => $label ) {

				$value = $gateway->get_order_meta( $order, $key );

				if ( $value ) {

					$data[] = array(
						'name'  => $label,
						'value' => $value,
					);
				}
			}
		}

		return $data;
	}


	/**
	 * Removes the gateway data during an order data erasure.
	 *
	 * @since 3.0.0
	 *
	 * @param \WC_Order $order order object
	 */
	public function remove_order_personal_data( $order ) {

		$order = wc_get_order( $order );

		// ensure we have a full order object and it belongs to the plugin's gateway
		if ( $order && $this->get_plugin()->has_gateway( $order->get_payment_method() ) ) {

			$gateway = $this->get_plugin()->get_gateway( $order->get_payment_method() );

			$meta_to_remove = array(
				'account_four'     => 'XXXX',
				'account_type'     => '',
				'card_type'        => '',
				'card_expiry_date' => 'XXXX',
			);

			/**
			 * Filters the personal order meta data to remove during a customer erasure request.
			 *
			 * @since 3.0.0
			 *
			 * @param array $meta_keys personal order meta data to remove during a customer erasure request, in the form of $meta_key => $anonymized_value
			 * @param \WC_Order $order order object
			 */
			$meta_to_remove = apply_filters( 'wc_' . $gateway->get_id() . '_order_personal_data_to_remove', $meta_to_remove, $order );

			foreach ( $meta_to_remove as $key => $anonymized_value ) {

				// if the meta value already exists (don't add new meta to orders)
				$value = $gateway->get_order_meta( $order, $key );

				if ( $value ) {

					// if no anon value was specified, let WP use its default
					if ( empty( $anonymized_value ) && function_exists( 'wp_privacy_anonymize_data' ) ) {
						$anonymized_value = wp_privacy_anonymize_data( 'text', $value );
					}

					$gateway->update_order_meta( $order, $key, $anonymized_value );
				}
			}

			// clear the payment token (we don't want any "[deleted]" value stored)
			if ( $gateway->get_order_meta( $order, 'payment_token' ) ) {
				$gateway->update_order_meta( $order, 'payment_token', '' );
			}
		}
	}


	/**
	 * Gets the payment gateway plugin instance.
	 *
	 * @since 3.0.0
	 *
	 * @return Payment_Gateway_Plugin
	 */
	protected function get_plugin() {

		return $this->plugin;
	}
}
