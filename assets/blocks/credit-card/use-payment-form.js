/**
 * External dependencies
 */
import { useState, useCallback, useMemo, useRef } from '@wordpress/element';

/**
 * Internal dependencies
 */
import {
	getSquareServerData,
	handleErrors,
	log,
	logData,
} from '../square-utils';
import { PAYMENT_METHOD_NAME } from './constants';

/**
 * @typedef {import('@woocommerce/type-defs/registered-payment-method-props').BillingDataProps} BillingDataProps
 * @typedef {import('../square-utils/type-defs').PaymentsFormHandler} PaymentsFormHandler
 * @typedef {import('../square-utils/type-defs').SquareContext} SquareContext
 */

/**
 * Payment Form Handler
 *
 * @param {BillingDataProps} billing           Checkout billing data.
 * @param {boolean}          shouldSavePayment True if customer has checked box to save card. Defaults to false
 * @param {string}           token             Saved card/token ID passed from server.
 *
 * @return {PaymentsFormHandler} An object with properties that interact with the Square Payment Form
 */
export const usePaymentForm = (
	billing,
	shouldSavePayment = false,
	token = null
) => {
	const [isLoaded, setLoaded] = useState(false);
	const [cardType, setCardType] = useState('');
	const resolveCreateNonce = useRef(null);

	const verificationDetails = useMemo(() => {
		const intent = shouldSavePayment && !token ? 'STORE' : 'CHARGE';
		const newVerificationDetails = {
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
			intent,
		};

		if (intent === 'CHARGE') {
			newVerificationDetails.amount = (
				billing.cartTotal.value / 100
			).toString();
			newVerificationDetails.currencyCode = billing.currency.code;
		}
		return newVerificationDetails;
	}, [
		billing.billingData,
		billing.cartTotal.value,
		billing.currency.code,
		shouldSavePayment,
		token,
	]);

	const getPaymentMethodData = useCallback(
		(inputData) => {
			const {
				cardData = {},
				nonce,
				verificationToken,
				notices,
				logs,
			} = inputData;

			const data = {
				[`wc-${PAYMENT_METHOD_NAME}-card-type`]: cardData?.brand || '',
				[`wc-${PAYMENT_METHOD_NAME}-last-four`]: cardData?.last4 || '',
				[`wc-${PAYMENT_METHOD_NAME}-exp-month`]:
					cardData?.expMonth?.toString() || '',
				[`wc-${PAYMENT_METHOD_NAME}-exp-year`]:
					cardData?.expYear?.toString() || '',
				[`wc-${PAYMENT_METHOD_NAME}-payment-postcode`]:
					cardData?.postalCode || '',
				[`wc-${PAYMENT_METHOD_NAME}-payment-nonce`]: nonce || '',
				[`wc-${PAYMENT_METHOD_NAME}-payment-token`]: token || '',
				[`wc-${PAYMENT_METHOD_NAME}-buyer-verification-token`]:
					verificationToken || '',
				[`wc-${PAYMENT_METHOD_NAME}-tokenize-payment-method`]:
					shouldSavePayment || false,
				'log-data': logs.length > 0 ? JSON.stringify(logs) : '',
				'checkout-notices':
					notices.length > 0 ? JSON.stringify(notices) : '',
			};

			if (token) {
				data.token = token;
			}

			return data;
		},
		[cardType, shouldSavePayment, token]
	);

	/**
	 * Generates a payment nonce
	 *
	 * @param {object} card Instance of Payments.card().
	 *
	 * @return {Promise} Returns Promise<TokenResult>
	 */
	const createNonce = useCallback(
		async (card) => {
			if (!token) {
				return await card.tokenize();
			}

			return token;
		},
		[token]
	);

	/**
	 * Generates a verification buyer token
	 *
	 * @param {Object} payments     Instance of Square.payments().
	 * @param {string} paymentToken Payment Token to verify
	 *
	 * @return {Promise} Returns promise which will be resolved in handleVerifyBuyerResponse callback
	 */
	const verifyBuyer = useCallback(
		async (payments, paymentToken) => {
			let verificationResponse;
			try {
				verificationResponse = await payments.verifyBuyer(
					paymentToken,
					verificationDetails
				);

				return handleVerifyBuyerResponse(verificationResponse);
			} catch (error) {
				handleErrors([error]);
			}

			return false;
		},
		[verificationDetails, handleVerifyBuyerResponse]
	);

	/**
	 * Handles the response from Payments.verifyBuyer() and resolves promise
	 *
	 * @param {Object} verificationResult Verify buyer result from Square
	 */
	const handleVerifyBuyerResponse = useCallback((verificationResult) => {
		const response = {
			notices: [],
			logs: [],
		};

		// no errors, but also no verification token.
		if (!verificationResult || !verificationResult.token) {
			logData(
				'Verification token is missing from the Square response',
				response
			);
			log(
				'Verification token is missing from the Square response',
				'error'
			);
			handleErrors([], response);
		} else {
			response.verificationToken = verificationResult.token;
		}

		return response;
	}, []);

	/**
	 * When customers interact with the Square Payments iframe elements,
	 * determine whether the cardBrandChanged event has occurred and set card type.
	 *
	 * @param {Object} event Input event object
	 */
	const handleInputReceived = useCallback((event) => {
		// change card icon
		if (event.eventType === 'cardBrandChanged') {
			const brand = event.cardBrand;
			let newCardType = 'plain';

			if (brand === null || brand === 'unknown') {
				newCardType = '';
			}

			if (getSquareServerData().availableCardTypes[brand] !== null) {
				newCardType = getSquareServerData().availableCardTypes[brand];
			}

			log(`Card brand changed to ${brand}`);
			setCardType(newCardType);
		}
	}, []);

	/**
	 * Returns the postcode value from BillingDataProps or an empty string
	 *
	 * @return {string} Postal Code value or an empty string
	 */
	const getPostalCode = useCallback(() => {
		const postalCode = billing.billingData.postcode || '';
		return postalCode;
	}, [billing.billingData.postcode]);

	return {
		handleInputReceived,
		isLoaded,
		setLoaded,
		getPostalCode,
		cardType,
		createNonce,
		verifyBuyer,
		getPaymentMethodData,
	};
};
