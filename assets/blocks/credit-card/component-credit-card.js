/**
 * Internal dependencies
 */
import { CheckoutHandler } from './checkout-handler';
import { usePaymentForm } from './use-payment-form';
import { SquareWebPaymentsForm } from './square-web-payments-form';
import { ComponentCardFields } from './component-card-fields';
import { getSquareServerData } from '../square-utils';

/**
 * @typedef {import('@woocommerce/type-defs/registered-payment-method-props').RegisteredPaymentMethodProps} RegisteredPaymentMethodProps
 */

/**
 * Square's credit card component
 *
 * @param {RegisteredPaymentMethodProps} props Incoming props
 */
export const ComponentCreditCard = ({
	billing,
	eventRegistration,
	emitResponse,
	shouldSavePayment,
}) => {
	const { isTokenizationForced } = getSquareServerData();
	const shouldMaybeSavePayment = shouldSavePayment || isTokenizationForced;

	const form = usePaymentForm(billing, shouldMaybeSavePayment);

	return (
		<SquareWebPaymentsForm defaults={{ postalCode: form.getPostalCode() }}>
			<ComponentCardFields />
			<CheckoutHandler
				checkoutFormHandler={form}
				eventRegistration={eventRegistration}
				emitResponse={emitResponse}
			/>
		</SquareWebPaymentsForm>
	);
};
