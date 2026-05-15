<?php
/**
 * Class Abilities_Registrar
 *
 * @package WooCommerce\Square
 */

// @phan-file-suppress PhanUndeclaredFunction, PhanUndeclaredClassMethod @phan-suppress-current-line UnusedSuppression -- Abilities API ships with WooCommerce 10.9; the suppression covers static analysis runs on older WC versions where the wp_register_ability()/AbilitiesLoader symbols are not loaded. @todo Remove when Square for WooCommerce requires WooCommerce >= 10.9.

namespace WooCommerce\Square\Internal\Abilities;

defined( 'ABSPATH' ) || exit;

/**
 * Registers Square for WooCommerce abilities with the WordPress Abilities API.
 *
 * Thin coordinator: holds the ABILITY_CLASSES list and the
 * can_manage_woocommerce_square() capability helper that mirrors the load-bearing
 * read gate resolved by the plugin's REST controllers
 * (WC_Square_REST_Base_Controller::check_permission()).
 *
 * Gated by the `woocommerce_square_abilities_enabled` filter (default false).
 *
 * Registration pattern: abilities are registered exclusively via Woo
 * Core's `woocommerce_ability_definition_classes` loader filter
 * (introduced in WC 10.9). On stores running WC < 10.9 the feature
 * silently no-ops — see woo_abilities_loader_available().
 *
 * @internal This class may be modified, moved or removed in future releases.
 */
class Abilities_Registrar {

	/**
	 * Category slug used for every Square for WooCommerce ability.
	 *
	 * The `woocommerce` category is owned and registered by WooCommerce
	 * Core (10.9+); plugin ownership lives in the ability namespace, not
	 * the category. Mirrored on Domain\AbstractSquareAbility::CATEGORY_SLUG
	 * so Domain classes can reference self::CATEGORY_SLUG without a
	 * cross-namespace static call.
	 *
	 * @var string
	 */
	const CATEGORY_SLUG = 'woocommerce';

	/**
	 * Ability definition classes registered through the WC 10.9 loader.
	 *
	 * Every Square for WooCommerce ability is listed here. The ::class
	 * constants are compile-time strings — referencing them does NOT
	 * autoload the classes. They resolve only when Woo's loader iterates
	 * the filter return value on WC 10.9+.
	 *
	 * @var array<int, class-string>
	 */
	private const ABILITY_CLASSES = array(
		Domain\GetSyncStatus::class,
		Domain\GetSyncRecords::class,
		Domain\GetConnectionStatus::class,
		Domain\GetLocations::class,
		Domain\GetProductSyncState::class,
		Domain\GetPendingJobs::class,
		Domain\GetOrderPaymentStatus::class,
	);

	/**
	 * Whether init() has already wired its action callbacks.
	 *
	 * Without this guard, repeated calls to init() while the feature
	 * filter is true would each append a fresh add_action() for the
	 * registrar callbacks, and the Abilities Registry would emit
	 * _doing_it_wrong notices for every already-registered slug when the
	 * action fires.
	 *
	 * @var bool
	 */
	private static $initialized = false;

	/**
	 * Initialize the abilities registration.
	 *
	 * @return void
	 */
	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}

		/**
		 * Filter whether Square for WooCommerce's Abilities API registrations are active.
		 *
		 * This filter is evaluated from Plugin::__construct() the first time
		 * wc_square() is called — typically inside the plugin's
		 * `plugins_loaded` priority-10 init. To take effect, callbacks must
		 * be registered _before_ that point. Safe registration windows:
		 *   - a must-use plugin, or wp-config-time code;
		 *   - any `plugins_loaded` callback at priority < 10;
		 *   - a priority-10 `plugins_loaded` callback that runs BEFORE Square's
		 *     own (depends on load order — fragile, prefer one of the above).
		 *
		 * Callbacks registered on later hooks (`init`, `wp_loaded`,
		 * `rest_api_init`, …) will silently no-op.
		 *
		 * @since 5.4.0
		 *
		 * @param bool $enabled Whether to register Square for WooCommerce abilities. Default false.
		 */
		if ( ! apply_filters( 'woocommerce_square_abilities_enabled', false ) ) {
			return;
		}

		if ( ! self::woo_abilities_loader_available() ) {
			// Abilities feature requires WC 10.9. Silently no-op on older
			// versions; the feature flag is the rollout safety net.
			return;
		}

		self::$initialized = true;

		add_filter( 'woocommerce_ability_definition_classes', array( __CLASS__, 'append_classes' ) );
	}

	/**
	 * Reset the idempotency guard set by init().
	 *
	 * @internal Test-isolation helper. Not part of the public API.
	 *
	 * @return void
	 */
	public static function reset_initialized_for_testing(): void {
		self::$initialized = false;
	}

	/**
	 * Whether WC 10.9's AbilitiesLoader is available.
	 *
	 * Used as a hard gate: on WC < 10.9 the abilities feature silently
	 * no-ops. WC 10.9 also depends on WP 6.9, so wp_register_ability()
	 * is implicitly available wherever the loader exists.
	 *
	 * @return bool
	 */
	private static function woo_abilities_loader_available(): bool {
		return class_exists( '\\Automattic\\WooCommerce\\Internal\\Abilities\\AbilitiesLoader' );
	}

	/**
	 * Append Square for WooCommerce ability classes to Woo Core's loader.
	 *
	 * Filter callback for `woocommerce_ability_definition_classes`.
	 *
	 * @param array $classes Class names accumulated by the loader.
	 * @return array
	 */
	public static function append_classes( array $classes ): array {
		return array_merge( $classes, self::ABILITY_CLASSES );
	}

	/**
	 * Permission callback for Square for WooCommerce read abilities.
	 *
	 * Mirrors the read gate resolved by
	 * WC_Square_REST_Base_Controller::check_permission().
	 *
	 * @return bool
	 */
	public static function can_manage_woocommerce_square(): bool {
		return current_user_can( 'manage_woocommerce' ); // phpcs:ignore WordPress.WP.Capabilities.Unknown
	}
}
