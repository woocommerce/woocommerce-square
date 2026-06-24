import { registerSettingsExtension } from '@woocommerce/modern-settings-sdk';
import OAuthConnect from './general/oauth-connect';
import EnvironmentSelector from './general/environment-selector';
import SectionHeader from './general/section-header';
import GatewayList from './payment-methods/gateway-list';
import DigitalWalletToggle from './payment-methods/digital-wallet-toggle';
import HiddenField from './payment-methods/hidden-field';
import CashAppButtonPreview from './cash-app/button-preview';
import DigitalWalletPreview from './digital-wallets/preview';
import TextCounted from './payments-transactions/text-counted';
import TextareaCounted from './payments-transactions/textarea-counted';
import SubHeader from './payments-transactions/sub-header';
import squareSaveHandler from './save-handler';

registerSettingsExtension( {
	scope: { page: 'square' },
	components: {
		'square/oauth-connect': OAuthConnect,
		'square/environment-selector': EnvironmentSelector,
		'square/section-header': SectionHeader,
		'square/gateway-list': GatewayList,
		'square/digital-wallet-toggle': DigitalWalletToggle,
		'square/hidden-field': HiddenField,
		'square/cash-app-button-preview': CashAppButtonPreview,
		'square/digital-wallet-preview': DigitalWalletPreview,
		'square/text-counted': TextCounted,
		'square/textarea-counted': TextareaCounted,
		'square/sub-header': SubHeader,
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
		// Hide the whole preview (and its "Preview" label) when neither wallet is on.
		digital_wallet_preview: ( { values } ) =>
			values.digital_wallets_google_pay_enabled === 'yes' ||
			values.digital_wallets_apple_pay_enabled === 'yes',
		// Payments & Transactions tab: Authorization-only sub-fields appear only
		// when the matching Transaction Type is set to 'authorization'.
		cc_charge_virtual_orders: ( { values } ) =>
			values.cc_transaction_type === 'authorization',
		cc_enable_paid_capture: ( { values } ) =>
			values.cc_transaction_type === 'authorization',
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
		// Payments & Transactions tab: Cash App section is shown only when Cash
		// App is enabled on the Payment Methods tab (seeded `cash_app_enabled`).
		pt_cash_app_section: ( { values } ) =>
			values.cash_app_enabled === 'yes',
	},
	saveHandlers: {
		'square/save': squareSaveHandler,
	},
} );
