import { CheckboxControl } from '@wordpress/components';
import { RawHTML } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Inventory synchronization toggle.
 *
 * `enable_inventory_sync` is a single option key, but the legacy screen explains
 * it differently depending on the system of record, and the system of record can
 * change without a reload. The help text therefore cannot be baked into the
 * server-rendered schema, which is why this is not the native checkbox type.
 * Everything else matches the native rendering: no self-save, the value rides
 * the SDK form and is persisted by the page Save button.
 *
 * @param {Object}   props
 * @param {Object}   props.field    SDK field descriptor (id + label).
 * @param {*}        props.value    Current value: 'yes' | 'no' | boolean.
 * @param {Object}   props.values   All current form values.
 * @param {Function} props.onChange SDK change handler for this field.
 */
export default function SyncInventoryToggle( {
	field,
	value,
	values,
	onChange,
} ) {
	const isSquare = values?.system_of_record === 'square';

	const description = isSquare
		? __(
				'Inventory is fetched from Square periodically and updated in WooCommerce.',
				'woocommerce-square'
		  )
		: sprintf(
				/* translators: %1$s and %2$s are placeholders for the strong tag */
				__(
					'Inventory is %1$salways fetched from Square%2$s periodically to account for sales from other channels.',
					'woocommerce-square'
				),
				'<strong>',
				'</strong>'
		  );

	return (
		<div className="wc-square-sync-inventory">
			<CheckboxControl
				className="wc-settings-ui__control"
				label={ field?.label ?? '' }
				checked={ value === true || value === 'yes' }
				__nextHasNoMarginBottom
				onChange={ onChange }
			/>
			<div className="wc-square-sync-inventory__description">
				<RawHTML>{ description }</RawHTML>
			</div>
		</div>
	);
}
