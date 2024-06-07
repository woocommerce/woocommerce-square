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
import { useSteps } from '../../hooks';

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

	const locationsList = [
		{ label: __('Please choose a location', 'woocommerce-square'), value: '' },
		...locations
	]

	if ( 1 === locationCount) {
		// Remove the label, to make the only location selected.
		locationsList.shift();
		
		// Set the first location value in data.
		const first_location_id = locations[0].value;
		setSquareSettingData( { sandbox_location_id: first_location_id } )
	}

	const noLocation = (
		<>
			<Section>
				<SectionTitle title={ __( 'Your Square account is missing a Business Location', 'woocommerce-square' ) } />
				<SectionDescription>
					<p dangerouslySetInnerHTML={ {
						__html: sprintf(
							__( 'Please %1$sgo here%2$s or use the button below to create a Business Location and then return to WooCommerce to complete setup.', 'woocommerce-square' ),
							'<a href="https://squareup.com/dashboard/locations/" target="_blank">',
							'</a>'
						)
					} } />
				</SectionDescription>
			</Section>
			<Button
				variant="button-primary"
				className="button-primary"
				onClick={ () => window.open( 'https://squareup.com/dashboard/locations/', '_blank' ) }
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
									options={ locationsList }
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
