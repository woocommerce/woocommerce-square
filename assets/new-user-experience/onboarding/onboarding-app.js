/**
 * External dependencies.
 */
import { useState } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import { useSquareSettings } from '../settings/hooks';
import { usePaymentGatewaySettings } from '../onboarding/hooks';
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

import { OnboardingHeader, PaymentGatewaySettingsSaveButton } from '../components';

const paymentGatwaySettingsWithSaveButton = ( WrappedComponent ) => ( props ) => (
	<>
		<div className="woo-square-onboarding__cover">
			<WrappedComponent { ...props } />
			<PaymentGatewaySettingsSaveButton />
		</div>
	</>
);

const WrapperCreditCardSetup = paymentGatwaySettingsWithSaveButton( CreditCardSetup );
const WrapperDigitalWalletsSetup = paymentGatwaySettingsWithSaveButton( DigitalWalletsSetup );
const WrapperGiftCardSetup = paymentGatwaySettingsWithSaveButton( GiftCardSetup );

export const OnboardingApp = () => {
	const [step, setStep] = useState('credit-card');
	const [title, setTitle] = useState('Plugin Activated');
	const [backStep, setBackStep] = useState('payment-methods');
	const {
		settings,
	} = useSquareSettings( true );

	// Calling this once to populate the data store.
	usePaymentGatewaySettings( true );

	if ( 'start' === step && settings.is_connected ) {
		setStep('business-location');
	}

	return (
		<>
			<OnboardingHeader backStep={backStep} title={title} setStep={setStep} />
			{ step === 'start' && <ConnectSetup /> }
			{ step === 'business-location' && <BusinessLocation /> }
			{ step === 'payment-methods' && <PaymentMethods setStep={setStep} /> }
			{ step === 'credit-card' && <WrapperCreditCardSetup /> }
			{ step === 'digital-wallets' && <WrapperDigitalWalletsSetup /> }
			{ step === 'gift-card' && <WrapperGiftCardSetup /> }
			{ step === 'cash-app' && <CashAppSetup /> }
			{ step === 'payment-complete' && <PaymentComplete setStep={setStep} /> }
		</>
	)
};
