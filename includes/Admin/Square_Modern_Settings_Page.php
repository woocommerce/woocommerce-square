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
			$editable_tabs = array(
				Payments_Square_Hub::TAB_GENERAL,
				Payments_Square_Hub::TAB_PAYMENT_METHODS,
			);

			return in_array( Payments_Square_Hub::get_active_tab(), $editable_tabs, true )
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
				case Payments_Square_Hub::TAB_PAYMENT_METHODS:
					return $this->get_payment_methods_tab_groups();
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
								'<a href="https://developer.squareup.com/console/en/apps" target="_blank" rel="noopener">',
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
								'<a href="https://developer.squareup.com/console/en/sandbox-test-accounts" target="_blank" rel="noopener">',
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
						'<a href="https://woocommerce.com/document/woocommerce-square/sync-settings/#woocommerce-square-sync-general-settings" target="_blank" rel="noopener">',
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
		 * Builds the credential payload injected into the digital wallet preview field value.
		 *
		 * The preview component (square/digital-wallet-preview) reads this JSON to
		 * initialise window.Square.payments(applicationId, locationId) client-side.
		 *
		 * @since x.x.x
		 *
		 * @return string JSON-encoded credentials object.
		 */
		private function get_digital_wallet_preview_data(): string {
			$is_sandbox = wc_square()->get_settings_handler()->is_sandbox();

			return (string) wp_json_encode(
				array(
					'applicationId' => wc_square()->get_gateway()->get_application_id(),
					'locationId'    => wc_square()->get_settings_handler()->get_location_id(),
					'squareJsUrl'   => $is_sandbox
						? 'https://sandbox.web.squarecdn.com/v1/square.js'
						: 'https://web.squarecdn.com/v1/square.js',
					'countryCode'   => 'US',
					'currencyCode'  => get_woocommerce_currency() ? get_woocommerce_currency() : 'USD',
				)
			);
		}

		/**
		 * Returns the field groups for the Payment Methods tab.
		 *
		 * Three groups: the default gateway list, then Digital Wallet and Cash App
		 * customise sub-pages. JS `groupVisibility` predicates show exactly one group
		 * at a time based on the `payment_methods_view` sentinel field.
		 *
		 * @since x.x.x
		 *
		 * @return array<string, array>
		 */
		private function get_payment_methods_tab_groups(): array {
			$credit_card = (array) get_option( Rest\WC_REST_Square_Credit_Card_Payment_Settings_Controller::SQUARE_PAYMENT_SETTINGS_OPTION_NAME, array() );
			$cash_app    = (array) get_option( Rest\WC_REST_Square_Cash_App_Settings_Controller::SQUARE_CASH_APP_SETTINGS_OPTION_NAME, array() );
			$gift_cards  = (array) get_option( \WooCommerce\Square\Gateway\Gift_Card::SQUARE_PAYMENT_SETTINGS_OPTION_NAME, array() );

			$gateway_states = (string) wp_json_encode(
				array(
					'credit_card'    => wc_string_to_bool( $credit_card['enabled'] ?? 'yes' ),
					'digital_wallet' => wc_string_to_bool( $credit_card['enable_digital_wallets'] ?? 'yes' ),
					'cash_app'       => wc_string_to_bool( $cash_app['enabled'] ?? 'yes' ),
					'gift_cards'     => wc_string_to_bool( $gift_cards['enabled'] ?? 'yes' ),
				)
			);

			return array(
				'payment_methods_list'    => array(
					'id'          => 'payment_methods_list',
					'title'       => __( 'Choose your payment methods', 'woocommerce-square' ),
					'description' => '',
					'actions'     => array(),
					'order'       => 0,
					'fields'      => array(
						array(
							'id'        => 'payment_methods_view',
							'type'      => 'text',
							'component' => 'square/hidden-field',
							'is_option' => false,
							'label'     => '',
							'value'     => 'list',
							'save'      => array( 'adapter' => 'none' ),
						),
						array(
							// Shared, reactive parent Digital Wallet enable state. The
							// gateway list toggle and the per-wallet toggles both read
							// and write this so the two sub-views stay in sync. Persisted
							// to the Credit Card gateway via apiFetch on change (not the
							// page Save), so the save adapter is 'none' here.
							'id'        => 'enable_digital_wallets',
							'type'      => 'text',
							'component' => 'square/hidden-field',
							'is_option' => false,
							'label'     => '',
							'value'     => wc_bool_to_string( wc_string_to_bool( $credit_card['enable_digital_wallets'] ?? 'yes' ) ),
							'save'      => array( 'adapter' => 'none' ),
						),
						array(
							'id'          => 'payment_methods_gateway_list',
							'type'        => 'text',
							'component'   => 'square/gateway-list',
							'is_option'   => false,
							'label'       => '',
							'description' => '',
							'value'       => $gateway_states,
							'save'        => array( 'adapter' => 'none' ),
						),
					),
				),
				'digital_wallets_section' => array(
					'id'          => 'digital_wallets_section',
					'title'       => __( 'Digital wallet settings', 'woocommerce-square' ),
					'description' => __( 'Allow customers to pay with Apple Pay or Google Pay from your Product, Cart and Checkout pages. <a href="https://woocommerce.com/document/woocommerce-square/" target="_blank">Learn more about digital wallets</a>', 'woocommerce-square' ),
					'actions'     => array(),
					'order'       => 1,
					'fields'      => array(
						array(
							'id'        => 'digital_wallets_google_pay_header',
							'type'      => 'text',
							'component' => 'square/section-header',
							'is_option' => false,
							'label'     => __( 'Google Pay', 'woocommerce-square' ),
							'value'     => '',
							'save'      => array( 'adapter' => 'none' ),
						),
						array(
							'id'        => 'digital_wallets_google_pay_enabled',
							'label'     => __( 'Activate Google Pay', 'woocommerce-square' ),
							'type'      => 'checkbox',
							'component' => 'square/digital-wallet-toggle',
							'value'     => wc_bool_to_string( wc_string_to_bool( $credit_card['digital_wallets_google_pay_enabled'] ?? 'yes' ) ),
						),
						array(
							'id'          => 'digital_wallets_google_pay_button_type',
							'label'       => __( 'Button label', 'woocommerce-square' ),
							'type'        => 'select',
							'description' => '',
							'value'       => $credit_card['digital_wallets_google_pay_button_type'] ?? $credit_card['digital_wallets_button_type'] ?? 'buy',
							'options'     => array(
								array(
									'value' => 'buy',
									'label' => __( 'Buy now', 'woocommerce-square' ),
								),
								array(
									'value' => 'checkout',
									'label' => __( 'Checkout', 'woocommerce-square' ),
								),
								array(
									'value' => 'pay',
									'label' => __( 'Pay', 'woocommerce-square' ),
								),
								array(
									'value' => 'plain',
									'label' => __( 'Plain (no text)', 'woocommerce-square' ),
								),
								array(
									'value' => 'donate',
									'label' => __( 'Donate', 'woocommerce-square' ),
								),
								array(
									'value' => 'book',
									'label' => __( 'Book', 'woocommerce-square' ),
								),
								array(
									'value' => 'subscribe',
									'label' => __( 'Subscribe', 'woocommerce-square' ),
								),
								array(
									'value' => 'order',
									'label' => __( 'Order', 'woocommerce-square' ),
								),
							),
						),
						array(
							'id'          => 'digital_wallets_google_pay_button_color',
							'label'       => __( 'Button color', 'woocommerce-square' ),
							'type'        => 'select',
							'description' => '',
							'value'       => $credit_card['digital_wallets_google_pay_button_color'] ?? 'black',
							'options'     => array(
								array(
									'value' => 'black',
									'label' => __( 'Black', 'woocommerce-square' ),
								),
								array(
									'value' => 'white',
									'label' => __( 'White', 'woocommerce-square' ),
								),
							),
						),
						array(
							'id'        => 'digital_wallets_apple_pay_header',
							'type'      => 'text',
							'component' => 'square/section-header',
							'is_option' => false,
							'label'     => __( 'Apple Pay', 'woocommerce-square' ),
							'value'     => '',
							'save'      => array( 'adapter' => 'none' ),
						),
						array(
							'id'        => 'digital_wallets_apple_pay_enabled',
							'label'     => __( 'Enable Apple Pay', 'woocommerce-square' ),
							'type'      => 'checkbox',
							'component' => 'square/digital-wallet-toggle',
							'value'     => wc_bool_to_string( wc_string_to_bool( $credit_card['digital_wallets_apple_pay_enabled'] ?? 'yes' ) ),
						),
						array(
							'id'          => 'digital_wallets_apple_pay_button_type',
							'label'       => __( 'Button label', 'woocommerce-square' ),
							'type'        => 'select',
							'description' => '',
							'value'       => $credit_card['digital_wallets_apple_pay_button_type'] ?? $credit_card['digital_wallets_button_type'] ?? 'buy',
							'options'     => array(
								array(
									'value' => 'buy',
									'label' => __( 'Buy now', 'woocommerce-square' ),
								),
								array(
									'value' => 'checkout',
									'label' => __( 'Checkout', 'woocommerce-square' ),
								),
								array(
									'value' => 'pay',
									'label' => __( 'Pay', 'woocommerce-square' ),
								),
								array(
									'value' => 'plain',
									'label' => __( 'Plain (no text)', 'woocommerce-square' ),
								),
								array(
									'value' => 'donate',
									'label' => __( 'Donate', 'woocommerce-square' ),
								),
								array(
									'value' => 'book',
									'label' => __( 'Book', 'woocommerce-square' ),
								),
								array(
									'value' => 'subscribe',
									'label' => __( 'Subscribe', 'woocommerce-square' ),
								),
								array(
									'value' => 'order',
									'label' => __( 'Order', 'woocommerce-square' ),
								),
							),
						),
						array(
							'id'          => 'digital_wallets_apple_pay_button_color',
							'label'       => __( 'Button color', 'woocommerce-square' ),
							'type'        => 'select',
							'description' => '',
							'value'       => $credit_card['digital_wallets_apple_pay_button_color'] ?? 'black',
							'options'     => array(
								array(
									'value' => 'black',
									'label' => __( 'Black', 'woocommerce-square' ),
								),
								array(
									'value' => 'white',
									'label' => __( 'White', 'woocommerce-square' ),
								),
								array(
									'value' => 'white-with-line',
									'label' => __( 'White with outline', 'woocommerce-square' ),
								),
							),
						),
						array(
							'id'          => 'digital_wallet_preview',
							'type'        => 'text',
							'component'   => 'square/digital-wallet-preview',
							'is_option'   => false,
							'label'       => __( 'Preview', 'woocommerce-square' ),
							'description' => '',
							'value'       => $this->get_digital_wallet_preview_data(),
						),
					),
				),
				'cash_app_pay_section'    => array(
					'id'          => 'cash_app_pay_section',
					'title'       => __( 'Cash App Pay settings', 'woocommerce-square' ),
					'description' => __( 'Customize the way Cash App appears on your website.', 'woocommerce-square' ),
					'actions'     => array(),
					'order'       => 2,
					'fields'      => array(
						array(
							'id'          => 'button_theme',
							'label'       => __( 'Button Theme', 'woocommerce-square' ),
							'type'        => 'select',
							'description' => '',
							'value'       => $cash_app['button_theme'] ?? 'dark',
							'options'     => array(
								array(
									'value' => 'dark',
									'label' => __( 'Dark', 'woocommerce-square' ),
								),
								array(
									'value' => 'light',
									'label' => __( 'Light', 'woocommerce-square' ),
								),
							),
						),
						array(
							'id'          => 'button_shape',
							'label'       => __( 'Button Shape', 'woocommerce-square' ),
							'type'        => 'select',
							'description' => '',
							'value'       => $cash_app['button_shape'] ?? 'semiround',
							'options'     => array(
								array(
									'value' => 'semiround',
									'label' => __( 'Semiround', 'woocommerce-square' ),
								),
								array(
									'value' => 'round',
									'label' => __( 'Round', 'woocommerce-square' ),
								),
								array(
									'value' => 'square',
									'label' => __( 'Square', 'woocommerce-square' ),
								),
							),
						),
						array(
							'id'          => 'cash_app_button_preview',
							'type'        => 'text',
							'component'   => 'square/cash-app-button-preview',
							'is_option'   => false,
							'label'       => __( 'Preview', 'woocommerce-square' ),
							'value'       => '',
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
