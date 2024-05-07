const getCreditCardData = ( state, key ) => key ? state.creditCard[ key ] || state.creditCard : state.creditCard;

const getDigitalWalletData = ( state, key ) => key ? state.digitalWallet[ key ] || state.digitalWallet : state.digitalWallet;

const getGiftCardData = ( state, key ) => key ? state.giftCard[ key ] || state.giftCard : state.giftCard;

const getCashAppData = ( state, key ) => key ? state.cashApp[ key ] || state.cashApp : state.cashApp;

const getSquareSettings = ( state, key ) => key ? state.squareSettings[ key ] || state.squareSettings : state.squareSettings;

const getSquareSettingsSavingProcess = ( state ) => state.savingProcessStatus.squareSettingsIsSaving;

const getCreditCardSettingsSavingProcess = ( state ) => state.savingProcessStatus.creditCardSettingsIsSaving;

const getCashAppSettingsSavingProcess = ( state ) => state.savingProcessStatus.cashAppSettingsIsSaving;

const getGiftCardsSettingsSavingProcess = ( state ) => state.savingProcessStatus.giftCardsSettingsIsSaving;

const getStep = ( state ) => state.step.step;

const getBackStep = ( state ) => state.step.backStep;

export default {
	getCreditCardData,
	getDigitalWalletData,
	getGiftCardData,
	getCashAppData,
	getSquareSettings,
	getSquareSettingsSavingProcess,
	getCreditCardSettingsSavingProcess,
	getCashAppSettingsSavingProcess,
	getGiftCardsSettingsSavingProcess,
	getStep,
	getBackStep,
};
