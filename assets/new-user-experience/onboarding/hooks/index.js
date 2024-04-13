/**
 * External dependencies.
 */
import { useSelect, useDispatch } from '@wordpress/data';

/**
 * Internal dependencies.
 */
import { store } from '../data/store';

/**
 * Getters / Setters for payment gateway data store.
 */
export const useSettings = () => {
	const dispatch = useDispatch();

	const getCreditCardData = ( key ) => useSelect((select) => select( store ).getCreditCardData( key ));
	const getDigitalWalletData = ( key ) => useSelect((select) => select( store ).getDigitalWalletData( key ));
	const getGiftCardData = ( key ) => useSelect((select) => select( store ).getGiftCardData( key ));

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
