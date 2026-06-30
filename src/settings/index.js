import { registerSettingsExtension } from '@woocommerce/modern-settings-sdk';
import OAuthConnect from './general/oauth-connect';
import EnvironmentSelector from './general/environment-selector';
import SectionHeader from './general/section-header';
import GatewayList from './payment-methods/gateway-list';
import GatewayToggle from './payment-methods/gateway-toggle';
import HiddenField from './payment-methods/hidden-field';
import CashAppButtonPreview from './cash-app/button-preview';
import DigitalWalletPreview from './digital-wallets/preview';
import squareSaveHandler from './save-handler';

registerSettingsExtension( {
	scope: { page: 'square' },
	components: {
		'square/oauth-connect': OAuthConnect,
		'square/environment-selector': EnvironmentSelector,
		'square/section-header': SectionHeader,
		'square/gateway-list': GatewayList,
		'square/gateway-toggle': GatewayToggle,
		'square/hidden-field': HiddenField,
		'square/cash-app-button-preview': CashAppButtonPreview,
		'square/digital-wallet-preview': DigitalWalletPreview,
	},
	fieldVisibility: {
		// Sandbox credential fields are only shown when sandbox is selected.
		// The radio component stores 'yes' (sandbox) or 'no' (production).
		sandbox_application_id: ( { values } ) =>
			values.enable_sandbox === 'yes',
		sandbox_token: ( { values } ) => values.enable_sandbox === 'yes',
		// Digital wallet sub-page: show Google Pay label+color only when Google Pay is active.
		digital_wallets_google_pay_button_type: ( { values } ) =>
			values.digital_wallets_google_pay_enabled === 'yes',
		digital_wallets_google_pay_button_color: ( { values } ) =>
			values.digital_wallets_google_pay_enabled === 'yes',
		// Digital wallet sub-page: show Apple Pay label+color only when Apple Pay is active.
		digital_wallets_apple_pay_button_type: ( { values } ) =>
			values.digital_wallets_apple_pay_enabled === 'yes',
		digital_wallets_apple_pay_button_color: ( { values } ) =>
			values.digital_wallets_apple_pay_enabled === 'yes',
	},
	groupVisibility: {
		// Payment Methods tab: show one sub-page at a time based on the
		// `payment_methods_view` sentinel field (never persisted).
		payment_methods_list: ( { values } ) =>
			! values.payment_methods_view ||
			values.payment_methods_view === 'list',
		digital_wallets_section: ( { values } ) =>
			values.payment_methods_view === 'digital-wallet',
		cash_app_pay_section: ( { values } ) =>
			values.payment_methods_view === 'cash-app',
	},
	saveHandlers: {
		'square/save': squareSaveHandler,
	},
} );
