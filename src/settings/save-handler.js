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

// Cash App "Customize" sub-page fields routed to the Cash App endpoint.
const CASH_APP_FIELDS = new Set( [ 'button_theme', 'button_shape' ] );

// Digital wallet "Customize" fields routed to the Credit Card gateway option
// via the payment_settings controller. Keys are the SDK field ids; the field id
// and the REST param name are intentionally identical (1:1) so the same option
// keys are shared with the legacy settings page. The per-wallet enable toggles
// and the per-wallet button-label selects use dedicated keys (replacing the old
// shared `digital_wallets_button_type` and `digital_wallets_hide_button_options`).
const DIGITAL_WALLET_FIELDS = {
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

	for ( const [ key, val ] of Object.entries( changedValues ) ) {
		if ( GENERAL_FIELDS.has( key ) ) {
			add( ENDPOINTS.settings, key, val );
		} else if ( CASH_APP_FIELDS.has( key ) ) {
			add( ENDPOINTS.cashApp, key, val );
		} else if ( key in DIGITAL_WALLET_FIELDS ) {
			add( ENDPOINTS.paymentSettings, DIGITAL_WALLET_FIELDS[ key ], val );
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

	if ( endpoints.length === 0 ) {
		// Nothing routed through this handler — fields may have self-saved (e.g.
		// gateway-list toggles hit the gateways endpoint directly). Still return a
		// notice so the SDK exits its loading state and acknowledges the save.
		return {
			values,
			notice: __( 'Settings saved.', 'woocommerce-square' ),
		};
	}

	try {
		await Promise.all(
			endpoints.map( ( path ) =>
				apiFetch( { path, method: 'POST', data: payloads[ path ] } )
			)
		);
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
