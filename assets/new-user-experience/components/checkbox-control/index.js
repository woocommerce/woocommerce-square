/**
 * External dependencies
 */
import { CheckboxControl } from '@wordpress/components';

/**
 * Internal dependencies
 */
import './index.scss';

export const SquareCheckboxControl = ( { checked, onChange, label } ) => {
	return (
		<div className="woo-square-setting__input-field--checkbox">
			<CheckboxControl
				checked={ checked }
				onChange={ onChange }
			/>
			<div className="woo-square-setting__input-field--checkbox-label">
				{ label }
			</div>
		</div>
	)
}