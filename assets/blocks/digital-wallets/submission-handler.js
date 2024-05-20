/**
 * External dependencies
 */
import { select, dispatch } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { getSquareServerData } from '../square-utils';

/**
 * Returns the AJAX URL for a given action.
 *
 * @param {string} action Corresponding action name for the AJAX endpoint.
 * @return {string} AJAX URL
 */
const getAjaxUrl = (action) => {
	return getSquareServerData().ajaxUrl.replace(
		'%%endpoint%%',
		`square_digital_wallet_${action}`
	);
};

/**
 * Returns the payment request object to create
 * Square payment request object.
 *
 * @return {Object} data to create Square payment request.
 */
const getPaymentRequest = () => {
	return new Promise((resolve, reject) => {
		const data = {
			context: getSquareServerData().context,
			security: getSquareServerData().paymentRequestNonce,
			is_pay_for_order_page: getSquareServerData().isPayForOrderPage,
		};

		jQuery.post(getAjaxUrl('get_payment_request'), data, (response) => {
			if (response.success) {
				return resolve(response.data);
			}

			return reject(response.data);
		});
	});
};

/**
 * Returns the Square payment request.
 *
 * @param {Object} payments Square payment object.
 * @return {Object} The payment request object.
 */
export const createPaymentRequest = async (payments) => {
	const __paymentRequestJson = await getPaymentRequest();
	const __paymentRequestObject = JSON.parse(__paymentRequestJson);
	const paymentRequest = payments.paymentRequest(__paymentRequestObject);

	paymentRequest.addEventListener('shippingoptionchanged', (option) =>
		handleShippingOptionChanged(option)
	);
	paymentRequest.addEventListener(
		'shippingcontactchanged',
		(shippingContact) => handleShippingAddressChanged(shippingContact)
	);

	return paymentRequest;
};

/**
 * Returns an object required to verify the buyer.
 *
 * @param {Object} billing Billing data object.
 *
 * @return {Object} Formatted data required to verify the buyer.
 */
export const buildVerificationDetails = (billing) => {
	return {
		intent: 'CHARGE',
		amount: (billing.cartTotal.value / 100).toString(),
		currencyCode: billing.currency.code,
		billingContact: {
			familyName: billing.billingData.last_name || '',
			givenName: billing.billingData.first_name || '',
			email: billing.billingData.email || '',
			country: billing.billingData.country || '',
			region: billing.billingData.state || '',
			city: billing.billingData.city || '',
			postalCode: billing.billingData.postcode || '',
			phone: billing.billingData.phone || '',
			addressLines: [
				billing.billingData.address_1 || '',
				billing.billingData.address_2 || '',
			],
		},
	};
};

/**
 * Verifies a buyer.
 *
 * @param {Object} payments            Square payments object.
 * @param {string} token               Square payment token.
 * @param {Object} verificationDetails Buyer verification data object.
 *
 * @return {Object} Verification details
 */
export const verifyBuyer = async (payments, token, verificationDetails) => {
	const verificationResults = await payments.verifyBuyer(
		token,
		verificationDetails
	);

	return verificationResults;
};

/**
 * Tokenizes the payment method.
 *
 * @param {Object} button Instance of the Google|Apple Pay button.
 * @return {Object|boolean} Returns the token result, or false if tokenisation fails.
 */
export const tokenize = async (button) => {
	const tokenResult = await button.tokenize();

	if (tokenResult.status === 'OK') {
		return tokenResult;
	}

	return false;
};

/**
 * Callback for the `shippingoptionchanged` event.
 *
 * @param {Object} shippingOption Shipping option object
 * @return {Object} Recalculated totals after the shipping option is changed.
 */
export const handleShippingOptionChanged = async (shippingOption) => {
	const data = {
		context: getSquareServerData().context,
		shipping_option: shippingOption.id,
		security: getSquareServerData().recalculateTotalNonce,
		is_pay_for_order_page: getSquareServerData().isPayForOrderPage,
	};

	const response = await recalculateTotals(data);
	return response;
};

/**
 * Callback for the `shippingcontactchanged` event.
 *
 * @param {Object} shippingContact Shipping option object
 * @return {Object} Recalculated totals after the shipping option is changed.
 */
