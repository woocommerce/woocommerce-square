import apiFetch from '@wordpress/api-fetch'; // eslint-disable-line @woocommerce/dependency-group

import {
	CREDIT_CARD_DEFAULT_STATE,
	DIGITAL_WALLETS_DEFAULT_STATE,
	GIFT_CARDS_DEFAULT_STATE,
	CASH_APP_DEFAULT_STATE,
	SQUARE_SETTINGS_DEFAULT_STATE,
} from '../new-user-experience/onboarding/data/reducers';

export const getPaymentGatewaySettingsData = async () => {
	const settings = await apiFetch( {
		path: '/wc/v3/wc_square/payment_settings',
	} );

	const creditCard = {
		enabled: settings.enabled || CREDIT_CARD_DEFAULT_STATE.enabled,
		title: settings.title || CREDIT_CARD_DEFAULT_STATE.title,
		description:
			settings.description || CREDIT_CARD_DEFAULT_STATE.description,
		transaction_type:
			settings.transaction_type ||
			CREDIT_CARD_DEFAULT_STATE.transaction_type,
		charge_virtual_orders:
			settings.charge_virtual_orders ||
			CREDIT_CARD_DEFAULT_STATE.charge_virtual_orders,
		enable_paid_capture:
			settings.enable_paid_capture ||
			CREDIT_CARD_DEFAULT_STATE.enable_paid_capture,
		card_types: settings.card_types || CREDIT_CARD_DEFAULT_STATE.card_types,
		tokenization:
			settings.tokenization || CREDIT_CARD_DEFAULT_STATE.tokenization,
	};

	const digitalWallet = {
		enable_digital_wallets:
			settings.enable_digital_wallets ||
			DIGITAL_WALLETS_DEFAULT_STATE.enable_digital_wallets,
		digital_wallets_button_type:
			settings.digital_wallets_button_type ||
			DIGITAL_WALLETS_DEFAULT_STATE.digital_wallets_button_type,
		digital_wallets_apple_pay_button_color:
			settings.digital_wallets_apple_pay_button_color ||
			DIGITAL_WALLETS_DEFAULT_STATE.digital_wallets_apple_pay_button_color,
		digital_wallets_google_pay_button_color:
			settings.digital_wallets_google_pay_button_color ||
			DIGITAL_WALLETS_DEFAULT_STATE.digital_wallets_google_pay_button_color,
		digital_wallets_hide_button_options:
			settings.digital_wallets_hide_button_options ||
			DIGITAL_WALLETS_DEFAULT_STATE.digital_wallets_hide_button_options,
	};

	return { creditCard, digitalWallet };
};

export const getGiftCardsSettingsData = async () => {
	const settings = await apiFetch( {
		path: '/wc/v3/wc_square/gift_cards_settings',
	} );

	const giftCard = {
		enabled: settings.enabled || GIFT_CARDS_DEFAULT_STATE.enabled,
		is_default_placeholder: settings.is_default_placeholder || GIFT_CARDS_DEFAULT_STATE.is_default_placeholder,
	};

	return { giftCard };
};

export const getCashAppSettingsData = async () => {
	const settings = await apiFetch( {
		path: '/wc/v3/wc_square/cash_app_settings',
	} );

	const cashApp = {
		enabled: settings.enabled || CASH_APP_DEFAULT_STATE.enabled,
		title: settings.title || CASH_APP_DEFAULT_STATE.title,
		description: settings.description || CASH_APP_DEFAULT_STATE.description,
		transaction_type:
			settings.transaction_type ||
			CASH_APP_DEFAULT_STATE.transaction_type,
		charge_virtual_orders:
			settings.charge_virtual_orders ||
			CASH_APP_DEFAULT_STATE.charge_virtual_orders,
		enable_paid_capture:
			settings.enable_paid_capture ||
			CASH_APP_DEFAULT_STATE.enable_paid_capture,
		button_theme:
			settings.button_theme || CASH_APP_DEFAULT_STATE.button_theme,
		button_shape:
			settings.button_shape || CASH_APP_DEFAULT_STATE.button_shape,
	};

	return { cashApp };
};

