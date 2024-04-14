/**
 * External dependencies.
 */
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';

import { CreditCardSetup, DigitalWalletsSetup, GiftCardSetup } from '../../new-user-experience/onboarding/steps';
import { usePaymentGatewayData } from '../onboarding/hooks';
import { savePaymentGatewaySettings } from '../utils';

export const PaymentGatewaySettingsApp = () => {
	const { paymentGatewayData, settingsLoaded } = usePaymentGatewayData();
	const [ saveInProgress, setSaveInProgress ] = useState( false );
	const { createSuccessNotice } = useDispatch( noticesStore );

	const saveSettings = async () => {
		setSaveInProgress( true );
		const response = await savePaymentGatewaySettings( paymentGatewayData );

		if ( response.success ) {
			createSuccessNotice( __( 'Settings saved!', 'woocommerce-square' ), {
				type: 'snackbar',
			} )
		}

		setSaveInProgress( false );
	};

	const style = {
		width: '100%',
		maxWidth: '780px',
		marginTop: '50px',
		marginLeft: '50px',
	};

	if ( ! settingsLoaded ) {
		return null;
	}

	return (
		<div style={ style }>
			<CreditCardSetup />
			<DigitalWalletsSetup />
			<GiftCardSetup />
			<Button
				variant='primary'
				onClick={ () => saveSettings() }
				isBusy={ saveInProgress }
			>
				{ __( 'Save Changes', 'woocommerce-square' ) }
			</Button>
		</div>
	)
};
