import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { check } from '@wordpress/icons';

import { useSquareSettings } from '../../settings/hooks';
import { usePaymentGatewaySettings } from '../../onboarding/hooks';

const withSaveSquareSettingsButton = ( WrappedComponent ) => {
	return ( props ) => {
		const {
			label = __( 'Apply Changes', 'woocommerce-square' ),
			afterSaveLabel = __( 'Changes Saved' ),
			nextStep,
			setStep,
			saveSettings = '',
		} = props;

		const {
			isSquareSettingsSaving,
			settings,
			saveSquareSettings,
		} = useSquareSettings();

		const { paymentGatewaySettings } = 'credit-card' === saveSettings && usePaymentGatewaySettings();
		const { savePaymentGatewaySettings } = 'credit-card' === saveSettings && usePaymentGatewaySettings();

		return (
			<WrappedComponent
				{ ...props }
				{ ...( null === isSquareSettingsSaving && { icon: check } ) }
				isBusy={ isSquareSettingsSaving }
				disabled={ isSquareSettingsSaving }
				variant="primary"
				onClick={ async () => {
					'credit-card' === saveSettings && await savePaymentGatewaySettings( paymentGatewaySettings );
					await saveSquareSettings( settings ).then( () => {
						if ( nextStep ) {
							setStep( nextStep );
						}
					} );
				} }
			>
				{ null === isSquareSettingsSaving ? afterSaveLabel : label }
			</WrappedComponent>
		)
	};
};

export const SquareSettingsSaveButton = withSaveSquareSettingsButton( Button );

