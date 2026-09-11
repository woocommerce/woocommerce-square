/**
 * External dependencies.
 */
import { SelectControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import parse from 'html-react-parser';

/**
 * Internal dependencies.
 */
import {
	Section,
	SectionTitle,
	SectionDescription,
	InputWrapper,
	SquareCheckboxControl,
} from '../../../components';
import { usePaymentGatewaySettings } from '../../hooks';

export const DigitalWalletsSetup = () => {
	const {
		paymentGatewaySettingsLoaded,
		paymentGatewaySettings,
		setDigitalWalletData,
	} = usePaymentGatewaySettings();

	const {
		enable_digital_wallets,
		digital_wallets_google_pay_enabled,
		digital_wallets_apple_pay_enabled,
		digital_wallets_google_pay_button_type,
		digital_wallets_apple_pay_button_type,
		digital_wallets_apple_pay_button_color,
		digital_wallets_google_pay_button_color,
	} = paymentGatewaySettings;

	if ( ! paymentGatewaySettingsLoaded ) {
		return null;
	}

	const parentEnabled = enable_digital_wallets === 'yes';
	const googleEnabled = digital_wallets_google_pay_enabled === 'yes';
	const appleEnabled = digital_wallets_apple_pay_enabled === 'yes';

	/**
	 * Updates a per-wallet enable flag and applies parent/child coupling:
	 * both wallets off → disable parent; parent on with both off → enable both.
	 *
	 * @param {string}  key     Option key being changed.
	 * @param {boolean} checked New checked state.
	 */
	const updateWalletEnabled = ( key, checked ) => {
		const nextGoogle =
			key === 'digital_wallets_google_pay_enabled'
				? checked
				: googleEnabled;
		const nextApple =
			key === 'digital_wallets_apple_pay_enabled'
				? checked
				: appleEnabled;

		const updates = {
			[ key ]: checked ? 'yes' : 'no',
		};

		if ( ! nextGoogle && ! nextApple ) {
			updates.enable_digital_wallets = 'no';
		}

		setDigitalWalletData( updates );
	};

	/**
	 * Toggles the parent digital wallets enable and defaults both children on
	 * when turning the parent on while both are off.
	 *
	 * @param {boolean} checked New checked state.
	 */
	const updateParentEnabled = ( checked ) => {
		const updates = {
			enable_digital_wallets: checked ? 'yes' : 'no',
		};

		if ( checked && ! googleEnabled && ! appleEnabled ) {
			updates.digital_wallets_google_pay_enabled = 'yes';
			updates.digital_wallets_apple_pay_enabled = 'yes';
		}

		setDigitalWalletData( updates );
	};

	return (
		<>
			<Section>
				<SectionTitle
					title={ __(
						'Manage Digital Wallet Settings',
						'woocommerce-square'
					) }
				/>
				<SectionDescription>
					{ __(
						'Accept payments with Apple Pay and Google Pay on your store, available in select countries. Enabling digital wallets adds payment buttons to Product, Cart and Checkout pages.',
						'woocommerce-square'
					) }
				</SectionDescription>

				<div className="woo-square-wizard__fields">
					<InputWrapper
						label={ __( 'Enable / Disable', 'woocommerce-square' ) }
						description={ parse(
							sprintf(
								/* translators: %1$s: opening link tag, %2$s: closing link tag */
								__(
									'Allow customers to pay with Apple Pay or Google Pay from your Product, Cart and Checkout pages. Read more about the availablity of digital wallets in our %1$sdocumentation%2$s.',
									'woocommerce-square'
								),
								'<a target="_blank" href="https://docs.woocommerce.com/document/woocommerce-square/">',
								'</a>'
							)
						) }
					>
						<SquareCheckboxControl
							data-testid="digital-wallet-gateway-toggle-field"
							label={ __(
								'Enable digital wallets.',
								'woocommerce-square'
							) }
							checked={ parentEnabled }
							onChange={ updateParentEnabled }
						/>
					</InputWrapper>

					<InputWrapper
						label={ __( 'Google Pay', 'woocommerce-square' ) }
					>
						<SquareCheckboxControl
							data-testid="digital-wallet-google-pay-enabled-field"
							label={ __(
								'Enable Google Pay',
								'woocommerce-square'
							) }
							checked={ googleEnabled }
							disabled={ ! parentEnabled }
							onChange={ ( value ) =>
								updateWalletEnabled(
									'digital_wallets_google_pay_enabled',
									value
								)
							}
						/>
					</InputWrapper>
					<InputWrapper
						label={ __(
							'Google Pay Button Label',
							'woocommerce-square'
						) }
					>
						<SelectControl
							data-testid="digital-wallet-google-pay-button-type-field"
							value={ digital_wallets_google_pay_button_type }
							disabled={ ! parentEnabled || ! googleEnabled }
							onChange={ ( value ) =>
								setDigitalWalletData( {
									digital_wallets_google_pay_button_type:
										value,
								} )
							}
							options={ [
								{
									label: __(
										'Buy with Google Pay',
										'woocommerce-square'
									),
									value: 'long',
								},
								{
									label: __(
										'Google Pay (icon only)',
										'woocommerce-square'
									),
									value: 'short',
								},
							] }
						/>
					</InputWrapper>
					<InputWrapper
						label={ __(
							'Google Pay Button Color',
							'woocommerce-square'
						) }
					>
						<SelectControl
							data-testid="digital-wallet-gatewaygoogle-pay-button-color-field"
							value={ digital_wallets_google_pay_button_color }
							disabled={ ! parentEnabled || ! googleEnabled }
							onChange={ ( value ) =>
								setDigitalWalletData( {
									digital_wallets_google_pay_button_color:
										value,
								} )
							}
							options={ [
								{
									label: __( 'Black', 'woocommerce-square' ),
									value: 'black',
								},
								{
									label: __( 'White', 'woocommerce-square' ),
									value: 'white',
								},
							] }
						/>
					</InputWrapper>
					<InputWrapper
						label={ __( 'Apple Pay', 'woocommerce-square' ) }
					>
						<SquareCheckboxControl
							data-testid="digital-wallet-apple-pay-enabled-field"
							label={ __(
								'Enable Apple Pay',
								'woocommerce-square'
							) }
							checked={ appleEnabled }
							disabled={ ! parentEnabled }
							onChange={ ( value ) =>
								updateWalletEnabled(
									'digital_wallets_apple_pay_enabled',
									value
								)
							}
						/>
					</InputWrapper>
					<InputWrapper
						label={ __(
							'Apple Pay Button Label',
							'woocommerce-square'
						) }
					>
						<SelectControl
							data-testid="digital-wallet-apple-pay-button-type-field"
							value={ digital_wallets_apple_pay_button_type }
							disabled={ ! parentEnabled || ! appleEnabled }
							onChange={ ( value ) =>
								setDigitalWalletData( {
									digital_wallets_apple_pay_button_type:
										value,
								} )
							}
							options={ [
								{
									label: __(
										'Buy Now',
										'woocommerce-square'
									),
									value: 'buy',
								},
								{
									label: __( 'Donate', 'woocommerce-square' ),
									value: 'donate',
								},
								{
									label: __(
										'No Text',
										'woocommerce-square'
									),
									value: 'plain',
								},
							] }
						/>
					</InputWrapper>
					<InputWrapper
						label={ __(
							'Apple Pay Button Color',
							'woocommerce-square'
						) }
					>
						<SelectControl
							data-testid="digital-wallet-gatewayapple-pay-button-color-field"
							value={ digital_wallets_apple_pay_button_color }
							disabled={ ! parentEnabled || ! appleEnabled }
							onChange={ ( value ) =>
								setDigitalWalletData( {
									digital_wallets_apple_pay_button_color:
										value,
								} )
							}
							options={ [
								{
									label: __( 'Black', 'woocommerce-square' ),
									value: 'black',
								},
								{
									label: __( 'White', 'woocommerce-square' ),
									value: 'white',
								},
								{
									label: __(
										'White with outline',
										'woocommerce-square'
									),
									value: 'white-outline',
								},
							] }
						/>
					</InputWrapper>
				</div>
			</Section>
		</>
	);
};
