/**
 * External dependencies
 */
import { useEffect, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { tokenize } from './utils';
import { getSquareServerData } from '../square-utils/utils';
import {
	useSquare,
	usePaymentRequest,
	useShippingContactChangeHandler,
	useShippingOptionChangeHandler,
	useGooglePay,
	useApplePay,
	usePaymentProcessing,
} from './hooks';

const Content = ({
	billing,
	shippingData,
	onClick,
	onClose,
	onSubmit,
	setExpressPaymentError,
	emitResponse,
	eventRegistration: { onPaymentSetup },
}) => {
	const { needsShipping } = shippingData;
	const payments = useSquare();
	const paymentRequest = usePaymentRequest(payments, needsShipping);
	const [googlePay, googlePayRef] = useGooglePay(payments, paymentRequest);
	const [applePay, applePayRef] = useApplePay(payments, paymentRequest);
	const [tokenResult, setTokenResult] = useState(null);
	const [clickedButton, setClickedButton] = useState(null);

	useShippingContactChangeHandler(paymentRequest);
	useShippingOptionChangeHandler(paymentRequest);
	usePaymentProcessing(
		payments,
		billing,
		clickedButton,
		tokenResult,
		emitResponse,
		onPaymentSetup
	);

	useEffect(() => {
		if (!clickedButton) {
			return;
		}

		setExpressPaymentError('');
		onClick();

		(async () => {
			const __tokenResult = await tokenize(clickedButton);

			if (!__tokenResult) {
				onClose();
				setClickedButton(null);
			} else {
				setTokenResult(__tokenResult);
				onSubmit();
			}
		})();
	}, [clickedButton]);

	const isGooglePayDisabled =
		getSquareServerData().hideButtonOptions.includes('google');
	const isApplePayDisabled =
		getSquareServerData().hideButtonOptions.includes('apple');

	const googlePayExpressButton = !isGooglePayDisabled && (
		<div
			tabIndex={0}
			role="button"
			ref={googlePayRef}
			onClick={() => setClickedButton(googlePay)}
			onKeyDown={() => setClickedButton(googlePay)}
		></div>
	);

	const applePayExpressButton = !isApplePayDisabled && (
		<div
			tabIndex={0}
			role="button"
			ref={applePayRef}
			onClick={() => setClickedButton(applePay)}
			onKeyDown={() => setClickedButton(applePay)}
		></div>
	);

	return (
		<>
			{applePayExpressButton}
			{googlePayExpressButton}
		</>
	);
};

export default Content;
