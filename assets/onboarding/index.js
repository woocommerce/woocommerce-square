import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';

const SettingsPage = () => {
	return <div>Square onboarding begins here</div>;
};

domReady( () => {
	const root = createRoot(
		document.getElementById( 'woocommerce-square__onboarding' )
	);

	root.render( <SettingsPage /> );
} );
