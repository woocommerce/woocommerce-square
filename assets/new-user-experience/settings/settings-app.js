/**
 * External dependencies.
 */
import { __, sprintf } from '@wordpress/i18n';
import { useEffect } from '@wordpress/element';
import parse from 'html-react-parser';
import {
	SelectControl,
	Button,
} from '@wordpress/components';

/**
 * Internal dependencies.
 */
import {
	Section,
	SectionTitle,
	SectionDescription,
	InputWrapper,
	SquareSettingsSaveButton,
	Loader,
} from '../components';
import {
	ConfigureSync,
	AdvancedSettings,
	SandboxSettings,
} from '../modules';
import { useSquareSettings } from './hooks';
import { connectToSquare, filterBusinessLocations } from '../utils';

export const SettingsApp = () => {
	const {
		settings,
		isSquareSettingsSaving,
		squareSettingsLoaded,
		setSquareSettingData,
		saveSquareSettings,
	} = useSquareSettings( true );

	const {
		enable_sandbox = 'yes',
		sandbox_location_id = '',
		is_connected = false,
		connection_url = '',
		disconnection_url = '',
		locations = [],
	} = settings;

	const initiateConnection = async () => {
		let response = await saveSquareSettings();

		if ( 'yes' !== enable_sandbox ) {
			window.location.assign( connection_url );
			return;
		}

		if ( ! response?.success ) {
			return;
		}

		const businessLocations = await connectToSquare();

		if ( businessLocations.success ) {
			const filteredBusinessLocations = filterBusinessLocations( businessLocations.data );
			setSquareSettingData( { locations: filteredBusinessLocations } );
			setSquareSettingData( { is_connected: true } );
		}
	};

	if ( ! squareSettingsLoaded ) {
		return <Loader />;
	}

	return (
		<>
			<SandboxSettings indent={ 2 } />

			<InputWrapper
					label={ __( 'Connection', 'woocommerce-square' ) }
					variant="boxed"
					className="square-settings__connection"
				>
					<Button
						data-testid="connect-to-square-button"
						variant='primary'
						{ ...( is_connected && { href: disconnection_url } ) }
						onClick={ () => initiateConnection() }
						isBusy={ isSquareSettingsSaving }
						disabled={ isSquareSettingsSaving }
					>
						{
							is_connected
							? __( 'Disconnect from Square', 'woocommerce-square' )
							: __( 'Connect to Square', 'woocommerce-square' )
						}
					</Button>
			</InputWrapper>

			{ is_connected && ( <Section>
				<SectionTitle title={ __( 'Select your business location', 'woocommerce-square' ) } />
				<SectionDescription>
					{ parse(
						sprintf(
							__( 'Please select the location you wish to link with this WooCommerce store. Only active %1$slocations%2$s that support credit card processing in Square can be linked.' ),
							'<a target="_blank" href="https://docs.woocommerce.com/document/woocommerce-square/#section-4">',
							'</a>'
						)
					) }
				</SectionDescription>

				<InputWrapper
					label={ __( 'Business location', 'woocommerce-square' ) }
				>
					<SelectControl
						data-testid="business-location-field"
						value={ sandbox_location_id }
						onChange={ ( sandbox_location_id ) => setSquareSettingData( { sandbox_location_id } ) }
						options={ [
							{ label: __( 'Please choose a location', 'woocommerce-square' ), value: '' },
							...locations
						] }
					/>
				</InputWrapper>
			</Section> ) }

			{ is_connected && <ConfigureSync indent={ 2 } /> }

			<AdvancedSettings />

			<SquareSettingsSaveButton
				label={ __( 'Save changes', 'woocommerce-square' ) }
				afterSaveLabel={ __( 'Changes Saved', 'woocommerce-square' ) }
			/>
		</>
	)
};
