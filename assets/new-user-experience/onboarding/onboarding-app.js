/**
 * External dependencies.
 */
import { useEffect } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import { getPaymentGatewaySettingsData } from '../utils';
import { PaymentMethods } from './steps';

export const OnboardingApp = () => {

	return (
		<>
			<PaymentMethods />
		</>
	)
};
