import { __ } from '@wordpress/i18n';
import useSquareSettings from '../use-square-settings';

/**
 * Wraps a connect/disconnect action with the separator that visually divides it
 * from the fields above, matching the Figma design. Kept together so the divider
 * never renders on its own when no button is shown (e.g. sandbox before
 * connecting).
 *
 * @param {Object}      props
 * @param {JSX.Element} props.children The action link.
 * @return {JSX.Element} The divider and action.
 */
function ConnectAction( { children } ) {
	return (
		<div>
			<div className="wc-square-divider" aria-hidden="true" />
			{ children }
		</div>
	);
}

export default function OAuthConnect( { values } ) {
	const { loading, data } = useSquareSettings();

	if ( loading || ! data ) {
		return null;
	}

	// Connection state reflects the saved environment.
	if ( data.is_connected ) {
		// Guard against a missing URL so we never render a dead link.
		if ( ! data.disconnection_url ) {
			return null;
		}
		return (
			<ConnectAction>
				<a
					href={ data.disconnection_url }
					className="button button-primary"
				>
					{ __( 'Disconnect from Square', 'woocommerce-square' ) }
				</a>
			</ConnectAction>
		);
	}

	// OAuth Connect is a production-only flow; sandbox connects via the manual
	// Application ID + Access Token fields. Track the live Environment Selection
	// radio so the button hides immediately when Sandbox is selected.
	const isSandbox =
		values?.enable_sandbox !== undefined
			? values.enable_sandbox === 'yes'
			: data.enable_sandbox === 'yes';

	if ( isSandbox ) {
		return null;
	}

	// Avoid rendering a dead link when the URL is unavailable.
	if ( ! data.connection_url ) {
		return null;
	}

	return (
		<ConnectAction>
			<a href={ data.connection_url } className="button button-primary">
				{ __( 'Connect to Square', 'woocommerce-square' ) }
			</a>
		</ConnectAction>
	);
}
