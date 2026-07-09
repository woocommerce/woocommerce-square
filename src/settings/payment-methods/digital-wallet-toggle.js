import { ToggleControl } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

const GOOGLE = 'digital_wallets_google_pay_enabled';
const APPLE = 'digital_wallets_apple_pay_enabled';

/**
 * Persist digital wallet fields immediately to the Credit Card gateway.
 *
 * @param {Object} data Payload of REST field => value pairs.
 * @return {Promise<void>}
 */
async function persist( data ) {
	await apiFetch( {
		path: '/wc/v3/wc_square/payment_settings',
		method: 'POST',
		data,
	} );
}

/**
 * Per-wallet enable toggle (Google Pay / Apple Pay) inside the Digital Wallet
 * Customize sub-page.
 *
 * Enable toggles self-save immediately (they mirror the parent list toggles,
 * which also save on change). Coupling rule: when BOTH wallets end up disabled,
 * the parent Digital Wallet method is auto-disabled too and the view returns to
 * the list — otherwise the method would be "on" with nothing to display. The
 * per-wallet values themselves are never wiped, so re-enabling the parent
 * restores the merchant's prior choices.
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
			onChange={ async ( checked ) => {
				const next = checked ? 'yes' : 'no';
				onChange( next );
				await persist( { [ id ]: next } ).catch( () => {} );

				// Both wallets now off → disable the parent method and go back to
				// the list (its Customize entry hides while the parent is off).
				const siblingOn = values?.[ siblingId ] === 'yes';
				if ( ! checked && ! siblingOn ) {
					setValue( 'enable_digital_wallets', 'no' );
					setValue( 'payment_methods_view', 'list' );
					await persist( { enable_digital_wallets: 'no' } ).catch(
						() => {}
					);
				}
			} }
		/>
	);
}
