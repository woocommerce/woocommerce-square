import { ToggleControl } from '@wordpress/components';

/**
 * Renders a ToggleControl for gateway enable/disable fields in sub-pages.
 *
 * The SDK checkbox type renders as a standard checkbox. This component
 * renders it as a blue toggle to match the design. Value is 'yes'/'no'.
 *
 * @param {Object}   props
 * @param {Object}   props.field    - Field config from SDK (provides label).
 * @param {string}   props.value    - 'yes' or 'no'.
 * @param {Function} props.onChange - SDK change handler.
 */
export default function GatewayToggle( { field, value, onChange } ) {
	return (
		<ToggleControl
			label={ field?.label ?? '' }
			checked={ value === 'yes' }
			onChange={ ( checked ) => onChange( checked ? 'yes' : 'no' ) }
			__nextHasNoMarginBottom
		/>
	);
}
