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

if ( class_exists( '\Automattic\WooCommerce\Admin\Settings\SettingsSection' ) ) {

	/**
	 * Registers the Square hub as a native settings section (WC 11.0+).
	 *
	 * Returned from SettingsSectionRegistry when get_settings_ui_page() is available
	 * (WooCommerce Core PR #65975, milestone 11.0.0). On WC 10.9 the subclass path in
	 * Payments_Square_Hub is used instead and this class is never instantiated.
	 *
	 * @since x.x.x
	 */
	class Square_Settings_Section extends \Automattic\WooCommerce\Admin\Settings\SettingsSection {

		/**
		 * {@inheritDoc}
		 */
		public function get_parent_page_id(): string {
			return Payments_Square_Hub::CHECKOUT_TAB;
		}

		/**
		 * {@inheritDoc}
		 */
		public function get_id(): string {
			return Payments_Square_Hub::SECTION;
		}

		/**
		 * {@inheritDoc}
		 */
		public function get_label(): string {
			return __( 'Square', 'woocommerce-square' );
		}

		/**
		 * {@inheritDoc}
		 *
		 * Not used on the native path — schema is provided by Square_Modern_Settings_Page.
		 */
		public function get_settings( \WC_Settings_Page $parent_page ): array {
			return array();
		}

		/**
		 * {@inheritDoc}
		 *
		 * Returns the Square hub adapter so WC Core uses it directly instead of the
		 * legacy RegisteredSettingsSectionAdapter / from_legacy_settings() path.
		 */
		public function get_settings_ui_page( \WC_Settings_Page $parent_page ): ?\Automattic\WooCommerce\Admin\Settings\SettingsUIPageInterface {
			return new Square_Modern_Settings_Page( $parent_page );
		}
	}
}
