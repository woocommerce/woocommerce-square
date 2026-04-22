<?php
/**
 * WooCommerce Square
 *
 * Square settings hub under WooCommerce > Settings > Payments (checkout tab).
 *
 * @author    WooCommerce
 * @copyright Copyright: (c) 2026, Automattic, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0 or later
 */

namespace WooCommerce\Square\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the consolidated Square settings section on the Payments settings screen.
 *
 * @since 5.4.0
 */
final class Payments_Square_Hub {

	/** Checkout section ID ( WooCommerce &rarr; Settings &rarr; Payments &rarr; Square ). */
	public const SECTION_ID = 'square';

	/** Query argument for inner tab navigation. */
	public const TAB_QUERY_VAR = 'square_tab';

	/** Inner tab: General. */
	public const TAB_GENERAL = 'general';

	/** Inner tab: Payment Methods. */
	public const TAB_PAYMENT_METHODS = 'payment-methods';

	/** Inner tab: Payments & Transactions. */
	public const TAB_PAYMENTS_TRANSACTIONS = 'payments-transactions';

	/** Inner tab: Synchronize Square. */
	public const TAB_SYNCHRONIZE = 'synchronize';

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'woocommerce_get_sections_checkout', array( __CLASS__, 'add_square_section' ), 30 );
		add_action( 'woocommerce_settings_checkout', array( __CLASS__, 'render_hub' ), 5 );
		add_action( 'admin_init', array( __CLASS__, 'redirect_legacy_square_settings_tab' ) );
	}

	/**
	 * Adds the Square section link to the Payments (checkout) settings sub-navigation.
	 *
	 * @param array $sections Section ID => label.
	 * @return array
	 */
	public static function add_square_section( $sections ) {
		if ( ! is_array( $sections ) ) {
			$sections = array();
		}
		$sections[ self::SECTION_ID ] = __( 'Square', 'woocommerce-square' );
		return $sections;
	}

	/**
	 * Redirects legacy WooCommerce &rarr; Settings &rarr; Square (tab) URLs to the Payments hub.
	 *
	 * @return void
	 */
	public static function redirect_legacy_square_settings_tab() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing.
		if ( ! isset( $_GET['page'], $_GET['tab'] ) || 'wc-settings' !== $_GET['page'] || 'square' !== $_GET['tab'] ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$legacy_section = isset( $_GET['section'] ) ? sanitize_text_field( wp_unslash( $_GET['section'] ) ) : '';

		$tab = self::TAB_GENERAL;
		if ( 'update' === $legacy_section ) {
			$tab = self::TAB_SYNCHRONIZE;
		}

		wp_safe_redirect( self::get_hub_url( $tab ) );
		exit;
	}

	/**
	 * Builds the admin URL for the Square hub.
	 *
	 * @param string $tab Inner tab slug (see TAB_* constants).
	 * @return string
	 */
	public static function get_hub_url( $tab = self::TAB_GENERAL ) {
		$args = array(
			'page'    => 'wc-settings',
			'tab'     => 'checkout',
			'section' => self::SECTION_ID,
		);

		if ( self::TAB_GENERAL !== $tab ) {
			$args[ self::TAB_QUERY_VAR ] = $tab;
		}

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * URL for the main WooCommerce Payments settings screen (breadcrumb parent).
	 *
	 * @return string
	 */
	public static function get_payments_screen_url() {
		return add_query_arg(
			array(
				'page' => 'wc-settings',
				'tab'  => 'checkout',
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Renders the hub shell (inner tabs + placeholder content).
	 *
	 * @return void
	 */
	public static function render_hub() {
		global $current_section;

		if ( self::SECTION_ID !== $current_section ) {
			return;
		}

		// Figma: primary Save is in the hub header; hide the default WC settings footer button until forms exist.
		$GLOBALS['hide_save_button'] = true;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tab navigation only.
		$current_tab = isset( $_GET[ self::TAB_QUERY_VAR ] )
			? sanitize_key( wp_unslash( $_GET[ self::TAB_QUERY_VAR ] ) )
			: self::TAB_GENERAL;

		// Labels match REDESIGN/FIGMA (--1.png, --2.png).
		$tabs = array(
			self::TAB_GENERAL               => __( 'General', 'woocommerce-square' ),
			self::TAB_PAYMENT_METHODS       => __( 'Payment methods', 'woocommerce-square' ),
			self::TAB_PAYMENTS_TRANSACTIONS => __( 'Payments & Transactions', 'woocommerce-square' ),
			self::TAB_SYNCHRONIZE           => __( 'Synchronize Square', 'woocommerce-square' ),
		);

		if ( ! array_key_exists( $current_tab, $tabs ) ) {
			$current_tab = self::TAB_GENERAL;
		}

		echo '<div class="wc-square-settings-hub">';

		echo '<div class="wc-square-settings-hub__header">';
		echo '<p class="wc-square-settings-hub__breadcrumb">';
		echo '<a href="' . esc_url( self::get_payments_screen_url() ) . '">' . esc_html__( 'Payments', 'woocommerce-square' ) . '</a>';
		echo '<span class="wc-square-settings-hub__sep">/</span>';
		echo '<span class="wc-square-settings-hub__current">' . esc_html__( 'Square settings', 'woocommerce-square' ) . '</span>';
		echo '</p>';
		if ( self::TAB_GENERAL === $current_tab ) {
			// Figma (--1.png): primary Save in header, label "Save", wired by settings.js.
			echo '<button type="button" class="button button-primary wc-square-settings-hub__save" id="wc-square-settings-hub__save-general">';
			echo esc_html__( 'Save', 'woocommerce-square' );
			echo '</button>';
		} else {
			echo '<button type="button" class="button button-primary wc-square-settings-hub__save" disabled aria-disabled="true" title="' . esc_attr__( 'Saving will be available when settings are added to this screen.', 'woocommerce-square' ) . '">';
			echo esc_html__( 'Save', 'woocommerce-square' );
			echo '</button>';
		}
		echo '</div>';

		echo '<nav class="wc-square-settings-hub__tablist" aria-label="' . esc_attr__( 'Square settings sections', 'woocommerce-square' ) . '">';
		foreach ( $tabs as $id => $label ) {
			$url     = self::get_hub_url( $id );
			$classes = 'wc-square-settings-hub__tab' . ( $current_tab === $id ? ' is-active' : '' );
			echo '<a href="' . esc_url( $url ) . '" class="' . esc_attr( $classes ) . '"';
			if ( $current_tab === $id ) {
				echo ' aria-current="page"';
			}
			echo '>';
			echo esc_html( $label );
			echo '</a>';
		}
		echo '</nav>';

		$panel_classes = 'wc-square-settings-hub__panel';
		if ( self::TAB_GENERAL === $current_tab ) {
			$panel_classes .= ' wc-square-settings-hub__panel--general';
		}
		echo '<div class="' . esc_attr( $panel_classes ) . '">';
		if ( self::TAB_GENERAL === $current_tab ) {
			echo '<div id="woocommerce-square-settings__container-general"></div>';
		} else {
			echo '<p class="description">' . esc_html__( 'Settings content for this tab will be added in a future release.', 'woocommerce-square' ) . '</p>';
		}
		echo '</div>';

		echo '</div>';
	}
}
