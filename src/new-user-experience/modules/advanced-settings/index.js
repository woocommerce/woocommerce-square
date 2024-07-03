/**
 * External dependencies.
 */
import { __, sprintf } from '@wordpress/i18n';
import { ToggleControl } from '@wordpress/components';
import parse from 'html-react-parser';

/**
 * Internal dependencies.
 */
import {
	Section,
	SectionTitle,
	SectionDescription,
	InputWrapper,
	SquareCheckboxControl,
} from '../../components';
import { DebugMode } from '../../modules';
import { useSquareSettings } from '../../settings/hooks';
import { useSteps } from '../../onboarding/hooks';

export const AdvancedSettings = () => {
	const { settings, squareSettingsLoaded, setSquareSettingData } =
		useSquareSettings();

	const {
		stepData: { step },
	} = useSteps();

	const { enable_customer_decline_messages, debug_logging_enabled = 'no' } =
		settings;

	if ( ! squareSettingsLoaded ) {
		return null;
	}

	return (
		<>
			<Section>
				<SectionTitle
					title={ __( 'Advanced Settings', 'woocommerce-square' ) }
				/>
				<SectionDescription>
					{ __(
						'Adjust these options to provide your customers with additional clarity and troubleshoot any issues more effectively.',
						'woocommerce-square'
					) }
					{ <br /> }
					{ step === 'advanced-settings' &&
						parse(
							sprintf(
								/* translators: %1$s and %2$s are HTML tags for the link to the Square settings page */
								__(
									'%1$sClick here%2$s to further refine your settings in the traditional view.',
									'woocommerce-square'
								),
								`<a href='${ wcSquareSettings.adminUrl }admin.php?page=wc-settings&tab=square'>`, // eslint-disable-line no-undef
								'</a>'
							)
						) }
				</SectionDescription>

				<div className="woo-square-wizard__fields">
					<InputWrapper
						label={ __(
							'Detailed Decline Messages',
							'woocommerce-square'
						) }
					>
						<SquareCheckboxControl
							checked={
								enable_customer_decline_messages === 'yes'
							}
							onChange={ ( enabled ) =>
								setSquareSettingData( {
									enable_customer_decline_messages: enabled
										? 'yes'
										: 'no',
								} )
							}
							label={ __(
								'Show detailed decline messages to the customer during checkout rather than a generic decline message.',
								'woocommerce-square'
							) }
						/>
					</InputWrapper>

					<DebugMode />

					<InputWrapper
						label={ __( 'Enable Logging', 'woocommerce-square' ) }
						variant="boxed"
						description={ parse(
							sprintf(
								/* translators: %1$s and %2$s are HTML tags for the link to the WooCommerce status log */
								__(
									'Log debug messages to the %1$sWooCommerce status log%2$s',
									'woocommerce-square'
								),
								`<a href="${ wcSquareSettings.adminUrl }admin.php?page=wc-status&tab=logs">`, // eslint-disable-line no-undef
								'</a>'
							)
						) }
					>
						<ToggleControl
							checked={ debug_logging_enabled === 'yes' }
							onChange={ ( enabled ) =>
								setSquareSettingData( {
									debug_logging_enabled: enabled
										? 'yes'
										: 'no',
								} )
							}
						/>
					</InputWrapper>
				</div>
			</Section>
		</>
	);
};
