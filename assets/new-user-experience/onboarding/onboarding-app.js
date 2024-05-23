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
import { recordEvent, ONBOARDING_TRACK_EVENTS } from '../../tracks';

export const OnboardingApp = () => {
	const [ settingsLoaded, setSettingsLoaded ] = useState( false );

	const {
		paymentGatewaySettingsLoaded,
		cashAppGatewaySettingsLoaded,
		giftCardsGatewaySettingsLoaded,
		paymentGatewaySettings,
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

	const {
		system_of_record,
		enable_inventory_sync,
		override_product_images,
		hide_missing_products,
		sync_interval,
		enable_customer_decline_messages,
		debug_mode,
		debug_logging_enabled,
		enable_sandbox,
	} = settings;

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
										recordEvent(
											ONBOARDING_TRACK_EVENTS.SAVE_BUSINESS_LOCATION,
											{
												number_of_locations: settings.locations.length
											}
										);
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
								recordEvent(
									ONBOARDING_TRACK_EVENTS.SAVE_DIGITAL_WALLET_SETTINGS,
									{
										digital_wallets_hide_button_options: paymentGatewaySettings.digital_wallets_hide_button_options
									}
								);
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
							let trackingProperties = {};

							if ( 'square' === system_of_record ) {
								trackingProperties = {
									system_of_record,
									enable_inventory_sync,
									override_product_images,
									hide_missing_products,
									sync_interval,
								}
							} else if ( 'woocommerce' === system_of_record ) {
								trackingProperties = {
									system_of_record,
									enable_inventory_sync,
									sync_interval,
								}
							} else {
								trackingProperties = {
									system_of_record,
								}
							}

							recordEvent(
								ONBOARDING_TRACK_EVENTS.SAVE_SYNC_SETTINGS,
								{
									...trackingProperties
								}
							);
							setStep( 'payment-complete' );
						} } />
					</>
				) }
				{ step === 'advanced-settings' && (
					<>
						<AdvancedSettings />
						<SquareSettingsSaveButton data-testid="square-settings-save-button" afterSaveCallback={ () => {
							recordEvent(
								ONBOARDING_TRACK_EVENTS.SAVE_ADVANCED_SETTINGS,
								{
									enable_customer_decline_messages,
									debug_mode,
									debug_logging_enabled,
								}
							);
							setStep( 'payment-complete' );
						} } />
					</>
				) }
				{ step === 'sandbox-settings' && (
					<>
						<SandboxSettings />
						<SquareSettingsSaveButton data-testid="square-settings-save-button" afterSaveCallback={ () => {
							recordEvent(
								ONBOARDING_TRACK_EVENTS.SAVE_SANDBOX_SETTINGS,
								{
									enable_sandbox
								}
							);
							setStep( 'payment-complete' );
						} } />
					</>
				) }
			</div>
		</>
	)
};
