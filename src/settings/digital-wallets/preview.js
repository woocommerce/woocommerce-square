import { useState, useEffect, useRef, useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Spinner } from '@wordpress/components';

/**
 * Dynamically loads square.js from the Square CDN (sandbox or production).
 * Resolves immediately if the script is already present on the page.
 *
 * @param {string} url - The Square SDK CDN URL.
 * @return {Promise<void>}
 */
function loadSquareSdk( url ) {
	return new Promise( ( resolve, reject ) => {
		if ( window.Square ) {
			resolve();
			return;
		}
		const existing = document.querySelector( `script[src="${ url }"]` );
		if ( existing ) {
			// Script tag is already in DOM — wait for it to finish loading.
			existing.addEventListener( 'load', resolve );
			existing.addEventListener( 'error', reject );
			return;
		}
		const script    = document.createElement( 'script' );
		script.src      = url;
		script.async    = true;
		script.onload   = resolve;
		script.onerror  = () => reject( new Error( __( 'Failed to load Square SDK.', 'woocommerce-square' ) ) );
		document.head.appendChild( script );
	} );
}

// Google Pay's web button is a PAGE-LEVEL SINGLETON — it always renders with the
// same fixed id (`gpay-button-online-api-id`). Creating/attaching a second button
// while a previous one is still being destroyed leaves that singleton in a stale
// state (e.g. stuck on old color/type classes). This module-level promise chains
// every create/attach AND destroy so they can never overlap: each new operation
// waits for all prior Google Pay work to finish before it touches the SDK.
let gpayLock = Promise.resolve();

/**
 * Runs a Google Pay SDK operation serially with respect to all other Google Pay
 * operations on the page. Returns a promise that resolves when this op is done.
 *
 * @param {Function} op - Async function performing the Google Pay work.
 * @return {Promise<*>}
 */
function runGooglePaySerial( op ) {
	const next = gpayLock.then( () => op(), () => op() );
	// Keep the chain alive even if op rejects, so the next op still waits.
	gpayLock = next.catch( () => {} );
	return next;
}

/**
 * Renders a single real Google Pay button via the Square Web Payments SDK.
 *
 * Google's API renders its own button markup (a `<button class="gpay-button">`,
 * which Google sometimes wraps in an iframe — both are legitimate Google
 * renderings; we don't control which). buttonColor / buttonType cannot be
 * changed on an already-attached button, so to reflect a dropdown change the
 * button must be destroyed and re-created.
 *
 * The PARENT gives this a `key` derived from the color and type, so any option
 * change unmounts the component and mounts a fresh one. Because the Google Pay
 * button is a page singleton, all create/attach/destroy work goes through
 * `runGooglePaySerial` so the new button's creation always waits for the old
 * button's teardown — preventing the stale-singleton race seen under rapid
 * dropdown changes.
 *
 * @param {Object} props
 * @param {Object} props.payments       - An initialised Square payments instance.
 * @param {Object} props.paymentRequest - A Square paymentRequest (SDK init only).
 * @param {string} props.buttonColor    - Google Pay buttonColor option.
 * @param {string} props.buttonType     - Google Pay buttonType option.
 */
function GooglePayButton( { payments, paymentRequest, buttonColor, buttonType } ) {
	const containerRef = useRef( null );

	useEffect( () => {
		if ( ! payments || ! paymentRequest ) {
			return;
		}

		let cancelled  = false;
		let googlePay  = null;

		// Create + attach, serialised behind any pending Google Pay work.
		const createPromise = runGooglePaySerial( async () => {
			if ( cancelled ) return;
			googlePay = await payments.googlePay( paymentRequest );
			if ( cancelled ) {
				await googlePay.destroy().catch( () => {} );
				googlePay = null;
				return;
			}
			if ( containerRef.current ) {
				await googlePay.attach( containerRef.current, {
					buttonColor,
					buttonType,
					buttonSizeMode: 'fill',
				} );
			}
		} );

		return () => {
			cancelled = true;
			// Destroy only after our own create has finished, and serialise it so
			// the next button's create waits for this teardown to complete.
			runGooglePaySerial( async () => {
				await createPromise.catch( () => {} );
				if ( googlePay ) {
					await googlePay.destroy().catch( () => {} );
					googlePay = null;
				}
			} );
		};
	}, [ payments, paymentRequest, buttonColor, buttonType ] );

	return (
		<div
			ref={ containerRef }
			style={ { width: '100%', minHeight: '48px' } }
		/>
	);
}

