/**
 * Multi-line textarea with a character counter "current / max" shown on
 * the right edge of the input, matching the Figma design for the gateway
 * Description fields on the Payments & Transactions tab.
 *
 * @param {Object}   props
 * @param {Object}   props.field    - Field config (label, description, maxLength).
 * @param {string}   props.value    - Current value.
 * @param {Function} props.onChange - SDK change handler.
 */
export default function TextareaCounted( { field, value, onChange } ) {
	const max = field?.maxLength ?? 0;
	const current = ( value ?? '' ).length;
	const helpId = `wc-square-field-${ field.id }-help`;

	return (
		<div className="wc-square-text-counted">
			{ field?.label && (
				<label
					className="wc-square-text-counted__label"
					htmlFor={ `wc-square-field-${ field.id }` }
				>
					{ field.label }
				</label>
			) }
			<div className="wc-square-text-counted__input-wrapper">
				<textarea
					id={ `wc-square-field-${ field.id }` }
					className="wc-square-text-counted__input wc-square-text-counted__input--textarea"
					rows={ 3 }
					value={ value ?? '' }
					maxLength={ max || undefined }
					// Tie the visible help text to the control so screen
					// readers announce it on focus, as the SDK's native
					// fields do.
					aria-describedby={ field?.description ? helpId : undefined }
					onChange={ ( e ) => onChange( e.target.value ) }
				/>
				{ !! max && (
					<span className="wc-square-text-counted__counter wc-square-text-counted__counter--textarea">
						{ current } / { max }
					</span>
				) }
			</div>
			{ field?.description && (
				<p className="wc-square-text-counted__help" id={ helpId }>
					{ field.description }
				</p>
			) }
		</div>
	);
}
