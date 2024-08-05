/**
 * External dependencies.
 */
import { SelectControl } from '@wordpress/components';
import { MultiSelectControl } from '@codeamp/block-components';
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
		digital_wallets_button_type,
		digital_wallets_apple_pay_button_color,
		digital_wallets_google_pay_button_color,
		digital_wallets_hide_button_options,
	} = paymentGatewaySettings;

	if ( ! paymentGatewaySettingsLoaded ) {
		return null;
	}

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
							checked={ enable_digital_wallets === 'yes' }
							onChange={ ( value ) =>
								setDigitalWalletData( {
									enable_digital_wallets: value
										? 'yes'
										: 'no',
								} )
							}
						/>
					</InputWrapper>

					<InputWrapper
						label={ __( 'Button Type', 'woocommerce-square' ) }
					>
						<SelectControl
							data-testid="digital-wallet-gatewaybutton-type-field"
							value={ digital_wallets_button_type }
							onChange={ ( value ) =>
								setDigitalWalletData( {
									digital_wallets_button_type: value,
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

					<InputWrapper
						label={ __(
							'Google Pay Button Color',
							'woocommerce-square'
						) }
					>
						<SelectControl
							data-testid="digital-wallet-gatewaygoogle-pay-button-color-field"
							value={ digital_wallets_google_pay_button_color }
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
						label={ __(
							'Hide Digital Wallet Buttons',
							'woocommerce-square'
						) }
					>
						<MultiSelectControl
							data-testid="digital-wallet-gatewayhide-buttons-field"
							label=""
							__experimentalShowHowTo={ false }
							value={ digital_wallets_hide_button_options }
							onChange={ ( value ) =>
								setDigitalWalletData( {
									digital_wallets_hide_button_options: value,
								} )
							}
							options={ [
								{
									label: __(
										'Apple Pay',
										'woocommerce-square'
									),
									value: 'apple',
								},
								{
									label: __(
										'Google Pay',
										'woocommerce-square'
									),
									value: 'google',
								},
							] }
						/>
					</InputWrapper>
				</div>
			</Section>
		</>
	);
};
