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
	const root = createRoot(
		document.getElementById( 'woocommerce-square-onboarding' )
	);

	root.render( <OnboardingApp /> );
} );
