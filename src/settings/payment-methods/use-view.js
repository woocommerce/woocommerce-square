import { useEffect } from '@wordpress/element';

/** Query arg that makes the Payment Methods sub-view addressable/reloadable. */
export const VIEW_PARAM = 'pm-view';

/**
 * Navigate to a Payment Methods sub-view ('list' | 'digital-wallet' | 'cash-app').
 *
 * Updates the shared SDK value (so the view switches without a reload, keeping
 * any unsaved changes) AND the URL via history so the view is deep-linkable,
 * survives a reload, and works with the browser Back/Forward buttons.
 *
 * @param {Function} setValue SDK setter.
 * @param {string}   view     Target view id.
 */
export function goToView( setValue, view ) {
	setValue( 'payment_methods_view', view );
	const url = new URL( window.location.href );
	if ( view && view !== 'list' ) {
		url.searchParams.set( VIEW_PARAM, view );
	} else {
		url.searchParams.delete( VIEW_PARAM );
	}
	window.history.pushState( {}, '', url );
}

/**
 * Keeps the SDK sub-view value in sync with the URL when the user presses the
 * browser Back/Forward buttons. Call from any component that is mounted in each
 * view so the sync is always active.
 *
 * @param {Function} setValue SDK setter.
 */
export function useViewSync( setValue ) {
	useEffect( () => {
		const onPop = () => {
			const view =
				new URL( window.location.href ).searchParams.get(
					VIEW_PARAM
				) || 'list';
			setValue( 'payment_methods_view', view );
		};
		window.addEventListener( 'popstate', onPop );
		return () => window.removeEventListener( 'popstate', onPop );
	}, [ setValue ] );
}
