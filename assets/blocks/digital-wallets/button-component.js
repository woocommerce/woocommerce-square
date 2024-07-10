/**
 * External dependencies
 */
import {
	useContext,
	useRef,
	useEffect,
	useLayoutEffect,
	useState,
} from '@wordpress/element';

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
	const {
		billing,
		onClick,
		onSubmit,
		onClose,
		eventRegistration,
		paymentStatus,
		buttonAttributes,
	} = props;
	const { onPaymentSetup } = eventRegistration;
	const googlePaybuttonRef = useRef();

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

					const applePayButtonContainer =
						document.getElementById('apple-pay-button');
					const color = getSquareServerData().applePayColor;
					const type = getSquareServerData().applePayType;

					if (type !== 'plain') {
						applePayButtonContainer.querySelector(
							'.text'
						).innerText =
							`${type.charAt(0).toUpperCase()}${type.slice(1)} with`;
						applePayButtonContainer.classList.add(
							'wc-square-wallet-button-with-text'
						);
					}

					applePayButtonContainer.style.cssText += `-apple-pay-button-type: ${type};`;
					applePayButtonContainer.style.cssText += `-apple-pay-button-style: ${color};`;
					applePayButtonContainer.style.display = 'block';
					applePayButtonContainer.classList.add(
						`wc-square-wallet-button-${color}`
					);

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
	}, [payments]); // eslint-disable-line react-hooks/exhaustive-deps

	useLayoutEffect(() => {
		if (!clickedButton) {
			return;
		}

		if (!paymentStatus.isStarted) {
			return;
		}

		const verificationDetails = buildVerificationDetails(billing);
		const unsubscribe = onPaymentSetup(() => {
			const checkout = initiateCheckout(
				payments,
				verificationDetails,
				clickedButton
			);
			checkout.then((response) => {
				if (response.type === 'failure') {
					setClickedButton(null);
					onClose();
				}
			});
			return checkout;
		});
		return unsubscribe;
	}, [clickedButton, onPaymentSetup, paymentStatus.isStarted]); // eslint-disable-line react-hooks/exhaustive-deps

	if (!payments) {
		return null;
	}

	const isGooglePayDisabled =
		getSquareServerData().hideButtonOptions.includes('google');
	const isApplePayDisabled =
		getSquareServerData().hideButtonOptions.includes('apple');

	// Default button height aligns with Woo defaults
	let buttonHeight = '48';
	if (typeof buttonAttributes !== 'undefined') {
		buttonHeight = buttonAttributes?.height || buttonHeight;
	}

	return (
		<>
			{!isApplePayDisabled && (
				<div
					style={{ height: buttonHeight }}
					tabIndex={0}
					role="button"
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
			{!isGooglePayDisabled && (
				<div
					style={{ height: `${buttonHeight}px` }}
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
		</>
	);
};

export default ButtonComponent;
