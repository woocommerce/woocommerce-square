/**
 * External dependencies
 */
import { useEffect, useState } from '@wordpress/element';
import { decodeEntities } from '@wordpress/html-entities';

/**
 * Internal dependencies
 */
import {
	getSquareCashAppPayServerData,
	createPaymentRequest,
	setContinuationSession,
	log,
} from './utils';
import { PAYMENT_METHOD_ID } from './constants';

const buttonId = 'wc-square-cash-app-pay';

/**
 * Square's credit card component
 *
 * @param {Object} props Incoming props
 */
export const ComponentCashAppPay = (props) => {
	const [errorMessage, setErrorMessage] = useState(null);
	const [isLoaded, setIsLoaded] = useState(false);
	const [paymentNonce, setPaymentNonce] = useState('');
	const {
		applicationId,
		locationId,
		buttonStyles,
		referenceId,
		generalError,
		gatewayIdDasherized,
		description,
	} = getSquareCashAppPayServerData();
	const {
		onSubmit,
		emitResponse,
		eventRegistration,
		billing: { cartTotal, currency },
		components: { LoadingMask },
		activePaymentMethod,
	} = props;
	const { onPaymentSetup } = eventRegistration;

	// Checkout handler.
	useEffect(() => {
		const unsubscribe = onPaymentSetup(() => {
			if (!paymentNonce) {
				return {
					type: emitResponse.responseTypes.ERROR,
					message: generalError,
				};
			}
			const paymentMethodData = {
				[`wc-${gatewayIdDasherized}-payment-nonce`]: paymentNonce || '',
			};
			return {
				type: emitResponse.responseTypes.SUCCESS,
				meta: {
					paymentMethodData,
				},
			};
		});
		return unsubscribe;
	}, [
		emitResponse.responseTypes.SUCCESS,
		emitResponse.responseTypes.ERROR,
		onPaymentSetup,
		paymentNonce,
	]);

	// Initialize the Square Cash App Pay Button.
	useEffect(() => {
		setIsLoaded(false);
		setErrorMessage(null);
		// Bail if Square is not loaded.
		if (!window.Square) {
			return;
		}

		log('[Square Cash App Pay] Initializing Square Cash App Pay Button');
		const payments = window.Square.payments(applicationId, locationId);
		if (!payments) {
			return;
		}

		(async () => {
			try {
				const paymentRequest = await createPaymentRequest(payments);
				if (window.wcSquareCashAppPay) {
					await window.wcSquareCashAppPay.destroy();
					window.wcSquareCashAppPay = null;
				}

				const cashAppPay = await payments.cashAppPay(paymentRequest, {
					redirectURL: window.location.href,
					referenceId: referenceId,
				});
				await cashAppPay.attach(`#${buttonId}`, buttonStyles);

				// Handle the payment response.
				cashAppPay.addEventListener('ontokenization', (event) => {
					const { tokenResult, error } = event.detail;
					if (error) {
						setPaymentNonce('');
						setErrorMessage(error.message);
					} else if (tokenResult.status === 'OK') {
						const nonce = tokenResult.token;
						if (!nonce) {
							setPaymentNonce('');
							setErrorMessage(generalError);
						}

						// Set the nonce.
						setPaymentNonce(nonce);

						// Place an Order.
						onSubmit();
					}
				});

				// Handle the customer interaction. set continuation session to select the Cash App Pay payment method after the redirect back from the Cash App.
				cashAppPay.addEventListener('customerInteraction', (event) => {
					if (event.detail && event.detail.isMobile) {
						return setContinuationSession();
					}
				});

				window.wcSquareCashAppPay = cashAppPay;
				log('[Square Cash App Pay] Square Cash App Pay Button Loaded');
			} catch (e) {
				setErrorMessage(generalError);
				console.error(e);
			}
			setIsLoaded(true);
		})();

		return () =>
			(async () => {
				if (window.wcSquareCashAppPay) {
					await window.wcSquareCashAppPay.destroy();
					window.wcSquareCashAppPay = null;
				}
			})();
	}, [cartTotal.value, currency.code]);

	// Disable the place order button when Cash App Pay is active. TODO: find a better way to do this.
	useEffect(() => {
		const button = document.querySelector(
			'button.wc-block-components-checkout-place-order-button'
		);
		if (button) {
			if (activePaymentMethod === PAYMENT_METHOD_ID && !paymentNonce) {
				button.setAttribute('disabled', 'disabled');
			}
			return () => {
				button.removeAttribute('disabled');
			};
		}
	}, [activePaymentMethod, paymentNonce]);

	return (
		<>
			<p>{decodeEntities(description || '')}</p>
			{errorMessage && (
				<div className="woocommerce-error">{errorMessage}</div>
			)}
			{!errorMessage && (
				<LoadingMask isLoading={!isLoaded} showSpinner={true}>
					<div id={buttonId}></div>
				</LoadingMask>
			)}
		</>
	);
};
