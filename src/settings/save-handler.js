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

	// The location picker writes to a single `location_id` field; route it to the
	// option key for the currently selected environment.
	if ( 'location_id' in changedValues ) {
		const isSandbox = values.enable_sandbox === 'yes';
		payload[ isSandbox ? 'sandbox_location_id' : 'production_location_id' ] =
			changedValues.location_id;
	}

	if ( Object.keys( payload ).length === 0 ) {
		// Nothing to save — return the full values so SDK state stays intact.
		return { values };
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

	// `values` is the SDK's full current state — returning it keeps every field
	// in sync. `notice` must be a plain string; the SDK passes it directly as
	// the Notice component's children.
	return {
		values,
		notice: __( 'Settings saved.', 'woocommerce-square' ),
	};
}
