import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

const ENDPOINTS = {
	settings: '/wc/v3/wc_square/settings',
	cashApp: '/wc/v3/wc_square/cash_app_settings',
	paymentSettings: '/wc/v3/wc_square/payment_settings',
};

// General tab fields routed to the main settings endpoint.
const GENERAL_FIELDS = new Set( [
	'enable_sandbox',
	'sandbox_application_id',
	'sandbox_token',
] );

// Synchronize Square tab fields. They live in the same `wc_square_settings`
// option as the General tab fields, but they are kept in their own set on
// purpose: GENERAL_FIELDS doubles as the "reload the page after saving" trigger
// (the Business location list has to be refetched) and a sync save must not
// reload.
const SYNC_FIELDS = new Set( [
	'system_of_record',
	'enable_inventory_sync',
	'override_product_images',
	'hide_missing_products',
	'sync_interval',
	'enable_order_fulfillment_sync',
	'enable_square_discount_codes',
] );

// Fields backed by an SDK checkbox. The native checkbox emits a boolean, while
// the stored option and every legacy `'yes' === ...` check expect a string.
const BOOLEAN_FIELDS = new Set( [
	'enable_inventory_sync',
	'override_product_images',
	'hide_missing_products',
	'enable_order_fulfillment_sync',
	'enable_square_discount_codes',
] );

/**
 * Converts an SDK checkbox value to the 'yes'/'no' string the option stores.
 *
 * @param {*} value Raw form value.
 * @return {string} 'yes' or 'no'.
 */
const toYesNo = ( value ) =>
	value === true || value === 'yes' || value === '1' ? 'yes' : 'no';

// Cash App "Customize" sub-page fields routed to the Cash App endpoint.
const CASH_APP_FIELDS = new Set( [ 'button_theme', 'button_shape' ] );

// Digital wallet "Customize" fields routed to the Credit Card gateway option
// via the payment_settings controller. Keys are the SDK field ids; the field id
// and the REST param name are intentionally identical (1:1) so the same option
// keys are shared with the legacy settings page. The per-wallet enable toggles
// and the per-wallet button-label selects use dedicated keys (replacing the old
// shared `digital_wallets_button_type` and `digital_wallets_hide_button_options`).
const DIGITAL_WALLET_FIELDS = {
	// Digital wallet parent enable (Credit Card gateway option).
	enable_digital_wallets: 'enable_digital_wallets',
	digital_wallets_google_pay_enabled: 'digital_wallets_google_pay_enabled',
	digital_wallets_apple_pay_enabled: 'digital_wallets_apple_pay_enabled',
	digital_wallets_google_pay_button_type:
		'digital_wallets_google_pay_button_type',
	digital_wallets_apple_pay_button_type:
		'digital_wallets_apple_pay_button_type',
	digital_wallets_google_pay_button_color:
		'digital_wallets_google_pay_button_color',
	digital_wallets_apple_pay_button_color:
		'digital_wallets_apple_pay_button_color',
};

// Payment Methods list enable toggles for real WooCommerce gateways. Keys are
// the SDK field ids; values are the WC gateway ids. These persist to the WC
// payment_gateways endpoint (PUT) on Save, not to a wc_square controller.
const GATEWAY_ENABLE_FIELDS = {
	square_credit_card_enabled: 'square_credit_card',
	square_cash_app_pay_enabled: 'square_cash_app_pay',
	gift_cards_pay_enabled: 'gift_cards_pay',
};

