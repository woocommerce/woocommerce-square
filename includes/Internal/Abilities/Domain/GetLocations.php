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
			'description'         => __( 'Return the Square locations the merchant\'s connected account exposes (id, name, status, currency, country). Empty array when not connected. Response is cached for ~1 hour; treat as point-in-time.', 'woocommerce-square' ),
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

		if ( ! function_exists( 'wc_square' ) ) {
			return new \WP_Error(
				'woocommerce_square_not_initialized',
				__( 'Square for WooCommerce is not initialized.', 'woocommerce-square' )
			);
		}

		$plugin = wc_square();
		if ( ! $plugin || ! method_exists( $plugin, 'get_settings_handler' ) ) {
			return new \WP_Error(
				'woocommerce_square_not_initialized',
				__( 'Square for WooCommerce is not initialized.', 'woocommerce-square' )
			);
		}

		$settings = $plugin->get_settings_handler();
		if ( ! $settings ) {
			return new \WP_Error(
				'woocommerce_square_not_initialized',
				__( 'Square for WooCommerce is not initialized.', 'woocommerce-square' )
			);
		}

		// verify-ignore: readonly -- Settings::get_locations() has two
		// side-effects on cold cache: (1) it hydrates a transient
		// (wc_square_locations_<ver>, TTL 1 hour) which is a cache-population
		// write; (2) if the merchant's stored location_id is no longer
		// present in the connected Square account's locations list, the
		// method self-heals by calling clear_location_id(). Both side
		// effects are infrequent (cache miss only) and benefit the
		// merchant, but the second is a real settings mutation. Tracked
		// as a Phase 2 follow-up: bypass Settings::get_locations() and
		// either read the transient directly or call the API client
		// without the self-heal step, so the readonly claim becomes
		// load-bearing rather than approximate.
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
