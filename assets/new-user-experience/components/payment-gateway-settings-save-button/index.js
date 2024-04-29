import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { check } from '@wordpress/icons';

import { usePaymentGatewaySettings } from '../../onboarding/hooks';

const withPaymentGatewaySettingsSaveButton = ( WrappedComponent ) => {
	return ( props ) => {
		const {
			label = __( 'Apply Changes', 'woocommerce-square' ),
			afterSaveLabel = __( 'Changes Saved' ),
			nextStep,
			setStep,
			saveSettings = '',
		} = props;

		const {
			isPaymentGatewaySettingsSaving,
			paymentGatewaySettings,
			giftCardsGatewaySettings,
			cashAppGatewaySettings,
			savePaymentGatewaySettings,
			saveGiftCardsSettings,
			saveCashAppSettings,
		} = usePaymentGatewaySettings();

		return (
			<WrappedComponent
				{ ...props }
				{ ...( null === isPaymentGatewaySettingsSaving && { icon: check } ) }
				isBusy={ isPaymentGatewaySettingsSaving }
				disabled={ isPaymentGatewaySettingsSaving }
				variant="primary"
				onClick={ () => {
					if ('gift-card' === saveSettings) {
						saveGiftCardsSettings( giftCardsGatewaySettings ).then( () => {
							if ( nextStep ) {
								setStep( nextStep );
							}
						} );
					} else if ('cash-app' === saveSettings) {
						saveCashAppSettings( cashAppGatewaySettings ).then( () => {
							if ( nextStep ) {
								setStep( nextStep );
							}
						} );
					} else {
						savePaymentGatewaySettings( paymentGatewaySettings ).then( () => {
							if ( nextStep ) {
								setStep( nextStep );
							}
						} );
					}
				} }
			>
				{ null === isPaymentGatewaySettingsSaving ? afterSaveLabel : label }
			</WrappedComponent>
		)
	};
};

export const PaymentGatewaySettingsSaveButton = withPaymentGatewaySettingsSaveButton( Button );

