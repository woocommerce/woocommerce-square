import { ToggleControl, Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

// Card icon (Credit/debit card + Digital wallet rows). Exported from Figma.
const CardIcon = () => (
	<svg
		width="40"
		height="40"
		viewBox="0 0 40 40"
		fill="none"
		xmlns="http://www.w3.org/2000/svg"
	>
		<path
			d="M0 4C0 1.79086 1.79086 0 4 0H36C38.2091 0 40 1.79086 40 4V36C40 38.2091 38.2091 40 36 40H4C1.79086 40 0 38.2091 0 36V4Z"
			fill="#F6F7F7"
		/>
		<path
			fillRule="evenodd"
			clipRule="evenodd"
			d="M13.5 17.5V15.5H26.5V17.5H13.5ZM13.5 20.5V24.5H26.5V20.5H13.5ZM12 15C12 14.7348 12.1054 14.4804 12.2929 14.2929C12.4804 14.1054 12.7348 14 13 14H27C27.2652 14 27.5196 14.1054 27.7071 14.2929C27.8946 14.4804 28 14.7348 28 15V25C28 25.2652 27.8946 25.5196 27.7071 25.7071C27.5196 25.8946 27.2652 26 27 26H13C12.7348 26 12.4804 25.8946 12.2929 25.7071C12.1054 25.5196 12 25.2652 12 25V15Z"
			fill="#1E1E1E"
		/>
	</svg>
);

// Square mark icon (Cash App + Square gift cards rows). Exported from Figma.
const SquareMarkIcon = () => (
	<svg
		width="40"
		height="40"
		viewBox="0 0 40 40"
		fill="none"
		xmlns="http://www.w3.org/2000/svg"
	>
		<path
			d="M0 4C0 1.79086 1.79086 0 4 0H36C38.2091 0 40 1.79086 40 4V36C40 38.2091 38.2091 40 36 40H4C1.79086 40 0 38.2091 0 36V4Z"
			fill="#F6F7F7"
		/>
		<path
			d="M28.6553 7H11.3447C8.9449 7 7 8.9449 7 11.3447V28.6553C7 31.0551 8.9449 33 11.3447 33H28.6553C31.0551 33 33 31.0551 33 28.6553V11.3447C33 8.9449 31.0551 7 28.6553 7ZM28.2737 26.9013C28.2737 27.6594 27.6594 28.2737 26.9013 28.2737H13.0987C12.3406 28.2737 11.7263 27.6594 11.7263 26.9013V13.0987C11.7263 12.3406 12.3406 11.7263 13.0987 11.7263H26.9013C27.6594 11.7263 28.2737 12.3406 28.2737 13.0987V26.9013ZM17.2421 23.5291C16.8055 23.5291 16.4553 23.1762 16.4553 22.7396V17.229C16.4553 16.7925 16.8055 16.437 17.2421 16.437H22.7605C23.1945 16.437 23.5474 16.7899 23.5474 17.229V22.737C23.5474 23.1735 23.1945 23.5264 22.7605 23.5264H17.2421V23.5291Z"
			fill="#1E1E1E"
		/>
	</svg>
);

/**
 * Main Payment Methods list shown on the Payment Methods tab.
 *
 * Every toggle is driven by a shared, reactive SDK value (one per method). A
 * toggle only updates that value — nothing self-saves. All enable states are
 * persisted together when the page Save button is clicked (routed by
 * save-handler.js: the WC gateways via the payment_gateways endpoint, digital
 * wallets via the Credit Card payment settings). Sharing state through `values`
 * also keeps this list in sync with the per-wallet toggles on the Digital
 * Wallet Customize sub-page (which can turn the parent off when both wallets
 * are disabled).
 *
 * @param {Object}   props
 * @param {Object}   props.values   - All current form values.
 * @param {Function} props.setValue - SDK setter for any field.
 */
export default function GatewayList( { values, setValue } ) {
	const isOn = ( key ) => values?.[ key ] === 'yes';

	const handleToggle = ( key, checked ) => {
		setValue( key, checked ? 'yes' : 'no' );

		// Turning the Digital Wallet parent on: default both wallets on when
		// neither is currently on (fresh config, or re-enabling after both were
		// turned off). Existing per-wallet choices are otherwise kept.
		if ( key === 'enable_digital_wallets' && checked ) {
			const googleOn =
				values?.digital_wallets_google_pay_enabled === 'yes';
			const appleOn = values?.digital_wallets_apple_pay_enabled === 'yes';
			if ( ! googleOn && ! appleOn ) {
				setValue( 'digital_wallets_google_pay_enabled', 'yes' );
				setValue( 'digital_wallets_apple_pay_enabled', 'yes' );
			}
		}
	};

	const rows = [
		{
			key: 'square_credit_card_enabled',
			icon: <CardIcon />,
			title: __( 'Credit/debit card', 'woocommerce-square' ),
			description: __(
				'Let your customers pay with major credit and debit cards without leaving your store.',
				'woocommerce-square'
			),
			customize: null,
		},
		{
			key: 'enable_digital_wallets',
			icon: <CardIcon />,
			title: __( 'Digital wallet', 'woocommerce-square' ),
			description: __(
				'Let customers pay quickly and securely using supported digital wallets like Apple pay and Google Pay.',
				'woocommerce-square'
			),
			customize: 'digital-wallet',
		},
		{
			key: 'square_cash_app_pay_enabled',
			icon: <SquareMarkIcon />,
			title: __( 'Cash App (US only)', 'woocommerce-square' ),
			description: __(
				'Enable customers to check out instantly using their Cash App balance or linked payment methods.',
				'woocommerce-square'
			),
			customize: 'cash-app',
		},
		{
			key: 'gift_cards_pay_enabled',
			icon: <SquareMarkIcon />,
			title: __( 'Square gift cards', 'woocommerce-square' ),
			description: __(
				'Accept Square Gift Cards online or in person, giving customers a convenient way to redeem their balance at checkout.',
				'woocommerce-square'
			),
			customize: null,
		},
	];

	return (
		<div className="wc-square-payment-methods-list">
			<p className="wc-square-payment-methods-list__description">
				{ __(
					"Select which payment methods you'd like to offer to your shoppers. You can update these at any time.",
					'woocommerce-square'
				) }
			</p>
			{ rows.map( ( row ) => (
				<div
					key={ row.key }
					className="wc-square-payment-methods-list__row"
				>
					<div className="wc-square-payment-methods-list__row-icon">
						{ row.icon }
					</div>
					<div className="wc-square-payment-methods-list__row-content">
						<strong className="wc-square-payment-methods-list__row-title">
							{ row.title }
						</strong>
						<p className="wc-square-payment-methods-list__row-description">
							{ row.description }
						</p>
					</div>
					<div className="wc-square-payment-methods-list__row-actions">
						{ row.customize &&
							// Display settings are only meaningful while the method
							// is enabled; hide the entry to its Customize sub-page
							// when that method's toggle is off.
							isOn( row.key ) && (
								<Button
									variant="link"
									onClick={ () =>
										setValue(
											'payment_methods_view',
											row.customize
										)
									}
								>
									{ __( 'Customize', 'woocommerce-square' ) }
								</Button>
							) }
						<ToggleControl
							checked={ isOn( row.key ) }
							onChange={ ( val ) => handleToggle( row.key, val ) }
							label=""
							aria-label={ sprintf(
								/* translators: %s: payment method name */
								__( 'Enable %s', 'woocommerce-square' ),
								row.title
							) }
							__nextHasNoMarginBottom
						/>
					</div>
				</div>
			) ) }
		</div>
	);
}
