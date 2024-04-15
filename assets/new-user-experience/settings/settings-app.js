/**
 * External dependencies.
 */
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';
import { useDispatch } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';
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
} from '../components';

import { useSettingsForm } from './hooks';
import { saveSquareSettings, connectToSquare, filterBusinessLocations } from '../utils';

export const SettingsApp = () => {
	const [ settingsData, setSettingsData ] = useState( null );
	const [ formState, setFieldValue ] = useSettingsForm( settingsData );
	const [ saveInProgress, setSaveInProgress ] = useState( null );
	const { createSuccessNotice } = useDispatch( 'core/notices' );

	const settingsWrapperStyle = {
		width: '100%',
		maxWidth: '780px',
		marginTop: '50px',
		marginLeft: '50px',
	};

	/**
	 * Initializes square settings state.
	 */
	useEffect( () => {
		apiFetch( { path: '/wc/v3/wc_square/settings' } ).then( ( settings ) => {
			setSettingsData( {
				...settings,
				locations: filterBusinessLocations( settings.locations ),
			} );
		} );
	}, [] );

	useEffect( () => {
		if ( false === saveInProgress ) {
			apiFetch( { path: '/wc/v3/wc_square/settings' } ).then( ( settings ) => {
				setSettingsData( {
					...settings,
					locations: filterBusinessLocations( settings.locations ),
				} );
			} );
		}
	}, [ saveInProgress ] );

	useEffect( () => {
		if ( null === settingsData ) {
			return;
		}

		setFieldValue( settingsData );
	}, [ settingsData ] );

	const {
		enable_sandbox = 'yes',
		sandbox_application_id = '',
		sandbox_token = '',
		debug_logging_enabled = 'no',
		sandbox_location_id = '',
		system_of_record = 'disabled',
		enable_inventory_sync = 'no',
		override_product_images = 'no',
		hide_missing_products = 'no',
		sync_interval = '0.25',
		is_connected = false,
		disconnection_url = '',
		locations = [],
	} = formState;

	const sync_interval_options = [
		{
			label: __( '15 minutes', 'woocommerce-square' ),
			value: '0.25',
		},
		{
			label: __( '30 minutes', 'woocommerce-square' ),
			value: '0.5',
		},
		{
			label: __( '45 minutes', 'woocommerce-square' ),
			value: '0.75',
		},
		{
			label: __( '1 hour', 'woocommerce-square' ),
			value: '1',
		},
		{
			label: __( '2 hours', 'woocommerce-square' ),
			value: '2',
		},
		{
			label: __( '3 hours', 'woocommerce-square' ),
			value: '3',
		},
		{
			label: __( '6 hours', 'woocommerce-square' ),
			value: '6',
		},
		{
			label: __( '8 hours', 'woocommerce-square' ),
			value: '8',
		},
		{
			label: __( '12 hours', 'woocommerce-square' ),
			value: '12',
		},
		{
			label: __( '24 hours', 'woocommerce-square' ),
			value: '24',
		},
	];

	const initiateConnection = async () => {
		setSaveInProgress( true );
		const settings = await saveSquareSettings( formState );
		setSaveInProgress( false );

		if ( ! settings?.success ) {
			return;
		}

		createSuccessNotice( __( 'Settings saved!', 'woocommerce-square' ), {
			type: 'snackbar',
		} );

		const businessLocations = await connectToSquare();

		if ( businessLocations.success ) {
			const filteredBusinessLocations = filterBusinessLocations( businessLocations.data );
			setFieldValue( { locations: filteredBusinessLocations } );
			setFieldValue( { is_connected: true } );
		}
		setSaveInProgress( null );
	};

	if ( ! settingsData ) {
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
						onChange={ ( enable_sandbox ) => setFieldValue( { enable_sandbox: enable_sandbox ? 'yes' : 'no' } ) }
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
								onChange={ ( sandbox_application_id ) => setFieldValue( { sandbox_application_id } ) }
							/>
						</InputWrapper>

						<InputWrapper
							label={ __( 'Sandbox Access Token', 'woocommerce-square' ) }
							description={ __( 'Access Token for the Sandbox Test Account, see the details in the Sandbox Test Account section. Make sure you use the correct Sandbox Access Token for your application. For a given Sandbox Test Account, each Authorized Application is assigned a different Access Token.', 'woocommerce-square' ) }
							indent={ 2 }
						>
							<TextControl
								value={ sandbox_token }
								onChange={ ( sandbox_token ) => setFieldValue( { sandbox_token } ) }
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
						isBusy={ saveInProgress }
						disabled={ saveInProgress }
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
						onChange={ ( sandbox_location_id ) => setFieldValue( { sandbox_location_id } ) }
						options={ [
							{ label: __( 'Please choose a location', 'woocommerce-square' ), value: '' },
							...locations
						] }
					/>
				</InputWrapper>
			</Section> ) }

			{ is_connected && ( <Section>
				<SectionTitle title={ __( 'Configure Sync Settings', 'woocommerce-square' ) } />
				<SectionDescription>
					{ __( 'Choose how you want your product data to flow between WooCommerce and Square to keep your inventory and listings perfectly aligned. Select from the options below to best match your business operations:', 'woocommerce-square' ) }
				</SectionDescription>

				<InputWrapper
					label={ __( 'Sync Settings', 'woocommerce-square' ) }
					description={
						parse(
							sprintf(
								__( "Choose where data will be updated for synced products. Inventory in Square is always checked for adjustments when sync is enabled. %1$sLearn more%2$s about choosing a system of record or %3$screate a ticket%4$s if you're experiencing technical issues.", 'woocommerce-square' ),
								'<a href="https://woocommerce.com/document/woocommerce-square/#section-8" target="_blank">',
								'</a>',
								'<a href="https://wordpress.org/support/plugin/woocommerce-square/" target="_blank">',
								'</a>',
							)
						)
					}
				>
					<SelectControl
						value={ system_of_record }
						onChange={ ( system_of_record ) => setFieldValue( { system_of_record } ) }
						options={ [
							{
								label: __( 'Disabled', 'woocommerce-square' ),
								value: 'disabled',
							},
							{
								label: __( 'Square', 'woocommerce-square' ),
								value: 'square',
							},
							{
								label: __( 'WooCommerce', 'woocommerce-square' ),
								value: 'woocommerce',
							},
						] }
					/>
				</InputWrapper>

				{
					'woocommerce' === system_of_record && (
						<InputWrapper
							label={ __( 'Sync Settings', 'woocommerce-square' ) }
							indent={ 2 }
							description={
								parse(
									sprintf(
										__( 'Inventory is %1$salways fetched from Square%2$s periodically to account for sales from other channels.', 'woocommerce-square' ),
										'<strong>',
										'</strong>'
									)
								)
							}
						>
							<SquareCheckboxControl
								checked={ 'yes' === enable_inventory_sync }
								onChange={ ( enable_inventory_sync ) => setFieldValue( { enable_inventory_sync: enable_inventory_sync ? 'yes' : 'no' } ) }
								label={ __( 'Enable to push inventory changes to Square', 'woocommerce-square' ) }
							/>
						</InputWrapper>
					)
				}

				{
					'square' === system_of_record && (
						<>
							<InputWrapper
								label={ __( 'Sync Settings', 'woocommerce-square' ) }
								indent={ 2 }
								description={ __( 'Inventory is fetched from Square periodically and updated in WooCommerce.', 'woocommerce-square' ) }
							>
								<SquareCheckboxControl
									checked={ 'yes' === enable_inventory_sync }
									onChange={ ( enable_inventory_sync ) => setFieldValue( { enable_inventory_sync: enable_inventory_sync ? 'yes' : 'no' } ) }
									label={ __( 'Enable to fetch inventory changes from Square', 'woocommerce-square' ) }
								/>
							</InputWrapper>

							<InputWrapper
								label={ __( 'Override product images', 'woocommerce-square' ) }
								indent={ 2 }
								description={ __( 'Product images that have been updated in Square will also be updated within WooCommerce during a sync.', 'woocommerce-square' ) }
							>
								<SquareCheckboxControl
									checked={ 'yes' === override_product_images }
									onChange={ ( override_product_images ) => setFieldValue( { override_product_images: override_product_images ? 'yes' : 'no' } ) }
									label={ __( 'Enable to override Product images from Square', 'woocommerce-square' ) }
								/>
							</InputWrapper>

							<InputWrapper
								label={ __( 'Handle missing products', 'woocommerce-square' ) }
								indent={ 2 }
								description={ __( 'Products not found in Square will be hidden in the WooCommerce product catalog.', 'woocommerce-square' ) }
							>
								<SquareCheckboxControl
									checked={ 'yes' === hide_missing_products }
									onChange={ ( hide_missing_products ) => setFieldValue( { hide_missing_products: hide_missing_products ? 'yes' : 'no' } ) }
									label={ __( 'Hide synced products when not found in Square', 'woocommerce-square' ) }
								/>
							</InputWrapper>
						</>
					)
				}

				{
					( 'woocommerce' === system_of_record || 'square' === system_of_record ) && (
						<InputWrapper
							label={ __( 'Sync interval', 'woocommerce-square' ) }
							description={ __( 'Frequency for how regularly WooCommerce will sync products with Square.', 'woocommerce-square' ) }
							indent={ 2 }
						>
							<SelectControl
								value={ sync_interval }
								options={ sync_interval_options }
								onChange={ ( sync_interval ) => setFieldValue( { sync_interval } ) }
							/>
						</InputWrapper>
					)
				}
			</Section> ) }

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
						onChange={ ( debug_logging_enabled ) => setFieldValue( { debug_logging_enabled: debug_logging_enabled ? 'yes' : 'no' } ) }
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

			<Button
				variant='primary'
				onClick={ () => {
					setSaveInProgress( true );
					saveSquareSettings( formState )
					setSaveInProgress( false );
					createSuccessNotice( __( 'Settings saved!', 'woocommerce-square' ), {
						type: 'snackbar',
					} );
				} }
				isBusy={ saveInProgress }
			>
				{ __( 'Save Changes', 'woocommerce-square' ) }
			</Button>
		</div>
	)
};
