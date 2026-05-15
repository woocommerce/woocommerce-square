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
	 * Resolve the plugin's Settings handler, or a uniform WP_Error if the
	 * plugin is not initialized.
	 *
	 * @return \WooCommerce\Square\Settings|\WP_Error
	 */
	protected static function get_settings_handler_or_error() {
		$plugin = self::get_plugin_or_error();
		if ( is_wp_error( $plugin ) ) {
			return $plugin;
		}
		$settings = $plugin->get_settings_handler();
		if ( ! $settings ) {
			return self::not_initialized_error();
		}
		return $settings;
	}

	/**
	 * Resolve the plugin's Sync handler, or a uniform WP_Error if the plugin
	 * is not initialized.
	 *
	 * @return \WooCommerce\Square\Handlers\Sync|\WP_Error
	 */
	protected static function get_sync_handler_or_error() {
		$plugin = self::get_plugin_or_error();
		if ( is_wp_error( $plugin ) ) {
			return $plugin;
		}
		$sync = $plugin->get_sync_handler();
		if ( ! $sync ) {
			return self::not_initialized_error();
		}
		return $sync;
	}

	/**
	 * Resolve the plugin instance, or a uniform WP_Error if the wc_square()
	 * accessor has not loaded yet.
	 *
	 * @return \WooCommerce\Square\Plugin|\WP_Error
	 */
	protected static function get_plugin_or_error() {
		if ( ! function_exists( 'wc_square' ) ) {
			return self::not_initialized_error();
		}
		$plugin = wc_square();
		if ( ! $plugin ) {
			return self::not_initialized_error();
		}
		return $plugin;
	}

	/**
	 * Uniform "plugin not initialized" error for execute() callbacks.
	 *
	 * @return \WP_Error
	 */
	protected static function not_initialized_error(): \WP_Error {
		return new \WP_Error(
			'woocommerce_square_not_initialized',
			__( 'Square for WooCommerce is not initialized.', 'woocommerce-square' )
		);
	}

	/**
	 * Execute a backing REST controller route and return its unwrapped response.
	 *
	 * Visibility is `protected` so Domain subclasses inherit this helper.
	 *
	 * Note: `$controller_class` is checked with `class_exists()` purely as a
	 * fail-fast for the "controller class is not autoloadable" case (returns
	 * a friendlier WP_Error than the native REST 404). It does NOT verify
	 * that the class is registered for `$route`; if the route is missing,
	 * `rest_do_request()` returns its native 404 unchanged.
	 *
	 * @param string $controller_class Fully-qualified backing controller class.
	 *                                 Gates execution: returns WP_Error if the
	 *                                 class cannot be autoloaded.
	 * @param string $method           HTTP method (GET, POST, PUT, DELETE).
	 * @param string $route            Resolved route path.
	 * @param array  $params           Request parameters.
	 * @return array|\WP_Error
	 */
	protected static function delegate_to_rest_controller(
		string $controller_class,
		string $method,
		string $route,
		array $params = array()
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
			return $response->get_data();
		}

		return is_array( $response ) ? $response : array( $response );
	}
}
