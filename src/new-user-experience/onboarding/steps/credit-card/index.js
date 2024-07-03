/**
 * External dependencies.
 */
import {
	TextControl,
	TextareaControl,
	SelectControl,
} from '@wordpress/components';
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

export const CreditCardSetup = ( { origin = '' } ) => {
	const {
		paymentGatewaySettings,
		paymentGatewaySettingsLoaded,
		setCreditCardData,
	} = usePaymentGatewaySettings();

	const {
		enabled,
		title,
		description,
		charge_virtual_orders,
		enable_paid_capture,
		transaction_type,
		tokenization,
		card_types,
	} = paymentGatewaySettings;

	if ( ! paymentGatewaySettingsLoaded ) {
		return null;
	}

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
					data-testid="credit-card-gateway-virtual-order-only-field"
					label={ __( 'Charge Virtual-Only Orders', 'woocommerce' ) }
					checked={ charge_virtual_orders === 'yes' }
					onChange={ ( value ) =>
						setCreditCardData( {
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
					data-testid="credit-card-gateway-capture-paid-orders-field"
					label={ __( 'Capture Paid Orders', 'woocommerce' ) }
					checked={ enable_paid_capture === 'yes' }
					onChange={ ( value ) =>
						setCreditCardData( {
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
							/* translators: %s: link to payments settings */
							__(
								'Manage Credit Card Payment Settings %s',
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
						'Here you can fine-tune the details of how credit card payments are processed, ensuring a secure and smooth transaction for every customer.',
						'woocommerce'
					) }
				</SectionDescription>

				<div className="woo-square-wizard__fields">
					<InputWrapper
						label={ __( 'Enable / Disable', 'woocommerce' ) }
					>
						<SquareCheckboxControl
							data-testid="credit-card-gateway-toggle-field"
							label={ __(
								'Enable this payment method.',
								'woocommerce'
							) }
							checked={ enabled === 'yes' }
							onChange={ ( value ) =>
								setCreditCardData( {
									enabled: value ? 'yes' : 'no',
								} )
							}
						/>
					</InputWrapper>

					<InputWrapper label={ __( 'Title', 'woocommerce' ) }>
						<TextControl
							data-testid="credit-card-gateway-title-field"
							value={ title }
							onChange={ ( value ) =>
								setCreditCardData( { title: value } )
							}
						/>
					</InputWrapper>

					<InputWrapper label={ __( 'Description', 'woocommerce' ) }>
						<TextareaControl
							data-testid="credit-card-gateway-description-field"
							value={ description }
							onChange={ ( value ) =>
								setCreditCardData( { description: value } )
							}
						/>
					</InputWrapper>

					<InputWrapper
						label={ __( 'Transaction Type', 'woocommerce' ) }
					>
						<SelectControl
							data-testid="credit-card-transaction-type-field"
							value={ transaction_type }
							onChange={ ( value ) =>
								setCreditCardData( { transaction_type: value } )
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
						label={ __( 'Accepted Card Logos', 'woocommerce' ) }
					>
						<MultiSelectControl
							className="credit-card-gateway-card-logos-field"
							id="credit-card-gateway-card-logos-field"
							label=""
							__experimentalShowHowTo={ false }
							value={ card_types }
							onChange={ ( value ) =>
								setCreditCardData( { card_types: value } )
							}
							options={ [
								{
									label: __( 'Visa', 'woocommerce' ),
									value: 'VISA',
								},
								{
									label: __( 'MasterCard', 'woocommerce' ),
									value: 'MC',
								},
								{
									label: __(
										'American Express',
										'woocommerce'
									),
									value: 'AMEX',
								},
								{
									label: __( 'Discover', 'woocommerce' ),
									value: 'DISC',
								},
								{
									label: __( 'Diners', 'woocommerce' ),
									value: 'DINERS',
								},
								{
									label: __( 'JCB', 'woocommerce' ),
									value: 'JCB',
								},
								{
									label: __( 'UnionPay', 'woocommerce' ),
									value: 'UNIONPAY',
								},
							] }
						/>
					</InputWrapper>

					<InputWrapper
						label={ __( 'Customer Profiles', 'woocommerce' ) }
					>
						<SquareCheckboxControl
							data-testid="credit-card-tokenization-field"
							label={ __(
								'Check to enable tokenization and allow customers to securely save their payment details for future checkout.',
								'woocommerce'
							) }
							checked={ tokenization === 'yes' }
							onChange={ ( value ) =>
								setCreditCardData( {
									tokenization: value ? 'yes' : 'no',
								} )
							}
						/>
					</InputWrapper>
				</div>
			</Section>
		</>
	);
};
