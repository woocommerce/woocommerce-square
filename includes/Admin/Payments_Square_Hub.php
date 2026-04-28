<?php
/**
 * Square settings hub for the consolidated admin redesign (Payments > Square).
 *
 * @package WooCommerce\Square\Admin
 */

namespace WooCommerce\Square\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the consolidated Square settings section on the Payments settings screen.
 *
 * @since x.x.x
 */
final class Payments_Square_Hub {

	/** Section ID for this hub (WooCommerce > Settings > Payments > Square). */
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
	 * @since x.x.x
	 * @return void
	 */
	public static function init() {
		add_filter( 'woocommerce_get_sections_checkout', array( __CLASS__, 'add_square_section' ), 30 );
		add_action( 'woocommerce_settings_checkout', array( __CLASS__, 'render_hub' ), 5 );
		add_action( 'admin_init', array( __CLASS__, 'redirect_legacy_square_settings_tab' ) );
		add_action( 'admin_init', array( __CLASS__, 'redirect_standalone_gateway_sections' ) );
		add_filter( 'woocommerce_get_sections_checkout', array( __CLASS__, 'remove_standalone_gateway_sections' ), 99 );
	}

	/**
	 * Adds the Square section link to the Payments settings sub-navigation.
	 *
	 * @since x.x.x
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
	 * Redirects legacy WooCommerce > Settings > Square (tab) URLs to the Payments hub.
	 *
	 * @since x.x.x
	 * @return void
	 */
	public static function redirect_legacy_square_settings_tab() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
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
	 * Removes standalone Cash App Pay and Gift Cards sections from the Payments nav.
	 *
	 * Those gateways are now consolidated under the Payment Methods tab.
	 *
	 * @since x.x.x
	 * @param array $sections Section ID => label.
	 * @return array
	 */
	public static function remove_standalone_gateway_sections( $sections ) {
		unset( $sections['square_cash_app_pay'], $sections['gift_cards_pay'] );
		return $sections;
	}

	/**
	 * Redirects direct URL access to the removed standalone gateway sections.
	 *
	 * @since x.x.x
	 * @return void
	 */
	public static function redirect_standalone_gateway_sections() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing.
		if ( ! isset( $_GET['page'], $_GET['tab'] ) || 'wc-settings' !== $_GET['page'] || 'checkout' !== $_GET['tab'] ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$section = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : '';

		if ( 'square_cash_app_pay' === $section || 'gift_cards_pay' === $section ) {
			wp_safe_redirect( self::get_hub_url( self::TAB_PAYMENT_METHODS ) );
			exit;
		}
	}

	/**
	 * Builds the admin URL for the Square hub.
	 *
	 * @since x.x.x
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
	 * @since x.x.x
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
	 * @since x.x.x
	 * @return void
	 */
	public static function render_hub() {
		global $current_section;

		if ( self::SECTION_ID !== $current_section ) {
			return;
		}

		$GLOBALS['hide_save_button'] = true;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tab navigation only.
		$current_tab = isset( $_GET[ self::TAB_QUERY_VAR ] )
			? sanitize_key( wp_unslash( $_GET[ self::TAB_QUERY_VAR ] ) )
			: self::TAB_GENERAL;

		$tabs = array(
			self::TAB_GENERAL               => __( 'General', 'woocommerce-square' ),
			self::TAB_PAYMENT_METHODS       => __( 'Payment methods', 'woocommerce-square' ),
			self::TAB_PAYMENTS_TRANSACTIONS => __( 'Payments & Transactions', 'woocommerce-square' ),
			self::TAB_SYNCHRONIZE           => __( 'Synchronize Square', 'woocommerce-square' ),
		);

		if ( ! array_key_exists( $current_tab, $tabs ) ) {
			$current_tab = self::TAB_GENERAL;
		}

		$payments_screen_url = self::get_payments_screen_url();

		/** This filter is documented in woocommerce/includes/admin/class-wc-admin-settings.php */
		$all_tabs         = apply_filters( 'woocommerce_settings_tabs_array', array() );
		$parent_tab_label = isset( $all_tabs['checkout'] ) ? $all_tabs['checkout'] : __( 'Payments', 'woocommerce-square' );

		/** This filter is documented in woocommerce/includes/admin/class-wc-admin-settings.php */
		$all_sections  = apply_filters( 'woocommerce_get_sections_checkout', array() );
		$section_label = isset( $all_sections[ self::SECTION_ID ] ) ? $all_sections[ self::SECTION_ID ] : __( 'Square', 'woocommerce-square' );

		include __DIR__ . '/views/html-payments-square-hub.php';
	}
}
