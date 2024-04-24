/**
 * External dependencies.
 */
import { __, sprintf } from '@wordpress/i18n';
import { useDispatch } from '@wordpress/data';
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
import { ConfigureSync, AdvancedSettings, SandboxSettings } from '../modules';
import { useSquareSettings } from './hooks';
import { connectToSquare, filterBusinessLocations } from '../utils';

export const SettingsApp = () => {
	const useSquareSettingsData = useSquareSettings( true );
	const {
		settings,
		isSquareSettingsSaving,
		squareSettingsLoaded,
		setSquareSettingData,
		saveSquareSettings,
	} = useSquareSettingsData;

	const { createSuccessNotice } = useDispatch( 'core/notices' );

	const settingsWrapperStyle = {
		width: '100%',
		maxWidth: '780px',
		marginTop: '50px',
		marginLeft: '50px',
	};

	const {
		enable_sandbox = 'yes',
		sandbox_location_id = '',
		is_connected = false,
		connection_url = '',
		disconnection_url = '',
		locations = [],
	} = settings;

	const initiateConnection = async () => {
		if ( 'yes' !== enable_sandbox ) {
			window.location.assign( connection_url );
			return;
		}

		const response = await saveSquareSettings();

		if ( ! response?.success ) {
			return;
		}

		createSuccessNotice( __( 'Settings saved!', 'woocommerce-square' ), {
			type: 'snackbar',
		} );

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
		<div style={ settingsWrapperStyle }>
			{ is_connected && ( <Section>
				<SectionTitle title={ __( 'Select your business location', 'woocommerce-square' ) } />
				<SectionDescription>
					{ parse(
						sprintf(
							__( 'Select a location to link to this site. Only active %1$slocations%2$s that support credit card processing in Square can be linked.' ),
							'<a target="_blank" href="https://docs.woocommerce.com/document/woocommerce-square/#section-4">',
							'</a>'
						)
					) }
				</SectionDescription>

				<InputWrapper
					label={ __( 'Business location', 'woocommerce-square' ) }
				>
					<SelectControl
						value={ sandbox_location_id }
						onChange={ ( sandbox_location_id ) => setSquareSettingData( { sandbox_location_id } ) }
						options={ [
							{ label: __( 'Please choose a location', 'woocommerce-square' ), value: '' },
							...locations
						] }
					/>
				</InputWrapper>
			</Section> ) }

			{ is_connected && <ConfigureSync indent={2} useSquareSettings={useSquareSettingsData} /> }

			<SandboxSettings useSquareSettings={useSquareSettingsData} />

			<InputWrapper
					label={ __( 'Connection', 'woocommerce-square' ) }
					variant="boxed"
				>
					<Button
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

			<AdvancedSettings useSquareSettings={useSquareSettingsData} />

			<SquareSettingsSaveButton label={ __( 'Save changes', 'woocommerce-square' ) } />
		</div>
	)
};
