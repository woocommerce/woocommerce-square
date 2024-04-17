/**
 * External dependencies.
 */
import { useState } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import { useSquareSettings } from '../settings/hooks';
import {
	CashAppSetup,
	ConnectSetup,
	CreditCardSetup,
	DigitalWalletsSetup,
	GiftCardSetup,
	PaymentMethods,
	BusinessLocation,
} from './steps';

export const OnboardingApp = () => {
	const [step, setStep] = useState('start');
	const {
		settings,
	} = useSquareSettings( true );

	if ( 'start' === step && settings.is_connected ) {
		setStep('payment-methods');
	}

	return (
		<>
			{ step === 'start' && <ConnectSetup /> }
			{ step === 'business-location' && <BusinessLocation /> }
			{ step === 'payment-methods' && <PaymentMethods setStep={setStep} /> }
			{ step === 'credit-card' && <CreditCardSetup /> }
			{ step === 'digital-wallets' && <DigitalWalletsSetup /> }
			{ step === 'gift-card' && <GiftCardSetup /> }
			{ step === 'cash-app' && <CashAppSetup /> }
		</>
	)
};
