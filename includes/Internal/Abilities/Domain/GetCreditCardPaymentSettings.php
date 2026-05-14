<?php
/**
 * Get credit-card payment gateway settings ability definition.
 *
 * @package WooCommerce\Square
 */

// @phan-file-suppress PhanUndeclaredClassMethod, PhanUndeclaredFunction @phan-suppress-current-line UnusedSuppression -- Abilities API + AbilityDefinition added in WC 10.9; suppression covers older-WC compat runs where this class never loads.

namespace WooCommerce\Square\Internal\Abilities\Domain;

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Abilities\AbilityDefinition;
use WooCommerce\Square\Admin\Rest\WC_REST_Square_Credit_Card_Payment_Settings_Controller;
use WooCommerce\Square\Internal\Abilities\Abilities_Registrar;

/**
 * Registers the woocommerce-square/get-credit-card-payment-settings ability.
 *
 * Shape-2 read (delegate-to-REST). Returns the Square credit-card gateway
 * configuration (enabled, title, transaction type, capture mode, accepted
 * card types, tokenization, digital-wallets toggle and styling) so an
 * agent can answer "how is the Square card gateway configured for
 * checkout?". Backed by GET /wc/v3/wc_square/payment_settings — a
 * zero-arg controller method that emits no telemetry and fires no hooks,
 * so the REST delegation is safe.
 *
 * @internal Only loaded when WooCommerce 10.9+ is active.
 */
class GetCreditCardPaymentSettings extends AbstractSquareAbility implements AbilityDefinition {

	public static function get_name(): string {
		return 'woocommerce-square/get-credit-card-payment-settings';
	}

	public static function get_registration_args(): array {
		return array(
			'label'               => __( 'Get Square credit-card gateway settings', 'woocommerce-square' ),
			'description'         => __( 'Return the Square credit-card gateway configuration (enabled, title, description, transaction type, capture, card types, tokenization, digital-wallets toggle and styling).', 'woocommerce-square' ),
			'category'            => self::CATEGORY_SLUG,
			'input_schema'        => array(
				'type'                 => 'object',
				'default'              => (object) array(),
				'properties'           => array(),
				'additionalProperties' => false,
			),
			'execute_callback'    => array( self::class, 'execute' ),
			'permission_callback' => array( Abilities_Registrar::class, 'can_manage_woocommerce_square' ),
			'meta'                => array(
				'annotations'  => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
				'show_in_rest' => true,
				'mcp'          => array(
					'public' => true,
				),
			),
		);
	}

	/**
	 * Execute callback.
	 *
	 * @param mixed $input Ignored.
	 * @return array|\WP_Error
	 */
	public static function execute( $input = null ) {
		unset( $input );

		return self::delegate_to_rest_controller(
			WC_REST_Square_Credit_Card_Payment_Settings_Controller::class,
			'GET',
			'/wc/v3/wc_square/payment_settings'
		);
	}
}
