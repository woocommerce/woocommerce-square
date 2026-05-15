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
	 * Resolve the plugin's Background_Job handler, or a uniform WP_Error if
	 * the plugin is not initialized.
	 *
	 * @return \WooCommerce\Square\Handlers\Background_Job|\WP_Error
	 */
	protected static function get_background_job_handler_or_error() {
		$plugin = self::get_plugin_or_error();
		if ( is_wp_error( $plugin ) ) {
			return $plugin;
		}
		$jobs = $plugin->get_background_job_handler();
		if ( ! $jobs ) {
			return self::not_initialized_error();
		}
		return $jobs;
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
}