/**
 * Live preview of the Google Pay and Apple Pay buttons.
 *
 * Google Pay: rendered by the real Square Web Payments SDK. `googlePay.attach()`
 * injects Google's own button markup (a `<button class="gpay-button">`, which
 * Google may also wrap in an iframe — both are valid Google renderings). So the
 * preview shows the exact button the merchant gets at checkout. Changing
 * color/type remounts the GooglePayButton child (via its key) so the button is
 * cleanly rebuilt with the new options.
 *
 * Apple Pay: rendered via the native CSS property
 * `-webkit-appearance: -apple-pay-button`, which IS the real Apple Pay button
 * mechanism in WebKit. `payments.applePay()` is still called (real API) to
 * validate credentials and initialise the instance, but per Square's docs you
 * do NOT call attach() for Apple Pay. The API call throws in non-WebKit
 * browsers (Chrome/Firefox), which is why the button only appears in Safari —
 * mirroring storefront behaviour.
 *
 * Credentials (applicationId, locationId, squareJsUrl) are injected by PHP into
 * the field's `value` prop as a JSON string. Button style/type values are read
 * live from the shared `values` prop.
 *
 * @param {Object} props
 * @param {string} props.value  - JSON string with Square credentials (injected by PHP).
 * @param {Object} props.values - All current form field values for the page.
 */
