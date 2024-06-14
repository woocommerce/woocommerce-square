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

namespace WooCommerce\Square\Framework\PaymentGateway\PaymentTokens;

use WooCommerce\Square\Framework\PaymentGateway\Payment_Gateway;
use WooCommerce\Square\Framework\Addresses\Customer_Address;
use WooCommerce\Square\Framework\Square_Helper;
use WooCommerce\Square\Framework\PaymentGateway\Api\Payment_Gateway_API_Response;
use WooCommerce\Square\Framework\PaymentGateway\Admin\Payment_Gateway_Admin_Payment_Token_Editor;

defined( 'ABSPATH' ) || exit;

/**
 * Handle the payment tokenization related functionality.
 *
 * @since 3.0.0
 */
class Payment_Gateway_Payment_Tokens_Handler {

	/** @var string the gateway environment ID */
	protected $environment_id;

	/** @var array|Payment_Gateway_Payment_Token[] array of cached user id to array of Payment_Gateway_Payment_Token token objects */
	protected $tokens;

	/** @var Payment_Gateway gateway instance */
	protected $gateway;


	/**
	 * Build the class.
	 *
	 * @since 3.0.0
	 *
	 * @param Payment_Gateway $gateway payment gateway instance
	 */
	public function __construct( Payment_Gateway $gateway ) {

		$this->gateway = $gateway;

		$this->environment_id = $gateway->get_environment();

		add_action( 'woocommerce_payment_token_deleted', array( $this, 'remove_token' ), 25, 2 );
		add_action( 'wc_payment_gateway_' . $gateway->get_id() . '_payment_method_added', array( $this, 'clear_cache_for_new_token' ), 10, 2 );
		add_filter( 'woocommerce_get_customer_payment_tokens_limit', array( $this, 'increase_query_limit_for_payment_methods' ) );
	}

	/**
	 * Clear the user's cached tokens when they add a new token.
	 *
	 * @param int $token_id The newly created token ID.
	 * @param int $user_id  The user ID.
	 */
	public function clear_cache_for_new_token( $token_id, $user_id ) {
		$this->clear_transient( $user_id );
	}

	/**
	 * Sets the `limit` parameter to 30 to retrieve more payment methods by not relying
	 * on the `posts_per_page` setting.
	 *
	 * 30 was chosen as the default here as this should be high enough to account for the majority of users.
	 *
	 * @since 4.2.0
	 *
	 * @return int
	 */
	public function increase_query_limit_for_payment_methods() {
		return 30;
	}

	/**
	 * A factory method to build and return a payment token object for the
	 * gateway.  Concrete classes can override this method to return a custom
	 * payment token implementation.
	 *
	 * @since 3.0.0
	 *
	 * @param string $token payment token
	 * @param array $data {
	 *     Payment token data.
	 *
	 *     @type bool   $default   Optional. Indicates this is the default payment token
	 *     @type string $type      Payment type. Either 'credit_card' or 'check'
	 *     @type string $last_four Last four digits of account number
	 *     @type string $card_type Credit card type (`visa`, `mc`, `amex`, `disc`, `diners`, `jcb`) or `echeck`
	 *     @type string $exp_month Optional. Expiration month (credit card only)
	 *     @type string $exp_year  Optional. Expiration year (credit card only)
	 * }
	 * @return Payment_Gateway_Payment_Token payment token
	 */
	public function build_token( $token, $data ) {

		return new Payment_Gateway_Payment_Token( $token, $data );
	}


	/** Handle single tokens **********************************************************************/


