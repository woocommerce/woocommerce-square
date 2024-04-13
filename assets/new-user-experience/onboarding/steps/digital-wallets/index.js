/**
 * External dependencies.
 */
import {
	TextControl,
	TextareaControl,
	SelectControl,
} from '@wordpress/components';
import { MultiSelectControl } from '@codeamp/block-components';
import { __ } from '@wordpress/i18n';

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

import { useSettings } from '../../hooks';

export const DigitalWalletsSetup = () => {
	const { setDigitalWalletData, getDigitalWalletData } = useSettings();
	const {
		digital_wallets_button_type,
		digital_wallets_apple_pay_button_color,
		digital_wallets_google_pay_button_color,
		digital_wallets_hide_button_options,
	} = getDigitalWalletData();

	return (
		<>
			<Section>
				<SectionTitle title={ __( 'Manage Digital Wallet Settings', 'woocommerce-square' ) } />
				<SectionDescription>
					{ __( 'Accept payments with Apple Pay and Google Pay on your store, available in select countries. Enabling digital wallets adds payment buttons to Product, Cart, and Checkout pages.', 'woocommerce-square' ) }
				</SectionDescription>

				<InputWrapper label={ __( 'Button Type', 'woocommerce-square' ) } >
					<TextControl
						value={ digital_wallets_button_type }
						onChange={ ( digital_wallets_button_type ) => setDigitalWalletData( { digital_wallets_button_type } ) }
						options={ [
							{
								label: __( 'Buy Now', 'woocommerce-square' ),
								value: 'buy',
							},
							{
								label: __( 'Donate', 'woocommerce-square' ),
								value: 'donate',
							},
							{
								label: __( 'No Text', 'woocommerce-square' ),
								value: 'plain',
							},
						] }
					/>
				</InputWrapper>

				<InputWrapper label={ __( 'Apple Pay Button Color', 'woocommerce-square' ) } >
					<SelectControl
						value={ digital_wallets_apple_pay_button_color }
						onChange={ ( digital_wallets_apple_pay_button_color ) => setDigitalWalletData( { digital_wallets_apple_pay_button_color } ) }
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
								label: __( 'White with outline', 'woocommerce-square' ),
								value: 'white-outline',
							},
						] }
					/>
				</InputWrapper>

				<InputWrapper label={ __( 'Google Pay Button Color', 'woocommerce-square' ) } >
					<SelectControl
						value={ digital_wallets_google_pay_button_color }
						onChange={ ( digital_wallets_google_pay_button_color ) => setDigitalWalletData( { digital_wallets_google_pay_button_color } ) }
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

				<InputWrapper label={ __( 'Hide Digital Wallet Buttons', 'woocommerce-square' ) } >
					<MultiSelectControl
						label=""
						__experimentalShowHowTo={ false }
						value={ digital_wallets_hide_button_options }
						onChange={ ( digital_wallets_hide_button_options ) => setDigitalWalletData( { digital_wallets_hide_button_options } ) }
						options={ [
							{
								label: __( 'Apple Pay', 'woocommerce-square' ),
								value: 'apple',
							},
							{
								label: __( 'Google Pay', 'woocommerce-square' ),
								value: 'google',
							},
						] }
					/>
				</InputWrapper>
			</Section>
		</>
	);
};
