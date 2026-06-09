/**
 * Divider field component.
 *
 * Renders a thin horizontal rule. The SDK has no native divider/separator
 * field type, so we provide one for use in the Square modern settings panels
 * to visually separate groups of fields from action buttons (e.g. between the
 * sandbox credentials and the Disconnect from Square button).
 *
 * Field schema: just `{ id, type: 'text', component: 'square/divider', is_option: false }`.
 * No props are needed.
 */
export default function Divider() {
	return <hr className="wc-square-divider" aria-hidden="true" />;
}
