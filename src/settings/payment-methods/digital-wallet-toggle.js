import { ToggleControl } from '@wordpress/components';

const GOOGLE = 'digital_wallets_google_pay_enabled';
const APPLE = 'digital_wallets_apple_pay_enabled';

/**
 * Per-wallet enable toggle (Google Pay / Apple Pay) inside the Digital Wallet
 * Customize sub-page.
 *
 * Only updates SDK values — nothing self-saves; everything persists when the
 * page Save button is clicked. Coupling rule: when BOTH wallets end up
 * disabled, the parent Digital Wallet method is auto-disabled too and the view
 * returns to the list — otherwise the method would be "on" with nothing to
 * display. The per-wallet values themselves are never wiped, so re-enabling the
 * parent restores the merchant's prior choices.
 *
 * @param {Object}   props
 * @param {Object}   props.field    - Field config from the SDK (id + label).
 * @param {string}   props.value    - 'yes' or 'no'.
 * @param {Object}   props.values   - All current form values (to read the sibling).
 * @param {Function} props.setValue - SDK setter for other fields.
 * @param {Function} props.onChange - SDK change handler for this field.
 */
export default function DigitalWalletToggle( {
	field,
	value,
	values,
	setValue,
	onChange,
} ) {
	const id = field?.id;
	const siblingId = id === GOOGLE ? APPLE : GOOGLE;

	return (
		<ToggleControl
			label={ field?.label ?? '' }
			checked={ value === 'yes' }
			__nextHasNoMarginBottom
			onChange={ ( checked ) => {
				onChange( checked ? 'yes' : 'no' );

				// Both wallets now off → disable the parent method and go back to
				// the list (its Customize entry hides while the parent is off).
				const siblingOn = values?.[ siblingId ] === 'yes';
				if ( ! checked && ! siblingOn ) {
					setValue( 'enable_digital_wallets', 'no' );
					setValue( 'payment_methods_view', 'list' );
				}
			} }
		/>
	);
}
