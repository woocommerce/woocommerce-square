import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { check } from '@wordpress/icons';

import { usePaymentGatewaySettings } from '../../onboarding/hooks';

const withPaymentGatewaySettingsSaveButton = (WrappedComponent) => {
	return (props) => {
		const {
			label = __('Apply Changes', 'woocommerce-square'),
			afterSaveLabel = __('Changes Saved!'),
			onClick,
		} = props;

		const {
			isPaymentGatewaySettingsSaving,
			isGiftCardsGatewaySettingsSaving,
			isCashAppGatewaySettingsSaving,
		} = usePaymentGatewaySettings();

		const isAtleastOneSaving =
			isPaymentGatewaySettingsSaving === null ||
			isGiftCardsGatewaySettingsSaving === null ||
			isCashAppGatewaySettingsSaving === null;

		const isSavingState = [
			isPaymentGatewaySettingsSaving,
			isGiftCardsGatewaySettingsSaving,
			isCashAppGatewaySettingsSaving,
		].some((state) => state);

		return (
			<WrappedComponent
				data-testid="payment-gateway-settings-save-button"
				{...props}
				{...(isAtleastOneSaving && { icon: check })}
				isBusy={isSavingState}
				disabled={isSavingState}
				variant="primary"
				onClick={() => onClick()}
			>
				{isAtleastOneSaving ? afterSaveLabel : label}
			</WrappedComponent>
		);
	};
};

export const PaymentGatewaySettingsSaveButton =
	withPaymentGatewaySettingsSaveButton(Button);
