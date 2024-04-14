/**
 * External dependencies.
 */
import { useSelect, useDispatch } from '@wordpress/data';
import { useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies.
 */
import store from '../data/store';

/**
 * Getters / Setters for payment gateway data store.
 */
export const useSettings = () => {
	const dispatch = useDispatch();

	const getCreditCardData = ( key ) => useSelect( ( select ) => select( store ).getCreditCardData( key ));
	const getDigitalWalletData = ( key ) => useSelect( ( select ) => select( store ).getDigitalWalletData( key ));
	const getGiftCardData = ( key ) => useSelect( ( select ) => select( store ).getGiftCardData( key ));

	const setCreditCardData = ( data ) => dispatch( store ).setCreditCardData( data );
	const setDigitalWalletData = ( data ) => dispatch( store ).setDigitalWalletData( data );
	const setGiftCardData = ( data ) => dispatch( store ).setGiftCardData( data );

	return {
		getCreditCardData,
		getDigitalWalletData,
		getGiftCardData,
		setCreditCardData,
		setDigitalWalletData,
		setGiftCardData
	};
};

export const usePaymentGatewayData = () => {
	const {
		getCreditCardData,
		getDigitalWalletData,
		getGiftCardData,
		setCreditCardData,
		setDigitalWalletData,
		setGiftCardData,
	} = useSettings();

	/**
	 * Initializes payment gateway data store.
	 */
	useEffect( () => {
		apiFetch( { path: '/wc/v3/wc_square/payment_settings' } ).then( ( settings ) => {
			const creditCard = {
				enabled: settings.enabled,
				title: settings.title,
				description: settings.description,
				transaction_type: settings.transaction_type,
				charge_virtual_orders: settings.charge_virtual_orders,
				enable_paid_capture: settings.enable_paid_capture,
				card_types: settings.card_types || [],
				tokenization: settings.tokenization,
			};

			const digitalWallet = {
				enable_digital_wallets: settings.enable_digital_wallets,
				digital_wallets_button_type: settings.digital_wallets_button_type,
				digital_wallets_apple_pay_button_color: settings.digital_wallets_apple_pay_button_color,
				digital_wallets_google_pay_button_color: settings.digital_wallets_google_pay_button_color,
				digital_wallets_hide_button_options: settings.digital_wallets_hide_button_options || [],
			};

			const giftCard = {
				enable_gift_cards: settings.enable_gift_cards
			};

			setCreditCardData( creditCard );
			setDigitalWalletData( digitalWallet );
			setGiftCardData( giftCard );
		} );
	}, [] );

	const paymentGatewayData = {
		...getCreditCardData(),
		...getDigitalWalletData(),
		...getGiftCardData(),
	};

	return { setCreditCardData, setDigitalWalletData, setGiftCardData, paymentGatewayData };
};
