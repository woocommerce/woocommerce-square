/**
 * External dependencies.
 */
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';

import { CreditCardSetup, DigitalWalletsSetup, GiftCardSetup } from '../../new-user-experience/onboarding/steps';
import { usePaymentGatewayData, useSettings } from '../onboarding/hooks';
import { savePaymentGatewaySettings } from '../utils';

export const PaymentGatewaySettingsApp = () => {
	const style = {
		width: '100%',
		maxWidth: '780px',
		marginTop: '50px',
		marginLeft: '50px',
	};

	const { paymentGatewayData } = usePaymentGatewayData();

	return (
		<div style={ style }>
			<CreditCardSetup />
			<DigitalWalletsSetup />
			<GiftCardSetup />
			<Button
				variant='primary'
				onClick={ () => savePaymentGatewaySettings( paymentGatewayData ) }
			>
				{ __( 'Save Changes', 'woocommerce-square' ) }
			</Button>
		</div>
	)
};
