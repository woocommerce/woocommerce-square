/**
 * Payments & Transactions tab — CC and Cash App transaction settings + Advanced.
 */
import { __ } from '@wordpress/i18n';
import { useEffect, useRef } from '@wordpress/element';
import { TextControl, TextareaControl, SelectControl } from '@wordpress/components';
import { usePaymentGatewaySettings } from '../onboarding/hooks';
import { useSquareSettings } from './hooks';
import { Loader, SquareCheckboxControl } from '../components';

const HUB_SAVE_BUTTON_ID = 'wc-square-settings-hub__save-payments-transactions';

const TRANSACTION_TYPE_OPTIONS = [
	{ label: __( 'Charge', 'woocommerce-square' ), value: 'charge' },
	{ label: __( 'Authorization', 'woocommerce-square' ), value: 'authorization' },
];

export const PaymentsTransactionsApp = () => {
	const {
		paymentGatewaySettingsLoaded,
		cashAppGatewaySettingsLoaded,
		paymentGatewaySettings,
		cashAppGatewaySettings,
		savePaymentGatewaySettings,
		saveCashAppSettings,
		setCreditCardData,
		setCashAppData,
	} = usePaymentGatewaySettings( true );

	const {
		settings,
		squareSettingsLoaded,
		setSquareSettingData,
		saveSquareSettings,
	} = useSquareSettings( true );

	const allLoaded =
		paymentGatewaySettingsLoaded &&
		cashAppGatewaySettingsLoaded &&
		squareSettingsLoaded;

	const saveAllRef = useRef( null );
	saveAllRef.current = () =>
		Promise.all( [
			savePaymentGatewaySettings(),
			saveCashAppSettings(),
			saveSquareSettings(),
		] );

	useEffect( () => {
		if ( ! allLoaded ) {
			return;
		}
		const hubBtn = document.getElementById( HUB_SAVE_BUTTON_ID );
		if ( ! hubBtn ) {
			return;
		}
		hubBtn.disabled = false;
		const onClick = ( e ) => {
			e.preventDefault();
			saveAllRef.current();
		};
		hubBtn.addEventListener( 'click', onClick );
		return () => {
			hubBtn.removeEventListener( 'click', onClick );
			hubBtn.disabled = true;
		};
	}, [ allLoaded ] );

	if ( ! allLoaded ) {
		return <Loader />;
	}

	const {
		title: ccTitle = '',
		description: ccDescription = '',
		transaction_type: ccTransactionType = 'charge',
		charge_virtual_orders = 'no',
		enable_paid_capture = 'no',
		tokenization = 'no',
	} = paymentGatewaySettings;

	const {
		title: cashAppTitle = '',
		description: cashAppDescription = '',
		transaction_type: cashAppTransactionType = 'charge',
		enabled: cashAppEnabled = 'no',
	} = cashAppGatewaySettings;

	const { enable_customer_decline_messages = 'no' } = settings;

	return (
		<div className="wc-square-payments-transactions">

			<div className="wc-square-payments-transactions__section">
				<div className="wc-square-payments-transactions__section-header">
					<h2 className="wc-square-payments-transactions__section-title">
						{ __( 'Credit card transaction settings', 'woocommerce-square' ) }
					</h2>
					<p className="wc-square-payments-transactions__section-description">
						{ __(
							'Fine-tune the details of how credit card payments are processed, ensuring a secure and smooth transaction for every customer.',
							'woocommerce-square'
						) }
					</p>
				</div>

				<div className="wc-square-payments-transactions__fields">
					<div className="wc-square-payments-transactions__field">
						<TextControl
							__nextHasNoMarginBottom
							label={ __( 'Title', 'woocommerce-square' ) }
							data-testid="cc-transactions-title-field"
							value={ ccTitle }
							onChange={ ( value ) =>
								setCreditCardData( { title: value } )
							}
							help={ __(
								"The value in the credit card title field of a customer's statement.",
								'woocommerce-square'
							) }
						/>
					</div>

					<div className="wc-square-payments-transactions__field">
						<TextareaControl
							__nextHasNoMarginBottom
							label={ __( 'Description', 'woocommerce-square' ) }
							data-testid="cc-transactions-description-field"
							value={ ccDescription }
							onChange={ ( value ) =>
								setCreditCardData( { description: value } )
							}
							help={ __(
								"The value in the description field of a customer's statement.",
								'woocommerce-square'
							) }
						/>
					</div>

					<div className="wc-square-payments-transactions__field">
						<SelectControl
							__nextHasNoMarginBottom
							label={ __( 'Transaction Preferences', 'woocommerce-square' ) }
							data-testid="cc-transactions-type-field"
							value={ ccTransactionType }
							options={ TRANSACTION_TYPE_OPTIONS }
							onChange={ ( value ) =>
								setCreditCardData( { transaction_type: value } )
							}
							help={ __(
								'Select how transactions should be processed. Charge submits all transactions for settlement; Authorization simply authorizes the order total for capture later.',
								'woocommerce-square'
							) }
						/>
					</div>

					{ ccTransactionType === 'authorization' && (
						<>
							<div className="wc-square-payments-transactions__field wc-square-payments-transactions__field--indent">
								<SquareCheckboxControl
									label={ __(
										'Charge Virtual-Only Orders',
										'woocommerce-square'
									) }
									checked={ charge_virtual_orders === 'yes' }
									onChange={ ( value ) =>
										setCreditCardData( {
											charge_virtual_orders: value ? 'yes' : 'no',
										} )
									}
								/>
								<p className="wc-square-payments-transactions__help">
									{ __(
										'If the order contains exclusively virtual items, enable this to immediately charge, rather than authorize, the transaction.',
										'woocommerce-square'
									) }
								</p>
							</div>

							<div className="wc-square-payments-transactions__field wc-square-payments-transactions__field--indent">
								<SquareCheckboxControl
									label={ __(
										'Capture Paid Orders',
										'woocommerce-square'
									) }
									checked={ enable_paid_capture === 'yes' }
									onChange={ ( value ) =>
										setCreditCardData( {
											enable_paid_capture: value ? 'yes' : 'no',
										} )
									}
								/>
								<p className="wc-square-payments-transactions__help">
									{ __(
										'Automatically capture orders when they are changed to Processing or Completed.',
										'woocommerce-square'
									) }
								</p>
							</div>
						</>
					) }

					<div className="wc-square-payments-transactions__field">
						<h3 className="wc-square-payments-transactions__subheading">
							{ __( 'Customer profiles', 'woocommerce-square' ) }
						</h3>
						<SquareCheckboxControl
							data-testid="cc-transactions-tokenization-field"
							label={ __(
								'Allow customers to save their payment details',
								'woocommerce-square'
							) }
							checked={ tokenization === 'yes' }
							onChange={ ( value ) =>
								setCreditCardData( {
									tokenization: value ? 'yes' : 'no',
								} )
							}
						/>
						<p className="wc-square-payments-transactions__help">
							{ __(
								'When enabled, it will allow customers to securely save their payment details for future checkout.',
								'woocommerce-square'
							) }
						</p>
					</div>
				</div>
			</div>

			{ cashAppEnabled === 'yes' && (
				<div className="wc-square-payments-transactions__section">
					<div className="wc-square-payments-transactions__section-header">
						<h2 className="wc-square-payments-transactions__section-title">
							{ __( 'Cash App Pay transaction settings', 'woocommerce-square' ) }
						</h2>
						<p className="wc-square-payments-transactions__section-description">
							{ __(
								'Fine-tune the details of how Cash App Pay is processed, ensuring a secure and smooth transaction for every customer.',
								'woocommerce-square'
							) }
						</p>
					</div>

					<div className="wc-square-payments-transactions__fields">
						<div className="wc-square-payments-transactions__field">
							<TextControl
								__nextHasNoMarginBottom
								label={ __( 'Title', 'woocommerce-square' ) }
								data-testid="cash-app-transactions-title-field"
								value={ cashAppTitle }
								onChange={ ( value ) =>
									setCashAppData( { title: value } )
								}
								help={ __(
									"The value in the credit card title field of a customer's statement.",
									'woocommerce-square'
								) }
							/>
						</div>

						<div className="wc-square-payments-transactions__field">
							<TextareaControl
								__nextHasNoMarginBottom
								label={ __( 'Description', 'woocommerce-square' ) }
								data-testid="cash-app-transactions-description-field"
								value={ cashAppDescription }
								onChange={ ( value ) =>
									setCashAppData( { description: value } )
								}
								help={ __(
									"The value in the description field of a customer's statement.",
									'woocommerce-square'
								) }
							/>
						</div>

						<div className="wc-square-payments-transactions__field">
							<SelectControl
								__nextHasNoMarginBottom
								label={ __( 'Transaction Preferences', 'woocommerce-square' ) }
								data-testid="cash-app-transactions-type-field"
								value={ cashAppTransactionType }
								options={ TRANSACTION_TYPE_OPTIONS }
								onChange={ ( value ) =>
									setCashAppData( { transaction_type: value } )
								}
								help={ __(
									'Select how transactions should be processed.',
									'woocommerce-square'
								) }
							/>
						</div>
					</div>
				</div>
			) }

			<div className="wc-square-payments-transactions__section">
				<div className="wc-square-payments-transactions__section-header">
					<h2 className="wc-square-payments-transactions__section-title">
						{ __( 'Advanced settings', 'woocommerce-square' ) }
					</h2>
					<p className="wc-square-payments-transactions__section-description">
						{ __(
							'Adjust these options to provide your customers with additional clarity and troubleshoot any issues more effectively.',
							'woocommerce-square'
						) }
					</p>
				</div>

				<div className="wc-square-payments-transactions__fields">
					<div className="wc-square-payments-transactions__field">
						<h3 className="wc-square-payments-transactions__subheading">
							{ __( 'Detailed decline messages', 'woocommerce-square' ) }
						</h3>
						<SquareCheckboxControl
							data-testid="decline-messages-field"
							label={ __(
								'Enable detailed decline messages',
								'woocommerce-square'
							) }
							checked={ enable_customer_decline_messages === 'yes' }
							onChange={ ( value ) =>
								setSquareSettingData( {
									enable_customer_decline_messages: value
										? 'yes'
										: 'no',
								} )
							}
						/>
						<p className="wc-square-payments-transactions__help">
							{ __(
								'When enabled, customers will see detailed decline messages during checkout when possible, rather than a generic decline message.',
								'woocommerce-square'
							) }
						</p>
					</div>
				</div>
			</div>

		</div>
	);
};
