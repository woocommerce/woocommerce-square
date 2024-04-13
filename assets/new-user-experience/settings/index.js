/**
 * External dependencies.
 */
import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import { SettingsApp } from './settings-app';

domReady( () => {
	const root = createRoot(
		document.getElementById( 'woocommerce-square-settings__container' )
	);

	root.render( <SettingsApp /> );
} );
