/**
 * External dependencies.
 */
import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';
import { register } from '@wordpress/data';

/**
 * Internal dependencies.
 */
import '../styles/index.scss';
import '../styles/settings.scss';
import { SettingsApp } from './settings-app';
import { PaymentGatewaySettingsApp } from './payment-gateway-settings-app';
import { CashAppSettingsApp } from './cash-app-gateway-settings-app';
import { GiftCardsSettingsApp } from './gift-cards-gateway-settings-app';
import store from '../../new-user-experience/onboarding/data/store';

register(store);

domReady(() => {
	let container = document.getElementById(
		'woocommerce-square-settings__container-general'
	);

	if (container) {
		const root = createRoot(container);
		root.render(<SettingsApp />);
	} else {
		container = document.getElementById(
			'woocommerce-square-payment-gateway-settings__container--square_credit_card'
		);
		if (container) {
			const root = createRoot(container);
			root.render(<PaymentGatewaySettingsApp />);
		}
		container = document.getElementById(
			'woocommerce-square-payment-gateway-settings__container--square_cash_app_pay'
		);
		if (container) {
			const root = createRoot(container);
			root.render(<CashAppSettingsApp />);
		}
		container = document.getElementById(
			'woocommerce-square-payment-gateway-settings__container--gift_cards_pay'
		);
		if (container) {
			const root = createRoot(container);
			root.render(<GiftCardsSettingsApp />);
		}
	}
});
