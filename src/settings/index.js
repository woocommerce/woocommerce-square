import { registerSettingsExtension } from '@woocommerce/modern-settings-sdk';
import OAuthConnect from './general/oauth-connect';
import EnvironmentSelector from './general/environment-selector';
import SectionHeader from './general/section-header';
import GatewayList from './payment-methods/gateway-list';
import DigitalWalletToggle from './payment-methods/digital-wallet-toggle';
import HiddenField from './payment-methods/hidden-field';
import CashAppButtonPreview from './cash-app/button-preview';
import DigitalWalletPreview from './digital-wallets/preview';
import squareSaveHandler from './save-handler';

/**
 * Resolves which Payment Methods sub-view is showing.
 *
 * The view comes from the `payment_methods_view` sentinel (set by the Customize
 * links, a `pm-view` deep link, or the browser Back/Forward buttons). Digital
 * wallets ride the Credit/debit card gateway's payment rail, so their Customize
 * sub-page is not reachable while that gateway is off — any request for it falls
 * back to the gateway list, which greys the method out and explains why.
 *
 * @param {Object} values All current form values.
 * @return {string} 'list' | 'digital-wallet' | 'cash-app'
 */
const paymentMethodsView = ( values ) => {
	const view = values.payment_methods_view || 'list';

	if (
		view === 'digital-wallet' &&
		values.square_credit_card_enabled !== 'yes'
	) {
		return 'list';
	}

	return view;
};

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
	},
	groupVisibility: {
		// Payment Methods tab: show one sub-page at a time based on the
		// `payment_methods_view` sentinel field (never persisted).
		payment_methods_list: ( { values } ) =>
			paymentMethodsView( values ) === 'list',
		digital_wallets_section: ( { values } ) =>
			paymentMethodsView( values ) === 'digital-wallet',
		cash_app_pay_section: ( { values } ) =>
			paymentMethodsView( values ) === 'cash-app',
	},
	saveHandlers: {
		'square/save': squareSaveHandler,
	},
} );
