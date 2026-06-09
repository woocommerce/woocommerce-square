import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

const getActiveLocations = ( locations = [] ) =>
	locations
		.filter( ( l ) => l.status === 'ACTIVE' )
		.map( ( l ) => ( { label: l.name, value: l.id } ) );

export default function LocationPicker( { values } ) {
	const [ state, setState ] = useState( {
		loading: true,
		locations: [],
		sandboxId: '',
		productionId: '',
		fetchedSandbox: false,
		saving: false,
		error: '',
	} );

	useEffect( () => {
		apiFetch( { path: '/wc/v3/wc_square/settings' } )
			.then( ( settings ) => {
				setState( ( prev ) => ( {
					...prev,
					loading: false,
					locations: getActiveLocations( settings.locations ?? [] ),
					sandboxId: settings.sandbox_location_id ?? '',
					productionId: settings.production_location_id ?? '',
					fetchedSandbox: settings.enable_sandbox === 'yes',
				} ) );
			} )
			.catch( () =>
				setState( ( prev ) => ( { ...prev, loading: false } ) )
			);
	}, [] );

	// Derive the environment from live form state so the picker tracks the
	// Environment Selection radio without a page reload. Fall back to the
	// fetched value when the form has not surfaced it yet.
	const isSandbox =
		values?.enable_sandbox !== undefined
			? values.enable_sandbox === 'yes'
			: state.fetchedSandbox;

	if ( state.loading || state.locations.length === 0 ) {
		return null;
	}

	const currentId = isSandbox ? state.sandboxId : state.productionId;

	const handleChange = async ( e ) => {
		const newId    = e.target.value;
		const field    = isSandbox ? 'sandbox_location_id' : 'production_location_id';
		const key      = isSandbox ? 'sandboxId' : 'productionId';
		const previous = currentId;

		// Optimistic update.
		setState( ( prev ) => ( {
			...prev,
			[ key ]: newId,
			saving: true,
			error: '',
		} ) );

		try {
			await apiFetch( {
				path: '/wc/v3/wc_square/settings',
				method: 'POST',
				data: { [ field ]: newId },
			} );
			setState( ( prev ) => ( { ...prev, saving: false } ) );
		} catch ( err ) {
			// Revert the optimistic update so the UI matches the server state.
			setState( ( prev ) => ( {
				...prev,
				[ key ]: previous,
				saving: false,
				error: __(
					'Failed to save the business location. Please try again.',
					'woocommerce-square'
				),
			} ) );
		}
	};

	return (
		<div>
			<select
				value={ currentId }
				onChange={ handleChange }
				disabled={ state.saving }
			>
				<option value="">
					{ __( '- Please select a location -', 'woocommerce-square' ) }
				</option>
				{ state.locations.map( ( loc ) => (
					<option key={ loc.value } value={ loc.value }>
						{ loc.label }
					</option>
				) ) }
			</select>
			{ state.error && (
				<p style={ { color: '#cc1818', marginTop: '8px' } }>
					{ state.error }
				</p>
			) }
		</div>
	);
}
