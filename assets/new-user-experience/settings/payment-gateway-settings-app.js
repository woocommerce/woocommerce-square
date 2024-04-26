/**
 * External dependencies.
 */
import { __ } from '@wordpress/i18n';

import { CreditCardSetup, DigitalWalletsSetup } from '../../new-user-experience/onboarding/steps';
import { usePaymentGatewaySettings } from '../onboarding/hooks';
import { PaymentGatewaySettingsSaveButton, Loader } from '../components';

export const PaymentGatewaySettingsApp = () => {
	const usePaymentGatewaySettingsData = usePaymentGatewaySettings( true );
	const {
		paymentGatewaySettingsLoaded,
	} = usePaymentGatewaySettingsData;

	const style = {
		width: '100%',
		maxWidth: '780px',
		marginTop: '50px',
		marginLeft: '50px',
	};

	if ( ! paymentGatewaySettingsLoaded ) {
		return <Loader />;
	}

	return (
		<div style={ style }>
			<CreditCardSetup usePaymentGatewaySettings={usePaymentGatewaySettingsData} />
			<DigitalWalletsSetup usePaymentGatewaySettings={usePaymentGatewaySettingsData} />
			<PaymentGatewaySettingsSaveButton />
		</div>
	)
};
