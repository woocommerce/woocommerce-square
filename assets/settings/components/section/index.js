export const Section = ( { children }) => {
	const style = {
		marginBottom: '110px',
	};

	return (
		<div className="woo-square-setting__section" style={ style }>
			{ children }
		</div>
	);
};
