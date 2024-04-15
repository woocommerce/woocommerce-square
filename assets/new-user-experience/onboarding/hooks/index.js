/**
 * External dependencies.
 */
import { useSelect, useDispatch } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies.
 */
import store from '../data/store';
import { getPaymentGatewaySettingsData } from '../../utils';

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

	const [ settingsLoaded, setSettingsLoaded ] = useState( false );

	/**
	 * Initializes payment gateway data store.
	 */
	useEffect( () => {
		( async function () {
			const { creditCard, digitalWallet, giftCard } = await getPaymentGatewaySettingsData();

			setCreditCardData( creditCard );
			setDigitalWalletData( digitalWallet );
			setGiftCardData( giftCard );
			setSettingsLoaded( true );
		} )();
	}, [] );

	const paymentGatewayData = {
		...getCreditCardData(),
		...getDigitalWalletData(),
		...getGiftCardData(),
	};

	return {
		getCreditCardData,
		getDigitalWalletData,
		getGiftCardData,
		setCreditCardData,
		setDigitalWalletData,
		setGiftCardData,
		paymentGatewayData,
		settingsLoaded
	};
};
