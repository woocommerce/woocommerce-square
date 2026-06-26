import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

const SETTINGS_PATH = '/wc/v3/wc_square/settings';

// Module-level cache so every component on the Square hub shares a single
// request instead of each fetching the settings endpoint on mount.
let cache = null;
let inflight = null;

/**
 * Loads the Square settings, reusing the in-flight request when one is pending.
 *
 * @return {Promise<Object>} The settings payload.
 */
function load() {
	if ( ! inflight ) {
		inflight = apiFetch( { path: SETTINGS_PATH } )
			.then( ( data ) => {
				cache = data;
				inflight = null;
				return data;
			} )
			.catch( ( error ) => {
				inflight = null;
				throw error;
			} );
	}

	return inflight;
}

/**
 * Shared hook exposing the Square settings payload.
 *
 * @return {{loading: boolean, data: Object|null, error: Object|null}} State.
 */
export default function useSquareSettings() {
	const [ state, setState ] = useState( () => ( {
		loading: cache === null,
		data: cache,
		error: null,
	} ) );

	useEffect( () => {
		let active = true;

		if ( cache !== null ) {
			setState( { loading: false, data: cache, error: null } );
			return undefined;
		}

		load()
			.then( ( data ) => {
				if ( active ) {
					setState( { loading: false, data, error: null } );
				}
			} )
			.catch( ( error ) => {
				if ( active ) {
					setState( { loading: false, data: cache, error } );
				}
			} );

		return () => {
			active = false;
		};
	}, [] );

	return state;
}
