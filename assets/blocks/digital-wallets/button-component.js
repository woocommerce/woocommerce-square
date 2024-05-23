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

					const applePayButtonContainer = document.getElementById('apple-pay-button');
					const color = getSquareServerData().applePayColor || 'black';
					const type = getSquareServerData().applePayButtonType || 'buy';

					// TODO: We need to get the value of this text from the server. It isn't returned in getSquareServerData right now so this will always use `buy` as the text.
					applePayButtonContainer.querySelector('.text').innerText = `${type} with`;
					applePayButtonContainer.classList.add('wc-square-wallet-button-with-text');
					applePayButtonContainer.style.cssText += `-apple-pay-button-type: ${type};`;

					applePayButtonContainer.classList.add(`wc-square-wallet-button-${color}`);
					applePayButtonContainer.style.cssText += `-apple-pay-button-style: ${color};`;
					applePayButtonContainer.style.display = 'block';

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

	const isGooglePayDisabled =
		getSquareServerData().hideButtonOptions.includes('google');
	const isApplePayDisabled =
		getSquareServerData().hideButtonOptions.includes('apple');

	return (
		<>
			{!isGooglePayDisabled && (
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
			{!isApplePayDisabled && (
				<div
					tabIndex={0}
					role="button"
					ref={applePaybuttonRef}
					id="apple-pay-button"
					className="apple-pay-button wc-square-wallet-buttons"
					onClick={() => {
						setClickedButton(applePayBtn);
						handleSubmission();
					}}
					onKeyDown={() => {
						setClickedButton(applePayBtn);
						handleSubmission();
					}}
				>
					<span className="text"></span>
					<span className="logo"></span>
				</div>
			)}
		</>
	);
};

export default ButtonComponent;
