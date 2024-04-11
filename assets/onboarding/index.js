import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';

import { CreditCard } from "./steps/setup/credit-card";

const SettingsPage = () => {
	return (
		<CreditCard />
	);
};

domReady( () => {
	const root = createRoot(
		document.getElementById( 'woocommerce-square-onboarding' )
	);

	root.render( <SettingsPage /> );
} );
