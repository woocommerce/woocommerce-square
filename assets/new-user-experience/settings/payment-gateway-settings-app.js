/**
 * Internal dependencies.
 */
import {
	CreditCardSetup,
	DigitalWalletsSetup,
} from '../../new-user-experience/onboarding/steps';
import { usePaymentGatewaySettings } from '../onboarding/hooks';
import { PaymentGatewaySettingsSaveButton, Loader } from '../components';

export const PaymentGatewaySettingsApp = () => {
	const { paymentGatewaySettingsLoaded, savePaymentGatewaySettings } =
		usePaymentGatewaySettings(true);

	if (!paymentGatewaySettingsLoaded) {
		return <Loader />;
	}

	return (
		<>
			<CreditCardSetup origin="settings" />
			<DigitalWalletsSetup />
			<PaymentGatewaySettingsSaveButton
				onClick={() => {
					savePaymentGatewaySettings();
				}}
			/>
		</>
	);
};
