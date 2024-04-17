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
} from './steps';

import { PaymentGatewaySettingsSaveButton } from '../components';

const paymentGatwaySettingsWithSaveButton = ( WrappedComponent ) => ( props ) => (
	<>
		<WrappedComponent { ...props } />
		<PaymentGatewaySettingsSaveButton />
	</>
);

const WrapperCreditCardSetup = paymentGatwaySettingsWithSaveButton( CreditCardSetup );
const WrapperDigitalWalletsSetup = paymentGatwaySettingsWithSaveButton( DigitalWalletsSetup );
const WrapperGiftCardSetup = paymentGatwaySettingsWithSaveButton( GiftCardSetup );

export const OnboardingApp = () => {
	const [step, setStep] = useState('start');
	const {
		settings,
	} = useSquareSettings( true );

	// Calling this once to populate the data store.
	usePaymentGatewaySettings( true );

	if ( 'start' === step && settings.is_connected ) {
		setStep('payment-methods');
	}

	return (
		<>
			{ step === 'start' && <ConnectSetup /> }
			{ step === 'business-location' && <BusinessLocation /> }
			{ step === 'payment-methods' && <PaymentMethods setStep={setStep} /> }
			{ step === 'credit-card' && <WrapperCreditCardSetup /> }
			{ step === 'digital-wallets' && <WrapperDigitalWalletsSetup /> }
			{ step === 'gift-card' && <WrapperGiftCardSetup /> }
			{ step === 'cash-app' && <CashAppSetup /> }
		</>
	)
};
