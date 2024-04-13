/**
 * External dependencies.
 */
import { useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies.
 */
import { useSettings } from './hooks';
import { getPaymentGatewaySettingsData } from '../utils';
import { CreditCardSetup, DigitalWalletsSetup } from './steps';

export const OnboardingApp = () => {
	const { setCreditCardData, setDigitalWalletData } = useSettings();

	/**
	 * Initialises payment gateway settings data.
	 */
	useEffect( () => {
		( async () => {
			const settings = await getPaymentGatewaySettingsData();

			if ( ! settings ) {
				return;
			}

			const { creditCard, digitalWallet } = settings;

			setCreditCardData( creditCard );
			setDigitalWalletData( digitalWallet );
		} )()
	}, [] );

	return (
		<>
			<CreditCardSetup />
			<DigitalWalletsSetup />
		</>
	)
};
