/**
 * External dependencies
 */
import { useContext } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { usePaymentProcessing } from './use-payment-processing';
import { useAfterProcessingCheckout } from './use-after-processing-checkout';
import { SquareWebContext } from './square-web-context';

/**
 * @typedef {import('../square-utils/type-defs').PaymentsFormHandler} PaymentsFormHandler
 * @typedef {import('@woocommerce/type-defs/registered-payment-method-props').EventRegistrationProps} EventRegistrationProps
 * @typedef {import('@woocommerce/type-defs/registered-payment-method-props').BillingDataProps} BillingDataProps
 * @typedef {import('@woocommerce/type-defs/registered-payment-method-props').EmitResponseProps} EmitResponseProps
 */

/**
 * Handles checkout processing
 *
 * @param {Object}                 props                     Incoming props
 * @param {PaymentsFormHandler}    props.checkoutFormHandler Checkout form handler
 * @param {EventRegistrationProps} props.eventRegistration   Event registration functions.
 * @param {EmitResponseProps}      props.emitResponse        Helpers for observer response objects.
 */
export const CheckoutHandler = ( {
	checkoutFormHandler,
	eventRegistration,
	emitResponse,
} ) => {
	const square = useContext( SquareWebContext );

	const {
		onPaymentSetup,
		onCheckoutAfterProcessingWithError,
		onCheckoutAfterProcessingWithSuccess,
	} = eventRegistration;

	const { getPaymentMethodData, createNonce, verifyBuyer } =
		checkoutFormHandler;

	usePaymentProcessing(
		onPaymentSetup,
		emitResponse,
		square,
		getPaymentMethodData,
		createNonce,
		verifyBuyer
	);

	useAfterProcessingCheckout(
		onCheckoutAfterProcessingWithError,
		onCheckoutAfterProcessingWithSuccess,
		emitResponse
	);

	return null;
};
