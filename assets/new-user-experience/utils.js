import apiFetch from '@wordpress/api-fetch';

export const getPaymentGatewaySettingsData = async () => {
	const settings = await apiFetch( { path: '/wc/v3/wc_square/payment_settings' } );

	const creditCard = {
		enabled: settings.enabled,
		title: settings.title,
		description: settings.description,
		transaction_type: settings.transaction_type,
		charge_virtual_orders: settings.charge_virtual_orders,
		enable_paid_capture: settings.enable_paid_capture,
		card_types: settings.card_types,
		tokenization: settings.tokenization,
	};

	const digitalWallet = {
		enable_digital_wallets: settings.enable_digital_wallets,
		digital_wallets_button_type: settings.digital_wallets_button_type,
		digital_wallets_apple_pay_button_color: settings.digital_wallets_apple_pay_button_color,
		digital_wallets_google_pay_button_color: settings.digital_wallets_google_pay_button_color,
		digital_wallets_hide_button_options: settings.digital_wallets_hide_button_options || [],
	}

	return { creditCard, digitalWallet };
};

export const savePaymentGatewaySettings = ( data ) => {
	
};
