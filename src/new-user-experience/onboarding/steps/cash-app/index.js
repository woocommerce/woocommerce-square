/**
 * External dependencies.
 */
import {
	TextControl,
	TextareaControl,
	SelectControl,
} from '@wordpress/components';
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

export const CashAppSetup = ( { origin = '' } ) => {
	const { cashAppGatewaySettings, setCashAppData } =
		usePaymentGatewaySettings();

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

	const authorizationFields = transaction_type === 'authorization' && (
		<>
			<InputWrapper
				description={ __(
					'If the order contains exclusively virtual items, enable this to immediately charge, rather than authorize, the transaction.',
					'woocommerce'
				) }
				indent={ 2 }
			>
				<SquareCheckboxControl
					data-testid="cash-app-gateway-virtual-order-only-field"
					label={ __( 'Charge Virtual-Only Orders', 'woocommerce' ) }
					checked={ charge_virtual_orders === 'yes' }
					onChange={ ( value ) =>
						setCashAppData( {
							charge_virtual_orders: value ? 'yes' : 'no',
						} )
					}
				/>
			</InputWrapper>

			<InputWrapper
				description={ __(
					'Automatically capture orders when they are changed to Processing or Completed.',
					'woocommerce'
				) }
				indent={ 2 }
			>
				<SquareCheckboxControl
					data-testid="cash-app-gateway-capture-paid-orders-field"
					label={ __( 'Capture Paid Orders', 'woocommerce' ) }
					checked={ enable_paid_capture === 'yes' }
					onChange={ ( value ) =>
						setCashAppData( {
							enable_paid_capture: value ? 'yes' : 'no',
						} )
					}
				/>
			</InputWrapper>
		</>
	);

	return (
		<>
			<Section>
				<SectionTitle
					title={ parse(
						sprintf(
							/* translators: %s: link to settings page */
							__(
								'Manage Cash App Pay Settings %s',
								'woocommerce'
							),
							origin === 'settings'
								? `<small className="wc-admin-breadcrumb"><a href="${ wcSquareSettings.adminUrl }admin.php?page=wc-settings&amp;tab=checkout" ariaLabel="Return to payments">⤴</a></small>` // eslint-disable-line no-undef
								: ''
						)
					) }
				/>
				<SectionDescription>
					{ __(
						'Cash App Pay is an innovative payment solution that offers your customers a quick and secure way to check out. With just a few settings, you can tailor how Cash App Pay appears and operates on your site.',
						'woocommerce'
					) }
				</SectionDescription>

				<div className="woo-square-wizard__fields">
					<InputWrapper
						label={ __( 'Enable / Disable', 'woocommerce' ) }
					>
						<SquareCheckboxControl
							data-testid="cash-app-gateway-toggle-field"
							label={ __(
								'Enable this payment method.',
								'woocommerce'
							) }
							checked={ enabled === 'yes' }
							onChange={ ( value ) =>
								setCashAppData( {
									enabled: value ? 'yes' : 'no',
								} )
							}
						/>
					</InputWrapper>

					<InputWrapper label={ __( 'Title', 'woocommerce' ) }>
						<TextControl
							data-testid="cash-app-gateway-title-field"
							value={ title }
							onChange={ ( value ) =>
								setCashAppData( { title: value } )
							}
						/>
					</InputWrapper>

					<InputWrapper label={ __( 'Description', 'woocommerce' ) }>
						<TextareaControl
							data-testid="cash-app-gateway-description-field"
							value={ description }
							onChange={ ( value ) =>
								setCashAppData( { description: value } )
							}
						/>
					</InputWrapper>

					<InputWrapper
						label={ __( 'Transaction Type', 'woocommerce' ) }
					>
						<SelectControl
							data-testid="cash-app-gateway-transaction-type-field"
							value={ transaction_type }
							onChange={ ( value ) =>
								setCashAppData( { transaction_type: value } )
							}
							options={ [
								{
									label: __( 'Charge', 'woocommerce' ),
									value: 'charge',
								},
								{
									label: __( 'Authorization', 'woocommerce' ),
									value: 'authorization',
								},
							] }
						/>
					</InputWrapper>

					{ authorizationFields }

					<InputWrapper
						label={ __(
							'Cash App Pay Button Theme',
							'woocommerce'
						) }
					>
						<SelectControl
							data-testid="cash-app-gateway-button-theme-field"
							value={ button_theme }
							onChange={ ( value ) =>
								setCashAppData( { button_theme: value } )
							}
							options={ [
								{
									label: __( 'Dark', 'woocommerce' ),
									value: 'dark',
								},
								{
									label: __( 'Light', 'woocommerce' ),
									value: 'light',
								},
							] }
						/>
					</InputWrapper>

					<InputWrapper
						label={ __(
							'Cash App Pay Button Shape',
							'woocommerce'
						) }
					>
						<SelectControl
							data-testid="cash-app-gateway-button-shape-field"
							value={ button_shape }
							onChange={ ( value ) =>
								setCashAppData( { button_shape: value } )
							}
							options={ [
								{
									label: __( 'Semiround', 'woocommerce' ),
									value: 'semiround',
								},
								{
									label: __( 'Round', 'woocommerce' ),
									value: 'round',
								},
							] }
						/>
					</InputWrapper>
				</div>
			</Section>
		</>
	);
};