	/**
	 * Tokenizes the current payment method and adds the standard transaction
	 * data to the order post record.
	 *
	 * @since 3.0.0
	 *
	 * @param \WC_Order $order order object
	 * @param Payment_Gateway_API_Create_Payment_Token_Response|null $response payment token API response, or null if the request should be made
	 * @param string $environment_id optional environment ID, defaults to the current environment
	 * @return \WC_Order order object
	 * @throws \Exception on transaction failure
	 */
	public function create_token( \WC_Order $order, $response = null, $environment_id = null ) {
		$gateway = $this->get_gateway();
		// default to current environment
		if ( is_null( $environment_id ) ) {
			$environment_id = $this->get_environment_id();
		}

		// perform the API request to tokenize the payment method if needed
		if ( ! $response || $this->get_gateway()->tokenize_after_sale() ) {
			$response = $gateway->get_api()->tokenize_payment_method( $order );
		}

		if ( $response->transaction_approved() ) {

			// add the token to the order object for processing
			$token   = $response->get_payment_token();
			$address = new Customer_Address();

			// generate an address from the order
			$address->set_from_order( $order );

			// store the billing hash on the token for later use in case it needs to be updated
			$token->set_billing_hash( $address->get_hash() );

			// set the resulting token on the order
			$order->payment->token = $token->get_id();

			// for credit card transactions add the card type, if known (some gateways return the credit card type as part of the response, others may require it as part of the request, and still others it may never be known)
			if ( $gateway->is_credit_card_gateway() && $token->get_card_type() ) {
				$order->payment->card_type = $token->get_card_type();
			}

			// set the token to the user account
			if ( $order->get_user_id() ) {
				$this->add_token( $order->get_user_id(), $token, $environment_id );
			}

			$order->add_order_note( $this->get_order_note( $token ) );

			// add the standard transaction data
			$gateway->add_transaction_data( $order, $response );

			// clear any cached tokens
			$transient_key = $this->get_transient_key( $order->get_user_id() );

			if ( $transient_key ) {
				delete_transient( $transient_key );
			}
		} else {

			if ( $response->get_status_code() && $response->get_status_message() ) {
				/* translators: Placeholders: %1$s - payment request response status code, %2$s - payment request response status message */
				$message = sprintf( esc_html__( 'Status code %1$s: %2$s', 'woocommerce-square' ), $response->get_status_code(), $response->get_status_message() );
			} elseif ( $response->get_status_code() ) {
				/* translators: Placeholders: %s - payment request response status code */
				$message = sprintf( esc_html__( 'Status code: %s', 'woocommerce-square' ), $response->get_status_code() );
			} elseif ( $response->get_status_message() ) {
				/* translators: Placeholders: %s - payment request response status message */
				$message = sprintf( esc_html__( 'Status message: %s', 'woocommerce-square' ), $response->get_status_message() );
			} else {
				$message = esc_html__( 'Unknown Error', 'woocommerce-square' );
			}

			// add transaction id if there is one
			if ( $response->get_transaction_id() ) {
				// translators: Placeholders: %s Transation ID.
				$message .= ' ' . sprintf( esc_html__( 'Transaction ID %s', 'woocommerce-square' ), $response->get_transaction_id() );
			}

			throw new \Exception( esc_html( $message ) );
		}

		return $order;
	}


	/**
	 * Adds a payment method and token as user meta.
	 *
	 * @since 3.0.0
	 *
	 * @param int $user_id user identifier
	 * @param Payment_Gateway_Payment_Token $token the token
	 * @param string|null $environment_id optional environment id, defaults to plugin current environment
	 */
	public function add_token( $user_id, $token, $environment_id = null ) {
		$payment_token = new Square_Credit_Card_Payment_Token();
		$payment_token->set_token( $token->get_id() );
		$payment_token->set_gateway_id( $this->get_gateway()->get_id() );
		$payment_token->set_last4( $token->get_last_four() );
		$payment_token->set_expiry_year( $token->get_exp_year() );
		$payment_token->set_expiry_month( $token->get_exp_month() );
		$payment_token->set_card_type( $token->get_card_type() );
		$payment_token->set_user_id( $user_id );
		$payment_token->set_environment( $this->get_environment_id() );
		$payment_token->set_billing_hash( $token->set_billing_hash() );
		$payment_token->save();
	}


