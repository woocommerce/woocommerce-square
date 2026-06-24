/**
 * Single-line text input with a character counter "current / max" shown
 * on the right edge of the input, matching the Figma design for the
 * gateway Title fields on the Payments & Transactions tab.
 *
 * @param {Object}   props
 * @param {Object}   props.field    - Field config (label, description, maxLength).
 * @param {string}   props.value    - Current value.
 * @param {Function} props.onChange - SDK change handler.
 */
export default function TextCounted( { field, value, onChange } ) {
	const max = field?.maxLength ?? 0;
	const current = ( value ?? '' ).length;

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
				<input
					id={ `wc-square-field-${ field.id }` }
					className="wc-square-text-counted__input"
					type="text"
					value={ value ?? '' }
					maxLength={ max || undefined }
					onChange={ ( e ) => onChange( e.target.value ) }
				/>
				{ !! max && (
					<span className="wc-square-text-counted__counter">
						{ current } / { max }
					</span>
				) }
			</div>
			{ field?.description && (
				<p className="wc-square-text-counted__help">
					{ field.description }
				</p>
			) }
		</div>
	);
}
