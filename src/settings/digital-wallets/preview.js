import { __ } from '@wordpress/i18n';

/**
 * Button-label text shown next to the wallet mark, keyed by the button "type"
 * the merchant selects. `plain` shows the mark only (no leading verb).
 */
const LABEL_PREFIX = {
	buy: __( 'Buy with', 'woocommerce-square' ),
	checkout: __( 'Checkout with', 'woocommerce-square' ),
	pay: __( 'Pay with', 'woocommerce-square' ),
	plain: '',
	donate: __( 'Donate with', 'woocommerce-square' ),
	book: __( 'Book with', 'woocommerce-square' ),
	subscribe: __( 'Subscribe with', 'woocommerce-square' ),
	order: __( 'Order with', 'woocommerce-square' ),
};

/** Google "G Pay" wallet mark. "Pay" inherits the button text colour. */
function GooglePayMark() {
	return (
		<span className="wc-square-wallet-preview__mark" aria-hidden="true">
			<span className="wc-square-wallet-preview__gpay-g">G</span> Pay
		</span>
	);
}

/** Apple "Pay" wallet mark. Apple glyph + "Pay" inherit the button text colour. */
function ApplePayMark() {
	return (
		<span className="wc-square-wallet-preview__mark" aria-hidden="true">
			<svg
				viewBox="0 0 24 24"
				width="15"
				height="15"
				style={ { fill: 'currentColor', verticalAlign: '-2px' } }
			>
				<path d="M17.05 12.54c-.02-1.98 1.62-2.93 1.69-2.98-.92-1.35-2.36-1.53-2.87-1.55-1.22-.12-2.38.72-3 .72-.62 0-1.57-.7-2.59-.68-1.33.02-2.56.77-3.24 1.96-1.38 2.4-.35 5.95.99 7.9.66.95 1.44 2.02 2.46 1.98.99-.04 1.36-.64 2.56-.64 1.19 0 1.53.64 2.58.62 1.07-.02 1.74-.97 2.39-1.93.75-1.1 1.06-2.17 1.08-2.22-.02-.01-2.07-.79-2.1-3.15zM15.1 6.79c.54-.66.91-1.57.81-2.49-.78.03-1.73.52-2.29 1.18-.5.58-.94 1.51-.82 2.4.87.07 1.76-.44 2.3-1.09z" />
			</svg>
			&nbsp;Pay
		</span>
	);
}

/**
 * Static preview button for a single wallet. Renders instantly from the chosen
 * label + colour — no SDK, no network, so it can never flicker or lag behind
 * the dropdown. It faithfully mirrors the storefront button's colour and label.
 *
 * (The real Square Web Payments SDK button is used at checkout; its preview
 * `attach()` only accepts `long`/`short`, so it cannot render the full set of
 * label options — hence a mock here.)
 *
 * @param {Object}  props
 * @param {string}  props.color      - 'black' | 'white' | 'white-with-line'.
 * @param {string}  props.buttonType - Label type key (buy, pay, plain, …).
 * @param {Element} props.mark       - Wallet mark element.
 * @param {string}  props.ariaLabel  - Accessible label.
 */
function WalletButton( { color, buttonType, mark, ariaLabel } ) {
	const isWhite = color === 'white' || color === 'white-with-line';
	const prefix = LABEL_PREFIX[ buttonType ] ?? LABEL_PREFIX.buy;
	const classes = [
		'wc-square-wallet-preview__button',
		isWhite ? 'is-white' : 'is-black',
		color === 'white-with-line' ? 'has-line' : '',
	]
		.filter( Boolean )
		.join( ' ' );

	return (
		<button disabled className={ classes } aria-label={ ariaLabel }>
			{ prefix && (
				<span className="wc-square-wallet-preview__label">
					{ prefix }
				</span>
			) }
			{ mark }
		</button>
	);
}

/**
 * Live preview of the Google Pay and Apple Pay buttons.
 *
 * Purely `values`-driven static mocks: each wallet previews only while its own
 * toggle is on, reflecting the selected button colour and label. The whole
 * preview field is hidden (via fieldVisibility) when neither wallet is enabled.
 *
 * @param {Object} props
 * @param {Object} props.values - All current form field values for the page.
 */
export default function DigitalWalletPreview( { values } ) {
	const googleEnabled = values?.digital_wallets_google_pay_enabled !== 'no';
	const appleEnabled = values?.digital_wallets_apple_pay_enabled !== 'no';

	const googleColor =
		values?.digital_wallets_google_pay_button_color ?? 'black';
	const googleType =
		values?.digital_wallets_google_pay_button_type ??
		values?.digital_wallets_button_type ??
		'buy';

	const appleColor =
		values?.digital_wallets_apple_pay_button_color ?? 'black';
	const appleType =
		values?.digital_wallets_apple_pay_button_type ??
		values?.digital_wallets_button_type ??
		'buy';

	return (
		<div className="wc-square-preview">
			{ googleEnabled && (
				<WalletButton
					color={ googleColor }
					buttonType={ googleType }
					mark={ <GooglePayMark /> }
					ariaLabel={ __(
						'Google Pay button preview',
						'woocommerce-square'
					) }
				/>
			) }
			{ appleEnabled && (
				<WalletButton
					color={ appleColor }
					buttonType={ appleType }
					mark={ <ApplePayMark /> }
					ariaLabel={ __(
						'Apple Pay button preview',
						'woocommerce-square'
					) }
				/>
			) }
		</div>
	);
}
