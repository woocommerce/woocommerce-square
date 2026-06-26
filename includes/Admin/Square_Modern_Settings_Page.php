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
		 * @param string $section WC section ID (unused; Square sub-tab is read from
		 *                        the square-tab query arg instead).
		 */
		public function get_schema( string $section ): array {
			$save_adapter = $this->get_save_adapter( $section );

			return array(
				'id'      => $this->get_page_id(),
				'title'   => __( 'Square', 'woocommerce-square' ),
				'section' => Payments_Square_Hub::get_active_tab(),
				'shell'   => array(
					'title'             => __( 'Square', 'woocommerce-square' ),
					'sectionNavigation' => $this->get_square_tab_navigation(),
				),
				'groups'  => $this->get_tab_groups(),
				'save'    => array(
					'adapter' => $save_adapter,
					'handler' => 'square/save',
				),
			);
		}

		/**
		 * {@inheritDoc}
		 *
		 * @param string $section Unused.
		 */
		public function get_script_handles( string $section ): array {
			return array( 'woocommerce-square-settings' );
		}

		/**
		 * {@inheritDoc}
		 *
		 * Returns 'custom' for tabs that have editable fields, 'none' otherwise.
		 *
		 * @param string $section Unused.
		 */
		public function get_save_adapter( string $section ): string {
			return Payments_Square_Hub::TAB_GENERAL === Payments_Square_Hub::get_active_tab()
				? 'custom'
				: 'none';
		}

		/**
		 * Returns the groups array for the currently active Square tab.
		 *
		 * @since x.x.x
		 *
		 * @return array<string, array>
		 */
		private function get_tab_groups(): array {
			switch ( Payments_Square_Hub::get_active_tab() ) {
				case Payments_Square_Hub::TAB_GENERAL:
					return $this->get_general_tab_groups();
				default:
					return array();
			}
		}

		/**
		 * Returns the field groups for the General tab.
		 *
		 * @since x.x.x
		 *
		 * @return array<string, array>
		 */
		private function get_general_tab_groups(): array {
			$settings = (array) get_option( 'wc_square_settings', array() );

			$is_sandbox          = ! empty( $settings['enable_sandbox'] ) && wc_string_to_bool( $settings['enable_sandbox'] );
			$current_location_id = $is_sandbox
				? ( $settings['sandbox_location_id'] ?? '' )
				: ( $settings['production_location_id'] ?? '' );

			return array(
				'square_connect_section'  => array(
					'id'          => 'square_connect_section',
					'title'       => '',
					'description' => '',
					'actions'     => array(),
					'order'       => 0,
					'fields'      => array(
						array(
							'id'          => 'square_connect_header',
							'type'        => 'text',
							'component'   => 'square/section-header',
							'is_option'   => false,
							'label'       => __( 'Connect to Square', 'woocommerce-square' ),
							'description' => __( 'Choose between connecting to a live production account for real transactions or a sandbox account for testing purposes.', 'woocommerce-square' ),
							'value'       => '',
						),
						array(
							'id'          => 'enable_sandbox',
							'label'       => __( 'Environment Selection', 'woocommerce-square' ),
							'type'        => 'text',
							'component'   => 'square/environment-selector',
							'description' => '',
							'value'       => ! empty( $settings['enable_sandbox'] ) && wc_string_to_bool( $settings['enable_sandbox'] ) ? 'yes' : 'no',
						),
						array(
							'id'          => 'sandbox_application_id',
							'label'       => __( 'Sandbox Application ID', 'woocommerce-square' ),
							'type'        => 'text',
							'description' => sprintf(
								/* translators: %1$s opening anchor tag, %2$s closing anchor tag */
								__( 'Application ID for the sandbox Application. View details in %1$sMy Applications ↗%2$s', 'woocommerce-square' ),
								'<a href="https://developer.squareup.com/apps" target="_blank" rel="noopener">',
								'</a>'
							),
							'value'       => $settings['sandbox_application_id'] ?? '',
						),
						array(
							'id'          => 'sandbox_token',
							'label'       => __( 'Sandbox Access Token', 'woocommerce-square' ),
							'type'        => 'text',
							'description' => sprintf(
								/* translators: %1$s opening anchor tag, %2$s closing anchor tag */
								__( 'Access Token for the Sandbox Test Account. See the details in the %1$sSandbox Test Account ↗%2$s section. Make sure you use the correct Sandbox Access Token for your application — each Authorized Application is assigned a different Access Token.', 'woocommerce-square' ),
								'<a href="https://developer.squareup.com/docs/devtools/sandbox/overview" target="_blank" rel="noopener">',
								'</a>'
							),
							'value'       => $settings['sandbox_token'] ?? '',
						),
						array(
							'id'          => 'square_connect_divider',
							'is_option'   => false,
							'label'       => '',
							'type'        => 'text',
							'component'   => 'square/divider',
							'value'       => '',
							'description' => '',
							'save'        => array( 'adapter' => 'none' ),
						),
						array(
							'id'          => 'square_oauth_connect',
							'is_option'   => false,
							'label'       => '',
							'type'        => 'text',
							'component'   => 'square/oauth-connect',
							'value'       => '',
							'description' => '',
							'save'        => array( 'adapter' => 'none' ),
						),
					),
				),
				'square_location_section' => array(
					'id'          => 'square_location_section',
					'title'       => '',
					'description' => '',
					'actions'     => array(),
					'order'       => 1,
					'fields'      => array(
						array(
							'id'          => 'square_location_header',
							'type'        => 'text',
							'component'   => 'square/section-header',
							'is_option'   => false,
							'label'       => __( 'Business location', 'woocommerce-square' ),
							'description' => sprintf(
								/* translators: %1$s opening anchor tag, %2$s closing anchor tag */
								__( 'Select the Square location you wish to link with this WooCommerce store. %1$sLearn more about active locations ↗%2$s', 'woocommerce-square' ),
								'<a href="https://squareup.com/help/us/en/article/5593-locations" target="_blank" rel="noopener">',
								'</a>'
							),
							'value'       => '',
						),
						array(
							'id'          => 'location_id',
							'label'       => '',
							'type'        => 'text',
							'component'   => 'square/location-picker',
							'value'       => $current_location_id,
							'description' => '',
						),
					),
				),
			);
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
				Payments_Square_Hub::TAB_GENERAL         => __( 'General', 'woocommerce-square' ),
				Payments_Square_Hub::TAB_PAYMENT_METHODS => __( 'Payment methods', 'woocommerce-square' ),
				Payments_Square_Hub::TAB_PAYMENTS_TRANSACTIONS => __( 'Payments & Transactions', 'woocommerce-square' ),
				Payments_Square_Hub::TAB_SYNCHRONIZE     => __( 'Synchronize Square', 'woocommerce-square' ),
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
