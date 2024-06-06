/**
 * External dependencies.
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { CashAppSetup } from '../onboarding/steps/';
import { usePaymentGatewaySettings } from '../onboarding/hooks';
import { Loader, PaymentGatewaySettingsSaveButton } from '../components';

export const CashAppSettingsApp = () => {
	const { cashAppGatewaySettingsLoaded, saveCashAppSettings } = usePaymentGatewaySettings( true );

	if ( ! cashAppGatewaySettingsLoaded ) {
		return <Loader />;
	}

	return (
		<>
			<CashAppSetup origin="settings" />
			<PaymentGatewaySettingsSaveButton onClick={ () => {
				saveCashAppSettings();
			} } />
		</>
	)
};
