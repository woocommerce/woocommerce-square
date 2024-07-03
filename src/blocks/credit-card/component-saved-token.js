/**
 * Internal dependencies
 */
import { SquareWebPaymentsForm } from './square-web-payments-form';
import { CheckoutHandler } from './checkout-handler';
import { usePaymentForm } from './use-payment-form';

/**
 * @typedef {import('@woocommerce/type-defs/registered-payment-method-props').RegisteredPaymentMethodProps} RegisteredPaymentMethodProps
 */

/**
 * Square's saved credit card component
 *
 * @param {RegisteredPaymentMethodProps} props Incoming props
 */
export const ComponentSavedToken = ( {
	billing,
	eventRegistration,
	emitResponse,
	token,
} ) => {
	const form = usePaymentForm( billing, false, token );

	return (
		<SquareWebPaymentsForm
			token={ token }
			defaults={ { postalCode: form.getPostalCode() } }
		>
			<CheckoutHandler
				checkoutFormHandler={ form }
				eventRegistration={ eventRegistration }
				emitResponse={ emitResponse }
			/>
		</SquareWebPaymentsForm>
	);
};
