/**
 * External dependencies
 */
import { useEffect, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { getSquareServerData } from '../square-utils/utils';
import {
	useGooglePay,
	useApplePay,
} from './hooks';

function useEditorCanvas() {
	const [ iframeCanvas, setIframeCanvas ] = useState( null );

	useEffect( () => {
		let observer;

		/**
		 * Function to check for the editor canvas iframe.
		 *
		 * @return {boolean} True if the iframe is found, false otherwise.
		 */
		const checkForCanvas = () => {
			const __iframeCanvas =
				document.getElementsByName( 'editor-canvas' );
			if ( __iframeCanvas.length > 0 ) {
				setIframeCanvas( __iframeCanvas[ 0 ] );
				return true;
			}
			return false;
		};

		// Perform an initial check in case the iframe already exists.
		if ( ! checkForCanvas() ) {
			// Set up the observer to listen for DOM mutations.
			observer = new MutationObserver( () => {
				if ( checkForCanvas() ) {
					// Disconnect the observer once the iframe is found.
					observer.disconnect();
				}
			} );

			// Observe changes to the DOM body or its descendants.
			observer.observe( document.body, {
				childList: true,
				subtree: true,
			} );
		}

		// Cleanup observer on component unmount.
		return () => observer?.disconnect();
	}, [] );

	return iframeCanvas;
};

function usePaymentRequest( payments, needsShipping ) {
	const [ paymentRequest, setPaymentRequest ] = useState( null );

	useEffect( () => {
		if ( ! payments ) {
			return;
		}

		const createPaymentRequest = async () => {
			const __paymentRequestObject = {
				requestShippingContact: true,
				requestEmailAddress: true,
				requestBillingContact: true,
				countryCode: 'US',
				currencyCode: 'USD',
				lineItems: [
					{
						label: 'Simple Product',
						amount: '12.00',
						pending: false,
					},
				],
				shippingOptions: [
					{
						id: '0',
						label: 'Pending',
						amount: '0.00',
						pending: false,
					},
				],
				total: {
					label: 'woo-addons (via WooCommerce)',
					amount: '12.00',
					pending: false,
				},
			};

			const __paymentRequest = payments.paymentRequest(
				__paymentRequestObject
			);

			setPaymentRequest( __paymentRequest );
		};

		createPaymentRequest();
	}, [ payments, needsShipping ] );

	return paymentRequest;
}

const MockButtons = ( { shippingData } ) => {
	const { needsShipping } = shippingData;
	const [ scriptLoaded, setScriptLoaded ] = useState( false );

	const editorCanvas = useEditorCanvas();

	useEffect( () => {
		if ( ! editorCanvas || scriptLoaded ) {
			return;
		}

		const iframeDoc =
			editorCanvas.contentDocument || editorCanvas.contentWindow.document;

		// Avoid injecting multiple times
		if (
			iframeDoc.querySelector(
				'script[src="https://web.squarecdn.com/v1/square.js"]'
			)
		) {
			setScriptLoaded( true );
			return;
		}

		const script = document.createElement( 'script' );
		script.src = 'https://web.squarecdn.com/v1/square.js';
		script.type = 'text/javascript';
		script.async = true;
		script.onload = () => setScriptLoaded( true );
		script.onerror = () => console.error( 'Failed to load Square.js' );

		iframeDoc.head.appendChild( script );
	}, [ editorCanvas, scriptLoaded ] );

	if ( ! scriptLoaded ) {
		return null;
	}

	return <ExpressButtons needsShipping={ needsShipping } />;
};

function useSquare() {
	const [ payment, setPayments ] = useState( null );

	useEffect( () => {
		const applicationId = getSquareServerData().applicationId;
		const locationId = getSquareServerData().locationId;
		const __iframeCanvas =
				document.getElementsByName( 'editor-canvas' );

		const SquareObj = __iframeCanvas[0]?.contentWindow?.Square;

		if ( ! SquareObj ) {
			return;
		}


		try {
			const __payments = SquareObj.payments(
				applicationId,
				locationId
			);
			setPayments( __payments );
		} catch ( e ) {
			console.error( e );
		}
	}, [] );

	return payment;
}

function ExpressButtons( { needsShipping = false } ) {
	const payments = useSquare();
	const paymentRequest = usePaymentRequest( payments, needsShipping );
	const [ googlePay, googlePayRef ] = useGooglePay(
		payments,
		paymentRequest
	);
	const [ applePay, applePayRef ] = useApplePay(
		payments,
		paymentRequest
	);

	const isGooglePayDisabled =
		getSquareServerData().hideButtonOptions.includes( 'google' );
	const isApplePayDisabled =
		getSquareServerData().hideButtonOptions.includes( 'apple' );

	const googlePayExpressButton = ! isGooglePayDisabled && (
		<div
			tabIndex={ 0 }
			role="button"
			ref={ googlePayRef }
		></div>
	);

	const applePayExpressButton = ! isApplePayDisabled && (
		<div
			tabIndex={ 0 }
			role="button"
			ref={ applePayRef }
			className="apple-pay-button wc-square-wallet-buttons"
		>
			<span className="text"></span>
			<span className="logo"></span>
		</div>
	);

	return (
		<>
			{ applePayExpressButton }
			{ googlePayExpressButton }
		</>
	);
}

export default MockButtons;
