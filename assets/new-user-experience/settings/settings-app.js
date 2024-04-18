/**
 * External dependencies.
 */
import { useEffect, useRef } from '@wordpress/element';
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
} from '../components';
import { ConfigureSync, AdvancedSettings, SandboxSettings } from '../modules';
import { useSquareSettings } from './hooks';
import { connectToSquare, filterBusinessLocations, getSquareSettings } from '../utils';

export const SettingsApp = () => {
	const {
		settings,
		isSquareSettingsSaving,
		squareSettingsLoaded,
		setSquareSettingData,
		setBusinessLocation,
		saveSquareSettings,
	} = useSquareSettings( true );

	const isFirstLoad = useRef( true );
	const { createSuccessNotice } = useDispatch( 'core/notices' );

	const settingsWrapperStyle = {
		width: '100%',
		maxWidth: '780px',
		marginTop: '50px',
		marginLeft: '50px',
	};

	useEffect( () => {
		if ( isFirstLoad.current ) {
			isFirstLoad.current = false;
			return;
		}
		if ( false === isSquareSettingsSaving ) {
			( async () => {
				const settings = await getSquareSettings();
				setBusinessLocation( settings.locations )
			} )()
		}
	}, [ isSquareSettingsSaving ] );

	const {
		sandbox_location_id = '',
		is_connected = false,
		disconnection_url = '',
		locations = [],
	} = settings;

	const initiateConnection = async () => {
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
		return null;
	}

	return (
		<div style={ settingsWrapperStyle }>
			<SandboxSettings />

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

			{ is_connected && <ConfigureSync indent={2} /> }

			<AdvancedSettings />

			<SquareSettingsSaveButton label={ __( 'Save changes', 'woocommerce-square' ) } />
		</div>
	)
};
