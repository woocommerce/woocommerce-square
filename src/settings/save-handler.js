import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

const SETTINGS_FIELDS = new Set( [
	'enable_sandbox',
	'sandbox_application_id',
	'sandbox_token',
] );

export default async function squareSaveHandler( { values, changedValues } ) {
	const payload = {};

	for ( const [ key, val ] of Object.entries( changedValues ) ) {
		if ( SETTINGS_FIELDS.has( key ) ) {
			payload[ key ] = val;
		}
	}

	// `enable_sandbox` comes from a custom radio component (EnvironmentSelector)
	// which calls onChange('yes') or onChange('no') directly — no conversion needed.
	// The REST API already expects 'yes'/'no' and that is exactly what we have.

	// The Business location select writes to a single `location_id` field; route
	// it to the option key for the currently selected environment. Skip it when the
	// environment changed in this same save: the location options are server-rendered
	// for the saved environment and only refetch on reload, so the selected value
	// still belongs to the old environment and would be written under the wrong env key.
	const envChanged = 'enable_sandbox' in changedValues;
	if ( 'location_id' in changedValues && ! envChanged ) {
		const isSandbox = values.enable_sandbox === 'yes';
		payload[
			isSandbox ? 'sandbox_location_id' : 'production_location_id'
		] = changedValues.location_id;
	}

	if ( Object.keys( payload ).length === 0 ) {
		// Nothing to send through this handler — fields may have self-saved
		// (e.g. gateway-list toggles call `/wc/v3/payment_gateways/{id}`
		// directly). Still return a notice so the SDK exits its loading
		// state and acknowledges the save.
		return {
			values,
			notice: __( 'Settings saved.', 'woocommerce-square' ),
		};
	}

	try {
		await apiFetch( {
			path: '/wc/v3/wc_square/settings',
			method: 'POST',
			data: payload,
		} );
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
	const needsReload = [ ...SETTINGS_FIELDS ].some(
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