	/**
	 * Returns the payment token object identified by $token from the user
	 * identified by $user_id
	 *
	 * @since 3.0.0
	 *
	 * @param int $user_id WordPress user identifier, or 0 for guest
	 * @param string $token payment token
	 * @param string|null $environment_id optional environment id, defaults to plugin current environment
	 * @return Square_Credit_Card_Payment_Token payment token object or null
	 */
	public function get_token( $user_id, $token, $environment_id = null ) {

		// default to current environment
		if ( is_null( $environment_id ) ) {
			$environment_id = $this->get_environment_id();
		}

		$tokens = $this->get_tokens( $user_id, array( 'environment_id' => $environment_id ) );

		$result = array_values(
			array_filter(
				$tokens,
				function( $customer_token ) use ( $token ) {
					return $token === $customer_token->get_token();
				}
			)
		);

		return count( $result ) > 0 ? $result[0] : null;
	}


	/**
	 * Updates a single token by persisting it to user meta
	 *
	 * @since 3.0.0
	 *
	 * @param int $user_id WP user ID
	 * @param Payment_Gateway_Payment_Token $token token to update
	 * @param string|null $environment_id optional environment ID, defaults to plugin current environment
	 * @return string|int updated user meta ID
	 */
	public function update_token( $user_id, $token, $environment_id = null ) {

		// default to current environment
		if ( null === $environment_id ) {
			$environment_id = $this->get_environment_id();
		}

		$tokens = $this->get_tokens( $user_id, array( 'environment_id' => $environment_id ) );

		if ( isset( $tokens[ $token->get_id() ] ) ) {
			$tokens[ $token->get_id() ] = $token;
		}

		return $this->update_tokens( $user_id, $tokens, $environment_id );
	}


	/**
	 * Deletes a credit card token from user meta
	 *
	 * @since 3.0.0
	 *
	 * @param string                    $token_id The payment token string.
	 * @param \WC_Payment_Token_CC|null $token    The payment token object.
	 * @return bool|int false if not deleted, updated user meta ID if deleted
	 */
	public function remove_token( $token_id, $token = null ) {

		$token_id = is_null( $token ) ? $token_id : $token->get_token();

		// for direct gateways that allow it, attempt to delete the token from the endpoint
		if ( $this->get_gateway()->get_api()->supports_remove_tokenized_payment_method() ) {

			try {

				$response = $this->get_gateway()->get_api()->remove_tokenized_payment_method( $token_id, $this->get_gateway()->get_customer_id( get_current_user_id() ) );

				if ( ! $response->transaction_approved() && ! $this->should_delete_token( $response ) ) {
					return false;
				}
			} catch ( \Exception $e ) {

				if ( $this->get_gateway()->debug_log() ) {
					$this->get_gateway()->get_plugin()->log( $e->getMessage(), $this->get_gateway()->get_id() );
				}

				return false;
			}
		}

		return true;
	}


	/**
	 * Determines if a token's local meta should be deleted based on an API response.
	 *
	 * @since 3.0.0
	 *
	 * @param Payment_Gateway_API_Response $response API response object
	 * @return bool
	 */
	public function should_delete_token( Payment_Gateway_API_Response $response ) {
		return false;
	}

