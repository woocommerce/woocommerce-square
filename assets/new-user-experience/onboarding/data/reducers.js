export const CREDIT_CARD_DEFAULT_STATE = {
	enabled: 'yes',
	title: 'Credit Card',
	description: 'Pay securely using your credit card.',
	transaction_type: 'charge',
	charge_virtual_orders: 'no',
	enable_paid_capture: 'no',
	card_types: [
		'VISA',
		'MC',
		'AMEX',
		'DISC',
		'DINERS',
		'JCB',
		'UNIONPAY',
	],
	tokenization: "no",
};

const creditCardReducer = ( state = CREDIT_CARD_DEFAULT_STATE, action ) => {
	switch ( action.type ) {
		case 'SET_CREDIT_CARD_DATA':
			return {
				...state,
				...action.payload,
			};

		default:
			return state;
	}
};

export const DIGITAL_WALLETS_DEFAULT_STATE = {
	enable_digital_wallets: 'yes',
	digital_wallets_button_type: 'buy',
	digital_wallets_apple_pay_button_color: 'black',
	digital_wallets_google_pay_button_color: 'black',
	digital_wallets_hide_button_options: [],
};

const digitalWalletsReducer = ( state = DIGITAL_WALLETS_DEFAULT_STATE, action ) => {
	switch ( action.type ) {
		case 'SET_DIGITAL_WALLETS_DATA':
			return {
				...state,
				...action.payload,
			};

		default:
			return state;
	}
};

export const GIFT_CARDS_DEFAULT_STATE = {
	enabled: 'no',
	title: 'Square Gift Cards',
	description: 'Allow customers to purchase and redeem gift cards during checkout.',
};

const giftCardReducer = ( state = GIFT_CARDS_DEFAULT_STATE, action ) => {
	switch ( action.type ) {
		case 'SET_GIFT_CARD_DATA':
			return {
				...state,
				...action.payload,
			};

		default:
			return state;
	}
};

export const CASH_APP_DEFAULT_STATE = {
	enabled: 'no',
	title: 'Cash App Pay',
	description: 'Pay securely using Cash App Pay.',
	transaction_type: 'charge',
	charge_virtual_orders: 'no',
	enable_paid_capture: 'no',
	button_theme: 'dark',
	button_shape: 'semiround',
};

const cashAppReducer = ( state = CASH_APP_DEFAULT_STATE, action ) => {
	switch ( action.type ) {
		case 'SET_CASH_APP_DATA':
			return {
				...state,
				...action.payload,
			};

		default:
			return state;
	}
};

export const SQUARE_SETTINGS_DEFAULT_STATE = {
	enable_sandbox: 'no',
	sandbox_application_id: '',
	sandbox_token: '',
	sandbox_location_id: '',
	system_of_record: 'disabled',
	enable_inventory_sync: 'no',
	override_product_images: 'no',
	hide_missing_products: 'no',
	sync_interval: '0.25',
	is_connected: false,
	disconnection_url: '',
	connection_url: '',
	connection_url_wizard: '',
	connection_url_sandbox: '',
	locations: [],
	enable_customer_decline_messages: "no",
	debug_mode: 'off',
	debug_logging_enabled: 'no',
}

const squareSettingsReducer = ( state = SQUARE_SETTINGS_DEFAULT_STATE, action ) => {
	switch ( action.type ) {
		case 'SET_SQUARE_SETTING_DATA':
			return {
				...state,
				...action.payload
			}

		default:
			return state;
	}
};

const PROCESS_STATUS_DEFAULT_STATE = {
	squareSettingsIsSaving: false,
	creditCardSettingsIsSaving: false,
	cashAppSettingsIsSaving: false,
	giftCardsSettingsIsSaving: false,
};

const savingProcessStatus = ( state = PROCESS_STATUS_DEFAULT_STATE, action ) => {
	switch ( action.type ) {
		case 'SET_SQUARE_SETTING_PROCESS_STATUS':
			return {
				...state,
				squareSettingsIsSaving: action.payload,
			};

		case 'SET_CREDIT_CARD_SETTING_PROCESS_STATUS':
			return {
				...state,
				creditCardSettingsIsSaving: action.payload,
			};

		case 'SET_CASH_APP_PROCESS_STATUS':
			return {
				...state,
				cashAppSettingsIsSaving: action.payload,
			};

		case 'SET_GIFT_CARDS_PROCESS_STATUS':
			return {
				...state,
				giftCardsSettingsIsSaving: action.payload,
			};

		default:
			return state;
	}
};

const STEP_DEFAULT_STATE = {
	step: 'connect-square',
	backStep: '',
};

const stepReducer = ( state = STEP_DEFAULT_STATE, action ) => {
	switch ( action.type ) {
		case 'SET_STEP':
			return {
				...state,
				step: action.payload,
			};

		case 'SET_BACK_STEP':
			return {
				...state,
				backStep: action.payload,
			};

		default:
			return state;
	}
}

export default { 
	creditCard: creditCardReducer,
	digitalWallet: digitalWalletsReducer,
	giftCard: giftCardReducer,
	cashApp: cashAppReducer,
	squareSettings: squareSettingsReducer,
	savingProcessStatus,
	step: stepReducer,
};
