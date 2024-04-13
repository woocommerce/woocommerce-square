// Selector for credit card data.
const getCreditCardData = ( state, key ) => key ? state.creditCard[ key ] || state.creditCard : state.creditCard;


// Selector for digital wallet data.
const getDigitalWalletData = ( state, key ) => key ? state.digitalWallet[ key ] || state.digitalWallet : state.digitalWallet;

// Selector for gift card data.
const getGiftCardData = ( state, key ) => key ? state.giftCard[ key ] || state.giftCard : state.giftCard;

export default { getCreditCardData, getDigitalWalletData, getGiftCardData };
