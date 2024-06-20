/**
 * Internal dependencies.
 */
import { GiftCardSetup } from '../../new-user-experience/onboarding/steps';
import { usePaymentGatewaySettings } from '../onboarding/hooks';
import { PaymentGatewaySettingsSaveButton, Loader } from '../components';

export const GiftCardsSettingsApp = () => {
	const { giftCardsGatewaySettingsLoaded, saveGiftCardsSettings } =
		usePaymentGatewaySettings(true);

	if (!giftCardsGatewaySettingsLoaded) {
		return <Loader />;
	}

	return (
		<>
			<GiftCardSetup />
			<PaymentGatewaySettingsSaveButton
				onClick={() => {
					saveGiftCardsSettings();
				}}
			/>
		</>
	);
};
