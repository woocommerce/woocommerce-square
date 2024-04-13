/**
 * External dependencies.
 */
import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import { OnboardingApp } from './onboarding-app';

domReady( () => {
	const wrapper = document.getElementById( 'woocommerce-square-onboarding' );

	if ( wrapper ) {
		const root = createRoot( wrapper );
		root.render( <OnboardingApp /> );
	}
} );
