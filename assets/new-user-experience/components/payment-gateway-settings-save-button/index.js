import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { check } from '@wordpress/icons';

import { usePaymentGatewaySettings, useSteps } from '../../onboarding/hooks'

const withPaymentGatewaySettingsSaveButton = ( WrappedComponent ) => {
	return ( props ) => {
		const {
			label = __( 'Apply Changes', 'woocommerce-square' ),
			afterSaveLabel = __( 'Changes Saved' ),
		} = props;

		const {
			isPaymentGatewaySettingsSaving,
			savePaymentGatewaySettings,
			saveGiftCardsSettings,
			saveCashAppSettings,
		} = usePaymentGatewaySettings();

		const {
			setStep,
			stepData: {
				step,
			} = {}
		} = useSteps();

		return (
			<WrappedComponent
				{ ...props }
				{ ...( null === isPaymentGatewaySettingsSaving && { icon: check } ) }
				isBusy={ isPaymentGatewaySettingsSaving }
				disabled={ isPaymentGatewaySettingsSaving }
				variant="primary"
				onClick={ () => {
					( async () => {
						if ('gift-card' === step) {
							await saveGiftCardsSettings();
						} else if ('cash-app' === step) {
							await saveCashAppSettings();
						} else {
							await savePaymentGatewaySettings();
						}

						setStep( 'payment-complete' );
					} )()
				} }
			>
				{ null === isPaymentGatewaySettingsSaving ? afterSaveLabel : label }
			</WrappedComponent>
		)
	};
};

export const PaymentGatewaySettingsSaveButton = withPaymentGatewaySettingsSaveButton( Button );

