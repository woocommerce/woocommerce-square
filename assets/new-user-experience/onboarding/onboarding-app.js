/**
 * Internal dependencies.
 */
import {
	CashAppSetup,
	ConnectSetup,
	CreditCardSetup,
	DigitalWalletsSetup,
	GiftCardSetup,
	PaymentMethods,
	BusinessLocation,
} from './steps';

export const OnboardingApp = ( { step } ) => {
	return (
		<>
			{ step === 'start' && <ConnectSetup /> }
			{ step === 'business-location' && <BusinessLocation /> }
			{ step === 'credit-card' && <CreditCardSetup /> }
			{ step === 'digital-wallets' && <DigitalWalletsSetup /> }
			{ step === 'gift-card' && <GiftCardSetup /> }
			{ step === 'cash-app' && <CashAppSetup /> }
			{ step === 'payment-methods' && <PaymentMethods /> }
		</>
	)
};
