import apiFetch from '@wordpress/api-fetch';

import {
	CREDIT_CARD_DEFAULT_STATE,
	DIGITAL_WALLETS_DEFAULT_STATE,
	GIFT_CARDS_DEFAULT_STATE,
	CASH_APP_DEFAULT_STATE,
} from '../new-user-experience/onboarding/data/reducers';

export const getPaymentGatewaySettingsData = async () => {
	const settings = await apiFetch( { path: '/wc/v3/wc_square/payment_settings' } );

	const creditCard = {
		enabled: settings.enabled || CREDIT_CARD_DEFAULT_STATE.enabled,
		title: settings.title || CREDIT_CARD_DEFAULT_STATE.title,
		description: settings.description || CREDIT_CARD_DEFAULT_STATE.description,
		transaction_type: settings.transaction_type || CREDIT_CARD_DEFAULT_STATE.transaction_type,
		charge_virtual_orders: settings.charge_virtual_orders || CREDIT_CARD_DEFAULT_STATE.charge_virtual_orders,
		enable_paid_capture: settings.enable_paid_capture || CREDIT_CARD_DEFAULT_STATE.enable_paid_capture,
		card_types: settings.card_types || CREDIT_CARD_DEFAULT_STATE.card_types,
		tokenization: settings.tokenization || CREDIT_CARD_DEFAULT_STATE.tokenization,
	};

	const digitalWallet = {
		enable_digital_wallets: settings.enable_digital_wallets || DIGITAL_WALLETS_DEFAULT_STATE.enable_digital_wallets,
		digital_wallets_button_type: settings.digital_wallets_button_type || DIGITAL_WALLETS_DEFAULT_STATE.digital_wallets_button_type,
		digital_wallets_apple_pay_button_color: settings.digital_wallets_apple_pay_button_color || DIGITAL_WALLETS_DEFAULT_STATE.digital_wallets_apple_pay_button_color,
		digital_wallets_google_pay_button_color: settings.digital_wallets_google_pay_button_color || DIGITAL_WALLETS_DEFAULT_STATE.digital_wallets_google_pay_button_color,
		digital_wallets_hide_button_options: settings.digital_wallets_hide_button_options || DIGITAL_WALLETS_DEFAULT_STATE.digital_wallets_hide_button_options,
	};

	const giftCard = {
		enable_gift_cards: settings.enable_gift_cards || GIFT_CARDS_DEFAULT_STATE.enable_gift_cards,
	};

	return { creditCard, digitalWallet, giftCard };
};

export const getCashAppSettingsData = async () => {
	const settings = await apiFetch( { path: '/wc/v3/wc_square/cash_app_settings' } );

	const cashApp = {
		enabled: settings.enabled || CASH_APP_DEFAULT_STATE.enabled,
		title: settings.title || CASH_APP_DEFAULT_STATE.title,
		description: settings.description || CASH_APP_DEFAULT_STATE.description,
		transaction_type: settings.transaction_type || CASH_APP_DEFAULT_STATE.transaction_type,
		charge_virtual_orders: settings.charge_virtual_orders || CASH_APP_DEFAULT_STATE.charge_virtual_orders,
		enable_paid_capture: settings.enable_paid_capture || CASH_APP_DEFAULT_STATE.enable_paid_capture,
		button_theme: settings.button_theme || CASH_APP_DEFAULT_STATE.button_theme,
		button_shape: settings.button_shape || CASH_APP_DEFAULT_STATE.button_shape,
		debug_mode: settings.debug_mode || CASH_APP_DEFAULT_STATE.debug_mode,
	};

	return { cashApp };
};

export const connectToSquare = async () => {
	try {
		const response = await fetch( `${ ajaxurl }?action=wc_square_settings_get_locations` );

		if ( ! response.ok ) {
			throw new Error( 'Failed to fetch business locations.' );
		}

		const data = await response.json();
		return data;
	} catch ( e ) {
		console.error( 'Error fetching business locations:', error );
	}

	return {};
};

export const filterBusinessLocations = ( locations = [] ) => {
	return locations
		.filter( ( location ) => 'ACTIVE' === location.status )
		.map( location => ( { label: location.name, value: location.id } ) );
};

export const getSquareSettings = async () => {
	const settings = await apiFetch( { path: '/wc/v3/wc_square/settings' } );

	return settings;
};
