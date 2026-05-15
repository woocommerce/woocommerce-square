<?php
/**
 * Get Square locations ability definition.
 *
 * @package WooCommerce\Square
 */

// @phan-file-suppress PhanUndeclaredClassMethod, PhanUndeclaredFunction @phan-suppress-current-line UnusedSuppression -- Abilities API + AbilityDefinition added in WC 10.9; suppression covers older-WC compat runs where this class never loads.

namespace WooCommerce\Square\Internal\Abilities\Domain;

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Abilities\AbilityDefinition;
use WooCommerce\Square\Internal\Abilities\Abilities_Registrar;

/**
 * Registers the woocommerce-square/get-locations ability.
 *
 * Returns the Square locations the merchant's connected account exposes —
 * id, name, status, currency, country (when available). Empty array when
 * not connected. Coerces Square SDK Location objects (\Square\Models\Location)
 * into plain associative arrays so the SDK class shape stays out of the
 * ability contract.
 *
 * Caveat: `Settings::get_locations()` round-trips the Square API on cache
 * miss (transient ttl is 1 hour). Treat the response as point-in-time.
 * Agents that need fresh data after a manual disconnect/reconnect must
 * accept a small staleness window.
 *
 * @internal Only loaded when WooCommerce 10.9+ is active.
 */
class GetLocations extends AbstractSquareAbility implements AbilityDefinition {

	public static function get_name(): string {
		return 'woocommerce-square/get-locations';
	}

	public static function get_registration_args(): array {
		return array(
			'label'               => __( 'Get Square locations', 'woocommerce-square' ),
			'description'         => __( 'Return the Square locations the merchant\'s connected account exposes (id, name, status, currency, country). Empty array when not connected. Response is cached for ~1 hour; treat as point-in-time. On cold cache the call hydrates a transient and may self-heal a stale stored location_id, so the underlying path is not strictly read-only — phase 2 will switch to a bypass.', 'woocommerce-square' ),
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
					// Cold-cache path writes a transient and may call
					// Settings::clear_location_id() (self-heal of a stale
					// stored location_id). Reflected here so MCP/agent
					// clients see the honest contract; phase 2 will
					// bypass Settings::get_locations() and restore readonly.
					'readonly'    => false,
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

		// Settings::get_locations() has two cold-cache side-effects:
		// (1) hydrates the wc_square_locations_<ver> transient (TTL 1 hour);
		// (2) self-heals a stale stored location_id via clear_location_id().
		// These are reflected in the ability's `readonly: false` annotation.
		// Phase 2 will bypass Settings::get_locations() (read transient + API
		// client directly, skip the self-heal) and restore `readonly: true`.
		$locations = $settings->get_locations( false );

		if ( ! is_array( $locations ) ) {
			return array();
		}

		$out = array();
		foreach ( $locations as $location ) {
			$out[] = self::normalize_location( $location );
		}
		return $out;
	}

	/**
	 * Coerce a Square SDK Location object (or array) into a plain array
	 * with the keys agents can rely on.
	 *
	 * @param mixed $location Square Location object or array-shaped value.
	 * @return array
	 */
	private static function normalize_location( $location ): array {
		if ( is_array( $location ) ) {
			return array(
				'id'       => isset( $location['id'] ) ? (string) $location['id'] : '',
				'name'     => isset( $location['name'] ) ? (string) $location['name'] : '',
				'status'   => isset( $location['status'] ) ? (string) $location['status'] : '',
				'currency' => isset( $location['currency'] ) ? (string) $location['currency'] : '',
				'country'  => isset( $location['country'] ) ? (string) $location['country'] : '',
			);
		}

		if ( ! is_object( $location ) ) {
			return array(
				'id'       => '',
				'name'     => '',
				'status'   => '',
				'currency' => '',
				'country'  => '',
			);
		}

		return array(
			'id'       => method_exists( $location, 'getId' ) ? (string) $location->getId() : '',
			'name'     => method_exists( $location, 'getName' ) ? (string) $location->getName() : '',
			'status'   => method_exists( $location, 'getStatus' ) ? (string) $location->getStatus() : '',
			'currency' => method_exists( $location, 'getCurrency' ) ? (string) $location->getCurrency() : '',
			'country'  => method_exists( $location, 'getCountry' ) ? (string) $location->getCountry() : '',
		);
	}
}
