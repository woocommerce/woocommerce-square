import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

export default function OAuthConnect( { values } ) {
	const [ state, setState ] = useState( {
		loading: true,
		isConnected: false,
		connectionUrl: '',
		connectionUrlSandbox: '',
		disconnectionUrl: '',
		fetchedSandbox: false,
	} );

	useEffect( () => {
		apiFetch( { path: '/wc/v3/wc_square/settings' } )
			.then( ( settings ) => {
				setState( {
					loading: false,
					isConnected: !! settings.is_connected,
					connectionUrl: settings.connection_url ?? '',
					connectionUrlSandbox: settings.connection_url_sandbox ?? '',
					disconnectionUrl: settings.disconnection_url ?? '',
					fetchedSandbox: settings.enable_sandbox === 'yes',
				} );
			} )
			.catch( () =>
				setState( ( prev ) => ( { ...prev, loading: false } ) )
			);
	}, [] );

	if ( state.loading ) {
		return null;
	}

	if ( state.isConnected ) {
		// Guard against a missing URL so we never render a dead link.
		if ( ! state.disconnectionUrl ) {
			return null;
		}
		return (
			<a
				href={ state.disconnectionUrl }
				className="button button-primary"
			>
				{ __( 'Disconnect from Square', 'woocommerce-square' ) }
			</a>
		);
	}

	// Track the live Environment Selection radio so the connect link points at
	// the right OAuth endpoint without a page reload. Fall back to the fetched
	// value when the form has not surfaced it yet.
	const isSandbox =
		values?.enable_sandbox !== undefined
			? values.enable_sandbox === 'yes'
			: state.fetchedSandbox;

	const connectionUrl = isSandbox
		? state.connectionUrlSandbox
		: state.connectionUrl;

	// Avoid rendering a dead link when the URL is unavailable.
	if ( ! connectionUrl ) {
		return null;
	}

	return (
		<a href={ connectionUrl } className="button button-primary">
			{ __( 'Connect to Square', 'woocommerce-square' ) }
		</a>
	);
}
