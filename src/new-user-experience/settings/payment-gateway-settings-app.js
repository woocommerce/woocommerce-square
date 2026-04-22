/**
 * Internal dependencies.
 */
import {
	CreditCardSetup,
	DigitalWalletsSetup,
} from '../../new-user-experience/onboarding/steps';
import { usePaymentGatewaySettings } from '../onboarding/hooks';
import { PaymentGatewaySettingsSaveButton, Loader } from '../components';
import { CreditCardEnabledDataForm } from './credit-card-enabled-dataform';

export const PaymentGatewaySettingsApp = () => {
	const {
		paymentGatewaySettingsLoaded,
		savePaymentGatewaySettings,
		paymentGatewaySettings,
		setCreditCardData,
	} = usePaymentGatewaySettings( true );
	const { enabled } = paymentGatewaySettings;

	if ( ! paymentGatewaySettingsLoaded ) {
		return <Loader />;
	}

	return (
		<>
			<CreditCardEnabledDataForm
				enabled={ enabled }
				setCreditCardData={ setCreditCardData }
			/>
			<CreditCardSetup origin="settings" />
			<DigitalWalletsSetup />
			<PaymentGatewaySettingsSaveButton
				onClick={ () => {
					savePaymentGatewaySettings();
				} }
			/>
		</>
	);
};
