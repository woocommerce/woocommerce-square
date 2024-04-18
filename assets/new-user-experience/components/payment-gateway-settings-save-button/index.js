import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { check } from '@wordpress/icons';

import { usePaymentGatewaySettings } from '../../onboarding/hooks';

const withPaymentGatewaySettingsSaveButton = ( WrappedComponent ) => {
	return ( props ) => {
		const {
			label = __( 'Apply Changes', 'woocommerce-square' ),
			afterSaveLabel = __( 'Changes applied' ),
			nextStep,
			setStep
		} = props;

		const {
			isPaymentGatewaySettingsSaving,
			paymentGatewaySettings,
			savePaymentGatewaySettings,
		} = usePaymentGatewaySettings();

		return (
			<WrappedComponent
				{ ...props }
				{ ...( null === isPaymentGatewaySettingsSaving && { icon: check } ) }
				isBusy={ isPaymentGatewaySettingsSaving }
				disabled={ isPaymentGatewaySettingsSaving }
				variant="primary"
				onClick={ () => savePaymentGatewaySettings( paymentGatewaySettings ).then( () => {
					if ( nextStep ) {
						setStep( nextStep );
					}
				} ) }
			>
				{ null === isPaymentGatewaySettingsSaving ? afterSaveLabel : label }
			</WrappedComponent>
		)
	};
};

export const PaymentGatewaySettingsSaveButton = withPaymentGatewaySettingsSaveButton( Button );

