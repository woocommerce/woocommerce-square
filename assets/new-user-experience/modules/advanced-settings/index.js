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
import { usePaymentGatewaySettings, useSteps } from '../../onboarding/hooks';

export const AdvancedSettings = () => {
	const {
		settings,
		squareSettingsLoaded,
		setSquareSettingData,
	} = useSquareSettings();

	const {
		stepData: {
			step,
		}
	} = useSteps();

	const {
		paymentGatewaySettingsLoaded,
		paymentGatewaySettings,
		setCreditCardData,
	} = usePaymentGatewaySettings();
	const enable_customer_decline_messages = paymentGatewaySettings?.enable_customer_decline_messages;

	const {
		debug_logging_enabled = 'no',
	} = settings;

	if ( ! ( squareSettingsLoaded && paymentGatewaySettingsLoaded ) ) {
		return null;
	}

	return (
		<>
			<Section>
				<SectionTitle title={ __( 'Advanced Settings', 'woocommerce-square' ) } />
				<SectionDescription>
					{ __( 'Adjust these options to provide your customers with additional clarity and troubleshoot any issues more effectively.', 'woocommerce-square' ) }
					{ <br /> }
					{ 'advanced-settings' === step && parse(
						sprintf(
							__( '%1sClick here%2s to further refine your settings in the traditional view.', 'woocommerce-square' ),
							'<a target="_blank" href="/wp-admin/admin.php?page=wc-settings&tab=square">',
							'</a>',
						)
					) }

				</SectionDescription>

				<div className='woo-square-wizard__fields'>
					<InputWrapper
						label={ __( 'Detailed Decline Messages', 'woocommerce-square' ) }
					>
						<SquareCheckboxControl
							checked={ 'yes' === enable_customer_decline_messages }
							onChange={ ( enabled ) => setCreditCardData( { enable_customer_decline_messages: enabled ? 'yes' : 'no' } ) }
							label={
								__( 'Check to enable detailed decline messages to the customer during checkout when possible, rather than a generic decline message.', 'woocommerce-square' )
							}
						/>
					</InputWrapper>

					<InputWrapper
						label={ __( 'Enable Logging', 'woocommerce-square' ) }
						variant="boxed"
					>
						<ToggleControl
							checked={ 'yes' === debug_logging_enabled }
							onChange={ ( enabled ) => setSquareSettingData( { debug_logging_enabled: enabled ? 'yes' : 'no' } ) }
						/>
					</InputWrapper>

					<DebugMode />
				</div>
			</Section>
		</>
	)
};
