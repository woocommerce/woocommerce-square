/**
 * Sub-section heading inside a group card (e.g. "Customer profiles",
 * "Detailed decline messages") — bolder than helper text, smaller than
 * the group title.
 *
 * @param {Object} props
 * @param {Object} props.field - Field config (label).
 */
export default function SubHeader( { field } ) {
	if ( ! field?.label ) {
		return null;
	}
	return (
		<h4 className="wc-square-sub-header">{ field.label }</h4>
	);
}
