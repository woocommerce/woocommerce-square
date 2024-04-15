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

export const CashAppSetup = () => {
	const { setCashAppData, getCashAppData } = useSettings();
	const {
		enabled,
		title,
		description,
		transaction_type,
		button_theme,
		button_shape,
        debug_mode,
	} = getCashAppData();

	return (
		<>
			<Section>
				<SectionTitle title={ __( 'Manage Cash App Pay Settings', 'woocommerce-square' ) } />
				<SectionDescription>
					{ __( 'Cash App Pay is an innovative payment solution that offers your customers a quick and secure way to check out. With just a few settings, you can tailor how Cash App Pay appears and operates on your site.', 'woocommerce-square' ) }
				</SectionDescription>

				<InputWrapper
					label={ __( 'Enable / Disable', 'woocommerce-square' ) }
					>
					<SquareCheckboxControl
						label={ __( 'Enable this gateway.', 'woocommerce-square' ) }
						checked={ 'yes' === enabled }
						onChange={ ( enabled ) => setCashAppData( { enabled: enabled ? 'yes' : 'no' } ) }
					/>
				</InputWrapper>

				<InputWrapper label={ __( 'Title', 'woocommerce-square' ) } >
					<TextControl
						value={ title }
						onChange={ ( title ) => setCashAppData( { title } ) }
					/>
				</InputWrapper>

				<InputWrapper label={ __( 'Description', 'woocommerce-square' ) } >
					<TextareaControl
						value={ description }
						onChange={ ( description ) => setCashAppData( { description } ) }
					/>
				</InputWrapper>

				<InputWrapper label={ __( 'Transaction Type', 'woocommerce-square' ) } >
					<SelectControl
						value={ transaction_type }
						onChange={ ( transaction_type ) => setCashAppData( { transaction_type } ) }
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
				</InputWrapper>

				<InputWrapper
					label={ __( 'Cash App Pay Button Theme', 'woocommerce-square' ) }
					>
					<SelectControl
						value={ button_theme }
						onChange={ ( button_theme ) => setCashAppData( { button_theme } ) }
						options={ [
							{
								label: __( 'Dark', 'woocommerce-square' ),
								value: 'dark'
							},
							{
								label: __( 'Light', 'woocommerce-square' ),
								value: 'light'
							}
						] }
					/>
				</InputWrapper>

				<InputWrapper
					label={ __( 'Cash App Pay Button Shape', 'woocommerce-square' ) }
					>
					<SelectControl
						value={ button_shape }
						onChange={ ( button_shape ) => setCashAppData( { button_shape } ) }
						options={ [
							{
								label: __( 'Semiround', 'woocommerce-square' ),
								value: 'semiround'
							},
							{
								label: __( 'Round', 'woocommerce-square' ),
								value: 'round'
							}
						] }
					/>
				</InputWrapper>

				<InputWrapper
					label={ __( 'Debug Mode', 'woocommerce-square' ) }
					>
					<SelectControl
						value={ debug_mode }
						onChange={ ( debug_mode ) => setCashAppData( { debug_mode } ) }
						options={ [
							{
								label: __( 'Off', 'woocommerce-square' ),
								value: 'off'
							},
							{
								label: __( 'Show on Checkout Page', 'woocommerce-square' ),
								value: 'Checkout'
							},
							{
								label: __( 'Save to Log', 'woocommerce-square' ),
								value: 'log'
							},
							{
								label: __( 'Both', 'woocommerce-square' ),
								value: 'both'
							}
						] }
					/>
				</InputWrapper>
			</Section>
		</>
	);
};
