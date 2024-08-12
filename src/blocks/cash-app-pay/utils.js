/**
 * External dependencies
 */
import { getSetting } from '@woocommerce/settings';
import { dispatch } from '@wordpress/data';

/**
 * Internal dependencies
 */
const { PAYMENT_STORE_KEY } = window.wc.wcBlocksData;
import { PAYMENT_METHOD_ID } from './constants';
let cachedSquareCashAppData = null;

/**
 * Square settings that comes from the server
 *
 * @return {Object} Square server data.
 */
export const getSquareCashAppPayServerData = () => {
	if ( cachedSquareCashAppData !== null ) {
		return cachedSquareCashAppData;
	}

	const squareData = getSetting( 'square_cash_app_pay_data', null );

	if ( ! squareData ) {
		throw new Error(
			'Square Cash App Pay initialization data is not available'
		);
	}

	cachedSquareCashAppData = {
		title: squareData.title || '',
		description: squareData.description || '',
		applicationId: squareData.application_id || '',
		locationId: squareData.location_id || '',
		isSandbox: squareData.is_sandbox || false,
		loggingEnabled: squareData.logging_enabled || false,
		generalError: squareData.general_error || '',
		showSavedCards: squareData.show_saved_cards || false,
		showSaveOption: squareData.show_save_option || false,
		supports: squareData.supports || {},
		isPayForOrderPage: squareData.is_pay_for_order_page || false,
		orderId: squareData.order_id || '',
		ajaxUrl: squareData.ajax_url || '',
		paymentRequestNonce: squareData.payment_request_nonce || '',
		continuationSessionNonce: squareData.continuation_session_nonce || '',
		gatewayIdDasherized: squareData.gateway_id_dasherized || '',
		buttonStyles: squareData.button_styles || {},
		isContinuation: squareData.is_continuation || false,
		refereneceId: squareData.reference_id || '',
	};

	return cachedSquareCashAppData;
};

/**
 * Returns the AJAX URL for a given action.
 *
 * @param {string} action Corresponding action name for the AJAX endpoint.
 * @return {string} AJAX URL
 */
const getAjaxUrl = ( action ) => {
	return getSquareCashAppPayServerData().ajaxUrl.replace(
		'%%endpoint%%',
		`square_cash_app_pay_${ action }`
	);
};

/**
 * Returns the payment request object to create
 * Square payment request object.
 *
 * @return {Object} data to create Square payment request.
 */
const getPaymentRequest = () => {
	return new Promise( ( resolve, reject ) => {
		const data = {
			security: getSquareCashAppPayServerData().paymentRequestNonce,
			is_pay_for_order_page:
				getSquareCashAppPayServerData().isPayForOrderPage || false,
			order_id: getSquareCashAppPayServerData().orderId || 0,
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
 * Returns the Square payment request.
 *
 * @param {Object} payments Square payment object.
 * @return {Object} The payment request object.
 */
export const createPaymentRequest = async ( payments ) => {
	const __paymentRequestJson = await getPaymentRequest();
	const __paymentRequestObject = JSON.parse( __paymentRequestJson );
	const paymentRequest = payments.paymentRequest( __paymentRequestObject );

	return paymentRequest;
};

/**
 * Set continuation session to select the cash app payment method after the redirect back from the cash app.
 *
 * @param {boolean} clear Clear the continuation session.
 * @return {Promise<Object>} Response from the server.
 */
export const setContinuationSession = ( clear = false ) => {
	return new Promise( ( resolve, reject ) => {
		const data = {
			security: getSquareCashAppPayServerData().continuationSessionNonce,
			clear,
		};

		jQuery.post(
			getAjaxUrl( 'set_continuation_session' ),
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
 * Clear continuation session.
 */
export const clearContinuationSession = () => {
	return setContinuationSession( true );
};

/**
 * Log to the console if logging is enabled
 *
 * @param {*}      data Data to log to console
 * @param {string} type Type of log, 'error' will log as an error
 */
export const log = ( data, type = 'notice' ) => {
	if ( ! getSquareCashAppPayServerData().loggingEnabled ) {
		return;
	}

	if ( type === 'error' ) {
		console.error( data );
	} else {
		console.log( data );
	}
};

/**
 * Select the Cash App Pay payment method if the continuation session is set.
 */
export const selectCashAppPaymentMethod = () => {
	const payMethodInput =
		document &&
		document.getElementById(
			'radio-control-wc-payment-method-options-square_cash_app_pay'
		);
	if (
		getSquareCashAppPayServerData().isContinuation &&
		! window.wcSquareCashAppPaySelected &&
		payMethodInput
	) {
		log( '[Square Cash App Pay] Selecting Cash App Pay payment method' );
		dispatch( PAYMENT_STORE_KEY ).__internalSetActivePaymentMethod(
			PAYMENT_METHOD_ID
		);
		window.wcSquareCashAppPaySelected = true;
		clearContinuationSession();
	}
};
