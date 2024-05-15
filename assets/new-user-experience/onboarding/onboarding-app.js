/**
 * External dependencies.
 */
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

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
	const [ settingsLoaded, setSettingsLoaded ] = useState( false );

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

	const {
		settings,
		squareSettingsLoaded,
	} = useSquareSettings( true );

	// Set info in local storage.
	useEffect( () => {
		localStorage.setItem('step', step);
		localStorage.setItem('backStep', backStep);
	}, [step, backStep]);

	// Set the settings loaded value based on the step.
	useEffect( () => {
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
	useEffect( () => {
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

	const isLoadingInProgress = [
		squareSettingsLoaded,
		paymentGatewaySettingsLoaded,
		cashAppGatewaySettingsLoaded,
		giftCardsGatewaySettingsLoaded,
	].every( isLoading => isLoading );

	if ( ! isLoadingInProgress ) {
		return <Loader />;
	}

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
						{
							settings.locations.length ? (
								<SquareSettingsSaveButton
									afterSaveLabel={ __( 'Changes Saved!', 'woocommerce-square' ) }
									afterSaveCallback={ () => {
										setStep( 'payment-methods' );
									} }
								/>
							) : null
						}
					</>
				) }
				{ step === 'payment-methods' && <PaymentMethods /> }
				{ step === 'payment-complete' && <PaymentComplete /> }
				{ step === 'credit-card' && (
					<>
						<CreditCardSetup />
						<PaymentGatewaySettingsSaveButton data-testid="credit-card-settings-save-button" onClick={ () => {
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
						<PaymentGatewaySettingsSaveButton data-testid="digital-wallets-settings-save-button" onClick={ () => {
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
						<PaymentGatewaySettingsSaveButton data-testid="gift-card-settings-save-button" onClick={ () => {
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
						<PaymentGatewaySettingsSaveButton data-testid="cash-app-settings-save-button" onClick={ () => {
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
						<SquareSettingsSaveButton data-testid="square-settings-save-button" afterSaveCallback={ () => {
							setStep( 'payment-complete' );
						} } />
					</>
				) }
				{ step === 'advanced-settings' && (
					<>
						<AdvancedSettings />
						<SquareSettingsSaveButton data-testid="square-settings-save-button" afterSaveCallback={ () => {
							setStep( 'payment-complete' );
						} } />
					</>
				) }
				{ step === 'sandbox-settings' && (
					<>
						<SandboxSettings />
						<SquareSettingsSaveButton data-testid="square-settings-save-button" afterSaveCallback={ () => {
							setStep( 'payment-complete' );
						} } />
					</>
				) }
			</div>
		</>
	)
};
