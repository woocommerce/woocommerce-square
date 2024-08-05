/**
 * External dependencies.
 */
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';

/**
 * Internal dependencies.
 */
import './index.scss';
import { Section, SectionTitle, SectionDescription } from '../../../components';
import { useSquareSettings } from '../../../settings/hooks';

export const ConnectSetup = () => {
	const { settings } = useSquareSettings( true );

	return (
		<div className="woo-square-onbarding__connect-square">
			<div className="woo-square-onbarding__connect-square--single">
				<Section>
					<SectionTitle
						title={ __(
							'Thanks for installing WooCommerce Square!',
							'woocommerce-square'
						) }
					/>
					<SectionDescription>
						{ __(
							"To get started, let's connect to your Square Account to complete the setup process.",
							'woocommerce-square'
						) }
					</SectionDescription>

					<Button
						variant="button-primary"
						className="button-primary"
						href={ settings.connection_url_wizard }
					>
						{ __( 'Connect with Square', 'woocommerce-square' ) }
					</Button>
				</Section>
			</div>
		</div>
	);
};
