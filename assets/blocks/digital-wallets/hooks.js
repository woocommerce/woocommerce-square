import { useState, useEffect, useRef, useCallback } from '@wordpress/element';

import { getSquareServerData } from '../square-utils';
import {
	getPaymentRequest,
	handleShippingOptionChanged,
	handleShippingAddressChanged,
	verifyBuyer,
	tokenize,
} from './utils';

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
	useEffect( () => {
		paymentRequest?.addEventListener(
			'shippingcontactchanged',
			(shippingContact) => handleShippingAddressChanged(shippingContact)
		);
	}, [ paymentRequest ] );

	return () => paymentRequest?.removeListener( 'shippingcontactchanged' );
}

export function useShippingOptionChangeHandler( paymentRequest ) {
	useEffect( () => {
		paymentRequest?.addEventListener(
			'shippingoptionchanged',
			(option) => handleShippingOptionChanged(option)
		);
	}, [ paymentRequest ] );

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

export function useApplePay( payments, paymentRequest ) {
	const [ applePay, setApplePay ] = useState( null );
	const applePayRef = useRef( null );

	useEffect( () => {
		if ( ! ( payments && paymentRequest ) ) {
			return;
		}

		( async () => {
			await payments.applePay( paymentRequest );
			const __applePay = await payments.applePay( paymentRequest );

			setApplePay( __applePay );
		} )()
	}, [ payments, paymentRequest ] );

	useEffect( () => {
		if ( ! applePayRef?.current ) {
			return;
		}

		const color = getSquareServerData().applePayColor;
		const type = getSquareServerData().applePayType;

		if (type !== 'plain') {
			applePayRef.current.querySelector(
				'.text'
			).innerText =
				`${type.charAt(0).toUpperCase()}${type.slice(1)} with`;
			applePayRef.current.classList.add(
				'wc-square-wallet-button-with-text'
			);
		}

		applePayRef.current.style.cssText += `-apple-pay-button-type: ${type};`;
		applePayRef.current.style.cssText += `-apple-pay-button-style: ${color};`;
		applePayRef.current.style.display = 'block';
		applePayRef.current.classList.add(
			`wc-square-wallet-button-${color}`
		);
	}, [ applePayRef ] );

	return [ applePay, applePayRef ];
}

export function usePaymentProcessing( payments, billing, button, tokenResult, onPaymentSetup ) {
	const verificationDetails = buildVerificationDetails( billing );

	useEffect( () => onPaymentSetup( () => {
		if ( ! button ) {
			return;
		}

		if ( ! tokenResult ) {
			return;
		}

		async function handlePaymentProcessing() {
			let response = { type: 'success' };

			if (!tokenResult) {
				response = {
					type: 'failure',
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

			wp.data.dispatch('wc/store/cart').setBillingAddress(response.meta.billingAddress);

			const needsShipping = wp.data.select('wc/store/cart').getNeedsShipping();

			if (needsShipping) {
				wp.data.dispatch('wc/store/cart').setShippingAddress(
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
		tokenResult,
	] );
}
