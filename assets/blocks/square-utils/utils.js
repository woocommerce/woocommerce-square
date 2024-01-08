/**
 * External dependencies
 */
import { getSetting } from '@woocommerce/settings';

/**
 * @typedef {import('./type-defs').SquareServerData} SquareServerData
 */

let cachedSquareData = null;

/**
 * Square settings that comes from the server
 *
 * @return {SquareServerData} Square server data.
 */
const getSquareServerData = () => {
	if (cachedSquareData !== null) {
		return cachedSquareData;
	}

	const squareData = getSetting('square_credit_card_data', null);

	if (!squareData) {
		throw new Error('Square initialization data is not available');
	}

	cachedSquareData = {
		title: squareData.title || '',
		applicationId: squareData.application_id || '',
		locationId: squareData.location_id || '',
		isSandbox: squareData.is_sandbox || false,
		availableCardTypes: squareData.available_card_types || {},
		loggingEnabled: squareData.logging_enabled || false,
		generalError: squareData.general_error || '',
		showSavedCards: squareData.show_saved_cards || false,
		showSaveOption: squareData.show_save_option || false,
		supports: squareData.supports || {},
		isTokenizationForced: squareData.is_tokenization_forced || false,
		paymentTokenNonce: squareData.payment_token_nonce || '',
		isDigitalWalletsEnabled: squareData.is_digital_wallets_enabled || false,
		isPayForOrderPage: squareData.is_pay_for_order_page || false,
		recalculateTotalNonce: squareData.recalculate_totals_nonce || false,
		context: squareData.context || '',
		ajaxUrl: squareData.ajax_url || '',
		paymentRequestNonce: squareData.payment_request_nonce || '',
		googlePayColor: squareData.google_pay_color || 'black',
		applePayColor: squareData.apple_pay_color || 'black',
		hideButtonOptions: squareData.hide_button_options || [],
	};

	return cachedSquareData;
};

/**
 * Handles errors received from Square requests
 *
 * @param {Array} errors
 * @param {Object} response
 */
const handleErrors = (errors, response = { logs: [], notices: [] }) => {
	let errorFound = false;

	if (errors) {
		const fieldOrder = [
			'none',
			'cardNumber',
			'expirationDate',
			'cvv',
			'postalCode',
		];

		if (errors.length >= 1) {
			// sort based on the field order without the brackets around a.field and b.field.
			// the precedence is different and gives different results.
			errors.sort((a, b) => {
				return (
					fieldOrder.indexOf(a.field) - fieldOrder.indexOf(b.field)
				);
			});
		}

		for (const error of errors) {
			if (
				error.type === 'UNSUPPORTED_CARD_BRAND' ||
				error.type === 'VALIDATION_ERROR'
			) {
				// To avoid confusion between CSC used in the frontend and CVV that is used in the error message.
				response.notices.push(error.message.replace(/CVV/, 'CSC'));
				errorFound = true;
			} else {
				logData(error, response);
			}
		}
	}

	// if no specific messages are set, display a general error.
	if (!errorFound) {
		response.notices.push(getSquareServerData().generalError);
	}
};

/**
 * Log to the console if logging is enabled
 *
 * @param {*}      data Data to log to console
 * @param {string} type Type of log, 'error' will log as an error
 */
const log = (data, type = 'notice') => {
	if (!getSquareServerData().loggingEnabled) {
		return;
	}

	if (type === 'error') {
		console.error(data);
	} else {
		console.log(data);
	}
};

/**
 * Logs data to Square Credit Card logs found in WooCommerce > Status > Logs if logging is enabled
 *
 * @param {*}      data     Data to log
 * @param {Object} response Checkout response object to attach log data to
 */
const logData = (data, response) => {
	if (!getSquareServerData().loggingEnabled || !response) {
		return;
	}

	response.logs.push(data);
};

export { getSquareServerData, handleErrors, log, logData };
