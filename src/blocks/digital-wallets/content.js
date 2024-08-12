/**
 * External dependencies
 */
import { useEffect, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import DigitalWalletContext from './context';
import ButtonComponent from './button-component';
import { getSquareServerData } from '../square-utils';

const Content = ( props ) => {
	const [ payments, setPayments ] = useState( null );
	const { onCheckoutFail } = props.eventRegistration;
	const { onClose } = props;

	useEffect( () => {
		const applicationId = getSquareServerData().applicationId;
		const locationId = getSquareServerData().locationId;

		if ( ! window.Square ) {
			return;
		}

		try {
			const __payments = window.Square.payments(
				applicationId,
				locationId
			);
			setPayments( __payments );
		} catch ( e ) {
			console.error( e );
		}
	}, [] );

	useEffect( () => {
		const unsubscribeOnCheckoutFail = onCheckoutFail( () => {
			onClose();
			return true;
		} );

		return unsubscribeOnCheckoutFail;
	}, [ onCheckoutFail ] ); // eslint-disable-line react-hooks/exhaustive-deps

	return (
		<>
			<DigitalWalletContext.Provider value={ { payments, props } }>
				<ButtonComponent />
			</DigitalWalletContext.Provider>
		</>
	);
};

export default Content;
