/**
 * External dependencies
 */
import { useRef, useEffect, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import DigitalWalletContext from './context';
import ButtonComponent from './button-component';
import { getSquareServerData } from '../square-utils';
import {
	useSquare,
	usePaymentRequest,
	useOnClickHandler,
	useShippingContactChangeHandler,
	useShippingOptionChangeHandler,
	useGooglePay,
	usePaymentProcessing,
} from './hooks';

const Content = ( {
	billing,
	components,
	shippingData,
	onClick,
	onClose,
	onSubmit,
	setExpressPaymentError,
	eventRegistration: {
		onPaymentSetup,
	}
} ) => {
	const { needsShipping } = shippingData;
	const payments = useSquare();
	const paymentRequest = usePaymentRequest( payments, needsShipping, billing );
	const [ googlePay, googlePayRef ] = useGooglePay( payments, paymentRequest );
	const [ tokenResult, setTokenResult ] = useState( null );
	const [ clickedButton, setClickedButton ] = useState( null );
	const onPaymentRequestButtonClick = useOnClickHandler(
		setExpressPaymentError,
		onClick,
		googlePay,
	);

	useShippingContactChangeHandler( paymentRequest );
	useShippingOptionChangeHandler( paymentRequest );
	usePaymentProcessing(
		payments,
		billing,
		clickedButton,
		tokenResult,
		onPaymentSetup,
	);

	async function onClickHandler( button ) {
		const __tokenResult = await onPaymentRequestButtonClick();

		if ( ! __tokenResult ) {
			onClose();
		} else {
			setClickedButton( button );
			setTokenResult( __tokenResult );
			onSubmit();
		}
	}

	return (
		<>
			<div
				ref={ googlePayRef }
				onClick={ () => onClickHandler( googlePay ) }
			>
			</div>
		</>
	);
};

export default Content;
