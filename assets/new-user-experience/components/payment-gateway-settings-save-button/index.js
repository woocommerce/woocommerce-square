import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { check } from '@wordpress/icons';

import { usePaymentGatewaySettings } from '../../onboarding/hooks'

const withPaymentGatewaySettingsSaveButton = ( WrappedComponent ) => {
	return ( props ) => {
		const {
			label = __( 'Apply Changes', 'woocommerce-square' ),
			afterSaveLabel = __( 'Changes Saved' ),
			onClick,
		} = props;

		const {
			isPaymentGatewaySettingsSaving,
			isGiftCardsGatewaySettingsSaving,
			isCashAppGatewaySettingsSaving,
		} = usePaymentGatewaySettings();

		const isSavingState = [
			isPaymentGatewaySettingsSaving,
			isGiftCardsGatewaySettingsSaving,
			isCashAppGatewaySettingsSaving,
		].some( state => state );

		return (
			<WrappedComponent
				{ ...props }
				{ ...( null === isPaymentGatewaySettingsSaving && { icon: check } ) }
				isBusy={ isSavingState }
				disabled={ isSavingState }
				variant="primary"
				onClick={ () => onClick() }
			>
				{ null === isPaymentGatewaySettingsSaving ? afterSaveLabel : label }
			</WrappedComponent>
		)
	};
};

export const PaymentGatewaySettingsSaveButton = withPaymentGatewaySettingsSaveButton( Button );

