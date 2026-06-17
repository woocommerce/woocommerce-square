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
 * option values expected by the REST API.
 *
 * @param {Object}   props
 * @param {Object}   props.field    SDK field descriptor (label, etc.).
 * @param {string}   props.value    Current value: 'yes' | 'no'.
 * @param {Function} props.onChange Called with the new value on change.
 */
export default function EnvironmentSelector( { field, value, onChange } ) {
	return (
		<div>
			{ field?.label && (
				<div style={ { fontWeight: 600, marginBottom: '12px' } }>
					{ field.label }
				</div>
			) }
			<fieldset style={ { border: 'none', padding: 0, margin: 0 } }>
				{ OPTIONS.map( ( option ) => (
					<label
						key={ option.value }
						htmlFor={ `square_environment_${ option.value }` }
						aria-label={ option.label }
						style={ {
							display: 'flex',
							alignItems: 'flex-start',
							gap: '8px',
							marginBottom: '12px',
							cursor: 'pointer',
							fontWeight: 'normal',
						} }
					>
						<input
							type="radio"
							id={ `square_environment_${ option.value }` }
							name="square_environment"
							value={ option.value }
							checked={ value === option.value }
							onChange={ () => onChange( option.value ) }
							style={ { marginTop: '3px', flexShrink: 0 } }
						/>
						<span
							style={ {
								display: 'flex',
								flexDirection: 'column',
								gap: '2px',
							} }
						>
							<span style={ { fontWeight: 500 } }>
								{ option.label }
							</span>
							<span
								style={ { color: '#757575', fontSize: '13px' } }
							>
								{ option.description }
							</span>
						</span>
					</label>
				) ) }
			</fieldset>
		</div>
	);
}
