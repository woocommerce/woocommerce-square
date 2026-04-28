/**
 * External dependencies.
 */
import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';
import { register } from '@wordpress/data';
import { registerSettingsExtension } from '@woocommerce/modern-settings-sdk';

/**
 * Internal dependencies.
 */
import '../styles/index.scss';
import '../styles/settings.scss';
import { GeneralSettingsApp } from './general-settings-app';
import { PaymentGatewaySettingsApp } from './payment-gateway-settings-app';
import { CashAppSettingsApp } from './cash-app-gateway-settings-app';
import { GiftCardsSettingsApp } from './gift-cards-gateway-settings-app';
import './payment-method-field-types';
import store from '../../new-user-experience/onboarding/data/store';

register( store );

// Register our autonomous components with the WC modern settings SDK so they
// are available when settings-embed.js mounts the ModernSettingsPage for ?tab=square.
// Must run at module level (before domReady) because settings-embed loads after us.
registerSettingsExtension( {
	scope: { page: 'square' },
	components: {
		'square/general': GeneralSettingsApp,
		'square/payment-methods': PaymentGatewaySettingsApp,
	},
} );

domReady( () => {
	// Flag ON: SDK renders all tabs via the schema + registerSettingsExtension above.
	// The data-wc-modern-settings div is output by WC_Settings_Page::output() when
	// the modern-settings flag is ON and our Square_Modern_Settings_Page is registered.
	if ( document.querySelector( '[data-wc-modern-settings]' ) ) {
		return;
	}

	// Flag OFF: createRoot mounts for the Payments > Square hub (checkout-section path).

	// Hub: General tab.
	let container = document.getElementById(
		'woocommerce-square-settings__container-general'
	);
	if ( container ) {
		createRoot( container ).render( <GeneralSettingsApp /> );
		return;
	}

	// Hub: Payment Methods tab.
	container = document.getElementById(
		'woocommerce-square-settings__container-payment-methods'
	);
	if ( container ) {
		createRoot( container ).render( <PaymentGatewaySettingsApp /> );
		return;
	}

	// Legacy standalone gateway pages (pre-hub).
	container = document.getElementById(
		'woocommerce-square-payment-gateway-settings__container--square_credit_card'
	);
	if ( container ) {
		createRoot( container ).render( <PaymentGatewaySettingsApp /> );
	}
	container = document.getElementById(
		'woocommerce-square-payment-gateway-settings__container--square_cash_app_pay'
	);
	if ( container ) {
		createRoot( container ).render( <CashAppSettingsApp /> );
	}
	container = document.getElementById(
		'woocommerce-square-payment-gateway-settings__container--gift_cards_pay'
	);
	if ( container ) {
		createRoot( container ).render( <GiftCardsSettingsApp /> );
	}
} );