export const connectToSquare = async () => {
	try {
		const _wpnonce = wcSquareSettings ? wcSquareSettings.nonce : ''; // eslint-disable-line no-undef

		if ( _wpnonce === '' ) {
			throw new Error( 'Invalid nonce.' );
		}

		const requestURL = `${ wcSquareSettings.ajaxUrl }?action=wc_square_settings_get_locations&_wpnonce=${ _wpnonce }`; // eslint-disable-line no-undef

		const response = await fetch( requestURL );

		if ( ! response.ok ) {
			throw new Error( 'Failed to fetch business locations.' );
		}

		const data = await response.json();
		return data;
	} catch ( e ) {
		console.error( 'Error fetching business locations:', e );
	}

	return {};
};

export const filterBusinessLocations = ( locations = [] ) => {
	return locations
		.filter( ( location ) => location.status === 'ACTIVE' )
		.map( ( location ) => ( {
			label: location.name,
			value: location.id,
		} ) );
};

export const getSquareSettings = async () => {
	const settings = await apiFetch( { path: '/wc/v3/wc_square/settings' } );

	const squareSettings = {
		enable_sandbox:
			settings.enable_sandbox ||
			SQUARE_SETTINGS_DEFAULT_STATE.enable_sandbox,
		sandbox_application_id:
			settings.sandbox_application_id ||
			SQUARE_SETTINGS_DEFAULT_STATE.sandbox_application_id,
		sandbox_token:
			settings.sandbox_token ||
			SQUARE_SETTINGS_DEFAULT_STATE.sandbox_token,
		production_location_id:
			settings.production_location_id ||
			SQUARE_SETTINGS_DEFAULT_STATE.production_location_id,
		sandbox_location_id:
			settings.sandbox_location_id ||
			SQUARE_SETTINGS_DEFAULT_STATE.sandbox_location_id,
		system_of_record:
			settings.system_of_record ||
			SQUARE_SETTINGS_DEFAULT_STATE.system_of_record,
		enable_inventory_sync:
			settings.enable_inventory_sync ||
			SQUARE_SETTINGS_DEFAULT_STATE.enable_inventory_sync,
		override_product_images:
			settings.override_product_images ||
			SQUARE_SETTINGS_DEFAULT_STATE.override_product_images,
		hide_missing_products:
			settings.hide_missing_products ||
			SQUARE_SETTINGS_DEFAULT_STATE.hide_missing_products,
		sync_interval:
			settings.sync_interval ||
			SQUARE_SETTINGS_DEFAULT_STATE.sync_interval,
		is_connected:
			settings.is_connected || SQUARE_SETTINGS_DEFAULT_STATE.is_connected,
		disconnection_url:
			settings.disconnection_url ||
			SQUARE_SETTINGS_DEFAULT_STATE.disconnection_url,
		connection_url:
			settings.connection_url ||
			SQUARE_SETTINGS_DEFAULT_STATE.connection_url,
		connection_url_wizard:
			settings.connection_url_wizard ||
			SQUARE_SETTINGS_DEFAULT_STATE.connection_url_wizard,
		connection_url_sandbox:
			settings.connection_url_sandbox ||
			SQUARE_SETTINGS_DEFAULT_STATE.connection_url_sandbox,
		locations:
			settings.locations || SQUARE_SETTINGS_DEFAULT_STATE.locations,
		enable_customer_decline_messages:
			settings.enable_customer_decline_messages ||
			SQUARE_SETTINGS_DEFAULT_STATE.enable_customer_decline_messages,
		debug_mode:
			settings.debug_mode || SQUARE_SETTINGS_DEFAULT_STATE.debug_mode,
		debug_logging_enabled:
			settings.debug_logging_enabled ||
			SQUARE_SETTINGS_DEFAULT_STATE.debug_logging_enabled,
	};

	return squareSettings;
};
