/**
 * External dependencies.
 */
import {
	SelectControl,
	CheckboxControl,
	Flex,
	FlexItem,
	Button,
} from '@wordpress/components';
import { MultiSelectControl } from '@codeamp/block-components';
import parse from 'html-react-parser';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { ModalLayout } from '../../../components/modal-layout';
import { ModalHeader } from '../../../components/modal-header';
import { ModalDescription } from '../../../components/modal-description';
import { useSettings } from '../../../hooks';

export const DigitalWallets = () => {
	const { setDigitalWalletData, getDigitalWalletData } = useSettings();
	const {
		digital_wallets_button_type,
		digital_wallets_apple_pay_button_color,
		digital_wallets_google_pay_button_color,
		digital_wallets_hide_button_options,
	} = getDigitalWalletData();

	return (
		<ModalLayout>
			<ModalHeader text={ __( 'Manage Digital Wallet Settings', 'woocommerce-square' ) } />
			<ModalDescription>
				<div>
					{ __( 'Accept payments with Apple Pay and Google Pay on your store, available in select countries.', 'woocommerce-square' ) }
				</div>
				<div>
					{ __( 'Enabling digital wallets adds payment buttons to Product, Cart, and Checkout pages.', 'woocommerce-square' ) }
				</div>
			</ModalDescription>

			<SelectControl
				label={ __( 'Button Type', 'woocommerce-square' ) }
				value={ digital_wallets_button_type }
				onChange={ ( digital_wallets_button_type ) => setDigitalWalletData( { digital_wallets_button_type } ) }
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

			<SelectControl
				label={ __( 'Button Type', 'woocommerce-square' ) }
				help={ __( 'This setting only applies to the Apple Pay button. When Google Pay is available, the Google Pay button will always have the “Buy with” button text.', 'woocommerce-square' ) }
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

			<SelectControl
				label={ __( 'Apple Pay Button Color', 'woocommerce-square' ) }
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

			<SelectControl
				label={ __( 'Google Pay Button Color', 'woocommerce-square' ) }
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

			<MultiSelectControl
				label={ __( 'Accepted Card Logos', 'woocommerce-square' ) }
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

			<Flex justify="flex-end" gap="3">
				<FlexItem>
					<Button variant="secondary">
						{ __( 'Cancel', 'woocommerce-square' ) }
					</Button>
				</FlexItem>
				<FlexItem>
					<Button variant="primary">
						{ __( 'Apply Changes', 'woocommerce-square' ) }
					</Button>
				</FlexItem>
			</Flex>
		</ModalLayout>
	);
};
