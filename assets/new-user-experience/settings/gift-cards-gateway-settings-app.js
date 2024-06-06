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
	} = usePaymentGatewaySettings( true );

	if ( ! giftCardsGatewaySettingsLoaded ) {
		return <Loader />;
	}

	return (
		<>
			<GiftCardSetup origin="settings" />
			<PaymentGatewaySettingsSaveButton onClick={ () => {
				saveGiftCardsSettings();
			} } />
		</>
	)
};
