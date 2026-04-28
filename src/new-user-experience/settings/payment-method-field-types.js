/**
 * Payment method React components for the Payment Methods hub tab.
 *
 * Exported for use in PaymentGatewaySettingsApp (hub's createRoot mount).
 */

import { __ } from '@wordpress/i18n';
import { ToggleControl, SelectControl, CheckboxControl } from '@wordpress/components';

// ---------------------------------------------------------------------------
// Digital Wallets helpers
// ---------------------------------------------------------------------------

/**
 * Derives the per-wallet show flags from the legacy hide-list field.
 *
 * @param {string[]} hideOptions  Value of digital_wallets_hide_button_options.
 * @return {{ showApplePay: boolean, showGooglePay: boolean }}
 */
export const parseDigitalWalletVisibility = ( hideOptions = [] ) => ( {
	showApplePay: ! hideOptions.includes( 'apple' ),
	showGooglePay: ! hideOptions.includes( 'google' ),
} );

/**
 * Serialises per-wallet show flags back to the legacy hide-list format.
 *
 * @param {boolean} showApplePay
 * @param {boolean} showGooglePay
 * @return {string[]}
 */
export const serializeDigitalWalletVisibility = ( showApplePay, showGooglePay ) => {
	const hide = [];
	if ( ! showApplePay ) hide.push( 'apple' );
	if ( ! showGooglePay ) hide.push( 'google' );
	return hide;
};

// ---------------------------------------------------------------------------
// DigitalWalletsCard
// ---------------------------------------------------------------------------

/**
 * Sub-card for Digital Wallets inside the Credit Card payment method card.
 * Renders enable/disable toggle + separate Google Pay and Apple Pay checkboxes.
 *
 * @param {Object}   props
 * @param {Object}   props.settings          Credit card gateway settings object.
 * @param {Function} props.setDigitalWalletData  Partial updater for digital wallet slice.
 */
export const DigitalWalletsCard = ( { settings, setDigitalWalletData } ) => {
	const {
		enable_digital_wallets: enableDW = 'no',
		digital_wallets_hide_button_options: hideOptions = [],
	} = settings;

	const isDWEnabled = enableDW === 'yes';
	const { showApplePay, showGooglePay } = parseDigitalWalletVisibility( hideOptions );

	const handleWalletToggle = ( checked ) => {
		const updates = { enable_digital_wallets: checked ? 'yes' : 'no' };
		if ( checked ) {
			// Both default to enabled when parent toggle is turned on.
			updates.digital_wallets_hide_button_options = [];
		}
		setDigitalWalletData( updates );
	};

	const handleVisibilityChange = ( wallet, visible ) => {
		const updatedShow = {
			showApplePay: wallet === 'apple' ? visible : showApplePay,
			showGooglePay: wallet === 'google' ? visible : showGooglePay,
		};
		setDigitalWalletData( {
			digital_wallets_hide_button_options: serializeDigitalWalletVisibility(
				updatedShow.showApplePay,
				updatedShow.showGooglePay
			),
		} );
	};

	return (
		<div className="square-payment-method-card square-payment-method-card--digital-wallets">
			<div className="square-payment-method-card__header">
				<span className="square-payment-method-card__title">
					{ __( 'Digital Wallets', 'woocommerce-square' ) }
				</span>
				<ToggleControl
					__nextHasNoMarginBottom
					hideLabelFromVision
					label={ __( 'Enable Digital Wallets', 'woocommerce-square' ) }
					checked={ isDWEnabled }
					onChange={ handleWalletToggle }
					data-testid="digital-wallets-toggle"
				/>
			</div>

			{ isDWEnabled && (
				<div className="square-payment-method-card__wallet-options">
					<CheckboxControl
						__nextHasNoMarginBottom
						label={ __( 'Google Pay', 'woocommerce-square' ) }
						checked={ showGooglePay }
						onChange={ ( visible ) =>
							handleVisibilityChange( 'google', visible )
						}
						data-testid="google-pay-checkbox"
					/>
					<CheckboxControl
						__nextHasNoMarginBottom
						label={ __( 'Apple Pay', 'woocommerce-square' ) }
						checked={ showApplePay }
						onChange={ ( visible ) =>
							handleVisibilityChange( 'apple', visible )
						}
						data-testid="apple-pay-checkbox"
					/>
				</div>
			) }
		</div>
	);
};

// ---------------------------------------------------------------------------
// CashAppCustomizePanel
// ---------------------------------------------------------------------------

const BUTTON_THEME_OPTIONS = [
	{ label: __( 'Dark', 'woocommerce-square' ), value: 'dark' },
	{ label: __( 'Light', 'woocommerce-square' ), value: 'light' },
];

const BUTTON_SHAPE_OPTIONS = [
	{ label: __( 'Semiround', 'woocommerce-square' ), value: 'semiround' },
	{ label: __( 'Round', 'woocommerce-square' ), value: 'round' },
];

/**
 * Customize sub-panel for Cash App Pay: button theme and shape selects.
 *
 * @param {Object}   props
 * @param {Object}   props.settings     Cash App gateway settings object.
 * @param {Function} props.onChange     Partial settings updater.
 */
export const CashAppCustomizePanel = ( { settings, onChange } ) => {
	const { button_theme = 'dark', button_shape = 'semiround' } = settings;

	return (
		<div className="square-payment-method-card__customize">
			<SelectControl
				__nextHasNoMarginBottom
				label={ __( 'Button theme', 'woocommerce-square' ) }
				value={ button_theme }
				options={ BUTTON_THEME_OPTIONS }
				onChange={ ( value ) => onChange( { button_theme: value } ) }
				data-testid="cash-app-button-theme"
			/>
			<SelectControl
				__nextHasNoMarginBottom
				label={ __( 'Button shape', 'woocommerce-square' ) }
				value={ button_shape }
				options={ BUTTON_SHAPE_OPTIONS }
				onChange={ ( value ) => onChange( { button_shape: value } ) }
				data-testid="cash-app-button-shape"
			/>
		</div>
	);
};

// ---------------------------------------------------------------------------
// PaymentMethodCard
// ---------------------------------------------------------------------------

/**
 * Generic payment method card: enabled toggle + optional children (customize panel).
 *
 * Used as the Edit component for the 'square_payment_method' field type.
 *
 * @param {Object}        props
 * @param {Object}        props.value        Full gateway settings array (from get_option).
 * @param {Function}      props.onChange     Called with updated settings object.
 * @param {string}        props.label        Card title (gateway name).
 * @param {string}        [props.description] Optional description shown below the title.
 * @param {React.Element} [props.children]   Optional customize panel content.
 */
export const PaymentMethodCard = ( { value = {}, onChange, label, description, children } ) => {
	const isEnabled = value.enabled === 'yes';

	return (
		<div className="square-payment-method-card">
			<div className="square-payment-method-card__header">
				<div className="square-payment-method-card__text">
					<span className="square-payment-method-card__title">
						{ label }
					</span>
					{ description && (
						<span className="square-payment-method-card__description">
							{ description }
						</span>
					) }
				</div>
				<ToggleControl
					__nextHasNoMarginBottom
					hideLabelFromVision
					label={ label }
					checked={ isEnabled }
					onChange={ ( checked ) =>
						onChange( { ...value, enabled: checked ? 'yes' : 'no' } )
					}
					data-testid={ `${ label }-gateway-toggle` }
				/>
			</div>
			{ isEnabled && children }
		</div>
	);
};