export default function DigitalWalletPreview( { value, values } ) {
	// Persist SDK objects across renders. payments/paymentRequest are created
	// once per credential set and shared with the GooglePayButton child.
	const applePayRef = useRef( null );

	const [ status, setStatus ]             = useState( 'idle' ); // 'idle' | 'loading' | 'ready' | 'error'
	const [ errorMsg, setErrorMsg ]         = useState( '' );
	const [ payments, setPayments ]         = useState( null );
	const [ paymentRequest, setPaymentRequest ] = useState( null );
	// Tracks whether payments.applePay() succeeded. It throws in non-WebKit
	// browsers (Chrome/Firefox), so this stays false there — which is why the
	// Apple Pay button is hidden outside Safari, mirroring storefront behaviour.
	const [ applePaySupported, setApplePaySupported ] = useState( false );

	// Credentials are static (set at page load by PHP) — memoised so the init
	// effect only re-runs if the credentials themselves change.
	const credentials = useMemo( () => {
		try {
			return JSON.parse( value || '{}' );
		} catch {
			return {};
		}
	}, [ value ] );

	const {
		applicationId,
		locationId,
		squareJsUrl,
		countryCode  = 'US',
		currencyCode = 'USD',
	} = credentials;

	const googleColor = values?.digital_wallets_google_pay_button_color  ?? 'black';
	const appleStyle  = values?.digital_wallets_apple_pay_button_color   ?? 'black';
	// Prefer the per-gateway field; fall back to the legacy shared field for backwards compat.
	const buttonType  = values?.digital_wallets_google_pay_button_type   ?? values?.digital_wallets_button_type ?? 'buy';

	// Debounce the Google Pay options. Each change remounts the GooglePayButton,
	// which creates and attaches a new SDK button asynchronously. Google's Pay
	// client is effectively page-level, so rapid dropdown changes spawn
	// overlapping create/attach calls that race and flicker. Debouncing rebuilds
	// the button only once the options settle, avoiding the churn.
	const [ debouncedGoogle, setDebouncedGoogle ] = useState( {
		color: googleColor,
		type:  buttonType,
	} );

	useEffect( () => {
		const timer = setTimeout( () => {
			setDebouncedGoogle( { color: googleColor, type: buttonType } );
		}, 350 );
		return () => clearTimeout( timer );
	}, [ googleColor, buttonType ] );

	// Load the SDK and initialise payments ONCE per credential set. Apple Pay is
	// initialised here too (its appearance is driven entirely by CSS variables).
	useEffect( () => {
		if ( ! applicationId || ! locationId || ! squareJsUrl ) {
			setStatus( 'error' );
			setErrorMsg( __( 'Connect your Square account to see a live preview.', 'woocommerce-square' ) );
			return;
		}

		let cancelled = false;

		( async () => {
			setStatus( 'loading' );
			setApplePaySupported( false );
			try {
				await loadSquareSdk( squareJsUrl );
				if ( cancelled ) return;

				const paymentsInstance = window.Square.payments( applicationId, locationId );

				// A minimal payment request is required to initialise the buttons.
				// Amount and label are for SDK initialisation only — no payment occurs.
				const request = paymentsInstance.paymentRequest( {
					countryCode,
					currencyCode,
					total: { amount: '1.00', label: 'Preview' },
				} );

				// Apple Pay — real API call. Throws in non-WebKit browsers.
				try {
					applePayRef.current = await paymentsInstance.applePay( request );
					if ( ! cancelled ) {
						setApplePaySupported( true );
					}
				} catch {
					if ( ! cancelled ) {
						setApplePaySupported( false );
					}
				}

				if ( ! cancelled ) {
					setPayments( paymentsInstance );
					setPaymentRequest( request );
					setStatus( 'ready' );
				}
			} catch ( err ) {
				if ( ! cancelled ) {
					setStatus( 'error' );
					setErrorMsg( err.message || __( 'Preview unavailable.', 'woocommerce-square' ) );
				}
			}
		} )();

		return () => {
			cancelled = true;
			if ( applePayRef.current && typeof applePayRef.current.destroy === 'function' ) {
				applePayRef.current.destroy().catch( () => {} );
				applePayRef.current = null;
			}
		};
	}, [ applicationId, locationId, squareJsUrl, countryCode, currencyCode ] );

	const containerStyle = {
		padding:      '16px',
		border:       '1px solid #e0e0e0',
		borderRadius: '4px',
		marginTop:    '4px',
	};

	const placeholderStyle = {
		textAlign:  'center',
		padding:    '24px 0',
		color:      '#757575',
		fontSize:   '13px',
	};

	// The CSS property -webkit-appearance: -apple-pay-button IS the real Apple
	// Pay button in WebKit browsers — not a replica. In Chrome/Firefox it renders
	// as nothing, which mirrors storefront behaviour.
	const appleButtonStyle = {
		display:                            'block',
		width:                              '100%',
		height:                             '48px',
		marginTop:                          '8px',
		cursor:                             'default',
		WebkitAppearance:                   '-apple-pay-button',
		'--apple-pay-button-width':         '100%',
		'--apple-pay-button-height':        '48px',
		'--apple-pay-button-border-radius': '4px',
	};

	return (
		<div style={ containerStyle }>
			{ status === 'loading' && (
				<div style={ placeholderStyle }>
					<Spinner />
				</div>
			) }
			{ status === 'error' && (
				<p style={ { margin: 0, ...placeholderStyle } }>{ errorMsg }</p>
			) }

			{ /* Google Pay — the keyed child remounts on every color/type change so
			     the Square SDK injects a fresh real iframe button each time. */ }
			{ status === 'ready' && payments && paymentRequest && (
				<GooglePayButton
					key={ `${ debouncedGoogle.color }-${ debouncedGoogle.type }` }
					payments={ payments }
					paymentRequest={ paymentRequest }
					buttonColor={ debouncedGoogle.color }
					buttonType={ debouncedGoogle.type }
				/>
			) }

			{ /* Apple Pay — only rendered when payments.applePay() succeeded, i.e.
			     in Safari/WebKit. The button itself is drawn by the CSS property
			     -webkit-appearance: -apple-pay-button (Apple's mandated mechanism;
			     attach() is not used for Apple Pay). In Chrome/Firefox the API call
			     throws, applePaySupported stays false, and nothing renders here. */ }
			{ status === 'ready' && applePaySupported && (
				<div
					lang="en"
					style={ {
						...appleButtonStyle,
						// buttonStyle controls black vs. white; read live from values.
						'--apple-pay-button-style': appleStyle,
					} }
					aria-label={ __( 'Apple Pay button preview', 'woocommerce-square' ) }
				/>
			) }

			{ /* Outside Safari the Apple Pay API is unavailable. Tell the merchant
			     why the button is absent so the empty space is not mistaken for a bug. */ }
			{ status === 'ready' && ! applePaySupported && (
				<p style={ { margin: '8px 0 0', fontSize: '12px', color: '#757575' } }>
					{ __( 'Apple Pay preview is only available in Safari. The button will appear for eligible customers at checkout.', 'woocommerce-square' ) }
				</p>
			) }
		</div>
	);
}
