export const CREDIT_CARD_DEFAULT_STATE = {
	enabled: 'no',
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
	enable_gift_cards: 'no',
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
	debug_mode: 'semiround',
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

export default { 
	creditCard: creditCardReducer,
	digitalWallet: digitalWalletsReducer,
	giftCard: giftCardReducer,
	cashApp: cashAppReducer,
};
