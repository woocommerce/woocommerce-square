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
				Payments_Square_Hub::TAB_PAYMENTS_TRANSACTIONS,
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
				case Payments_Square_Hub::TAB_PAYMENTS_TRANSACTIONS:
					return $this->get_payments_transactions_tab_groups();
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
			$base_location = wc_get_base_location();
			$country_code  = ! empty( $base_location['country'] ) ? $base_location['country'] : 'US';

			return (string) wp_json_encode(
				array(
					'applicationId' => wc_square()->get_gateway()->get_application_id(),
					'locationId'    => wc_square()->get_settings_handler()->get_location_id(),
					'squareJsUrl'   => wc_square()->get_settings_handler()->get_square_js_url(),
					'countryCode'   => $country_code,
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

			// Each payment method's enable state is a shared, reactive value read
			// and written by the gateway list toggles (and, for digital wallets,
			// the per-wallet toggles on the Customize sub-page). Nothing here
			// self-saves: the values ride the SDK form and are persisted only when
			// the page Save button is clicked (routed by save-handler.js), so the
			// save adapter is 'none' on each.
			// Read each gateway's real enabled state via is_enabled() rather than
			// defaulting an absent option key to 'yes': the gateway default is 'no',
			// so a never-saved gateway must show its toggle off (and, since Save only
			// sends changed values, a wrong default could never be corrected).
			$enable_state_fields = array(
				'square_credit_card_enabled'  => wc_bool_to_string( wc_square()->get_gateway( \WooCommerce\Square\Plugin::GATEWAY_ID )->is_enabled() ),
				'enable_digital_wallets'      => $credit_card['enable_digital_wallets'] ?? 'yes',
				'square_cash_app_pay_enabled' => wc_bool_to_string( wc_square()->get_gateway( \WooCommerce\Square\Plugin::CASH_APP_PAY_GATEWAY_ID )->is_enabled() ),
				'gift_cards_pay_enabled'      => wc_bool_to_string( wc_square()->get_gateway( \WooCommerce\Square\Plugin::GIFT_CARD_PAY_GATEWAY_ID )->is_enabled() ),
			);

			// Which sub-view to show is URL-addressable via the `pm-view` query arg
			// so the Customize sub-pages survive a reload and are deep-linkable.
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$pm_view = isset( $_GET['pm-view'] ) ? sanitize_key( wp_unslash( $_GET['pm-view'] ) ) : 'list';
			if ( ! in_array( $pm_view, array( 'list', 'digital-wallet', 'cash-app' ), true ) ) {
				$pm_view = 'list';
			}

			$enable_fields = array(
				array(
					'id'        => 'payment_methods_view',
					'type'      => 'text',
					'component' => 'square/hidden-field',
					'is_option' => false,
					'label'     => '',
					'value'     => $pm_view,
					'save'      => array( 'adapter' => 'none' ),
				),
			);

			foreach ( $enable_state_fields as $field_id => $raw ) {
				$enable_fields[] = array(
					'id'        => $field_id,
					'type'      => 'text',
					'component' => 'square/hidden-field',
					'is_option' => false,
					'label'     => '',
					'value'     => wc_bool_to_string( wc_string_to_bool( $raw ) ),
					'save'      => array( 'adapter' => 'none' ),
				);
			}

			$enable_fields[] = array(
				'id'          => 'payment_methods_gateway_list',
				'type'        => 'text',
				'component'   => 'square/gateway-list',
				'is_option'   => false,
				'label'       => '',
				'description' => '',
				'value'       => '',
				'save'        => array( 'adapter' => 'none' ),
			);

			return array(
				'payment_methods_list'    => array(
					'id'          => 'payment_methods_list',
					'title'       => __( 'Choose your payment methods', 'woocommerce-square' ),
					'description' => '',
					'actions'     => array(),
					'order'       => 0,
					'fields'      => $enable_fields,
				),
				'digital_wallets_section' => array(
					'id'          => 'digital_wallets_section',
					'title'       => __( 'Digital wallet settings', 'woocommerce-square' ),
					'description' => sprintf(
						/* translators: %s: "Learn more about digital wallets" link to the documentation */
						__( 'Allow customers to pay with Apple Pay or Google Pay from your Product, Cart and Checkout pages. %s', 'woocommerce-square' ),
						'<a href="https://woocommerce.com/document/woocommerce-square/payment-settings/enabling-digital-wallets/" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Learn more about digital wallets', 'woocommerce-square' ) . '</a>'
					),
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
							// The Square Web Payments SDK Google Pay button only
							// renders two types: `long` (full "Buy with G Pay" text)
							// and `short` (mark only). The Google-API named labels
							// (buy/pay/plain/...) are not accepted by
							// `googlePay.attach()` and do not render at checkout.
							'value'       => $credit_card['digital_wallets_google_pay_button_type'] ?? 'long',
							'options'     => array(
								array(
									'value' => 'long',
									'label' => __( 'Buy with Google Pay', 'woocommerce-square' ),
								),
								array(
									'value' => 'short',
									'label' => __( 'Google Pay (icon only)', 'woocommerce-square' ),
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
							// Options mirror the legacy settings screen's Button Type
							// select exactly (buy / donate / plain), which is the set
							// the checkout Apple Pay button honours.
							'value'       => $credit_card['digital_wallets_apple_pay_button_type'] ?? 'buy',
							'options'     => array(
								array(
									'value' => 'buy',
									'label' => __( 'Buy Now', 'woocommerce-square' ),
								),
								array(
									'value' => 'donate',
									'label' => __( 'Donate', 'woocommerce-square' ),
								),
								array(
									'value' => 'plain',
									'label' => __( 'No Text', 'woocommerce-square' ),
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
									'value' => 'white-outline',
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
		 * Returns the transaction-type select options shared by the Credit Card
		 * and Cash App Pay sections (Charge / Authorization).
		 *
		 * @since x.x.x
		 *
		 * @return array<int, array{value: string, label: string}>
		 */
		private function get_transaction_type_options(): array {
			return array(
				array(
					'value' => 'charge',
					'label' => _x( 'Charge', 'noun, credit card transaction type', 'woocommerce-square' ),
				),
				array(
					'value' => 'authorization',
					'label' => _x( 'Authorization', 'credit card transaction type', 'woocommerce-square' ),
				),
			);
		}

		/**
		 * Returns the field groups for the Payments & Transactions tab.
		 *
		 * Migrates the transaction-handling fields that previously lived on the
		 * separate Credit Card, Cash App and main Square settings pages into one
		 * tab. UI-only migration: same option keys, same REST controllers.
		 *
		 * Three groups:
		 *  - Credit and debit cards: title, description, transaction type
		 *    (+ Authorization-only sub-fields) and customer profiles.
		 *  - Cash App Pay: title, description, transaction type. Shown only when
		 *    Cash App is enabled on the Payment Methods tab (JS groupVisibility
		 *    keyed off the seeded `cash_app_enabled` field).
		 *  - Advanced: detailed decline messages.
		 *
		 * Debug/logging settings are intentionally NOT migrated here. They are
		 * being refactored from the legacy 7-option dropdown to a two-boolean
		 * model under SQUARE-295, and are handled in that ticket.
		 *
		 * @since x.x.x
		 *
		 * @return array<string, array>
		 */
		private function get_payments_transactions_tab_groups(): array {
			$settings = (array) get_option( 'wc_square_settings', array() );
			$cc       = (array) get_option( 'woocommerce_square_credit_card_settings', array() );
			$cash_app = (array) get_option( 'woocommerce_square_cash_app_pay_settings', array() );

			$cash_app_enabled = wc_string_to_bool( $cash_app['enabled'] ?? 'no' );

			$transaction_type_description = __( 'Select how transactions should be processed. Charge submits all transactions for settlement, Authorization simply authorizes the order total for capture later.', 'woocommerce-square' );

			return array(
				'pt_credit_card_section' => array(
					'id'          => 'pt_credit_card_section',
					'title'       => __( 'Credit card transaction settings', 'woocommerce-square' ),
					'description' => __( 'Fine-tune the details of how credit card payments are processed, ensuring a secure and smooth transaction for every customer.', 'woocommerce-square' ),
					'actions'     => array(),
					'order'       => 0,
					'fields'      => array(
						array(
							'id'          => 'cc_title',
							'label'       => __( 'Title', 'woocommerce-square' ),
							'type'        => 'text',
							'component'   => 'square/text-counted',
							'description' => __( 'The value in the credit card title field of a customer\'s statement.', 'woocommerce-square' ),
							'value'       => $cc['title'] ?? __( 'Credit Card', 'woocommerce-square' ),
							'maxLength'   => 22,
						),
						array(
							'id'          => 'cc_description',
							'label'       => __( 'Description', 'woocommerce-square' ),
							'type'        => 'text',
							'component'   => 'square/textarea-counted',
							'description' => __( 'The value in the description field of a customer\'s statement.', 'woocommerce-square' ),
							'value'       => $cc['description'] ?? '',
							'maxLength'   => 100,
						),
						array(
							'id'          => 'cc_transaction_type',
							'label'       => __( 'Transaction Preferences', 'woocommerce-square' ),
							'type'        => 'select',
							'description' => $transaction_type_description,
							'value'       => $cc['transaction_type'] ?? 'charge',
							'options'     => $this->get_transaction_type_options(),
						),
						array(
							'id'          => 'cc_charge_virtual_orders',
							'label'       => __( 'Charge Virtual-Only Orders', 'woocommerce-square' ),
							'type'        => 'checkbox',
							'description' => __( 'If the order contains exclusively virtual items, enable this to immediately charge, rather than authorize, the transaction.', 'woocommerce-square' ),
							'value'       => wc_bool_to_string( wc_string_to_bool( $cc['charge_virtual_orders'] ?? 'no' ) ),
						),
						array(
							'id'          => 'cc_enable_paid_capture',
							'label'       => __( 'Capture Paid Orders', 'woocommerce-square' ),
							'type'        => 'checkbox',
							'description' => __( 'Automatically capture orders when they are changed to a paid status.', 'woocommerce-square' ),
							'value'       => wc_bool_to_string( wc_string_to_bool( $cc['enable_paid_capture'] ?? 'no' ) ),
						),
						array(
							'id'        => 'pt_customer_profiles_header',
							'type'      => 'text',
							'component' => 'square/sub-header',
							'is_option' => false,
							'label'     => __( 'Customer profiles', 'woocommerce-square' ),
							'value'     => '',
							'save'      => array( 'adapter' => 'none' ),
						),
						array(
							'id'          => 'cc_tokenization',
							'label'       => __( 'Allow customers to save their payment details', 'woocommerce-square' ),
							'type'        => 'checkbox',
							'description' => __( 'When enabled, it will allow customers to securely save their payment details for future checkout.', 'woocommerce-square' ),
							'value'       => wc_bool_to_string( wc_string_to_bool( $cc['tokenization'] ?? 'no' ) ),
						),
					),
				),
				'pt_cash_app_section'    => array(
					'id'          => 'pt_cash_app_section',
					'title'       => __( 'Cash App Pay transaction settings', 'woocommerce-square' ),
					'description' => __( 'Fine-tune the details of how Cash App Pay is processed, ensuring a secure and smooth transaction for every customer.', 'woocommerce-square' ),
					'actions'     => array(),
					'order'       => 1,
					'fields'      => array(
						array(
							'id'        => 'cash_app_enabled',
							'type'      => 'text',
							'component' => 'square/hidden-field',
							'is_option' => false,
							'label'     => '',
							'value'     => wc_bool_to_string( $cash_app_enabled ),
							'save'      => array( 'adapter' => 'none' ),
						),
						array(
							'id'          => 'cashapp_title',
							'label'       => __( 'Title', 'woocommerce-square' ),
							'type'        => 'text',
							'component'   => 'square/text-counted',
							'description' => __( 'The value in the Cash App Pay title field of a customer\'s statement.', 'woocommerce-square' ),
							'value'       => $cash_app['title'] ?? __( 'Cash App Pay', 'woocommerce-square' ),
							'maxLength'   => 22,
						),
						array(
							'id'          => 'cashapp_description',
							'label'       => __( 'Description', 'woocommerce-square' ),
							'type'        => 'text',
							'component'   => 'square/textarea-counted',
							'description' => __( 'The value in the description field of a customer\'s statement.', 'woocommerce-square' ),
							'value'       => $cash_app['description'] ?? '',
							'maxLength'   => 100,
						),
						array(
							'id'          => 'cashapp_transaction_type',
							'label'       => __( 'Transaction Preferences', 'woocommerce-square' ),
							'type'        => 'select',
							'description' => $transaction_type_description,
							'value'       => $cash_app['transaction_type'] ?? 'charge',
							'options'     => $this->get_transaction_type_options(),
						),
					),
				),
				'pt_advanced_section'    => array(
					'id'          => 'pt_advanced_section',
					'title'       => __( 'Advanced settings', 'woocommerce-square' ),
					'description' => __( 'Adjust these options to provide your customers with additional clarity and troubleshoot any issues more effectively.', 'woocommerce-square' ),
					'actions'     => array(),
					'order'       => 2,
					'fields'      => array(
						array(
							'id'        => 'pt_decline_header',
							'type'      => 'text',
							'component' => 'square/sub-header',
							'is_option' => false,
							'label'     => __( 'Detailed decline messages', 'woocommerce-square' ),
							'value'     => '',
							'save'      => array( 'adapter' => 'none' ),
						),
						array(
							'id'          => 'enable_customer_decline_messages',
							'label'       => __( 'Enable detailed decline messages', 'woocommerce-square' ),
							'type'        => 'checkbox',
							'description' => __( 'When enabled, customers will see detailed decline messages during checkout when possible, rather than a generic decline message.', 'woocommerce-square' ),
							'value'       => wc_bool_to_string( wc_string_to_bool( $settings['enable_customer_decline_messages'] ?? 'no' ) ),
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
