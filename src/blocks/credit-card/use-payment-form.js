/**
 * External dependencies
 */
import { useState, useCallback } from '@wordpress/element';

/**
 * Internal dependencies
 */
import {
	getSquareServerData,
	log,
	handleErrors,
	convertAmount,
	shouldChargeOrder,
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
	const [ isLoaded, setLoaded ] = useState( false );
	const [ cardType, setCardType ] = useState( '' );

	const getVerificationDetails = useCallback( async () => {
		let intent = 'CHARGE';
		if ( shouldSavePayment && ! token ) {
			intent = 'STORE';
			const shouldCharge = await shouldChargeOrder();
			if ( shouldCharge ) {
				intent = 'CHARGE_AND_STORE';
			}
		}

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
			customerInitiated: true,
			sellerKeyedIn: false,
		};

		if ( intent === 'CHARGE' || intent === 'CHARGE_AND_STORE' ) {
			newVerificationDetails.amount = convertAmount(
				billing.cartTotal.value,
				billing.currency.code
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
	] );

	const getPaymentMethodData = useCallback(
		( inputData ) => {
			const {
				cardData = {},
				nonce,
				verificationToken,
				tokenizedToken,
				notices,
				logs,
			} = inputData;

			const data = {
				[ `wc-${ PAYMENT_METHOD_NAME }-card-type` ]:
					cardData?.brand || '',
				[ `wc-${ PAYMENT_METHOD_NAME }-last-four` ]:
					cardData?.last4 || '',
				[ `wc-${ PAYMENT_METHOD_NAME }-exp-month` ]:
					cardData?.expMonth?.toString() || '',
				[ `wc-${ PAYMENT_METHOD_NAME }-exp-year` ]:
					cardData?.expYear?.toString() || '',
				[ `wc-${ PAYMENT_METHOD_NAME }-payment-postcode` ]:
					cardData?.postalCode || '',
				[ `wc-${ PAYMENT_METHOD_NAME }-payment-nonce` ]: nonce || '',
				[ `wc-${ PAYMENT_METHOD_NAME }-payment-token` ]: token || '',
				[ `wc-${ PAYMENT_METHOD_NAME }-buyer-verification-token` ]:
					verificationToken || '',
				[ `wc-${ PAYMENT_METHOD_NAME }-tokenized-token` ]:
					tokenizedToken || '',
				[ `wc-${ PAYMENT_METHOD_NAME }-tokenize-payment-method` ]:
					shouldSavePayment || false,
				'log-data': logs.length > 0 ? JSON.stringify( logs ) : '',
				'checkout-notices':
					notices.length > 0 ? JSON.stringify( notices ) : '',
			};

			if ( token ) {
				data.token = token;
			}

			return data;
		},
		[ cardType, shouldSavePayment, token ] // eslint-disable-line react-hooks/exhaustive-deps
	);

	/**
	 * Generates a payment nonce
	 *
	 * @param {Object} card Instance of Payments.card().
	 *
	 * @return {Promise} Returns Promise<TokenResult>
	 */
	const createNonce = useCallback(
		async ( card ) => {
			if ( ! token ) {
				const verificationDetails = await getVerificationDetails();
				return await card.tokenize( verificationDetails );
			}

			return token;
		},
		[ token, getVerificationDetails ]
	);

	/**
	 * Tokenizes a saved card
	 *
	 * @param {Object} payments   Instance of Payments.
	 * @param {string} savedToken Saved card token.
	 *
	 * @return {Promise} Returns Promise<TokenResult>
	 */
	const tokenizeSavedCard = useCallback(
		async ( payments, savedToken ) => {
			try {
				const card = await payments.card();
				const verificationDetails = await getVerificationDetails();
				const tokenResult = await card.tokenize(
					verificationDetails,
					savedToken
				);

				return tokenResult;
			} catch ( error ) {
				handleErrors( [ error ] );
			}
		},
		[ token, getVerificationDetails ]
	);

	/**
	 * When customers interact with the Square Payments iframe elements,
	 * determine whether the cardBrandChanged event has occurred and set card type.
	 *
	 * @param {Object} event Input event object
	 */
	const handleInputReceived = useCallback( ( event ) => {
		// change card icon
		if ( event.eventType === 'cardBrandChanged' ) {
			const brand = event.cardBrand;
			let newCardType = 'plain';

			if ( brand === null || brand === 'unknown' ) {
				newCardType = '';
			}

			if ( getSquareServerData().availableCardTypes[ brand ] !== null ) {
				newCardType = getSquareServerData().availableCardTypes[ brand ];
			}

			log( `Card brand changed to ${ brand }` );
			setCardType( newCardType );
		}
	}, [] );

	/**
	 * Returns the postcode value from BillingDataProps or an empty string
	 *
	 * @return {string} Postal Code value or an empty string
	 */
	const getPostalCode = useCallback( () => {
		const postalCode = billing.billingData.postcode || '';
		return postalCode;
	}, [ billing.billingData.postcode ] );

	return {
		handleInputReceived,
		isLoaded,
		setLoaded,
		getPostalCode,
		cardType,
		createNonce,
		tokenizeSavedCard,
		getPaymentMethodData,
	};
};
