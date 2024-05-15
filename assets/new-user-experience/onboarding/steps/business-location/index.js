import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	SelectControl,
} from '@wordpress/components';

import {
	Section,
	SectionTitle,
	SectionDescription,
	InputWrapper,
} from '../../../components';
import { useSquareSettings } from '../../../settings/hooks';

export const BusinessLocation = () => {
	const {
		settings,
		squareSettingsLoaded,
		setSquareSettingData,
	} = useSquareSettings();

	const {
		sandbox_location_id,
		locations
	} = settings;

	if ( ! squareSettingsLoaded ) {
		return null;
	}

	// Check if no locations found.
	const locationCount = locations.length;
	const buttonText = 1 === locationCount ? __( 'Confirm', 'woocommerce-square' ) : __( 'Next', 'woocommerce-square' );

	const noLocation = (
		<>
			<Section>
				<SectionTitle title={ __( 'Your Square account is missing a Business Location', 'woocommerce-square' ) } />
				<SectionDescription>
					<p>
						{
							sprintf(
								__( 'Please %1$sgo here%2$s or use the button below to create a Business Location and then return to WooCommerce to complete setup.', 'woocommerce-square' ),
								'<a href="https://squareup.com/dashboard/locations" target="_blank">',
								'</a>'
							)
						}
					</p>
				</SectionDescription>
			</Section>
			<Button
				variant="primary"
				onClick={ () => window.open( 'https://squareup.com/', '_blank' ) }
			>
				{ __( 'Create a Business Location', 'woocommerce-square' ) }
			</Button>
		</>
	);

	const intro = 1 === locationCount ? (
		<>
			<SectionTitle title={ __( 'Confirm Your Business Location', 'woocommerce-square' ) } />
			<SectionDescription>
				<p>
					{ __( 'Great, you\'re nearly there! We\'ve detected your business location as listed in Square.', 'woocommerce-square' ) }
				</p>
				<p>
					{ __( 'Please confirm that this is the correct location where you\'ll be making sales:', 'woocommerce-square' ) }
				</p>
			</SectionDescription>
		</>
	) : (
		<>
			<SectionTitle title={ __( 'Select your business location', 'woocommerce-square' ) } />
			<SectionDescription>
				<p>
					{ __( 'You\'re on your way!  It looks like you have multiple business locations associated with your Square account.', 'woocommerce-square' ) }
				</p>
				<p>
					{ __( 'Please select the location you wish to link with this WooCommerce store', 'woocommerce-square' ) }
				</p>
			</SectionDescription>
		</>
	);

	return (
		<div>
			<Section>
				{ ( locationCount === 0 && noLocation ) ||
					( locationCount && (
					<>
						{intro}
						<div className='woo-square-wizard__fields'>
							<InputWrapper label={ __( 'Business Location:', 'woocommerce-square' ) }>
								<SelectControl
								data-testid="business-location-field"
								value={ sandbox_location_id }
								onChange={ ( sandbox_location_id ) =>
									setSquareSettingData( { sandbox_location_id } )
								}
								options={[
									{ label: __('Please choose a location', 'woocommerce-square'), value: '' },
									...locations
								]}
								/>
							</InputWrapper>
						</div>
					</>
					) )
				}
			</Section>
		</div>
	);
};
