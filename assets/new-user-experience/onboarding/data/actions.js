const setCreditCardData = (data) => {
	return {
		type: 'SET_CREDIT_CARD_DATA',
		payload: data,
	};
};

const setDigitalWalletData = (data) => {
	return {
		type: 'SET_DIGITAL_WALLETS_DATA',
		payload: data,
	};
};

const setGiftCardData = (data) => {
	return {
		type: 'SET_GIFT_CARD_DATA',
		payload: data,
	};
};

const setCashAppData = (data) => {
	return {
		type: 'SET_CASH_APP_DATA',
		payload: data,
	};
};

const setSquareSettings = (data) => {
	return {
		type: 'SET_SQUARE_SETTING_DATA',
		payload: data,
	};
};

const setSquareSettingsSavingProcess = (data) => {
	return {
		type: 'SET_SQUARE_SETTING_PROCESS_STATUS',
		payload: data,
	};
};

const setCreditCardSettingsSavingProcess = (data) => {
	return {
		type: 'SET_CREDIT_CARD_SETTING_PROCESS_STATUS',
		payload: data,
	};
};

const setCashAppSettingsSavingProcess = (data) => {
	return {
		type: 'SET_CASH_APP_PROCESS_STATUS',
		payload: data,
	};
};

const setGiftCardsSettingsSavingProcess = (data) => {
	return {
		type: 'SET_GIFT_CARDS_PROCESS_STATUS',
		payload: data,
	};
};

const setStep = (data) => {
	return {
		type: 'SET_STEP',
		payload: data,
	};
};

const setBackStep = (data) => {
	return {
		type: 'SET_BACK_STEP',
		payload: data,
	};
};

export default {
	setCreditCardData,
	setDigitalWalletData,
	setGiftCardData,
	setCashAppData,
	setSquareSettings,
	setSquareSettingsSavingProcess,
	setCreditCardSettingsSavingProcess,
	setCashAppSettingsSavingProcess,
	setGiftCardsSettingsSavingProcess,
	setStep,
	setBackStep,
};
