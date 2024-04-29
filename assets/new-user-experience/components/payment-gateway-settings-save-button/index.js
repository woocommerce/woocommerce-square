import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { check } from '@wordpress/icons';

import { usePaymentGatewaySettings } from '../../onboarding/hooks';

const withPaymentGatewaySettingsSaveButton = ( WrappedComponent ) => {
	return ( props ) => {
		const {
			label = __( 'Apply Changes', 'woocommerce-square' ),
			afterSaveLabel = __( 'Changes Saved' ),
			afterSaveCallback = null,
			saveSettings = '',
		} = props;

		const {
			isPaymentGatewaySettingsSaving,
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
					( async () => {
						if ('gift-card' === saveSettings) {
							saveGiftCardsSettings();
						} else if ('cash-app' === saveSettings) {
							saveCashAppSettings();
						} else {
							savePaymentGatewaySettings();
						}

						if ( afterSaveCallback ) {
							afterSaveCallback();
						}
					} )()
				} }
			>
				{ null === isPaymentGatewaySettingsSaving ? afterSaveLabel : label }
			</WrappedComponent>
		)
	};
};

export const PaymentGatewaySettingsSaveButton = withPaymentGatewaySettingsSaveButton( Button );

