/**
 * External dependencies
 */
import { CheckboxControl } from '@wordpress/components';

/**
 * Internal dependencies
 */
import './index.scss';

const withSquareCheckboxControl = () => {
	return (props) => {
		const { label, ...remainingProps } = props;
		return (
			<div className="woo-square-setting__input-field--checkbox">
				<CheckboxControl {...remainingProps} />
				<div className="woo-square-setting__input-field--checkbox-label">
					{label}
				</div>
			</div>
		);
	};
};

export const SquareCheckboxControl = withSquareCheckboxControl();
