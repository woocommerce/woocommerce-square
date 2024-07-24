import { useState, useEffect, useRef, useCallback } from '@wordpress/element';

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
 * Callback for the `shippingoptionchanged` event.
 *
 * @param {Object} shippingOption Shipping option object
 * @return {Object} Recalculated totals after the shipping option is changed.
 */
const handleShippingOptionChanged = async (shippingOption) => {
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
const handleShippingAddressChanged = async (shippingContact) => {
	const data = {
		context: getSquareServerData().context,
		shipping_contact: shippingContact,
		security: getSquareServerData().recalculateTotalNonce,
		is_pay_for_order_page: getSquareServerData().isPayForOrderPage,
	};

	const response = await recalculateTotals(data);
	return response;
};

const buildVerificationDetails = (billing) => {
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

export function useSquare() {
	const [ payment, setPayments ] = useState( null );

	useEffect(() => {
		const applicationId = getSquareServerData().applicationId;
		const locationId = getSquareServerData().locationId;

		if ( ! window.Square ) {
			return;
		}

		try {
			const __payments = window.Square.payments(
				applicationId,
				locationId
			);
			setPayments(__payments);
		} catch (e) {
			console.error(e);
		}
	}, []);

	return payment;
}

export function usePaymentRequest( payments, needsShipping, billing ) {
	const [ paymentRequest, setPaymentRequest ] = useState( null );

	useEffect( () => {
		if ( ! payments ) {
			return;
		}

		const createPaymentRequest = async ( payments ) => {
			const __paymentRequestJson = await getPaymentRequest();
			const __paymentRequestObject = JSON.parse(__paymentRequestJson);
			const __paymentRequest = payments.paymentRequest(__paymentRequestObject);

			setPaymentRequest( __paymentRequest );
		}

		createPaymentRequest( payments );
	}, [ payments, needsShipping ] );

	return paymentRequest;
}

export function useShippingContactChangeHandler( paymentRequest ) {
	paymentRequest?.addEventListener(
		'shippingcontactchanged',
		(shippingContact) => handleShippingAddressChanged(shippingContact)
	);

	return () => paymentRequest?.removeListener( 'shippingcontactchanged' );
}

export function useShippingOptionChangeHandler( paymentRequest ) {
	paymentRequest?.addEventListener(
		'shippingoptionchanged',
		(option) => handleShippingOptionChanged(option)
	);

	return () => paymentRequest?.removeListener( 'shippingoptionchanged' );
}

export function useGooglePay( payments, paymentRequest ) {
	const [ googlePay, setGooglePay ] = useState( null );
	const googlePayRef = useRef( null );

	useEffect( () => {
		if ( ! ( payments && paymentRequest ) ) {
			return;
		}

		( async () => {
			await payments.googlePay( paymentRequest );
			const __googlePay = await payments.googlePay( paymentRequest );
			await __googlePay.attach( googlePayRef.current, {
				buttonColor: getSquareServerData().googlePayColor,
				buttonSizeMode: 'fill',
				buttonType: 'long',
			});

			setGooglePay( __googlePay );
		} )()
	}, [ payments, paymentRequest ] );

	return [ googlePay, googlePayRef ];
}

export function useOnClickHandler( setExpressPaymentError, onClick, onSubmit ) {
	return useCallback(
		() => {
			// Reset any Payment Request errors.
			setExpressPaymentError( '' );

			// Call the Blocks API `onClick` handler.
			onClick();

			onSubmit();
		},
		[ onClick ]
	);
}

export function usePaymentProcessing( billing, button, onPaymentSetup, onClose ) {
	const verificationDetails = buildVerificationDetails( billing );

	useEffect( () => onPaymentSetup( () => {
		if ( ! button ) {
			return;
		}

		async function handlePaymentProcessing() {
			const tokenResult = await tokenize(button);
			let response = { type: 'success' };

			console.log('returning false');

			return {
				type: 'failure'
			}
		
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
		
			/*
			 * For key contact details fall back to anything that is provided.
			 *
			 * This accounts for slight differences in how Apple Pay and Google Pay provide
			 * contact details. Apple Pay provides contact details in the shipping object,
			 * even for products that don't require shipping. Google Pay provides contact
			 * details in the billing object.
			 */
			const billingEmailAddress =
				billingContact?.email ?? shippingContact?.email ?? '';
			const billingPhoneNumber =
				billingContact?.phone ?? shippingContact?.phone ?? '';
			const shippingPhoneNumber =
				shippingContact?.phone ?? billingContact?.phone ?? '';
		
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
					email: billingEmailAddress,
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
					phone: billingPhoneNumber,
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
					phone: shippingPhoneNumber,
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
		}

		return handlePaymentProcessing();
	} ), [
		onPaymentSetup,
		billing.billingData,
		button,
	] );
}
