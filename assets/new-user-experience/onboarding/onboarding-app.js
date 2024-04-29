/**
 * External dependencies.
 */
import { useEffect, useState } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import { useSquareSettings } from '../settings/hooks';
import { usePaymentGatewaySettings, useSteps } from './hooks';
import {
	CashAppSetup,
	ConnectSetup,
	CreditCardSetup,
	DigitalWalletsSetup,
	GiftCardSetup,
	PaymentMethods,
	BusinessLocation,
	PaymentComplete,
} from './steps';
import { ConfigureSync, AdvancedSettings, SandboxSettings } from '../modules';

import { OnboardingHeader, PaymentGatewaySettingsSaveButton, SquareSettingsSaveButton, Loader } from '../components';

export const OnboardingApp = () => {
	const [settingsLoaded, setSettingsLoaded ] = useState( false );

	const {
		paymentGatewaySettingsLoaded,
		cashAppGatewaySettingsLoaded,
		giftCardsGatewaySettingsLoaded,
		savePaymentGatewaySettings,
		saveGiftCardsSettings,
		saveCashAppSettings,
	} = usePaymentGatewaySettings( true );

	const {
		stepData,
		setStep,
		setBackStep,
	} = useSteps( true );

	const { step, backStep } = stepData;

	const useSquareSettingsData = useSquareSettings( true );

	const {
		settings,
		squareSettingsLoaded
	} = useSquareSettingsData;

	// Set info in local storage.
	useEffect(() => {
		localStorage.setItem('step', step);
		localStorage.setItem('backStep', backStep);
	}, [step, backStep]);

	// Set the settings loaded value based on the step.
	useEffect(() => {
		switch (step) {
			case 'connect-square':
				setSettingsLoaded(false);
				break;
			case 'cash-app':
				setSettingsLoaded(cashAppGatewaySettingsLoaded);
				break;
			case 'gift-card':
			case 'payment-methods':
				setSettingsLoaded(giftCardsGatewaySettingsLoaded);
				break;
			case 'sync-settings':
			case 'advanced-settings':
			case 'sandbox-settings':
				setSettingsLoaded(squareSettingsLoaded);
				break;
			default:
				setSettingsLoaded(paymentGatewaySettingsLoaded);
				break;
		}
	}, [step, squareSettingsLoaded, paymentGatewaySettingsLoaded, cashAppGatewaySettingsLoaded, giftCardsGatewaySettingsLoaded]);

	// Set the backStep value.
	useEffect(() => {
		switch (step) {
			case 'connect-square':
			case 'business-location':
				setBackStep('');
				break;
			case 'payment-methods':
				setBackStep('business-location');
				break;
			case 'payment-complete':
				setBackStep('payment-methods');
				break;
			default:
				setBackStep('payment-complete');
				break;
		}
	}, [step]);

	if ( ! squareSettingsLoaded ) {
		return <Loader />;
	}

	const paymentGatwaySettingsWithSaveButton = ( WrappedComponent ) => ( props ) => (
		<>
			<WrappedComponent { ...props } />
			<PaymentGatewaySettingsSaveButton />
		</>
	);
	
	const squareSettingsWithSaveButton = ( WrappedComponent ) => ( props ) => (
		<>
			<WrappedComponent { ...props } />
			<SquareSettingsSaveButton />
		</>
	);

	// Redirect to the next page from the connect page when connection is successful.
	if ( 'connect-square' === step && settings.is_connected ) {
		setStep('business-location');
		setSettingsLoaded(true);
	}

	// Redirect to the connect page when connection is not successful.
	if ( 'connect-square' !== step && ! settings.is_connected ) {
		setStep('connect-square');
		setSettingsLoaded(true);
	}

	// Set the settings loaded when the connection is not successful on the connection page.
	if ( 'connect-square' === step && ! settings.is_connected && ! settingsLoaded ) {
		setSettingsLoaded(true);
	}

	return (
		<>
			<OnboardingHeader />
			<div className={'woo-square-onboarding__cover ' + step}>
				{ step === 'connect-square' && <ConnectSetup /> }
				{ step === 'business-location' && (
					<>
						<BusinessLocation />
						<SquareSettingsSaveButton afterSaveCallback={ () => {
							setStep( 'payment-methods' );
						} } />
					</>
				) }
				{ step === 'payment-methods' && <PaymentMethods /> }
				{ step === 'payment-complete' && <PaymentComplete /> }
				{ step === 'credit-card' && (
					<>
						<CreditCardSetup />
						<PaymentGatewaySettingsSaveButton onClick={ () => {
							( async () => {
								await savePaymentGatewaySettings();
								setStep( 'payment-complete' );
							} )()
						} } />
					</>
				) }
				{ step === 'digital-wallets' && (
					<>
						<DigitalWalletsSetup />
						<PaymentGatewaySettingsSaveButton onClick={ () => {
							( async () => {
								await savePaymentGatewaySettings();
								setStep( 'payment-complete' );
							} )()
						} } />
					</>
				) }
				{ step === 'gift-card' && (
					<>
						<GiftCardSetup />
						<PaymentGatewaySettingsSaveButton onClick={ () => {
							( async () => {
								await saveGiftCardsSettings();
								setStep( 'payment-complete' );
							} )()
						} } />
					</>
				) }
				{ step === 'cash-app' && (
					<>
						<CashAppSetup />
						<PaymentGatewaySettingsSaveButton onClick={ () => {
							( async () => {
								await saveCashAppSettings();
								setStep( 'payment-complete' );
							} )()
						} } />
					</>
				) }
				{ step === 'sync-settings' && (
					<>
						<ConfigureSync />
						<SquareSettingsSaveButton afterSaveCallback={ () => {
							setStep( 'payment-methods' );
						} } />
					</>
				) }
				{ step === 'advanced-settings' && (
					<>
						<AdvancedSettings />
						<SquareSettingsSaveButton afterSaveCallback={ () => {
							setStep( 'payment-methods' );
						} } />
					</>
				) }
				{ step === 'sandbox-settings' && (
					<>
						<SandboxSettings />
						<SquareSettingsSaveButton afterSaveCallback={ () => {
							setStep( 'payment-methods' );
						} } />
					</>
				) }
			</div>
		</>
	)
};
