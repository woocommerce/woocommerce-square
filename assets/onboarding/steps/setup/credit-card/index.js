import {
	TextControl,
	TextareaControl,
	SelectControl,
	CheckboxControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { ModalLayout } from '../../../components/modal-layout';
import { ModalHeader } from '../../../components/modal-header';
import { ModalDescription } from '../../../components/modal-description';
import { useSettings } from '../../../hooks';

export const CreditCard = () => {
	const { setCreditCardData, getCreditCardData } = useSettings();
	const {
		title,
		description,
		charge,
		tokenization,
	} = getCreditCardData();

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
				value={ charge }
				onChange={ ( charge ) => setCreditCardData( { charge } ) }
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

			<CheckboxControl
				label={ __( 'Customer Profiles', 'woocommerce-square' ) }
				checked={ 'yes' === tokenization }
				onChange={ ( tokenization ) => setCreditCardData( { tokenization: tokenization ? 'yes' : 'no' } ) }
			/>
		</ModalLayout>
	);
};
