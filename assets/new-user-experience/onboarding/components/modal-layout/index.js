export const ModalLayout = ( { children } ) => {
	const style = {
		padding: '75px 30px 30px 78px',
		width: '100%',
		maxWidth: '1000px',
		margin: '0 auto',
		marginTop: '147px',
		backgroundColor: '#fff',
	};

	return (
		<div style={ style }>
			{ children }
		</div>
	)
};
