/**
 * External dependencies.
 */
import { useEffect } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import { getPaymentGatewaySettingsData } from '../utils';
import { ConnectSetup, CreditCardSetup, PaymentMethods } from './steps';

export const OnboardingApp = ( { step } ) => {

	return (
		<>
			{ step === 'start' && <ConnectSetup /> }
			{ step === 'payments' && <PaymentMethods /> }
		</>
	)
};
