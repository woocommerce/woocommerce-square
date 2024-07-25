/**
 * External dependencies
 */
import { useEffect, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { tokenize } from './utils';
import {
	useSquare,
	usePaymentRequest,
	useShippingContactChangeHandler,
	useShippingOptionChangeHandler,
	useGooglePay,
	useApplePay,
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
	const [ applePay, applePayRef ] = useApplePay( payments, paymentRequest );
	const [ tokenResult, setTokenResult ] = useState( null );
	const [ clickedButton, setClickedButton ] = useState( null );

	useShippingContactChangeHandler( paymentRequest );
	useShippingOptionChangeHandler( paymentRequest );
	usePaymentProcessing(
		payments,
		billing,
		clickedButton,
		tokenResult,
		onPaymentSetup,
	);

	useEffect( () => {
		if ( ! clickedButton ) {
			return;
		}

		setExpressPaymentError( '' );
		onClick();

		( async () => {
			const __tokenResult = await tokenize( clickedButton );

			if ( ! __tokenResult ) {
				onClose();
			} else {
				setTokenResult( __tokenResult );
				onSubmit();
			}
		} )();
	}, [ clickedButton ] );

	return (
		<>
			<div
				ref={ googlePayRef }
				onClick={ () => setClickedButton( googlePay ) }
			>
			</div>
			<div
				ref={ applePayRef }
				onClick={ () => setClickedButton( applePay ) }
			>
			</div>
		</>
	);
};

export default Content;
