/**
 * External dependencies.
 */
import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';
import { register } from '@wordpress/data';

/**
 * Internal dependencies.
 */
import { OnboardingApp } from './onboarding-app';
import store from '../../new-user-experience/onboarding/data/store';

register( store );

domReady( () => {
	const urlParams = new URLSearchParams( window.location.search );
	const step = urlParams.get( 'step' ) || 'start';
	const wrapper = document.getElementById( 'woocommerce-square-onboarding-' + step );

	if ( wrapper ) {
		const root = createRoot( wrapper );
		root.render( <OnboardingApp step={step} /> );
	}
} );