	/**
	 * Sets the default token for a user.
	 *
	 * This is shown as "Default Card" in the frontend and will be auto-selected during checkout.
	 *
	 * @since 3.0.0
	 *
	 * @param int $user_id user identifier
	 * @param Payment_Gateway_Payment_Token|string $token the token to make default
	 * @param string|null $environment_id optional environment id, defaults to plugin current environment
	 * @return string|bool false if not set, updated user meta ID if set
	 */
	public function set_default_token( $user_id, $token, $environment_id = null ) {

		// default to current environment
		if ( is_null( $environment_id ) ) {
			$environment_id = $this->get_environment_id();
		}

		// unknown token?
		if ( ! $this->user_has_token( $user_id, $token ) ) {
			return false;
		}

		// get the payment token object as needed
		if ( ! is_object( $token ) ) {
			$token = $this->get_token( $user_id, $token, $environment_id );
		}

		// get existing tokens
		$tokens = $this->get_tokens( $user_id, array( 'environment_id' => $environment_id ) );

		// mark $token as the only active
		foreach ( $tokens as $key => $_token ) {

			if ( $token->get_id() === $_token->get_id() ) {
				$tokens[ $key ]->set_default( true );
			} else {
				$tokens[ $key ]->set_default( false );
			}
		}

		// persist the updated tokens
		return $this->update_tokens( $user_id, $tokens, $environment_id );

	}


	/** Handle all tokens *************************************************************************/


	/**
	 * Gets the available payment tokens for a user as an associative array of
	 * payment token to Payment_Gateway_Payment_Token
	 *
	 * @since 3.0.0
	 *
	 * @param int $user_id WordPress user identifier, or 0 for guest
	 * @param array $args optional arguments, can include
	 *      `customer_id` - if not provided, this will be looked up based on $user_id
	 *      `environment_id` - defaults to plugin current environment
	 * @return array|Payment_Gateway_Payment_Token[] associative array of string token to Payment_Gateway_Payment_Token object
	 */
	public function get_tokens( $user_id, $args = array() ) {

		// default to current environment
		if ( ! isset( $args['environment_id'] ) ) {
			$args['environment_id'] = $this->get_environment_id();
		}

		if ( ! isset( $args['customer_id'] ) ) {
			$args['customer_id'] = $this->get_gateway()->get_customer_id( $user_id, array( 'environment_id' => $args['environment_id'] ) );
		}

		$environment_id = $args['environment_id'];
		$customer_id    = $args['customer_id'];
		$transient_key  = $this->get_transient_key( $user_id );

		// return tokens cached during a single request
		if ( isset( $this->tokens[ $environment_id ][ $user_id ] ) ) {
			return $this->tokens[ $environment_id ][ $user_id ];
		}

		// return tokens cached in transient
		if ( $transient_key && false !== get_transient( $transient_key ) ) {
			$this->tokens[ $environment_id ][ $user_id ] = get_transient( $transient_key );
			return $this->tokens[ $environment_id ][ $user_id ];
		}

		$this->tokens[ $environment_id ][ $user_id ] = array();
		$tokens                                      = array();

		// retrieve the datastore persisted tokens first, so we have them for
		// gateways that don't support fetching them over an API, as well as the
		// default token for those that do
		if ( $user_id ) {

			$_tokens = get_user_meta( $user_id, $this->get_user_meta_name( $environment_id ), true );

			// from database format
			if ( is_array( $_tokens ) ) {
				foreach ( $_tokens as $token => $data ) {
					$tokens[ $token ] = $this->build_token( $token, $data );
				}
			}

			$this->tokens[ $environment_id ][ $user_id ] = $tokens;
		}

		// if the payment gateway API supports retrieving tokens directly, do so as it's easier to stay synchronized
		if ( $this->get_gateway()->get_api()->supports_get_tokenized_payment_methods() && $customer_id ) {

			try {

				// retrieve the payment method tokes from the remote API
				$response                                    = $this->get_gateway()->get_api()->get_tokenized_payment_methods( $customer_id );
				$this->tokens[ $environment_id ][ $user_id ] = $response->get_payment_tokens();

				// check for a default from the persisted set, if any
				$default_token = null;
				foreach ( $tokens as $default_token ) {
					if ( $default_token->is_default() ) {
						break;
					}
				}

				// mark the corresponding token from the API as the default one
				if ( $default_token && $default_token->is_default() && isset( $this->tokens[ $environment_id ][ $user_id ][ $default_token->get_id() ] ) ) {
					$this->tokens[ $environment_id ][ $user_id ][ $default_token->get_id() ]->set_default( true );
				}

				// merge local token data with remote data, sometimes local data is more robust
				$this->tokens[ $environment_id ][ $user_id ] = $this->merge_token_data( $tokens, $this->tokens[ $environment_id ][ $user_id ] );

				// persist locally after merging
				$this->update_tokens( $user_id, $this->tokens[ $environment_id ][ $user_id ], $environment_id );

			} catch ( \Exception $e ) {

				// communication or other error

				$this->get_gateway()->add_debug_message( $e->getMessage(), 'error' );

				$this->tokens[ $environment_id ][ $user_id ] = $tokens;
			}
		}

		// set the payment type image url, if any, for convenience
		foreach ( $this->tokens[ $environment_id ][ $user_id ] as $key => $token ) {
			$this->tokens[ $environment_id ][ $user_id ][ $key ]->set_image_url( $this->get_gateway()->get_payment_method_image_url( $token->is_credit_card() ? $token->get_card_type() : 'echeck' ) );
		}

		if ( $transient_key ) {
			set_transient( $transient_key, $this->tokens[ $environment_id ][ $user_id ], 60 );
		}

		/**
		 * Direct Payment Gateway Payment Tokens Loaded Action.
		 *
		 * Fired when payment tokens have been completely loaded.
		 *
		 * @since 3.0.0
		 *
		 * @param array $tokens array of Payment_Gateway_Payment_Tokens
		 * @param Payment_Gateway gateway class instance
		 */
		do_action( 'wc_payment_gateway_' . $this->get_gateway()->get_id() . '_payment_tokens_loaded', $this->tokens[ $environment_id ][ $user_id ], $this );

		return $this->tokens[ $environment_id ][ $user_id ];
	}