export default async function squareSaveHandler( { values, changedValues } ) {
	// Collect a payload per REST endpoint so each settings group is saved
	// against its own controller without wiping the others.
	const payloads = {};
	const add = ( endpoint, key, val ) => {
		if ( ! payloads[ endpoint ] ) {
			payloads[ endpoint ] = {};
		}
		payloads[ endpoint ][ key ] = val;
	};

	// WC gateway enables persist via PUT to the payment_gateways endpoint, one
	// request per changed gateway.
	const gatewayPuts = [];

	for ( const [ key, val ] of Object.entries( changedValues ) ) {
		if ( GENERAL_FIELDS.has( key ) ) {
			add( ENDPOINTS.settings, key, val );
		} else if ( SYNC_FIELDS.has( key ) ) {
			add(
				ENDPOINTS.settings,
				key,
				BOOLEAN_FIELDS.has( key ) ? toYesNo( val ) : val
			);
		} else if ( CASH_APP_FIELDS.has( key ) ) {
			add( ENDPOINTS.cashApp, key, val );
		} else if ( key in DIGITAL_WALLET_FIELDS ) {
			add( ENDPOINTS.paymentSettings, DIGITAL_WALLET_FIELDS[ key ], val );
		} else if ( key in GATEWAY_ENABLE_FIELDS ) {
			gatewayPuts.push( {
				id: GATEWAY_ENABLE_FIELDS[ key ],
				enabled: val === 'yes',
			} );
		}
	}

	// The Business location select writes to a single `location_id` field; route
	// it to the option key for the currently selected environment. Skip it when the
	// environment changed in this same save: the location options are server-rendered
	// for the saved environment and only refetch on reload, so the selected value
	// still belongs to the old environment and would be written under the wrong env key.
	const envChanged = 'enable_sandbox' in changedValues;
	if ( 'location_id' in changedValues && ! envChanged ) {
		const isSandbox = values.enable_sandbox === 'yes';
		add(
			ENDPOINTS.settings,
			isSandbox ? 'sandbox_location_id' : 'production_location_id',
			changedValues.location_id
		);
	}

	const endpoints = Object.keys( payloads );

	if ( endpoints.length === 0 && gatewayPuts.length === 0 ) {
		// Nothing changed routed through this handler. Still return a notice so
		// the SDK exits its loading state and acknowledges the save.
		return {
			values,
			notice: __( 'Settings saved.', 'woocommerce-square' ),
		};
	}

	try {
		// Run the controller POSTs first, then the gateway enable PUTs, never
		// concurrently. A gateway PUT and a controller POST can target the SAME
		// stored option (e.g. the credit-card gateway PUT writes `enabled` while
		// the payment_settings POST writes `enable_digital_wallets`, both in
		// woocommerce_square_credit_card_settings; likewise Cash App). Firing them
		// together read-modify-writes the same array in parallel and the slower
		// response clobbers the faster one. Sequencing removes the race; each PUT
		// then reads the option the POST already persisted. The POSTs target
		// distinct options, as do the PUTs, so each group can still run in parallel.
		if ( endpoints.length > 0 ) {
			await Promise.all(
				endpoints.map( ( path ) =>
					apiFetch( { path, method: 'POST', data: payloads[ path ] } )
				)
			);
		}
		if ( gatewayPuts.length > 0 ) {
			await Promise.all(
				gatewayPuts.map( ( g ) =>
					apiFetch( {
						path: `/wc/v3/payment_gateways/${ g.id }`,
						method: 'PUT',
						data: { enabled: g.enabled },
					} )
				)
			);
		}
	} catch ( error ) {
		// The SDK catches a thrown Error and renders error.message as the save
		// notice. Surface a clean, translatable message instead of the raw REST
		// error object.
		throw new Error(
			error?.message ||
				__(
					'Unable to save Square settings. Please try again.',
					'woocommerce-square'
				)
		);
	}

	// Reload only when the environment or credentials changed, since that is what
	// repopulates the Business location dropdown from freshly fetched locations for
	// the saved environment (matching the legacy settings page). A location-only save
	// needs no reload. The short delay lets the success notice render first; clear the
	// SDK's beforeunload guard so a "Leave site?" prompt can't block the reload.
	const needsReload = [ ...GENERAL_FIELDS ].some(
		( field ) => field in changedValues
	);
	if ( needsReload ) {
		setTimeout( () => {
			window.onbeforeunload = null;
			window.location.reload();
		}, 1200 );
	}

	// `values` is the SDK's full current state — returning it keeps every field
	// in sync. `notice` must be a plain string; the SDK passes it directly as
	// the Notice component's children.
	return {
		values,
		notice: __( 'Settings saved.', 'woocommerce-square' ),
	};
}
