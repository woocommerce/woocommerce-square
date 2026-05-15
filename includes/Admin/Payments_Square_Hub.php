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
 * When the modern-settings path is active, this class:
 *   - Extends the WooCommerce Payments/Checkout settings tab to include a Square section.
 *   - Provides get_modern_settings_page() so the SDK renders the 4-tab Square hub.
 *   - Redirects the legacy ?tab=square URL to the new hub location.
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
		add_filter( 'woocommerce_get_settings_pages', array( self::class, 'add_square_hub_to_checkout_page' ), 20 );
		add_action( 'admin_init', array( self::class, 'redirect_legacy_square_settings_tab' ) );
	}

	/**
	 * Replaces the WC Payments settings page instance with a Square-hub-aware subclass.
	 *
	 * @since x.x.x
	 *
	 * @param array $pages Settings pages returned by the woocommerce_get_settings_pages filter.
	 * @return array
	 */
	public static function add_square_hub_to_checkout_page( array $pages ): array {
		if ( ! class_exists( 'WC_Settings_Payment_Gateways' ) ) {
			return $pages;
		}

		foreach ( $pages as $index => $page ) {
			if ( ! ( $page instanceof \WC_Settings_Payment_Gateways ) ) {
				continue;
			}

			self::remove_checkout_page_hooks( $page );
			$pages[ $index ] = self::create_checkout_page_with_square_hub();
			break;
		}

		return $pages;
	}

	/**
	 * Removes all hooks registered by the original WC_Settings_Payment_Gateways instance.
	 *
	 * @since x.x.x
	 *
	 * @param \WC_Settings_Payment_Gateways $page Original checkout settings page.
	 */
	private static function remove_checkout_page_hooks( \WC_Settings_Payment_Gateways $page ): void {
		remove_filter( 'woocommerce_settings_tabs_array', array( $page, 'add_settings_page' ), 20 );
		remove_action( 'woocommerce_sections_' . $page->get_id(), array( $page, 'output_sections' ) );
		remove_action( 'woocommerce_settings_' . $page->get_id(), array( $page, 'output' ) );
		remove_action( 'woocommerce_settings_save_' . $page->get_id(), array( $page, 'save' ) );
		remove_action( 'woocommerce_admin_field_add_settings_slot', array( $page, 'add_settings_slot' ) );
		remove_filter( 'admin_body_class', array( $page, 'add_modern_settings_body_class' ) );
		remove_filter( 'admin_body_class', array( $page, 'add_body_classes' ), 30 );
		remove_action( 'admin_head', array( $page, 'hide_help_tabs' ) );
		remove_action( 'in_admin_header', array( $page, 'suppress_admin_notices' ), PHP_INT_MAX );
		remove_filter( 'woocommerce_admin_features', array( $page, 'suppress_store_alerts' ), PHP_INT_MAX );
	}

	/**
	 * Creates a WC_Settings_Payment_Gateways subclass that adds the Square hub section.
	 *
	 * @since x.x.x
	 *
	 * @return \WC_Settings_Payment_Gateways
	 */
	private static function create_checkout_page_with_square_hub(): \WC_Settings_Payment_Gateways {
		return new class() extends \WC_Settings_Payment_Gateways {

			/**
			 * {@inheritDoc}
			 *
			 * Appends the Square section to the standard Payments/Checkout sections.
			 */
			public function get_sections(): array {
				return parent::get_sections() + array( Payments_Square_Hub::SECTION => __( 'Square', 'woocommerce-square' ) );
			}

			/**
			 * {@inheritDoc}
			 *
			 * Returns the Square modern settings adapter when the Square section is active.
			 */
			public function get_modern_settings_page(): ?\Automattic\WooCommerce\Admin\Settings\ModernSettingsPageInterface {
				if ( ! $this->is_square_section() ) {
					return null;
				}

				return new Square_Modern_Settings_Page( $this );
			}

			/**
			 * Returns true when ?tab=checkout&section=square is active.
			 */
			private function is_square_section(): bool {
				global $current_tab, $current_section;

				return Payments_Square_Hub::CHECKOUT_TAB === $current_tab
					&& Payments_Square_Hub::SECTION === $current_section;
			}
		};
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
