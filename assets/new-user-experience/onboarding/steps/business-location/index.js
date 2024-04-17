import { __ } from '@wordpress/i18n';
import {
	SelectControl,
} from '@wordpress/components';

import {
	Section,
	SectionTitle,
	SectionDescription,
	InputWrapper,
	SquareSettingsSaveButton,
} from '../../../components';

import { useSquareSettings } from '../../../settings/hooks';

export const BusinessLocation = () => {
	const {
		settings,
		squareSettingsLoaded,
		setSquareSettingData,
	} = useSquareSettings( true );

	const {
		sandbox_location_id,
		locations
	} = settings;

	if ( ! squareSettingsLoaded ) {
		return null;
	}

	const intro = 1 === locations.length ? (
		<>
			<SectionTitle title={ __( 'Confirm Your Business Location', 'woocommerce-square' ) } />
			<SectionDescription>
				<p>
					{ __( "Great, you're nearly there! We've detected your business location as listed in Square.", 'woocommerce-square' ) }
				</p>
				<p>
					{ __( "Please confirm that this is the correct location where you'll be making sales", 'woocommerce-square' ) }
				</p>
			</SectionDescription>
		</>
	) : (
		<>
			<SectionTitle title={ __( 'Select your business location', 'woocommerce-square' ) } />
			<SectionDescription>
				<p>
					{ __( "You're on your way!  It looks like you have multiple business locations associated with your Square account.", 'woocommerce-square' ) }
				</p>
				<p>
					{ __( "Please select the location you wish to link with this WooCommerce store", 'woocommerce-square' ) }
				</p>
			</SectionDescription>
		</>
	);

	return (
		<div>
			<Section>
				{ intro }
				<InputWrapper>
					<SelectControl
						value={ sandbox_location_id }
						onChange={ ( sandbox_location_id ) => setSquareSettingData( { sandbox_location_id }) }
						options={ [
							{ label: __( 'Please choose a location', 'woocommerce-square' ), value: '' },
							...locations
						] }
					/>
				</InputWrapper>
				<SquareSettingsSaveButton label={ __( 'Apply changes', 'woocommerce-square' ) } />
			</Section>
		</div>
	);
};
