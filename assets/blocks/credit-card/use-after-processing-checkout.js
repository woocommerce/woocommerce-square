/**
 * External dependencies
 */
import { useEffect } from '@wordpress/element';

/**
 * @typedef {import('../square-utils/type-defs').PaymentsFormHandler} PaymentsFormHandler
 * @typedef {import('@woocommerce/type-defs/registered-payment-method-props').EmitResponseProps} EmitResponseProps
 */

/**
 * Handle after checkout processing events.
 *
 * Once the checkout has been submitted and server-side processing has completed, check
 * if there's any client-side errors that need to be returned.
 *
 * Once checkout process is completed, reset the PaymentsFormHandler state.
 *
 * @param {Function}          onCheckoutAfterProcessingWithError   Callback for registering observers for after the checkout has been processed and has an error
 * @param {Function}          onCheckoutAfterProcessingWithSuccess Callback for registering observers for after the checkout has been processed and is successful
 * @param {EmitResponseProps} emitResponse                         Helpers for observer response objects
 */
export const useAfterProcessingCheckout = (
	onCheckoutAfterProcessingWithError,
	onCheckoutAfterProcessingWithSuccess,
	emitResponse
) => {
	useEffect(() => {
		/**
		 * Before finishing the checkout with the 'success' status, check that there
		 * are no checkout errors and return them if so.
		 *
		 * This function is attached onCheckoutAfterProcessingWithError and onCheckoutAfterProcessingWithSuccess hooks.
		 *
		 * @param {Object} checkoutResponse
		 *
		 * @return {Object} If checkout errors, return an error response object with message, otherwise return true.
		 */
		const onCheckoutComplete = (checkoutResponse) => {
			let response = { type: emitResponse.responseTypes.SUCCESS };

			const { paymentStatus, paymentDetails } =
				checkoutResponse.processingResponse;

			if (
				paymentStatus === emitResponse.responseTypes.ERROR &&
				paymentDetails.checkoutNotices
			) {
				response = {
					type: emitResponse.responseTypes.ERROR,
					message: JSON.parse(paymentDetails.checkoutNotices),
					messageContext: emitResponse.noticeContexts.PAYMENTS,
					retry: true,
				};
			}

			return response;
		};

		const unsubscribeCheckoutCompleteError =
			onCheckoutAfterProcessingWithError(onCheckoutComplete);
		const unsubscribeCheckoutCompleteSuccess =
			onCheckoutAfterProcessingWithSuccess(onCheckoutComplete);

		return () => {
			unsubscribeCheckoutCompleteError();
			unsubscribeCheckoutCompleteSuccess();
		};
	}, [
		onCheckoutAfterProcessingWithError,
		onCheckoutAfterProcessingWithSuccess,
		emitResponse.noticeContexts.PAYMENTS,
		emitResponse.responseTypes.ERROR,
		emitResponse.responseTypes.SUCCESS,
	]);
};
