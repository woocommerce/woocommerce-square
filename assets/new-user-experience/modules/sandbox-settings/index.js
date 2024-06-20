/**
 * External dependencies.
 */
import { __, sprintf } from '@wordpress/i18n';
import parse from 'html-react-parser';
import { TextControl, ToggleControl } from '@wordpress/components';

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

export const SandboxSettings = ( { indent = 0, showToggle = true } ) => {
	const {
		settings,
		squareSettingsLoaded,
		setSquareSettingData,
	} = useSquareSettings();

	const {
		enable_sandbox = 'no',
		sandbox_application_id = '',
		sandbox_token = '',
	} = settings;

	if (!squareSettingsLoaded) {
		return null;
	}

	return (
		<>
			<Section>
				{ showToggle &&
					<>
						<SectionTitle title={ __( 'Configure Sandbox Settings', 'woocommerce-square' ) } />
						<SectionDescription>
							{ __( 'Activate Sandbox Mode to safely simulate transactions and sync operations, ensuring your WooCommerce/Square integration functions seamlessly. Experiment with payment methods and product data syncing in a risk-free environment before going live with your store.', 'woocommerce-square' ) }
						</SectionDescription>
					</>
				}

				<div className='woo-square-wizard__fields'>
					{ showToggle &&
						<InputWrapper
							label={ __( 'Enable Sandbox Mode', 'woocommerce-square' ) }
							description={ __( 'After enabling you\'ll see a new Sandbox settings section with two fields: Sandbox Application ID & Sandbox Access Token.', 'woocommerce-square' ) }
							variant="boxed"
						>
							<ToggleControl
								className='enable-sandbox-mode-field'
								checked={ 'yes' === enable_sandbox }
								onChange={ ( enable_sandbox ) => setSquareSettingData( { enable_sandbox: enable_sandbox ? 'yes' : 'no' } ) }
							/>
						</InputWrapper>
					}

					{enable_sandbox === 'yes' && (
						<>
							<InputWrapper
								label={__(
									'Sandbox Application ID',
									'woocommerce-square'
								)}
								description={parse(
									sprintf(
										/* translators: %1$s: opening anchor tag, %2$s: closing anchor tag */
										__(
											'Application ID for the Sandbox Application, see the details in the %1$sMy Applications%2$s section.',
											'woocommerce-square'
										),
										'<a target="_blank" href="https://squareupsandbox.com/dashboard/apps/my-applications">',
										'</a>'
									)
								)}
								indent={indent}
							>
								<TextControl
									required
									data-testid="sandbox-application-id-field"
									value={sandbox_application_id}
									onChange={(value) =>
										setSquareSettingData({
											sandbox_application_id: value,
										})
									}
								/>
							</InputWrapper>

							<InputWrapper
								label={__(
									'Sandbox Access Token',
									'woocommerce-square'
								)}
								description={parse(
									sprintf(
										/* translators: %1$s: opening anchor tag, %2$s: closing anchor tag */
										__(
											'Access Token for the Sandbox Test Account, see the details in the %1$sSandbox Test Account%2$s section. Make sure you use the correct Sandbox Access Token for your application. For a given Sandbox Test Account, each Authorized Application is assigned a different Access Token.',
											'woocommerce-square'
										),
										'<a target="_blank" href="https://developer.squareup.com/console/en/sandbox-test-accounts">',
										'</a>'
									)
								)}
								indent={indent}
							>
								<TextControl
									required
									data-testid="sandbox-token-field"
									value={sandbox_token}
									onChange={(value) =>
										setSquareSettingData({
											sandbox_token: value,
										})
									}
								/>
							</InputWrapper>
						</>
					)}
				</div>
			</Section>
		</>
	);
};
