import { __ } from '@wordpress/i18n';

const OPTIONS = [
	{
		value: 'no',
		label: __( 'Production', 'woocommerce-square' ),
		description: __(
			'Connect to a live production account for real transactions.',
			'woocommerce-square'
		),
	},
	{
		value: 'yes',
		label: __( 'Sandbox', 'woocommerce-square' ),
		description: __(
			'Connect to a sandbox account for testing purposes.',
			'woocommerce-square'
		),
	},
];

/**
 * Environment selector custom field component.
 *
 * Renders a radio group (Production / Sandbox) and calls `onChange` with
 * 'yes' (sandbox) or 'no' (production) — matching the `enable_sandbox`
 * option values expected by the REST API. The SDK's native `radio` field
 * type renders a dropdown (SelectControl), not the Figma radio layout with
 * per-option descriptions, so this stays a custom component.
 *
 * @param {Object}   props
 * @param {Object}   props.field    SDK field descriptor (label, etc.).
 * @param {string}   props.value    Current value: 'yes' | 'no'.
 * @param {Function} props.onChange Called with the new value on change.
 */
export default function EnvironmentSelector( { field, value, onChange } ) {
	return (
		<fieldset className="wc-square-environment-selector">
			{ field?.label && (
				<legend className="wc-square-environment-selector__legend">
					{ field.label }
				</legend>
			) }
			{ OPTIONS.map( ( option ) => (
				<label
					key={ option.value }
					htmlFor={ `square_environment_${ option.value }` }
					className="wc-square-environment-selector__option"
				>
					<input
						type="radio"
						id={ `square_environment_${ option.value }` }
						name="square_environment"
						value={ option.value }
						checked={ value === option.value }
						onChange={ () => onChange( option.value ) }
						className="wc-square-environment-selector__input"
					/>
					<span className="wc-square-environment-selector__text">
						<span className="wc-square-environment-selector__label">
							{ option.label }
						</span>
						<span className="wc-square-environment-selector__description">
							{ option.description }
						</span>
					</span>
				</label>
			) ) }
		</fieldset>
	);
}
