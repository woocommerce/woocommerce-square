/**
 * External dependencies
 */
import { useEffect, useRef } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { getSquareServerData, handleErrors } from '../square-utils';

/**
 * @typedef {import('@woocommerce/type-defs/registered-payment-method-props').EmitResponseProps} EmitResponseProps
 * @typedef {import('../square-utils/type-defs').SquareContext} SquareContext
 */

/**
 * Sets up payment details and POST data to be processed on server-side on checkout submission.
 *
 * If tokenization produced a nonce or a verified token, this function sends a SUCCESS response to
 * the server with that data inside paymentMethodData.
 *
 * If tokenization did not produce a payment token, the checkout is stopped client-side with an
 * ERROR response carrying the messages Square returned. Submitting without a payment token would
 * make the Store API create the order and reserve stock before failing gateway validation, which
 * strands a pending order the customer never paid for.
 *
 * @param {Function}          onPaymentSetup       Callback for registering observers on the payment processing event
 * @param {EmitResponseProps} emitResponse         Helpers for observer response objects
 * @param {SquareContext}     squareContext        Square payment form context variable
 * @param {Function}          getPaymentMethodData CreateNonce function
 * @param {Function}          createNonce          CreateNonce function
 * @param {Function}          tokenizeSavedCard    TokenizeSavedCard function
 */
export const usePaymentProcessing = (
	onPaymentSetup,
	emitResponse,
	squareContext,
	getPaymentMethodData,
	createNonce,
	tokenizeSavedCard
) => {
	const square = useRef( squareContext );

	useEffect( () => {
		square.current = squareContext;
	}, [ squareContext ] );

	useEffect( () => {
		const processCheckout = async () => {
			const response = { type: emitResponse.responseTypes.SUCCESS };
			const paymentData = {
				nonce: '',
				notices: [],
				logs: [],
			};

			if ( square.current?.token ) {
				const { paymentTokenNonce } = getSquareServerData();
				const __response = await fetch(
					`${ wc.wcSettings.ADMIN_URL }admin-ajax.php?action=wc_square_credit_card_get_token_by_id&token_id=${ square.current.token }&nonce=${ paymentTokenNonce }`
				);
				const { success, data: token } = await __response.json();
				const savedCardToken = success ? token : '';

				if ( savedCardToken ) {
					const tokenizeSavedCardResponse = await tokenizeSavedCard(
						square.current.payments,
						savedCardToken
					);

					if (
						tokenizeSavedCardResponse?.status === 'OK' &&
						tokenizeSavedCardResponse?.token
					) {
						paymentData.verifiedToken =
							tokenizeSavedCardResponse.token;
					} else {
						handleErrors(
							tokenizeSavedCardResponse?.errors,
							paymentData
						);
					}
				} else {
					// The saved card could not be looked up, so there is nothing to tokenize.
					handleErrors( null, paymentData );
				}
			} else {
				let createNonceResponse;

				try {
					createNonceResponse = await createNonce(
						square.current.card
					);
				} catch ( error ) {
					handleErrors( [ error ], paymentData );
				}

				if (
					createNonceResponse?.status === 'OK' &&
					createNonceResponse?.token
				) {
					paymentData.nonce = createNonceResponse.token;

					if (
						createNonceResponse?.details?.card &&
						createNonceResponse?.details?.billing
					) {
						paymentData.cardData = {
							...createNonceResponse.details.card,
							...createNonceResponse.details.billing,
						};
					}
				} else if ( createNonceResponse ) {
					// Tokenization resolved with a non-OK status (Invalid, Error, Abort).
					handleErrors( createNonceResponse.errors, paymentData );
				}
			}

			const paymentToken = paymentData.verifiedToken || paymentData.nonce;

			// Fail closed: without a payment token the request can never pay, so stop here
			// rather than letting the server create an order we would only have to fail.
			if ( ! paymentToken ) {
				response.type = emitResponse.responseTypes.ERROR;
				// The checkout only renders `message` when it is a non-empty string,
				// so collapse the collected notices rather than passing the array.
				response.message = paymentData.notices.join( ' ' );
				response.messageContext = emitResponse.noticeContexts.PAYMENTS;
				response.retry = true;

				return response;
			}

			response.meta = {
				paymentMethodData: getPaymentMethodData( paymentData ),
			};

			return response;
		};

		const unsubscribe = onPaymentSetup( processCheckout );
		return unsubscribe;
	}, [
		onPaymentSetup,
		emitResponse.responseTypes.SUCCESS,
		emitResponse.responseTypes.ERROR,
		emitResponse.noticeContexts.PAYMENTS,
		createNonce,
		tokenizeSavedCard,
		getPaymentMethodData,
	] );
};
