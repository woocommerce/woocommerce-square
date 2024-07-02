/**
 * Internal dependencies
 */
import { ComponentCreditCard } from './component-credit-card';
import { ComponentSavedToken } from './component-saved-token';
import { PAYMENT_METHOD_ID } from './constants';
import { getSquareServerData } from '../square-utils';

/**
 * @typedef {import('@woocommerce/type-defs/registered-payment-method-props').RegisteredPaymentMethodProps} RegisteredPaymentMethodProps
 */

/**
 * Label component
 *
 * @param {RegisteredPaymentMethodProps} props
 */
const SquareLabel = (props) => {
	const { PaymentMethodLabel } = props.components;
	return <PaymentMethodLabel text={getSquareServerData().title} />;
};

/**
 * Payment method content component
 *
 * @param {Object}                                  props                   Incoming props for component (including props from Payments API)
 * @param {ComponentCreditCard|ComponentSavedToken} props.RenderedComponent Component to render
 */
const SquareComponent = ({ RenderedComponent, ...props }) => {
	return <RenderedComponent {...props} />;
};

const squareCreditCardMethod = {
	name: PAYMENT_METHOD_ID,
	label: <SquareLabel />,
	content: <SquareComponent RenderedComponent={ComponentCreditCard} />,
	edit: <SquareComponent RenderedComponent={ComponentCreditCard} />,
	savedTokenComponent: (
		<SquareComponent RenderedComponent={ComponentSavedToken} />
	),
	paymentMethodId: PAYMENT_METHOD_ID,
	ariaLabel: 'Square',
	canMakePayment: () =>
		getSquareServerData().applicationId && getSquareServerData().locationId
			? true
			: false,
	supports: {
		features: getSquareServerData().supports,
		showSavedCards: getSquareServerData().showSavedCards,
		showSaveOption: getSquareServerData().showSaveOption,
	},
};

export default squareCreditCardMethod;
