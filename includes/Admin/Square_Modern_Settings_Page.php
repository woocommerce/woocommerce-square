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

if ( class_exists( '\Automattic\WooCommerce\Admin\Settings\LegacySettingsPageAdapter' ) ) {

	/**
	 * Adapts the Square settings for the WooCommerce modern-settings SDK.
	 *
	 * Provides the schema used by the SDK to render the 4-tab Square hub:
	 *   General | Payment Methods | Payments & Transactions | Synchronize Square
	 *
	 * Tickets 2-5 add field groups to each tab. This Ticket 1 implementation
	 * registers the shell with empty groups so the tab structure is navigable.
	 *
	 * @since x.x.x
	 */
	class Square_Modern_Settings_Page extends \Automattic\WooCommerce\Admin\Settings\LegacySettingsPageAdapter {

		/**
		 * {@inheritDoc}
		 *
		 * Returns 'square' so the schema id matches the JS registerSettingsExtension
		 * scope.page value. Must NOT return the WC tab id ('checkout').
		 */
		public function get_page_id(): string {
			return Payments_Square_Hub::SECTION;
		}

		/**
		 * {@inheritDoc}
		 *
		 * Returns the schema for the Square hub. The groups array is intentionally
		 * empty in Ticket 1; field groups are added in Tickets 2–5.
		 *
		 * @param string $section WC section ID (unused; Square sub-tab is read from
		 *                        the square-tab query arg instead).
		 */
		public function get_schema( string $section ): array {
			return array(
				'id'      => $this->get_page_id(),
				'title'   => __( 'Square', 'woocommerce-square' ),
				'section' => Payments_Square_Hub::SECTION,
				'shell'   => array(
					'title'             => __( 'Square', 'woocommerce-square' ),
					'sectionNavigation' => $this->get_square_tab_navigation(),
				),
				'groups'  => array(),
				'save'    => array( 'adapter' => $this->get_save_adapter( $section ) ),
			);
		}

		/**
		 * {@inheritDoc}
		 *
		 * No JS bundle in Ticket 1 (empty shell, no fields). Ticket 2 adds the
		 * woocommerce-square-settings handle once the webpack bundle exists.
		 *
		 * @param string $section Unused.
		 */
		public function get_script_handles( string $section ): array {
			return array();
		}

		/**
		 * {@inheritDoc}
		 *
		 * Returns 'none' for Ticket 1 since no fields are present yet.
		 * Subsequent tickets switch applicable groups to 'custom' with
		 * the square/save handler routing to the existing REST endpoints.
		 *
		 * @param string $section Unused.
		 */
		public function get_save_adapter( string $section ): string {
			return 'none';
		}

		/**
		 * Builds the sectionNavigation array for the 4 Square hub sub-tabs.
		 *
		 * @since x.x.x
		 *
		 * @return array<int, array{id: string, label: string, href: string, active: bool}>
		 */
		private function get_square_tab_navigation(): array {
			$active_tab = Payments_Square_Hub::get_active_tab();
			$base_url   = Payments_Square_Hub::get_hub_url();

			$tabs = array(
				Payments_Square_Hub::TAB_GENERAL               => __( 'General', 'woocommerce-square' ),
				Payments_Square_Hub::TAB_PAYMENT_METHODS       => __( 'Payment Methods', 'woocommerce-square' ),
				Payments_Square_Hub::TAB_PAYMENTS_TRANSACTIONS => __( 'Payments & Transactions', 'woocommerce-square' ),
				Payments_Square_Hub::TAB_SYNCHRONIZE           => __( 'Synchronize Square', 'woocommerce-square' ),
			);

			$nav = array();
			foreach ( $tabs as $id => $label ) {
				$nav[] = array(
					'id'     => $id,
					'label'  => $label,
					'href'   => add_query_arg( Payments_Square_Hub::TAB_ARG, $id, $base_url ),
					'active' => $active_tab === $id,
				);
			}

			return $nav;
		}
	}
}
