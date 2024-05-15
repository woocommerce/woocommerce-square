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
import {
	getPaymentGatewaySettingsData,
	getCashAppSettingsData,
	getGiftCardsSettingsData,
} from '../../utils';

/**
 * Getters / Setters for payment gateway data store.
 */
export const usePaymentGatewaySettings = ( fromServer = false ) => {
	const dispatch = useDispatch();

	const [ paymentGatewaySettingsLoaded, setPaymentGatewaySettingsLoaded ] = useState( false );
	const [ cashAppGatewaySettingsLoaded, setCashAppGatewaySettingsLoaded ] = useState( false );
	const [ giftCardsGatewaySettingsLoaded, setGiftCardsGatewaySettingsLoaded ] = useState( false );
	const getCreditCardData = ( key ) => useSelect( ( select ) => select( store ).getCreditCardData( key ) );
	const getDigitalWalletData = ( key ) => useSelect( ( select ) => select( store ).getDigitalWalletData( key ) );
	const getGiftCardData = ( key ) => useSelect( ( select ) => select( store ).getGiftCardData( key ) );
	const getCashAppData = ( key ) => useSelect( ( select ) => select( store ).getCashAppData( key ) );
	const getCreditCardSettingsSavingProcess = ( key ) => useSelect( ( select ) => select( store ).getCreditCardSettingsSavingProcess( key ) );
	const getCashAppSettingsSavingProcess = ( key ) => useSelect( ( select ) => select( store ).getCashAppSettingsSavingProcess( key ) );
	const getGiftCardsSettingsSavingProcess = ( key ) => useSelect( ( select ) => select( store ).getGiftCardsSettingsSavingProcess( key ) );

	const setCreditCardData = ( data ) => dispatch( store ).setCreditCardData( data );
	const setDigitalWalletData = ( data ) => dispatch( store ).setDigitalWalletData( data );
	const setGiftCardData = ( data ) => dispatch( store ).setGiftCardData( data );
	const setCashAppData = ( data ) => dispatch( store ).setCashAppData( data );

	const setCreditCardSettingsSavingProcess = ( data ) => dispatch( store ).setCreditCardSettingsSavingProcess( data );
	const setCashAppSettingsSavingProcess = ( data ) => dispatch( store ).setCashAppSettingsSavingProcess( data );
	const setGiftCardsSettingsSavingProcess = ( data ) => dispatch( store ).setGiftCardsSettingsSavingProcess( data );

	const isPaymentGatewaySettingsSaving = getCreditCardSettingsSavingProcess();
	const isCashAppGatewaySettingsSaving = getCashAppSettingsSavingProcess();
	const isGiftCardsGatewaySettingsSaving = getGiftCardsSettingsSavingProcess();

	const paymentGatewaySettings = {
		...getCreditCardData(),
		...getDigitalWalletData(),
	};

	const giftCardsGatewaySettings = {
		...getGiftCardData(),
	};

	const cashAppGatewaySettings = {
		...getCashAppData(),
	};

	const savePaymentGatewaySettings = async () => {
		setCreditCardSettingsSavingProcess( true );

		const response = await apiFetch( {
			path: '/wc/v3/wc_square/payment_settings',
			method: 'POST',
			data: paymentGatewaySettings,
		} );

		setCreditCardSettingsSavingProcess( null ); // marks that the saving is over.
		await new Promise( ( resolve ) => setTimeout( resolve, 1000 ) );
		setCreditCardSettingsSavingProcess( false );
	
		return response;
	};

	const saveGiftCardsSettings = async () => {
		setGiftCardsSettingsSavingProcess( true );

		const response = await apiFetch( {
			path: '/wc/v3/wc_square/gift_cards_settings',
			method: 'POST',
			data: giftCardsGatewaySettings,
		} );

		setGiftCardsSettingsSavingProcess( null ); // marks that the saving is over.
		await new Promise( ( resolve ) => setTimeout( resolve, 1000 ) );
		setGiftCardsSettingsSavingProcess( false );
	
		return response;
	};

	const saveCashAppSettings = async () => {
		setCashAppSettingsSavingProcess( true );

		const response = await apiFetch( {
			path: '/wc/v3/wc_square/cash_app_settings',
			method: 'POST',
			data: cashAppGatewaySettings,
		} );

		setCashAppSettingsSavingProcess( null ); // marks that the saving is over.
		await new Promise( ( resolve ) => setTimeout( resolve, 1000 ) );
		setCashAppSettingsSavingProcess( false );
	
		return response;
	};

	// Credit Card + DW + GC.
	useEffect( () => {
		if ( ! fromServer ) {
			setPaymentGatewaySettingsLoaded( true );
			setGiftCardsGatewaySettingsLoaded( true );
			setCashAppGatewaySettingsLoaded( true );
			return;
		}

		( async () => {
			if ( ! paymentGatewaySettingsLoaded ) {
				const { creditCard, digitalWallet } = await getPaymentGatewaySettingsData();
				setCreditCardData( creditCard );
				setDigitalWalletData( digitalWallet );
				setPaymentGatewaySettingsLoaded( true );
			}

			if ( ! giftCardsGatewaySettingsLoaded ) {
				const { giftCard } = await getGiftCardsSettingsData();
				setGiftCardData( giftCard );
				setGiftCardsGatewaySettingsLoaded( true );
			}
		} )()
	}, [ fromServer ] );

	// Cash App
	useEffect( () => {
		if ( ! fromServer ) {
			setCashAppGatewaySettingsLoaded( true );
			return;
		}

		( async () => {
			const { cashApp } = await getCashAppSettingsData();

			if ( ! cashAppGatewaySettingsLoaded ) {
				setCashAppData( cashApp );
				setCashAppGatewaySettingsLoaded( true );
			}
		} )()
	}, [ fromServer ] );

	return {
		isPaymentGatewaySettingsSaving,
		isCashAppGatewaySettingsSaving,
		isGiftCardsGatewaySettingsSaving,
		paymentGatewaySettings,
		cashAppGatewaySettings,
		giftCardsGatewaySettings,
		paymentGatewaySettingsLoaded,
		cashAppGatewaySettingsLoaded,
		giftCardsGatewaySettingsLoaded,
		getCreditCardData,
		getDigitalWalletData,
		getGiftCardData,
		getCashAppData,
		setCreditCardData,
		setDigitalWalletData,
		setGiftCardData,
		setCashAppData,
		savePaymentGatewaySettings,
		saveGiftCardsSettings,
		saveCashAppSettings,
	}
};

/**
 * Getters / Setters for steps data store.
 */
export const useSteps = ( fromServer = false ) => {
	const dispatch = useDispatch();

	const getStep = ( key ) => useSelect( ( select ) => select( store ).getStep( key ) );
	const getBackStep = ( key ) => useSelect( ( select ) => select( store ).getBackStep( key ) );

	const setStep = ( data ) => dispatch( store ).setStep( data );
	const setBackStep = ( data ) => dispatch( store ).setBackStep( data );

	const stepData = {
		step: getStep(),
		backStep: getBackStep(),
	};

	useEffect( () => {
		if ( ! fromServer ) {
			return;
		}

		setStep( localStorage.getItem('step') || stepData.step );
		setBackStep( localStorage.getItem('backStep') || stepData.stepData );
	}, [ fromServer ] )

	return {
		stepData,
		getStep,
		getBackStep,
		setStep,
		setBackStep,
	}
};
