/**
 * External dependencies.
 */
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';

/**
 * Internal dependencies.
 */
import { GiftCardSetup } from '../../new-user-experience/onboarding/steps';
import { usePaymentGatewaySettings } from '../onboarding/hooks';
import { PaymentGatewaySettingsSaveButton, Loader } from '../components';

export const GiftCardsSettingsApp = () => {
	const {
		giftCardsGatewaySettingsLoaded,
		saveGiftCardsSettings,
		giftCardsGatewaySettings
	} = usePaymentGatewaySettings( true );
	const [ saveInProgress, setSaveInProgress ] = useState( false );
	const { createSuccessNotice } = useDispatch( noticesStore );


	if ( ! giftCardsGatewaySettingsLoaded ) {
		return <Loader />;
	}

	const saveSettings = async () => {
		setSaveInProgress( true );
		const response = await saveGiftCardsSettings( giftCardsGatewaySettings );

		if ( response.success ) {
			createSuccessNotice( __( 'Settings saved!', 'woocommerce-square' ), {
				type: 'snackbar',
			} )
		}

		setSaveInProgress( false );
	};

	return (
		<>
			<GiftCardSetup />
			<PaymentGatewaySettingsSaveButton onClick={ () => {
				saveGiftCardsSettings();
			} } />
		</>
	)
};
