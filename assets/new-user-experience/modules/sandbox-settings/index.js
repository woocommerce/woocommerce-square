/**
 * External dependencies.
 */
import { __, sprintf } from '@wordpress/i18n';
import {
	TextControl,
	ToggleControl,
} from '@wordpress/components';

/**
 * Internal dependencies.
 */
import {
	Section,
	SectionTitle,
	SectionDescription,
	InputWrapper,
} from '../../components';
import { useSquareSettings } from '../../settings/hooks';

export const SandboxSettings = ( { indent = 0 } ) => {
	const {
		settings,
		squareSettingsLoaded,
		setSquareSettingData,
	} = useSquareSettings();

	const {
		enable_sandbox = 'yes',
		sandbox_application_id = '',
		sandbox_token = '',
	} = settings;

	if ( ! squareSettingsLoaded ) {
		return null;
	}

	return (
		<>
			<Section>
				<SectionTitle title={ __( 'Configure Sandbox Settings', 'woocommerce-square' ) } />
				<SectionDescription>
					{ __( 'Activate Sandbox Mode to safely simulate transactions and sync operations, ensuring your WooCommerce/Square integration functions seamlessly. Experiment with payment methods and product data syncing in a risk-free environment before going live with your store.', 'woocommerce-square' ) }
				</SectionDescription>

				<div className='woo-square-wizard__fields'>
					<InputWrapper
						label={ __( 'Enable Sandbox Mode', 'woocommerce-square' ) }
						description={ __( 'After enabling you\'ll see a new Sandbox settings section with two fields: Sandbox Application ID & Sandbox Access Token.', 'woocommerce-square' ) }
						variant="boxed"
					>
						<ToggleControl
							checked={ 'yes' === enable_sandbox }
							onChange={ ( enable_sandbox ) => setSquareSettingData( { enable_sandbox: enable_sandbox ? 'yes' : 'no' } ) }
						/>
					</InputWrapper>

					{ 'yes' === enable_sandbox && (
						<>
							<InputWrapper
								label={ __( 'Sandbox Application ID', 'woocommerce-square' ) }
								description={
									sprintf( __( 'Application ID for the Sandbox Application, see the details in the %1$sMy Applications%2$s section.', 'woocommerce-square' ),
										'<a target="_blank" href="https://squareupsandbox.com/dashboard/apps/my-applications">',
										'</a>'
									)
								}
								indent={ indent }
							>
								<TextControl
									value={ sandbox_application_id }
									onChange={ ( sandbox_application_id ) => setSquareSettingData( { sandbox_application_id } ) }
								/>
							</InputWrapper>

							<InputWrapper
								label={ __( 'Sandbox Access Token', 'woocommerce-square' ) }
								description={
									sprintf(
										__( 'Access Token for the Sandbox Test Account, see the details in the %1$sSandbox Test Account%2$s section. Make sure you use the correct Sandbox Access Token for your application. For a given Sandbox Test Account, each Authorized Application is assigned a different Access Token.', 'woocommerce-square' ),
										'<a target="_blank" href="https://developer.squareup.com/console/en/sandbox-test-accounts">',
										'</a>'
									)
								}
								indent={ indent }
							>
								<TextControl
									value={ sandbox_token }
									onChange={ ( sandbox_token ) => setSquareSettingData( { sandbox_token } ) }
								/>
							</InputWrapper>
						</>
					) }
				</div>
			</Section>
		</>
	)
};
