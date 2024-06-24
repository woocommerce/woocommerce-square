/**
 * External dependencies.
 */
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Spinner } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

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
import { ConfigureSync, AdvancedSettings, SandboxSettings } from '../modules'; // eslint-disable-line import/named

import {
	OnboardingHeader,
	PaymentGatewaySettingsSaveButton,
	SquareSettingsSaveButton,
	Loader,
} from '../components';
import { connectToSquare } from '../utils';

export const OnboardingApp = () => {
	const [settingsLoaded, setSettingsLoaded] = useState(false);
	const [sandboxConnectLabel, setSandboxConnectLabel] = useState('');
	const [isVerifyingConnection, setIsVerifyingConnection] = useState(false);
	const [sandboxConnected, setSandboxConnected] = useState(false);
	const [businessLocationLoaded, setBusinessLocationLoaded] = useState(false);

	const {
		paymentGatewaySettingsLoaded,
		cashAppGatewaySettingsLoaded,
		giftCardsGatewaySettingsLoaded,
		savePaymentGatewaySettings,
		saveGiftCardsSettings,
		saveCashAppSettings,
	} = usePaymentGatewaySettings(true);

	const { stepData, setStep, setBackStep } = useSteps(true);

	const { step, backStep } = stepData;

	const { settings, squareSettingsLoaded } = useSquareSettings(true);

	// Set info in local storage.
	useEffect(() => {
		localStorage.setItem('step', step); // eslint-disable-line no-undef
		localStorage.setItem('backStep', backStep); // eslint-disable-line no-undef
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
				apiFetch({
					path: '/wc/v3/wc_square/connected_page_visited',
					method: 'POST',
				});
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
	}, [
		step,
		squareSettingsLoaded,
		paymentGatewaySettingsLoaded,
		cashAppGatewaySettingsLoaded,
		giftCardsGatewaySettingsLoaded,
	]);

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

	const isLoadingInProgress = [
		squareSettingsLoaded,
		paymentGatewaySettingsLoaded,
		cashAppGatewaySettingsLoaded,
		giftCardsGatewaySettingsLoaded,
	].every((isLoading) => isLoading);

	if (!isLoadingInProgress) {
		return <Loader />;
	}

	// Redirect to the next page from the connect page when connection is successful.
	if (step === 'connect-square' && settings.is_connected) {
		setStep('business-location');
		setSettingsLoaded(true);
	}

	// Redirect to the connect page when connection is not successful.
	if (step !== 'connect-square' && !settings.is_connected) {
		setStep('connect-square');
		setSettingsLoaded(true);
	}

	// Set the settings loaded when the connection is not successful on the connection page.
	if (
		step === 'connect-square' &&
		!settings.is_connected &&
		!settingsLoaded
	) {
		setSettingsLoaded(true);
	}

	return (
		<>
			<OnboardingHeader />
			<div className={'woo-square-onboarding__cover ' + step}>
				{step === 'connect-square' && <ConnectSetup />}
				{step === 'business-location' && (
					<>
						<BusinessLocation />
						{settings.locations.length ? (
							<SquareSettingsSaveButton
								afterSaveLabel={__(
									'Changes Saved!',
									'woocommerce-square'
								)}
								afterSaveCallback={() => {
									setStep('payment-methods');
								}}
							/>
						) : null}
					</>
				)}
				{step === 'payment-methods' && <PaymentMethods />}
				{step === 'payment-complete' && <PaymentComplete />}
				{step === 'credit-card' && (
					<>
						<CreditCardSetup />
						<PaymentGatewaySettingsSaveButton
							data-testid="credit-card-settings-save-button"
							onClick={() => {
								(async () => {
									await savePaymentGatewaySettings();
									setStep('payment-complete');
								})();
							}}
						/>
					</>
				)}
				{step === 'digital-wallets' && (
					<>
						<DigitalWalletsSetup />
						<PaymentGatewaySettingsSaveButton
							data-testid="digital-wallets-settings-save-button"
							onClick={() => {
								(async () => {
									await savePaymentGatewaySettings();
									setStep('payment-complete');
								})();
							}}
						/>
					</>
				)}
				{step === 'gift-card' && (
					<>
						<GiftCardSetup />
						<PaymentGatewaySettingsSaveButton
							data-testid="gift-card-settings-save-button"
							onClick={() => {
								(async () => {
									await saveGiftCardsSettings();
									setStep('payment-complete');
								})();
							}}
						/>
					</>
				)}
				{step === 'cash-app' && (
					<>
						<CashAppSetup />
						<PaymentGatewaySettingsSaveButton
							data-testid="cash-app-settings-save-button"
							onClick={() => {
								(async () => {
									await saveCashAppSettings();
									setStep('payment-complete');
								})();
							}}
						/>
					</>
				)}
				{step === 'sync-settings' && (
					<>
						<ConfigureSync />
						<SquareSettingsSaveButton
							data-testid="square-settings-save-button"
							afterSaveCallback={() => {
								setStep('payment-complete');
							}}
						/>
					</>
				)}
				{step === 'advanced-settings' && (
					<>
						<AdvancedSettings />
						<SquareSettingsSaveButton
							data-testid="square-settings-save-button"
							afterSaveCallback={() => {
								setStep('payment-complete');
							}}
						/>
					</>
				)}
				{step === 'sandbox-settings' && (
					<>
						<SandboxSettings />
						{sandboxConnected &&
							(businessLocationLoaded ||
								setBusinessLocationLoaded(true)) &&
							settings.enable_sandbox === 'yes' && (
								<BusinessLocation loadData={true} />
							)}
						<SquareSettingsSaveButton
							data-testid="square-settings-save-button"
							afterSaveCallback={() => {
								(async () => {
									if (
										businessLocationLoaded ||
										settings.enable_sandbox !== 'yes'
									) {
										setStep('payment-complete');
										return;
									}

									setSandboxConnectLabel(
										__(
											'Verifying connection …',
											'woocommerce-square'
										)
									);
									setIsVerifyingConnection(true);
									const { data: locations } =
										await connectToSquare();

									if (locations.length) {
										setSandboxConnectLabel(
											__(
												'Connected to sandbox!',
												'woocommerce-square'
											)
										);
										await new Promise(setTimeout, 1000);
										setSandboxConnected(true);
									} else {
										setSandboxConnectLabel(
											__(
												'Connection to sandbox failed.',
												'woocommerce-square'
											)
										);
									}

									setIsVerifyingConnection(false);
								})();
							}}
						/>
						<p>
							{sandboxConnectLabel}
							{isVerifyingConnection && <Spinner />}
						</p>
					</>
				)}
			</div>
		</>
	);
};
