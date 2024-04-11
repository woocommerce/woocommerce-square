const setCreditCardData = ( data ) => {
	return {
		type: 'SET_CREDIT_CARD_DATA',
		payload: data,
	};
};

const setDigitalWalletData = ( data ) => {
	return {
		type: 'SET_DIGITAL_WALLETS_DATA',
		payload: data,
	};
};

const setGiftCardData = ( data ) => {
	return {
		type: 'SET_GIFT_CARD_DATA',
		payload: data,
	};
};

export default { setCreditCardData, setDigitalWalletData, setGiftCardData };
