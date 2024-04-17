/**
 * External dependencies.
 */
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { useState } from '@wordpress/element';

import { CreditCardSetup, DigitalWalletsSetup, GiftCardSetup } from '../../new-user-experience/onboarding/steps';
import { usePaymentGatewaySettings } from '../onboarding/hooks';
import { PaymentGatewaySettingsSaveButton } from '../components';

export const PaymentGatewaySettingsApp = () => {
	const {
		paymentGatewaySettings,
		paymentGatewaySettingsLoaded,
		savePaymentGatewaySettings,
	} = usePaymentGatewaySettings( true );
	const [ saveInProgress, setSaveInProgress ] = useState( false );

	const style = {
		width: '100%',
		maxWidth: '780px',
		marginTop: '50px',
		marginLeft: '50px',
	};

	if ( ! paymentGatewaySettingsLoaded ) {
		return null;
	}

	return (
		<div style={ style }>
			<CreditCardSetup />
			<DigitalWalletsSetup />
			<GiftCardSetup />
			<PaymentGatewaySettingsSaveButton />
		</div>
	)
};
