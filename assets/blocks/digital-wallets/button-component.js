/**
 * External dependencies
 */
import { useContext, useRef, useEffect, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import DigitalWalletContext from './context';
import {
	createPaymentRequest,
	initiateCheckout,
	buildVerificationDetails,
} from './submission-handler';

import { getSquareServerData } from '../square-utils';

const ButtonComponent = () => {
	const { payments, props } = useContext(DigitalWalletContext);
	const [googlePayBtn, setGooglePayBtn] = useState(null);
	const [applePayBtn, setApplePayBtn] = useState(null);
	const [clickedButton, setClickedButton] = useState(null);
	const { billing, onClick, onSubmit, eventRegistration, paymentStatus } =
		props;
	const { onPaymentSetup } = eventRegistration;
	const googlePaybuttonRef = useRef();
	const applePaybuttonRef = useRef();

	const handleSubmission = async () => {
		onClick();
		onSubmit();
	};

	useEffect(() => {
		if (!payments) {
			return;
		}

		(async () => {
			let googlePay = null;
			let applePay = null;

			try {
				const paymentRequest = await createPaymentRequest(payments);

				if (!googlePayBtn) {
					googlePay = await payments.googlePay(paymentRequest);
					await googlePay.attach(googlePaybuttonRef.current, {
						buttonColor: getSquareServerData().googlePayColor,
						buttonSizeMode: 'fill',
						buttonType: 'long',
					});
					setGooglePayBtn(googlePay);
				}

				if (!applePayBtn) {
					applePay = await payments.applePay(paymentRequest);
					/*
					 * Apple Pay doesn't need to be attached.
					 * https://developer.squareup.com/docs/web-payments/apple-pay#:~:text=Note%3A%20You%20do%20not%20need%20to%20%60attach%60%20applePay.
					 */
					setApplePayBtn(applePay);
				}
			} catch (e) {
				console.log(e);
			}
		})();

		return () =>
			(async () => {
				if (googlePayBtn) {
					await googlePayBtn.destroy();
				}

				if (applePayBtn) {
					await applePayBtn.destroy();
				}
			})();
	}, [payments]);

	useEffect(() => {
		if (!googlePayBtn) {
			return;
		}

		if (!paymentStatus.isStarted) {
			return;
		}

		const verificationDetails = buildVerificationDetails(billing);
		const unsubscribe = onPaymentSetup(() =>
			initiateCheckout(payments, verificationDetails, clickedButton)
		);
		return unsubscribe;
	}, [googlePayBtn, applePayBtn, onPaymentSetup, paymentStatus.isStarted]);

	if (!payments) {
		return null;
	}

	const isGooglePayEnabled =
		getSquareServerData().hideButtonOptions.includes('google');
	const isApplePayEnabled =
		getSquareServerData().hideButtonOptions.includes('apple');

	return (
		<>
			{!isGooglePayEnabled && (
				<div
					tabIndex={0}
					role="button"
					ref={googlePaybuttonRef}
					onClick={() => {
						setClickedButton(googlePayBtn);
						handleSubmission();
					}}
					onKeyDown={() => {
						setClickedButton(googlePayBtn);
						handleSubmission();
					}}
				></div>
			)}
			{!isApplePayEnabled && (
				<div
					tabIndex={0}
					role="button"
					ref={applePaybuttonRef}
					onClick={() => {
						setClickedButton(applePayBtn);
						handleSubmission();
					}}
					onKeyDown={() => {
						setClickedButton(applePayBtn);
						handleSubmission();
					}}
				></div>
			)}
		</>
	);
};

export default ButtonComponent;
