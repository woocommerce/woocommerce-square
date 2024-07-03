/**
 * Internal dependencies
 */
import './index.scss';

export const InputWrapper = ( {
	label,
	children,
	description,
	variant,
	indent = 0,
	className = '',
} ) => {
	if ( variant === 'boxed' ) {
		return (
			<div
				className={
					'woo-square-setting__input-wrapper woo-square-setting__input-wrapper--boxed ' +
					className
				}
			>
				<div className="woo-square-setting__input-wrapper--boxed-bg">
					<div className="woo-square-setting__input-label">
						{ label }
					</div>
					<div className="woo-square-setting__input-field">
						{ children }
					</div>
				</div>
				<div className="woo-square-setting__input-description">
					{ description }
				</div>
			</div>
		);
	}

	const style = {
		marginLeft: `${ indent * 16 }px`,
	};

	return (
		<div
			className={ 'woo-square-setting__input-wrapper ' + className }
			style={ style }
		>
			{ label && (
				<div className="woo-square-setting__input-label">{ label }</div>
			) }
			{ children }
			{ description && (
				<div className="woo-square-setting__input-description">
					{ description }
				</div>
			) }
		</div>
	);
};