	/**
	 * Updates the given payment tokens for the identified user, in the database.
	 *
	 * @since 3.0.0
	 *
	 * @param int $user_id WP user ID
	 * @param array $tokens array of tokens
	 * @param string|null $environment_id optional environment id, defaults to plugin current environment
	 * @return string updated user meta id
	 */
	public function update_tokens( $user_id, $tokens, $environment_id = null ) {

		// default to current environment
		if ( is_null( $environment_id ) ) {
			$environment_id = $this->get_environment_id();
		}

		// update the local cache
		$this->tokens[ $environment_id ][ $user_id ] = $tokens;

		// clear the transient
		$this->clear_transient( $user_id );

		// persist the updated tokens to the user meta
		return update_user_meta( $user_id, $this->get_user_meta_name( $environment_id ), $this->format_for_db( $tokens ) );
	}



	/** Admin methods *****************************************************************************/


	/**
	 * Get the admin token editor instance.
	 *
	 * @since 3.0.0
	 *
	 * @return Payment_Gateway_Admin_Payment_Token_Editor
	 */
	public function get_token_editor() {
		return new Payment_Gateway_Admin_Payment_Token_Editor( $this->get_gateway() );
	}


	/** Conditional methods ***********************************************************************/


	/**
	 * Determines if the identified user has the given payment token
	 *
	 * @since 3.0.0
	 *
	 * @param int $user_id WordPress user identifier, or 0 for guest
	 * @param string|Payment_Gateway_Payment_Token $token payment token
	 * @param string|null $environment_id optional environment id, defaults to plugin current environment
	 * @return bool
	 */
	public function user_has_token( $user_id, $token, $environment_id = null ) {

		// default to current environment
		if ( is_null( $environment_id ) ) {
			$environment_id = $this->get_environment_id();
		}

		if ( is_object( $token ) ) {
			$token = $token->get_id();
		}

		// token exists?
		return ! is_null( $this->get_token( $user_id, $token, $environment_id ) );
	}


