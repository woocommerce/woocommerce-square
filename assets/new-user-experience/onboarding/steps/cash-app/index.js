/**
 * External dependencies.
 */
import {
	TextControl,
	TextareaControl,
	SelectControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
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

export const CashAppSetup = ( { origin = '' } ) => {
	const {
		cashAppGatewaySettings,
		setCashAppData,
	} = usePaymentGatewaySettings();

	const {
		enabled,
		title,
		description,
		transaction_type,
		button_theme,
		charge_virtual_orders,
		enable_paid_capture,
		button_shape,
	} = cashAppGatewaySettings;

	const authorizationFields = 'authorization' === transaction_type && (
		<>
			<InputWrapper
				description={ __( 'If the order contains exclusively virtual items, enable this to immediately charge, rather than authorize, the transaction.', 'woocommerce-square' ) }
				indent={ 2 }
			>
				<SquareCheckboxControl
					data-testid="cash-app-gateway-virtual-order-only-field"
					label={ __( 'Charge Virtual-Only Orders', 'woocommerce-square' ) }
					checked={ 'yes' === charge_virtual_orders }
					onChange={ ( charge_virtual_orders ) => setCashAppData( { charge_virtual_orders: charge_virtual_orders ? 'yes' : 'no' } ) }
				/>
			</InputWrapper>

			<InputWrapper
				description={ __( 'Automatically capture orders when they are changed to Processing or Completed.', 'woocommerce-square' ) }
				indent={ 2 }
			>
				<SquareCheckboxControl
					data-testid="cash-app-gateway-capture-paid-orders-field"
					label={ __( 'Capture Paid Orders', 'woocommerce-square' ) }
					checked={ 'yes' === enable_paid_capture }
					onChange={ ( enable_paid_capture ) => setCashAppData( { enable_paid_capture: enable_paid_capture ? 'yes' : 'no' } ) }
				/>
			</InputWrapper>
		</>
	);

	return (
		<>
			<Section>
				<SectionTitle title={
					parse(
						sprintf(
							__( 'Manage Cash App Pay Settings %s', 'woocommerce-square' ),
							'settings' === origin ? '<small className="wc-admin-breadcrumb"><a href="/wp-admin/admin.php?page=wc-settings&amp;tab=checkout" ariaLabel="Return to payments">⤴</a></small>' : ''
						)
					)
				} />
				<SectionDescription>
					{ __( 'Cash App Pay is an innovative payment solution that offers your customers a quick and secure way to check out. With just a few settings, you can tailor how Cash App Pay appears and operates on your site.', 'woocommerce-square' ) }
				</SectionDescription>

				<div className='woo-square-wizard__fields'>
					<InputWrapper
						label={ __( 'Enable / Disable', 'woocommerce-square' ) }
						>
						<SquareCheckboxControl
							data-testid="cash-app-gateway-toggle-field"
							label={ __( 'Enable this gateway.', 'woocommerce-square' ) }
							checked={ 'yes' === enabled }
							onChange={ ( enabled ) => setCashAppData( { enabled: enabled ? 'yes' : 'no' } ) }
						/>
					</InputWrapper>

					<InputWrapper label={ __( 'Title', 'woocommerce-square' ) } >
						<TextControl
							data-testid="cash-app-gateway-title-field"
							value={ title }
							onChange={ ( title ) => setCashAppData( { title } ) }
						/>
					</InputWrapper>

					<InputWrapper label={ __( 'Description', 'woocommerce-square' ) } >
						<TextareaControl
							data-testid="cash-app-gateway-description-field"
							value={ description }
							onChange={ ( description ) => setCashAppData( { description } ) }
						/>
					</InputWrapper>

					<InputWrapper label={ __( 'Transaction Type', 'woocommerce-square' ) } >
						<SelectControl
							data-testid="cash-app-gateway-transaction-type-field"
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

					{ authorizationFields }

					<InputWrapper
						label={ __( 'Cash App Pay Button Theme', 'woocommerce-square' ) }
						>
						<SelectControl
							data-testid="cash-app-gateway-button-theme-field"
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
							data-testid="cash-app-gateway-button-shape-field"
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
				</div>
			</Section>
		</>
	);
};
