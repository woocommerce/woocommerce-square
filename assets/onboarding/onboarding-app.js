/**
 * External dependencies.
 */
import { useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies.
 */
import { useSettings } from './hooks';
import { CreditCard } from './steps/setup/credit-card';
import { DigitalWallets } from './steps/setup/digital-wallets';

export const OnboardingApp = () => {
	const { setCreditCardData, setDigitalWalletData } = useSettings();

	/**
	 * Initializes payment gateway data store.
	 */
	useEffect( () => {
		apiFetch( { path: '/wc/v3/wc_square/payment_settings' } ).then( ( settings ) => {
			const creditCard = {
				enabled: settings.enabled,
				title: settings.title,
				description: settings.description,
				transaction_type: settings.transaction_type,
				charge_virtual_orders: settings.charge_virtual_orders,
				enable_paid_capture: settings.enable_paid_capture,
				card_types: settings.card_types,
				tokenization: settings.tokenization,
			};

			const digitalWallet = {
				enable_digital_wallets: settings.enable_digital_wallets,
				digital_wallets_button_type: settings.digital_wallets_button_type,
				digital_wallets_apple_pay_button_color: settings.digital_wallets_apple_pay_button_color,
				digital_wallets_google_pay_button_color: settings.digital_wallets_google_pay_button_color,
				digital_wallets_hide_button_options: settings.digital_wallets_hide_button_options || [],
			};

			setCreditCardData( creditCard );
			setDigitalWalletData( digitalWallet );
		} );
	}, [] );

	return (
		<>
			<CreditCard />
			<DigitalWallets />
		</>
	)
};