	/**
	 * Determines if the current payment method should be tokenized.
	 *
	 * Whether requested by customer or otherwise forced. This parameter is passed from
	 * the checkout page/payment form.
	 *
	 * @since 3.0.0
	 *
	 * @return bool
	 */
	public function should_tokenize() {

		return Square_Helper::get_post( 'wc-' . $this->get_gateway()->get_id_dasherized() . '-tokenize-payment-method' ) && ! Square_Helper::get_post( 'wc-' . $this->get_gateway()->get_id_dasherized() . '-payment-token' );
	}


	/**
	 * Determines if tokenization should be forced on the checkout page.
	 *
	 * This is most useful to force tokenization for a subscription or pre-orders initial transaction.
	 *
	 * @since 3.0.0
	 *
	 * @return bool
	 */
	public function tokenization_forced() {

		/**
		 * Direct Gateway Tokenization Forced Filter.
		 *
		 * Allow actors to indicate that tokenization should be forced for the current
		 * checkout.
		 *
		 * @since 3.0.0
		 *
		 * @param bool $force true to force tokenization, false otherwise
		 * @param Payment_Gateway $this instance
		 */
		return apply_filters( 'wc_payment_gateway_' . $this->get_gateway()->get_id() . '_tokenization_forced', false, $this->get_gateway() );
	}


	/** Utility methods ***************************************************************************/


	/**
	 * Merges remote token data with local tokens.
	 *
	 * Sometimes local tokens can provide additional detail that's not provided remotely.
	 *
	 * @since 3.0.0
	 *
	 * @param array $local_tokens local tokens
	 * @param array $remote_tokens remote tokens
	 * @return array associative array of string token to Payment_Gateway_Payment_Token objects
	 */
	protected function merge_token_data( $local_tokens, $remote_tokens ) {

		foreach ( $remote_tokens as &$remote_token ) {

			$remote_token_id = $remote_token->get_id();

			// bail if the remote token doesn't exist locally
			if ( ! isset( $local_tokens[ $remote_token_id ] ) ) {
				continue;
			}

			foreach ( $this->get_merge_attributes() as $attribute ) {

				$get_method = "get_{$attribute}";
				$set_method = "set_{$attribute}";

				// if the remote token is missing an attribute and the local token has it...
				if ( ! $remote_token->$get_method() && $local_tokens[ $remote_token_id ]->$get_method() ) {

					// set the attribute on the remote token
					$remote_token->$set_method( $local_tokens[ $remote_token_id ]->$get_method() );
				}
			}
		}

		return $remote_tokens;
	}


	/**
	 * Returns the attributes that should be used to merge local token data into
	 * a remote token.
	 *
	 * Gateways can override this method to add their own attributes, but must
	 * also include the associated get_*() & set_*() methods in the token class.
	 *
	 * See Authorize.net CIM for an example implementation.
	 *
	 * @since 3.0.0
	 *
	 * @return array associative array of string token to Payment_Gateway_Payment_Token objects
	 */
	protected function get_merge_attributes() {

		return array( 'last_four', 'card_type', 'account_type', 'exp_month', 'exp_year', 'nickname' );
	}


	/**
	 * Gets the payment token transient key for the given user, gateway and environment.
	 *
	 * Payment token transients can be disabled by using the filter below.
	 *
	 * @since 3.0.0
	 *
	 * @param string|int $user_id
	 * @return string transient key
	 */
	protected function get_transient_key( $user_id = null ) {

		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		// ex: wc_square_tokens_<md5 hash of gateway_id, user ID, and environment ID>
		$key = sprintf( 'wc_square_tokens_%s', md5( $this->get_gateway()->get_id() . '_' . $user_id . '_' . $this->get_environment_id() ) );

		/**
		 * Filter payment tokens transient key
		 *
		 * Warning: this filter should generally only be used to disable token
		 * transients by returning false or an empty string. Setting an incorrect or invalid
		 * transient key (e.g. not keyed to the current user or environment) can
		 * result in unexpected and difficult to debug situations involving tokens.
		 *
		 * filter responsibly!
		 *
		 * @since 3.0.0
		 * @param string $key transient key (must be 45 chars or less)
		 * @param Payment_Gateway $this direct gateway class instance
		 */
		return apply_filters( 'wc_payment_gateway_' . $this->get_gateway()->get_id() . '_payment_tokens_transient_key', $key, $user_id, $this->get_gateway() );
	}


