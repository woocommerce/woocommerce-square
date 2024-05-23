import {
	recordEvent as coreRecordEvent,
	queueRecordEvent as coreQueueRecordEvent
} from '@woocommerce/tracks';

const getEventRecordParams = ( e = '', properties = {} ) => {
	const prefix = 'woocommerce_square_';

	const eventName = `${ prefix }${ e }`;
	const baseProperties = { plugin_version: wcSquareOnboarding.plugin_version }
	const allProperties = { ...properties, ...baseProperties };

	return {
		eventName,
		allProperties,
	};
}

export const recordEvent = ( e = '', properties = {} ) => {
	const {
		eventName,
		allProperties,
	} = getEventRecordParams( e, properties );

	coreRecordEvent( eventName, allProperties );
};

export const queueRecordEvent = ( e = '', properties = {} ) => {
	const {
		eventName,
		allProperties,
	} = getEventRecordParams( e, properties );

	coreQueueRecordEvent( eventName, allProperties );
}

export const ONBOARDING_TRACK_EVENTS = {
	PAYMENT_METHODS_NEXT_CLICKED: 'payment_methods_next_clicked',
	EXIT_CLICKED: 'exit_clicked',
	VISIT_STOREFRONT_CLICKED: 'visit_storefront_clicked',
	VISIT_SYNC_SETTINGS_CLICKED: 'visit_sync_settings_clicked',
	VISIT_CREDIT_CARD_SETTINGS_CLICKED: 'visit_credit_card_settings_clicked',
	VISIT_DIGITAL_WALLET_SETTINGS_CLICKED: 'visit_digital_wallet_settings_clicked',
	VISIT_GIFT_CARD_SETTINGS_CLICKED: 'visit_gift_card_settings_clicked',
	VISIT_CASH_APP_SETTINGS_CLICKED: 'visit_cash_app_settings_clicked',
	VISIT_ADVANCED_SETTINGS_CLICKED: 'visit_advanced_settings_clicked',
	VISIT_SANDBOX_SETTINGS_CLICKED: 'visit_sandbox_settings_clicked',
	SAVE_SYNC_SETTINGS: 'save_sync_settings',
	SAVE_DIGITAL_WALLET_SETTINGS: 'save_digital_wallet_settings',
	SAVE_SANDBOX_SETTINGS: 'save_sandbox_settings',
	SAVE_ADVANCED_SETTINGS: 'save_advanced_settings',
	SAVE_BUSINESS_LOCATION: 'save_business_location',
};
