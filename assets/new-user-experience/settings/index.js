/**
 * External dependencies.
 */
import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import { SettingsApp } from './settings-app';
import { PaymentGatewaySettingsApp } from './payment-gateway-settings-app';

domReady( () => {
	let container = document.getElementById( 'woocommerce-square-settings__container' );

	if ( container ) {
		const root = createRoot( container );
		root.render( <SettingsApp /> );
	} else {
		container = document.getElementById( 'woocommerce-square-payment-gateway-settings__container' );

		const root = createRoot( container );
		root.render( <PaymentGatewaySettingsApp /> );
	}
} );