	/**
	 * Helper method to clear the tokens transient
	 *
	 * TODO: ideally the transient would make use of actions to clear itself
	 * as needed (e.g. when customer IDs are updated/removed), but for now it's
	 * only cleared when the tokens are updated. @MR July 2015
	 *
	 * @since 3.0.0
	 *
	 * @param int|string $user_id
	 */
	public function clear_transient( $user_id ) {
		delete_transient( $this->get_transient_key( $user_id ) );
	}

	/**
	 * Clears all payment token transient.
	 *
	 * @since 3.8.3
	 */
	public function clear_all_transients() {
		global $wpdb;

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				array( '%_transient_wc_square_tokens_%' )
			)
		);
	}


	/**
	 * Returns the payment token user meta name for persisting the payment tokens.
	 *
	 * Defaults to _wc_{gateway id}_payment_tokens for the production environment,
	 * and _wc_{gateway id}_payment_tokens_{environment} for any other environment.
	 *
	 * NOTE: the gateway id, rather than plugin id, is used by default to create
	 * the meta key for this setting, because it's assumed that in the case of a
	 * plugin having multiple gateways (ie credit card and eCheck) the payment
	 * tokens will be distinct between them
	 *
	 * @since 3.0.0
	 *
	 * @param string|null $environment_id optional environment id, defaults to plugin current environment
	 * @return string payment token user meta name
	 */
	public function get_user_meta_name( $environment_id = null ) {

		// default to current environment
		if ( is_null( $environment_id ) ) {
			$environment_id = $this->get_environment_id();
		}

		// leading underscore since this will never be displayed to an admin user in its raw form
		return $this->get_gateway()->get_order_meta_prefix() . 'payment_tokens' . ( ! $this->get_gateway()->is_production_environment( $environment_id ) ? '_' . $environment_id : '' );
	}


	/**
	 * Gets the order note message when a customer saves their payment method
	 * to their account
	 *
	 * @since 3.0.0
	 *
	 * @param Payment_Gateway_Payment_Token $token the payment token being saved
	 * @return string
	 */
	protected function get_order_note( $token ) {

		$gateway = $this->get_gateway();

		$message = '';

		// order note based on gateway type
		if ( $gateway->is_credit_card_gateway() ) {

			$message = sprintf(
				/* translators: Placeholders: %1$s - payment gateway title (such as Authorize.net, Braintree, etc), %2$s - payment method name (mastercard, bank account, etc), %3$s - last four digits of the card/account, %4$s - card/account expiry date */
				esc_html__( '%1$s Payment Method Saved: %2$s ending in %3$s (expires %4$s)', 'woocommerce-square' ),
				$gateway->get_method_title(),
				$token->get_type_full(),
				$token->get_last_four(),
				$token->get_exp_date()
			);

		}

		return $message;
	}


	/**
	 * Returns $tokens in a format suitable for data storage
	 *
	 * @since 3.0.0
	 *
	 * @param array $tokens array of Payment_Gateway_Payment_Token tokens
	 * @return array data storage version of $tokens
	 */
	protected function format_for_db( $tokens ) {

		$_tokens = array();

		// to database format
		foreach ( $tokens as $key => $token ) {
			$_tokens[ $key ] = $token->to_datastore_format();
		}

		return $_tokens;
	}


	/**
	 * Get the gateway environment ID.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	protected function get_environment_id() {
		return $this->environment_id;
	}


	/**
	 * Gets the gateway instance.
	 *
	 * @since 3.0.0
	 *
	 * @return Payment_Gateway gateway instance
	 */
	protected function get_gateway() {
		return $this->gateway;
	}
}
