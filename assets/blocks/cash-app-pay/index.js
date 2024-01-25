/**
 * External dependencies
 */
import { decodeEntities } from '@wordpress/html-entities';
import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { ComponentCashAppPay } from './component-cash-app-pay';
import { PAYMENT_METHOD_ID } from './constants';
import { getSquareCashAppPayServerData, selectCashAppPaymentMethod } from './utils';
const { title, applicationId, locationId } = getSquareCashAppPayServerData();

/**
 * Label component
 *
 * @param {Object} props
 */
const SquareCashAppPayLabel = (props) => {
	const { PaymentMethodLabel } = props.components;
	return <PaymentMethodLabel text={decodeEntities(title)} />;
};

/**
 * Payment method content component
 *
 * @param {Object}              props                   Incoming props for component (including props from Payments API)
 * @param {ComponentCashAppPay} props.RenderedComponent Component to render
 */
const SquareComponent = ({ RenderedComponent, isEdit, ...props }) => {
	// Don't render anything if we're in the block editor.
	if (isEdit) {
		return null;
	}
	return <RenderedComponent {...props} />;
};

/**
 * Square Cash App Pay payment method.
 */
const squareCashAppPayMethod = {
	name: PAYMENT_METHOD_ID,
	label: <SquareCashAppPayLabel />,
	paymentMethodId: PAYMENT_METHOD_ID,
	ariaLabel:  __(
		'Cash App Pay payment method',
		'woocommerce-square'
	),
	content: <SquareComponent RenderedComponent={ComponentCashAppPay} />,
	edit: <SquareComponent RenderedComponent={ComponentCashAppPay} isEdit={true} />,
	canMakePayment: ({ billingData, cartTotals }) => {
		const isSquareConnected = applicationId && locationId;
		const isCountrySupported = billingData.country === 'US';
		const isCurrencySupported = cartTotals.currency_code === 'USD';
		const isEnabled = isSquareConnected && isCountrySupported && isCurrencySupported;

		/**
		 * Set the Cash App Pay payment method as active when the checkout form is rendered.
		 * 
		 * TODO: Find a better way to do this.
		 * Didn't find a suitable action to activate the cash app pay payment method when customer returns from the cash app.
		 * 
		 * Initially tried to use the experimental__woocommerce_blocks-checkout-render-checkout-form action but it doesn't work when stripe payment gateway is enabled.
		 */
		if ( isEnabled ) {
			selectCashAppPaymentMethod();
		}

		return isEnabled;
	},
	supports: {
		features: getSquareCashAppPayServerData().supports || [],
		showSavedCards: getSquareCashAppPayServerData().showSavedCards || false,
		showSaveOption: getSquareCashAppPayServerData().showSaveOption || false,
	}
};

// Register Square Cash App.
registerPaymentMethod( squareCashAppPayMethod );