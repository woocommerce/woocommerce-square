<?php
/**
 * Get Cash App Pay gateway settings ability definition.
 *
 * @package WooCommerce\Square
 */

// @phan-file-suppress PhanUndeclaredClassMethod, PhanUndeclaredFunction @phan-suppress-current-line UnusedSuppression -- Abilities API + AbilityDefinition added in WC 10.9; suppression covers older-WC compat runs where this class never loads.

namespace WooCommerce\Square\Internal\Abilities\Domain;

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Abilities\AbilityDefinition;
use WooCommerce\Square\Admin\Rest\WC_REST_Square_Cash_App_Settings_Controller;
use WooCommerce\Square\Internal\Abilities\Abilities_Registrar;

/**
 * Registers the woocommerce-square/get-cash-app-payment-settings ability.
 *
 * Shape-2 read (delegate-to-REST). Returns the Square Cash App Pay
 * gateway configuration (enabled, title, transaction type, capture,
 * button theme and shape) so an agent can answer "how is Cash App Pay
 * configured for checkout?". Backed by GET /wc/v3/wc_square/cash_app_settings —
 * a zero-arg controller method with no telemetry or side effects.
 *
 * @internal Only loaded when WooCommerce 10.9+ is active.
 */
class GetCashAppPaymentSettings extends AbstractSquareAbility implements AbilityDefinition {

	public static function get_name(): string {
		return 'woocommerce-square/get-cash-app-payment-settings';
	}

	public static function get_registration_args(): array {
		return array(
			'label'               => __( 'Get Square Cash App Pay gateway settings', 'woocommerce-square' ),
			'description'         => __( 'Return the Square Cash App Pay gateway configuration (enabled, title, description, transaction type, capture, button theme and shape).', 'woocommerce-square' ),
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
			WC_REST_Square_Cash_App_Settings_Controller::class,
			'GET',
			'/wc/v3/wc_square/cash_app_settings'
		);
	}
}
