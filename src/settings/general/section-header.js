import { RawHTML } from '@wordpress/element';

/**
 * In-card section header field component.
 *
 * Renders a heading (title + optional description) for a sub-section WITHIN a
 * single SDK card — e.g. the "Google Pay" / "Apple Pay" sub-headers inside the
 * one "Digital wallet settings" card in the Figma design. This is distinct from
 * a card header: the card title/description use the SDK group `title`/`description`.
 * The SDK's native `info` field type is unsuitable here (it reads `field.label`,
 * not a title, and forces a gray background).
 *
 * Field schema:
 *   - field.label       (string) — heading text
 *   - field.description  (string) — optional description HTML (rendered with RawHTML)
 *
 * @param {Object} props
 * @param {Object} props.field
 */
export default function SectionHeader( { field } ) {
	const title = field?.label ?? '';
	const description = field?.description ?? '';

	if ( ! title && ! description ) {
		return null;
	}

	return (
		<div className="wc-square-section-header">
			{ title && (
				<h3 className="wc-square-section-header__title">{ title }</h3>
			) }
			{ description && (
				<div className="wc-square-section-header__description">
					<RawHTML>{ description }</RawHTML>
				</div>
			) }
		</div>
	);
}
