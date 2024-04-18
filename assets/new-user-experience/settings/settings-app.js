/**
 * External dependencies.
 */
import { useEffect, useRef } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { useDispatch } from '@wordpress/data';
import parse from 'html-react-parser';
import {
	TextControl,
	ToggleControl,
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
	SquareCheckboxControl,
	SquareSettingsSaveButton,
} from '../components';
import { ConfigureSync } from '../modules';
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
		enable_sandbox = 'yes',
		sandbox_application_id = '',
		sandbox_token = '',
		debug_logging_enabled = 'no',
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
			<Section>
				<SectionTitle title={ __( 'Configure Sandbox Settings', 'woocommerce-square' ) } />
				<SectionDescription>
					{ __( 'Activate Sandbox Mode to safely simulate transactions and sync operations, ensuring your WooCommerce/Square integration functions seamlessly. Experiment with payment methods and product data syncing in a risk-free environment before going live with your store.', 'woocommerce-square' ) }
				</SectionDescription>

				<InputWrapper
					label={ __( 'Enable Sandbox Mode', 'woocommerce-square' ) }
					description={ __( "After enabling you'll see a new Sandbox settings section with two fields; Sandbox Application ID & Sandbox Access Token.", 'woocommerce-square' ) }
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
							description={ __( 'Application ID for the Sandbox Application, see the details in the My Applications section.', 'woocommerce-square' ) }
							indent={ 2 }
						>
							<TextControl
								value={ sandbox_application_id }
								onChange={ ( sandbox_application_id ) => setSquareSettingData( { sandbox_application_id } ) }
							/>
						</InputWrapper>

						<InputWrapper
							label={ __( 'Sandbox Access Token', 'woocommerce-square' ) }
							description={ __( 'Access Token for the Sandbox Test Account, see the details in the Sandbox Test Account section. Make sure you use the correct Sandbox Access Token for your application. For a given Sandbox Test Account, each Authorized Application is assigned a different Access Token.', 'woocommerce-square' ) }
							indent={ 2 }
						>
							<TextControl
								value={ sandbox_token }
								onChange={ ( sandbox_token ) => setSquareSettingData( { sandbox_token } ) }
							/>
						</InputWrapper>
					</>
				) }

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
			</Section>

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

			<Section>
				<SectionTitle title={ __( 'Advanced Settings', 'woocommerce-square' ) } />
				<SectionDescription>
					{ __( 'Adjust these options to provide your customers with additional clarity and troubleshoot any issues more effectively', 'woocommerce-square' ) }
				</SectionDescription>

				<InputWrapper
					label={ __( 'Detailed Decline Messages', 'woocommerce-square' ) }
				>
					<SquareCheckboxControl
						checked={ 'yes' === debug_logging_enabled }
						onChange={ ( debug_logging_enabled ) => setSquareSettingData( { debug_logging_enabled: debug_logging_enabled ? 'yes' : 'no' } ) }
						label={
							parse(
								sprintf(
									__( 'Log debug messages to the %1$sWooCommerce status log%2$s', 'woocommerce-square' ),
									'<a target="_blank" href="https://wcsquare.mylocal/wp-admin/admin.php?page=wc-status&tab=logs">',
									'</a>',
								)
							)
						}
					/>
				</InputWrapper>
			</Section>

			<SquareSettingsSaveButton label={ __( 'Save changes', 'woocommerce-square' ) } />
		</div>
	)
};
