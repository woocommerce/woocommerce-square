<?php

namespace WooCommerce\Square\Framework\PaymentGateway\PaymentTokens;

/**
 * Square Payment Gateway Token
 *
 * Represents a credit card payment token.
 *
 * @since 3.8.0
 */
class Square_Credit_Card_Payment_Token extends \WC_Payment_Token_CC {
	public function __construct( $token = '' ) {
		$this->extra_data = array_merge(
			$this->extra_data,
			array(
				'environment'  => '',
				'billing_hash' => '',
				'nickname'     => '',
			)
		);

		parent::__construct( $token );
	}

	/**
	 * Setter for a card's encironment.
	 *
	 * @param string $env Environment of the Square account.
	 * @since 3.8.0
	 */
	public function set_environment( $env = '' ) {
		$this->set_prop( 'environment', $env );
	}

	/**
	 * Setter for billing hash.
	 *
	 * @param string $value The billing hash string.
	 * @since 3.8.0
	 */
	public function set_billing_hash( $value = '' ) {
		$this->set_prop( 'billing_hash', $value );
	}

	/**
	 * Setter for a card's nickname.
	 *
	 * @param string $value The billing hash string.
	 * @since 3.8.0
	 */
	public function set_nickname( $value = '' ) {
		$this->set_prop( 'nickname', $value );
	}

	/**
	 * Getter for a card's environment.
	 *
	 * @since 3.8.0
	 * @return string
	 */
	public function get_environment( $context = 'view' ) {
		return $this->get_prop( 'environment', $context );
	}

	/**
	 * Getter for billing hash.
	 *
	 * @since 3.8.0
	 * @return string
	 */
	public function get_billing_hash( $context = 'view' ) {
		return $this->get_prop( 'billing_hash', $context );
	}

	/**
	 * Get expiry date.
	 *
	 * @since 3.8.0
	 * @return string
	 */
	public function get_exp_date() {
		$expiry_month = $this->get_expiry_month();
		$expiry_year  = $this->get_expiry_year();

		$expiry_date = $expiry_month . '/' . substr( $expiry_year, -2 );

		return $expiry_date;
	}

	/**
	 * Getter for a card's nickname.
	 *
	 * @param string $value The billing hash string.
	 * @since 3.8.0
	 */
	public function get_nickname( $context = 'view' ) {
		$this->get_prop( 'nickname', $context );
	}

	/**
	 * Retrieves array of Square Payment Tokens.
	 *
	 * @param WC_Payment_Token_CC[] $tokens Array of tokens
	 * @since 3.8.0
	 *
	 * @return Square_Credit_Card_Payment_Token[];
	 */
	public static function get_square_customer_tokens( $tokens = array() ) {
		$square_customer_tokens = array();

		/** @var \WC_Payment_Token_CC $token */
		foreach ( $tokens as $token_id => $token ) {
			if ( \WooCommerce\Square\Plugin::GATEWAY_ID !== $token->get_gateway_id() ) {
				continue;
			}

			$square_customer_tokens[ $token_id ] = new Square_Credit_Card_Payment_Token( $token );
		}

		return $square_customer_tokens;
	}
}
