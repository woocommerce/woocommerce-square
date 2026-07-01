<?php
/**
 * WooCommerce Square
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is bundled with this package in the file license.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0 or later
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@woocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade WooCommerce Square to newer
 * versions in the future. If you wish to customize WooCommerce Square for your
 * needs please refer to https://docs.woocommerce.com/document/woocommerce-square/
 *
 * @author    WooCommerce
 * @copyright Copyright: (c) 2019, Automattic, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0 or later
 */

namespace WooCommerce\Square\Admin;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Square\Plugin;

/**
 * Registers the Square settings hub under WooCommerce > Settings > Payments > Square.
 *
 * When the modern-settings path is active (WC 11.0+), registers Square_Settings_Section
 * via SettingsSectionRegistry so WC Core resolves the 4-tab hub natively. On older WC
 * or when the feature flag is off, the legacy Settings_Page.php path is used instead.
 *
 * @since x.x.x
 */
class Payments_Square_Hub {

	/** WC Payments/Checkout tab name. */
	const CHECKOUT_TAB = 'checkout';

	/** Section ID for the Square hub within the Checkout tab. */
	const SECTION = 'square'; // matches Plugin::PLUGIN_ID

	/** Query arg used to identify the active sub-tab within the Square hub. */
	const TAB_ARG = 'square-tab';

	const TAB_GENERAL               = 'general';
	const TAB_PAYMENT_METHODS       = 'payment-methods';
	const TAB_PAYMENTS_TRANSACTIONS = 'payments-transactions';
	const TAB_SYNCHRONIZE           = 'synchronize';

	/**
	 * Initialises hooks. Only called when the modern-settings path is active.
	 *
	 * @since x.x.x
	 */
	public static function init(): void {
		add_action( 'woocommerce_settings_sections_registration', array( self::class, 'register_settings_section' ) );
		add_action( 'admin_init', array( self::class, 'redirect_legacy_square_settings_tab' ) );
	}

	/**
	 * Registers the Square hub section via SettingsSectionRegistry.
	 *
	 * @since x.x.x
	 *
	 * @param \Automattic\WooCommerce\Admin\Settings\SettingsSectionRegistry $registry Registry instance.
	 */
	public static function register_settings_section( \Automattic\WooCommerce\Admin\Settings\SettingsSectionRegistry $registry ): void {
		$registry->register( new Square_Settings_Section() );
	}

	/** Gateway section IDs that should redirect to the Square hub. */
	const LEGACY_GATEWAY_SECTIONS = array(
		'square_credit_card',
		'square_cash_app_pay',
		'gift_cards_pay',
	);

	/**
	 * Redirects legacy Square URLs to the hub at ?tab=checkout&section=square.
	 *
	 * Handles two cases:
	 *   - ?tab=square (old dedicated Square tab)
	 *   - ?tab=checkout&section=<gateway_id> (payments-list deep-links for Square gateways)
	 *
	 * @since x.x.x
	 */
	public static function redirect_legacy_square_settings_tab(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['page'], $_GET['tab'] ) || 'wc-settings' !== $_GET['page'] ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = sanitize_key( wp_unslash( $_GET['tab'] ) );

		if ( Plugin::PLUGIN_ID === $tab ) {
			wp_safe_redirect( self::get_hub_url() );
			exit;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$section = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : '';

		if ( self::CHECKOUT_TAB === $tab && in_array( $section, self::LEGACY_GATEWAY_SECTIONS, true ) ) {
			wp_safe_redirect( self::get_hub_url() );
			exit;
		}
	}

	/**
	 * Redirects modern Square hub URLs to the legacy Square settings tab.
	 *
	 * Only hooked when the modern-settings path is inactive (WC < 11.0 or the
	 * feature flag is off). In that state the hub section is not registered, so
	 * ?tab=checkout&section=square renders an empty screen. Any such request
	 * (including specific square-tab sub-tabs) is sent to the legacy Square tab.
	 *
	 * @since x.x.x
	 */
	public static function redirect_modern_hub_to_legacy(): void {
		if ( ! self::is_square_payments_hub() ) {
			return;
		}

		// nosemgrep audit.php.wp.security.xss.query-arg
		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => 'wc-settings',
					'tab'  => Plugin::PLUGIN_ID,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Returns the URL for the Square hub (or a specific sub-tab).
	 *
	 * @since x.x.x
	 *
	 * @param string $tab Optional sub-tab ID. Defaults to the General tab.
	 * @return string
	 */
	public static function get_hub_url( string $tab = '' ): string {
		$params = array(
			'page'    => 'wc-settings',
			'tab'     => self::CHECKOUT_TAB,
			'section' => self::SECTION,
		);

		if ( '' !== $tab ) {
			$params[ self::TAB_ARG ] = $tab;
		}

		// nosemgrep audit.php.wp.security.xss.query-arg
		return add_query_arg( $params, admin_url( 'admin.php' ) );
	}

	/**
	 * Returns the currently active sub-tab within the Square hub.
	 *
	 * @since x.x.x
	 *
	 * @return string One of the TAB_* constants. Defaults to TAB_GENERAL.
	 */
	public static function get_active_tab(): string {
		$valid = array(
			self::TAB_GENERAL,
			self::TAB_PAYMENT_METHODS,
			self::TAB_PAYMENTS_TRANSACTIONS,
			self::TAB_SYNCHRONIZE,
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = isset( $_GET[ self::TAB_ARG ] ) ? sanitize_key( wp_unslash( $_GET[ self::TAB_ARG ] ) ) : self::TAB_GENERAL;

		return in_array( $tab, $valid, true ) ? $tab : self::TAB_GENERAL;
	}

	/**
	 * Returns true when currently viewing the Square payments hub.
	 *
	 * @since x.x.x
	 *
	 * @return bool
	 */
	public static function is_square_payments_hub(): bool {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		return isset( $_GET['page'], $_GET['tab'], $_GET['section'] )
			&& 'wc-settings' === $_GET['page']
			&& self::CHECKOUT_TAB === $_GET['tab']
			&& self::SECTION === $_GET['section'];
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}
}
