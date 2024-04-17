/**
 * External dependencies.
 */
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';

/**
 * Internal dependencies.
 */
import './index.scss';
import {
	SectionTitle,
	SectionDescription,
} from '../../../components';
import { useSquareSettings } from '../../../settings/hooks';

export const ConnectSetup = ( {} ) => {
	const {
		settings,
	} = useSquareSettings( true );

	return (
		<div className="woo-square-onbarding__connect-square">
			<div className="woo-square-onbarding__connect-square--right">
				<div className="woo-square-onbarding__connect-square__toggles">
					<SectionTitle title={ __( 'Thanks for installing WooCommerce Square!', 'woocommerce-square' ) } />
					<SectionDescription>
						{ __( "To get started, let's connect to your Square Account to complete the setup process.", 'woocommerce-square' ) }
					</SectionDescription>

					<Button
							variant='primary'
							href={settings.connection_url}
					>
						{
							__( 'Connect to Square', 'woocommerce-square' )
						}
					</Button>
				</div>
			</div>
		</div>
	);
};
