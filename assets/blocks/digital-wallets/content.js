/**
 * External dependencies
 */
import { useRef, useEffect } from '@wordpress/element';

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
		onCheckoutFail,
		onCheckoutSuccess,
	},
	paymentStatus
} ) => {
	const { needsShipping } = shippingData;
	const payments = useSquare();
	const paymentRequest = usePaymentRequest( payments, needsShipping, billing );
	const [ googlePay, googlePayRef ] = useGooglePay( payments, paymentRequest );
	const onPaymentRequestButtonClick = useOnClickHandler(
		setExpressPaymentError,
		onClick,
		onSubmit,
	);

	useShippingContactChangeHandler( paymentRequest );
	useShippingOptionChangeHandler( paymentRequest );
	usePaymentProcessing(
		billing,
		googlePay,
		onPaymentSetup,
		onClose
	);

	console.table(paymentStatus)

	useEffect( () => {
		if ( paymentStatus.hasError ) {
			onClose();
			setExpressPaymentError( '' );
		}
	}, [
		paymentStatus
	] );

	return (
		<>
			<div
				ref={ googlePayRef }
				onClick={ onPaymentRequestButtonClick }
			>
			</div>
		</>
	);
};

export default Content;
