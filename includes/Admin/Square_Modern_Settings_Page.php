<?php
/**
 * WC_Settings_Page subclass for the Square hub — registered when the
 * modern-settings feature flag is ON, placing Square as its own top-level
 * WooCommerce settings tab (`?tab=square`).
 *
 * @package WooCommerce\Square\Admin
 */

namespace WooCommerce\Square\Admin;

use Automattic\WooCommerce\Admin\Settings\ModernSettingsPageInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Square modern settings page registered via woocommerce_get_settings_pages.
 *
 * @since x.x.x
 */
class Square_Modern_Settings_Page extends \WC_Settings_Page {

	/**
	 * Sets up the page id and label, then delegates to WC_Settings_Page.
	 *
	 * @since x.x.x
	 */
	public function __construct() {
		$this->id    = 'square';
		$this->label = __( 'Square', 'woocommerce-square' );
		parent::__construct();
	}

	/**
	 * Returns the four inner tabs as WC sections.
	 *
	 * @since x.x.x
	 * @return string[]
	 */
	public function get_sections() {
		return array(
			''                      => __( 'General', 'woocommerce-square' ),
			'payment-methods'       => __( 'Payment methods', 'woocommerce-square' ),
			'payments-transactions' => __( 'Payments & Transactions', 'woocommerce-square' ),
			'synchronize'           => __( 'Synchronize Square', 'woocommerce-square' ),
		);
	}

	/**
	 * Returns field declarations consumed by ModernSettingsSchema.
	 *
	 * Each tab uses a single autonomous-component field (`is_option => false`,
	 * `component => 'square/<tab>'`). The JS component ignores the SDK's
	 * onChange/value contract and manages its own REST save flow, so the
	 * SDK Save button is always disabled for our tabs.
	 *
	 * @since x.x.x
	 * @param string $section Section slug.
	 * @return array
	 */
	public function get_settings( $section = '' ) {
		switch ( $section ) {
			case 'payment-methods':
				return array(
					array(
						'type'  => 'title',
						'id'    => 'square_payment_methods_title',
						'title' => '',
					),
					array(
						'id'        => 'square_payment_methods_ui',
						'type'      => 'text',
						'title'     => '',
						'component' => 'square/payment-methods',
						'is_option' => false,
						'value'     => '',
					),
					array(
						'type' => 'sectionend',
						'id'   => 'square_payment_methods_title',
					),
				);

			case 'payments-transactions':
				return array(
					array(
						'type'  => 'title',
						'id'    => 'square_payments_transactions_title',
						'title' => '',
					),
					array(
						'id'        => 'square_payments_transactions_ui',
						'type'      => 'text',
						'title'     => '',
						'component' => 'square/payments-transactions',
						'is_option' => false,
						'value'     => '',
					),
					array(
						'type' => 'sectionend',
						'id'   => 'square_payments_transactions_title',
					),
				);

			case 'synchronize':
				return array(); // Ticket 5 scope.

			default: // General.
				return array(
					array(
						'type'  => 'title',
						'id'    => 'square_general_title',
						'title' => '',
					),
					array(
						'id'        => 'square_general_ui',
						'type'      => 'text',
						'title'     => '',
						'component' => 'square/general',
						'is_option' => false,
						'value'     => '',
					),
					array(
						'type' => 'sectionend',
						'id'   => 'square_general_title',
					),
				);
		}
	}

	/**
	 * Returns the modern settings adapter for this page.
	 *
	 * @since x.x.x
	 * @return ModernSettingsPageInterface|null
	 */
	public function get_modern_settings_page(): ?ModernSettingsPageInterface {
		return new Square_Modern_Settings_Adapter( $this );
	}

	/**
	 * No-op: all saves are handled by our existing REST controllers.
	 *
	 * @since x.x.x
	 * @return void
	 */
	public function save() {
		// Intentional no-op — REST controllers own persistence.
	}
}
