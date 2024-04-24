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
import { Loader } from '../components';

export const GiftCardsSettingsApp = () => {
	const usePaymentGatewaySettingsData = usePaymentGatewaySettings( true );
	const {
		giftCardsGatewaySettingsLoaded,
        saveGiftCardsSettings,
        giftCardsGatewaySettings
	} = usePaymentGatewaySettingsData;
    const [ saveInProgress, setSaveInProgress ] = useState( false );
    const { createSuccessNotice } = useDispatch( noticesStore );


    if ( ! giftCardsGatewaySettingsLoaded ) {
		return <Loader />;
	}

	const style = {
		width: '100%',
		maxWidth: '780px',
		marginTop: '50px',
		marginLeft: '50px',
	};

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
		<div style={ style }>
			<GiftCardSetup usePaymentGatewaySettings={usePaymentGatewaySettingsData} />
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
