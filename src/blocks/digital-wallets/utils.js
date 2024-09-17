import { getSquareServerData } from '../square-utils';

export const buildVerificationDetails = ( billing ) => {
	return {
		intent: 'CHARGE',
		amount: ( billing.cartTotal.value / 100 ).toString(),
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
 * Returns the AJAX URL for a given action.
 *
 * @param {string} action Corresponding action name for the AJAX endpoint.
 * @return {string} AJAX URL
 */
export const getAjaxUrl = ( action ) => {
	return getSquareServerData().ajaxUrl.replace(
		'%%endpoint%%',
		`square_digital_wallet_${ action }`
	);
};

/**
 * Returns the payment request object to create
 * Square payment request object.
 *
 * @return {Object} data to create Square payment request.
 */
export const getPaymentRequest = () => {
	return new Promise( ( resolve, reject ) => {
		const data = {
			context: getSquareServerData().context,
			security: getSquareServerData().paymentRequestNonce,
			is_pay_for_order_page: getSquareServerData().isPayForOrderPage,
		};

		jQuery.post(
			getAjaxUrl( 'get_payment_request' ),
			data,
			( response ) => {
				if ( response.success ) {
					return resolve( response.data );
				}

				return reject( response.data );
			}
		);
	} );
};

/**
 * Recalculates cart total.
 *
 * @param {Object} data Cart data.
 * @return {Object} Updated data required to refresh the Gpay|Apple Pay popup.
 */
export const recalculateTotals = async ( data ) => {
	return new Promise( ( resolve, reject ) => {
		return jQuery.post(
			getAjaxUrl( 'recalculate_totals' ),
			data,
			( response ) => {
				if ( response.success ) {
					return resolve( response.data );
				}
				return reject( response.data );
			}
		);
	} );
};

/**
 * Callback for the `shippingoptionchanged` event.
 *
 * @param {Object} shippingOption Shipping option object
 * @return {Object} Recalculated totals after the shipping option is changed.
 */
export const handleShippingOptionChanged = async ( shippingOption ) => {
	const data = {
		context: getSquareServerData().context,
		shipping_option: shippingOption.id,
		security: getSquareServerData().recalculateTotalNonce,
		is_pay_for_order_page: getSquareServerData().isPayForOrderPage,
	};

	const response = await recalculateTotals( data );
	return response;
};

/**
 * Callback for the `shippingcontactchanged` event.
 *
 * @param {Object} shippingContact Shipping option object
 * @return {Object} Recalculated totals after the shipping option is changed.
 */
export const handleShippingAddressChanged = async ( shippingContact ) => {
	const data = {
		context: getSquareServerData().context,
		shipping_contact: shippingContact,
		security: getSquareServerData().recalculateTotalNonce,
		is_pay_for_order_page: getSquareServerData().isPayForOrderPage,
	};

	const response = await recalculateTotals( data );
	return response;
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
export const verifyBuyer = async ( payments, token, verificationDetails ) => {
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
export const tokenize = async ( button ) => {
	try {
		const tokenResult = await button.tokenize();

		if ( tokenResult.status === 'OK' ) {
			return tokenResult;
		}
	} catch ( e ) {
		return false;
	}

	return false;
};
