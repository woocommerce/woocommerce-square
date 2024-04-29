import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { check } from '@wordpress/icons';

import { usePaymentGatewaySettings, useSteps } from '../../onboarding/hooks';

const withPaymentGatewaySettingsSaveButton = ( WrappedComponent ) => {
	return ( props ) => {
		const {
			label = __( 'Apply Changes', 'woocommerce-square' ),
			afterSaveLabel = __( 'Changes Saved' ),
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

		const {
			setStep,
		} = useSteps();

		return (
			<WrappedComponent
				{ ...props }
				{ ...( null === isPaymentGatewaySettingsSaving && { icon: check } ) }
				isBusy={ isPaymentGatewaySettingsSaving }
				disabled={ isPaymentGatewaySettingsSaving }
				variant="primary"
				onClick={ () => {
					if ('gift-card' === saveSettings) {
						saveGiftCardsSettings().then( () => {
							setStep( 'payment-complete' );
						} );
					} else if ('cash-app' === saveSettings) {
						saveCashAppSettings().then( () => {
							setStep( 'payment-complete' );
						} );
					} else {
						savePaymentGatewaySettings().then( () => {
							setStep( 'payment-complete' );
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

