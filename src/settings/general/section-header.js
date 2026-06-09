import { RawHTML } from '@wordpress/element';

/**
 * Section header field component.
 *
 * Renders an in-panel heading (title + description) so each card looks like
 * a single-column section as required by the design. The SDK's native
 * `info` field type is unsuitable here — it reads `field.label` (not title)
 * and forces a gray background that we don't want.
 *
 * Field schema:
 *   - field.label       (string) — heading text
 *   - field.description (string) — description HTML (rendered with RawHTML)
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
				<h3 className="wc-square-section-header__title">
					{ title }
				</h3>
			) }
			{ description && (
				<div className="wc-square-section-header__description">
					<RawHTML>{ description }</RawHTML>
				</div>
			) }
		</div>
	);
}