export const handleShippingAddressChanged = async (shippingContact) => {
	const data = {
		context: getSquareServerData().context,
		shipping_contact: shippingContact,
		security: getSquareServerData().recalculateTotalNonce,
		is_pay_for_order_page: getSquareServerData().isPayForOrderPage,
	};

	const response = await recalculateTotals(data);
	return response;
};

/**
 * Recalculates cart total.
 *
 * @param {Object} data Cart data.
 * @return {Object} Updated data required to refresh the Gpay|Apple Pay popup.
 */
const recalculateTotals = async (data) => {
	return new Promise((resolve, reject) => {
		return jQuery.post(
			getAjaxUrl('recalculate_totals'),
			data,
			(response) => {
				if (response.success) {
					return resolve(response.data);
				}
				return reject(response.data);
			}
		);
	});
};

/**
 * Initiates checkout.
 *
 * @param {Object} payments            Square payments object.
 * @param {Object} verificationDetails Verification details object.
 * @param {Object} button              Instance of the GPay|Apple Pay button that is clicked.
 * @return {Object} Response object.
 */
export const initiateCheckout = async (
	payments,
	verificationDetails,
	button
) => {
	const tokenResult = await tokenize(button);
	let response = { type: 'success' };

	if (!tokenResult) {
		response = {
			type: 'failure',
			retry: true,
		};
		return response;
	}

	const {
		details: { card, method },
		token,
	} = tokenResult;

	const billingContact = tokenResult?.details?.billing || {};
	const { contact: shippingContact = {}, option: shippingOption = {} } =
		tokenResult?.details?.shipping || {};

	const verificationResult = await verifyBuyer(
		payments,
		token,
		verificationDetails
	);
	const keyPrefix = 'wc-square-credit-card';

	const { token: verificationToken } = verificationResult;

	response.meta = {
		paymentMethodData: {
			[`${keyPrefix}-card-type`]: method || '',
			[`${keyPrefix}-last-four`]: card?.last4 || '',
			[`${keyPrefix}-exp-month`]: card?.expMonth?.toString() || '',
			[`${keyPrefix}-exp-year`]: card?.expYear?.toString() || '',
			[`${keyPrefix}-payment-postcode`]: card?.postalCode || '',
			[`${keyPrefix}-payment-nonce`]: token || '',
			[`${keyPrefix}-buyer-verification-token`]: verificationToken || '',
			shipping_method: shippingOption.id ?? false,
		},
		billingAddress: {
			email: billingContact.email ?? '',
			first_name: billingContact.givenName ?? '',
			last_name: billingContact.familyName ?? '',
			company: '',
			address_1: billingContact.addressLines
				? billingContact.addressLines[0]
				: '',
			address_2: billingContact.addressLines
				? billingContact.addressLines[1]
				: '',
			city: billingContact.city ?? '',
			state: billingContact.state ?? '',
			postcode: billingContact.postalCode ?? '',
			country: billingContact.countryCode ?? '',
			phone: billingContact.phone ?? '',
		},
		shippingAddress: {
			first_name: shippingContact.givenName ?? '',
			last_name: shippingContact.familyName ?? '',
			company: '',
			address_1: shippingContact.addressLines
				? shippingContact.addressLines[0]
				: '',
			address_2: shippingContact.addressLines
				? shippingContact.addressLines[1]
				: '',
			city: shippingContact.city ?? '',
			state: shippingContact.state ?? '',
			postcode: shippingContact.postalCode ?? '',
			country: shippingContact.countryCode ?? '',
			phone: billingContact.phone,
		},
	};

	dispatch('wc/store/cart').setBillingAddress(response.meta.billingAddress);

	const needsShipping = select('wc/store/cart').getNeedsShipping();

	if (needsShipping) {
		dispatch('wc/store/cart').setShippingAddress(
			response.meta.shippingAddress
		);

		const shippingRates = wp.data
			.select('wc/store/cart')
			.getShippingRates();

		if (
			!shippingRates.some(
				(shippingRatePackage) =>
					shippingRatePackage.shipping_rates.length
			)
		) {
			response.type = 'failure';
			response.retry = true;

			return response;
		}
	}

	return response;
};
