/**
 * External dependencies.
 */
import {
	TextControl,
	TextareaControl,
	SelectControl,
	CheckboxControl,
	Flex,
	FlexItem,
	Button,
} from '@wordpress/components';
import { MultiSelectControl } from '@codeamp/block-components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { ModalLayout } from '../../../components/modal-layout';
import { ModalHeader } from '../../../components/modal-header';
import { ModalDescription } from '../../../components/modal-description';
import { useSettings } from '../../../hooks';

export const CreditCard = () => {
	const { setCreditCardData, getCreditCardData } = useSettings();
	const {
		title,
		description,
		charge_virtual_orders,
		enable_paid_capture,
		transaction_type,
		tokenization,
		card_types
	} = getCreditCardData();

	const authorizationFields = 'authorization' === transaction_type && (
		<>
			<CheckboxControl
				label={ __( 'Charge Virtual-Only Orders', 'woocommerce-square' ) }
				help={ __( 'If the order contains exclusively virtual items, enable this to immediately charge, rather than authorize, the transaction.', 'woocommerce-square' ) }
				checked={ 'yes' === charge_virtual_orders }
				onChange={ ( charge_virtual_orders ) => setCreditCardData( { charge_virtual_orders: charge_virtual_orders ? 'yes' : 'no' } ) }
			/>
			<CheckboxControl
				label={ __( 'Capture Paid Orders', 'woocommerce-square' ) }
				help={ __( 'Automatically capture orders when they are changed to Processing or Completed.', 'woocommerce-square' ) }
				checked={ 'yes' === enable_paid_capture }
				onChange={ ( enable_paid_capture ) => setCreditCardData( { enable_paid_capture: enable_paid_capture ? 'yes' : 'no' } ) }
			/>
		</>
	);

	return (
		<ModalLayout>
			<ModalHeader text={ __( 'Manage Credit Card Payment Settings', 'woocommerce-square' ) } />
			<ModalDescription>
				{ __( 'Accept payments with Apple Pay and Google Pay on your store, available in select countries. Enabling digital wallets adds payment buttons to Product, Cart, and Checkout pages.', 'woocommerce-square' ) }
			</ModalDescription> 

			<TextControl
				label={ __( 'Title', 'woocommerce-square' ) }
				value={ title }
				onChange={ ( title ) => setCreditCardData( { title } ) }
			/>

			<TextareaControl
				label={ __( 'Description', 'woocommerce-square' ) }
				value={ description }
				onChange={ ( description ) => setCreditCardData( { description } ) }
			/>

			<SelectControl
				label={ __( 'Transaction Type', 'woocommerce-square' ) }
				value={ transaction_type }
				onChange={ ( transaction_type ) => setCreditCardData( { transaction_type } ) }
				options={ [
					{
						label: __( 'Charge', 'woocommerce-square' ),
						value: 'charge'
					},
					{
						label: __( 'Authorization', 'woocommerce-square' ),
						value: 'authorization'
					}
				] }
			/>

			{ authorizationFields }

			<MultiSelectControl
				label={ __( 'Accepted Card Logos', 'woocommerce-square' ) }
				value={ card_types }
				onChange={ ( card_types ) => setCreditCardData( { card_types } ) }
				options={ [
					{
						label: __( 'Visa', 'woocommerce-square' ),
						value: 'VISA',
					},
					{
						label: __( 'MasterCard', 'woocommerce-square' ),
						value: 'MC',
					},
					{
						label: __( 'American Express', 'woocommerce-square' ),
						value: 'AMEX',
					},
					{
						label: __( 'Discover', 'woocommerce-square' ),
						value: 'DISC',
					},
					{
						label: __( 'Diners', 'woocommerce-square' ),
						value: 'DINERS',
					},
					{
						label: __( 'JCB', 'woocommerce-square' ),
						value: 'JCB',
					},
					{
						label: __( 'UnionPay', 'woocommerce-square' ),
						value: 'UNIONPAY',
					},
				] }
			/>

			<CheckboxControl
				label={ __( 'Customer Profiles', 'woocommerce-square' ) }
				help={ __( 'Check to enable tokenization and allow customers to securely save their payment details for future checkout.', 'woocommerce-square' ) }
				checked={ 'yes' === tokenization }
				onChange={ ( tokenization ) => setCreditCardData( { tokenization: tokenization ? 'yes' : 'no' } ) }
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
