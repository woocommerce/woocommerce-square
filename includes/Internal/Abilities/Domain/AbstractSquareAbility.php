<?php
/**
 * Abstract base class for Square for WooCommerce ability definitions.
 *
 * @package WooCommerce\Square
 */

namespace WooCommerce\Square\Internal\Abilities\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * Shared helpers for Square for WooCommerce ability definitions.
 *
 * Mirrors the shape of Woo Core's `Internal\Abilities\Domain\AbstractDomainAbility`
 * without coupling Square for WooCommerce to that class.
 *
 * @internal
 */
abstract class AbstractSquareAbility {

	/**
	 * Ability category slug shared across every Domain ability.
	 *
	 * The `woocommerce` category is owned and registered by WooCommerce
	 * Core (10.9+); plugin ownership lives in the ability namespace, not
	 * the category. Mirrors WooCommerce\Square\Internal\Abilities\Abilities_Registrar::CATEGORY_SLUG
	 * so Domain classes can reference self::CATEGORY_SLUG without a
	 * cross-namespace static call.
	 */
	public const CATEGORY_SLUG = 'woocommerce';

	/**
	 * Execute a backing REST controller route and return its unwrapped response.
	 *
	 * Visibility is `protected` so Domain subclasses inherit this helper.
	 *
	 * @param string $controller_class Fully-qualified backing controller class
	 *                                 (informational; surfaces a clear error when not loaded).
	 * @param string $method           HTTP method (GET, POST, PUT, DELETE).
	 * @param string $route            Resolved route path.
	 * @param array  $params           Request parameters.
	 * @param bool   $return_response  When true, return the WP_REST_Response object
	 *                                 so callers can read response headers.
	 * @return array|\WP_REST_Response|\WP_Error
	 */
	protected static function delegate_to_rest_controller(
		string $controller_class,
		string $method,
		string $route,
		array $params = array(),
		bool $return_response = false
	) {
		if ( ! class_exists( $controller_class ) ) {
			return new \WP_Error(
				'woocommerce_square_missing_controller',
				sprintf(
					/* translators: %s: fully-qualified class name of the missing REST controller. */
					__( 'REST controller %s is not loaded.', 'woocommerce-square' ),
					$controller_class
				),
				array( 'status' => 500 )
			);
		}

		$request = new \WP_REST_Request( $method, $route );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		$response = rest_do_request( $request );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( $response instanceof \WP_REST_Response ) {
			if ( $response->is_error() ) {
				return $response->as_error();
			}
			if ( $return_response ) {
				return $response;
			}
			return $response->get_data();
		}

		return is_array( $response ) ? $response : array( $response );
	}
}
