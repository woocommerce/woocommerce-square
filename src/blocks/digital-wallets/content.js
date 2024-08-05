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
	eventRegistration: { onPaymentSetup, onCheckoutFail },
}) => {
	const { needsShipping } = shippingData;
	const payments = useSquare();
	const paymentRequest = usePaymentRequest(payments, needsShipping);
	const [googlePay, googlePayRef] = useGooglePay(payments, paymentRequest);
	const [applePay, applePayRef] = useApplePay(payments, paymentRequest);
	const [tokenResult, setTokenResult] = useState(false);

	useShippingContactChangeHandler(paymentRequest);
	useShippingOptionChangeHandler(paymentRequest);
	usePaymentProcessing(
		payments,
		billing,
		tokenResult,
		emitResponse,
		onPaymentSetup
	);

	useEffect(() => {
		const unsubscribe = onCheckoutFail(() => {
			onClose();
			return true;
		});
		return unsubscribe;
	}, [onCheckoutFail]);

	const isGooglePayDisabled =
		getSquareServerData().hideButtonOptions.includes('google');
	const isApplePayDisabled =
		getSquareServerData().hideButtonOptions.includes('apple');

	function onClickHandler(buttonInstance) {
		if (!buttonInstance) {
			return;
		}

		setExpressPaymentError('');
		onClick();

		(async () => {
			const __tokenResult = await tokenize(buttonInstance);

			if (!__tokenResult) {
				onClose();
			} else {
				setTokenResult(__tokenResult);
				onSubmit();
			}
		})();
	}

	const googlePayExpressButton = !isGooglePayDisabled && (
		<div // eslint-disable-line jsx-a11y/click-events-have-key-events
			tabIndex={0}
			role="button"
			ref={googlePayRef}
			onClick={() => onClickHandler(googlePay)}
		></div>
	);

	const applePayExpressButton = !isApplePayDisabled && (
		<div // eslint-disable-line jsx-a11y/click-events-have-key-events
			tabIndex={0}
			role="button"
			ref={applePayRef}
			onClick={() => onClickHandler(applePay)}
			className="apple-pay-button wc-square-wallet-buttons"
		>
			<span className="text"></span>
			<span className="logo"></span>
		</div>
	);

	return (
		<>
			{applePayExpressButton}
			{googlePayExpressButton}
		</>
	);
};

export default Content;
