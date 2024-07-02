/**
 * External dependencies.
 */
import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';
import { register } from '@wordpress/data';

/**
 * Internal dependencies.
 */
import '../styles/index.scss';
import '../styles/onboarding.scss';
import { OnboardingApp } from './onboarding-app';
import store from '../../new-user-experience/onboarding/data/store';

register(store);

domReady(() => {
	const wrapper = document.getElementById('woocommerce-square-onboarding');

	if (wrapper) {
		const root = createRoot(wrapper);
		root.render(<OnboardingApp />);
	}
});
