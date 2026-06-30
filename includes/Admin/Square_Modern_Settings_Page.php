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
			// Only advertise the handle when its build artifact exists; otherwise
			// the SDK would enqueue an unregistered handle (Admin.php registers it
			// only when build/square-settings.asset.php is present), and WordPress
			// could print a broken <script src=""> tag.
			if ( ! file_exists( WC_SQUARE_PLUGIN_PATH . 'build/square-settings.asset.php' ) ) {
				return array();
			}

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
			$settings = (array) get_option( Rest\WC_REST_Square_Settings_Controller::SQUARE_GATEWAY_SETTINGS_OPTION_NAME, array() );

			$is_sandbox          = ! empty( $settings['enable_sandbox'] ) && wc_string_to_bool( $settings['enable_sandbox'] );
			$current_location_id = $is_sandbox
				? ( $settings['sandbox_location_id'] ?? '' )
				: ( $settings['production_location_id'] ?? '' );

			$groups = array(
				'square_connect_section' => array(
					'id'          => 'square_connect_section',
					'title'       => __( 'Connect to Square', 'woocommerce-square' ),
					'description' => __( 'Choose between connecting to a live production account for real transactions or a sandbox account for testing purposes.', 'woocommerce-square' ),
					'actions'     => array(),
					'order'       => 0,
					'fields'      => array(
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
			);

			// Only show the Business location section once connected; there are no
			// locations to choose before connecting.
			if ( wc_square()->get_settings_handler()->is_connected() ) {
				$groups['square_location_section'] = array(
					'id'          => 'square_location_section',
					'title'       => __( 'Business location', 'woocommerce-square' ),
					'description' => sprintf(
						/* translators: %1$s opening anchor tag, %2$s closing anchor tag */
						__( 'Select the Square location you wish to link with this WooCommerce store. %1$sLearn more about active locations ↗%2$s', 'woocommerce-square' ),
						'<a href="https://squareup.com/help/us/en/article/5593-locations" target="_blank" rel="noopener">',
						'</a>'
					),
					'actions'     => array(),
					'order'       => 1,
					'fields'      => array(
						array(
							'id'      => 'location_id',
							'label'   => '',
							'type'    => 'select',
							'value'   => $current_location_id,
							'options' => $this->get_location_options(),
						),
					),
				);
			}

			return $groups;
		}

		/**
		 * Builds the Business location select options from the connected account.
		 *
		 * Fetches a fresh list for the currently connected environment and filters
		 * to ACTIVE locations that can process credit cards, matching the legacy
		 * settings page (see Settings::add_location_settings_field()). The list is
		 * rendered server-side so it always reflects the saved environment after a
		 * save + reload, mirroring legacy behaviour.
		 *
		 * @since x.x.x
		 *
		 * @return array<int, array{label: string, value: string}>
		 */
		private function get_location_options(): array {
			$options = array(
				array(
					'label' => __( 'Please choose a location', 'woocommerce-square' ),
					'value' => '',
				),
			);

			$locations = wc_square()->get_settings_handler()->get_locations( true );

			if ( empty( $locations ) ) {
				return $options;
			}

			foreach ( $locations as $location ) {
				if ( 'ACTIVE' === $location->getStatus() && in_array( 'CREDIT_CARD_PROCESSING', (array) $location->getCapabilities(), true ) ) {
					$options[] = array(
						'label' => $location->getName(),
						'value' => $location->getId(),
					);
				}
			}

			return $options;
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
