<?php
/**
 * Get connection status ability definition.
 *
 * @package WooCommerce\Square
 */

// @phan-file-suppress PhanUndeclaredClassMethod, PhanUndeclaredFunction @phan-suppress-current-line UnusedSuppression -- Abilities API + AbilityDefinition added in WC 10.9; suppression covers older-WC compat runs where this class never loads.

namespace WooCommerce\Square\Internal\Abilities\Domain;

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Abilities\AbilityDefinition;
use WooCommerce\Square\Internal\Abilities\Abilities_Registrar;

/**
 * Registers the woocommerce-square/get-connection-status ability.
 *
 * Lean alternative to the kitchen-sink general settings read. Answers
 * "is this store connected to Square, against which environment, and
 * which location ID is configured?" with five focused fields — and
 * deliberately excludes access_tokens, connection/disconnection URLs,
 * and the locations list. The locations list is available as a
 * separate ability (woocommerce-square/get-locations).
 *
 * @internal Only loaded when WooCommerce 10.9+ is active.
 */
class GetConnectionStatus extends AbstractSquareAbility implements AbilityDefinition {

	public static function get_name(): string {
		return 'woocommerce-square/get-connection-status';
	}

	public static function get_registration_args(): array {
		return array(
			'label'               => __( 'Get Square connection status', 'woocommerce-square' ),
			'description'         => __( 'Return the Square OAuth connection state: connected (bool), configured (bool), environment (sandbox or production), the configured location id, and whether the sandbox toggle is on. Deliberately excludes tokens, connection URLs, and the locations list.', 'woocommerce-square' ),
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

		$settings = self::get_settings_handler_or_error();
		if ( is_wp_error( $settings ) ) {
			return $settings;
		}

		$location_id = $settings->get_location_id();

		return array(
			'is_connected'  => (bool) $settings->is_connected(),
			'is_configured' => (bool) $settings->is_configured(),
			'is_sandbox'    => (bool) $settings->is_sandbox(),
			'environment'   => (string) $settings->get_environment(),
			'location_id'   => ! empty( $location_id ) ? (string) $location_id : null,
		);
	}
}
