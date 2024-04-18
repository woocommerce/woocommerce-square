/**
 * External dependencies.
 */
import { __, sprintf } from '@wordpress/i18n';
import parse from 'html-react-parser';
import {
	SelectControl,
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
} from '../../components';

import { useSquareSettings } from '../../settings/hooks';

export const ConfigureSync = ( { indent = 0 }) => {
	const {
		settings,
		squareSettingsLoaded,
		setSquareSettingData,
	} = useSquareSettings( true );


	const {
		system_of_record = 'disabled',
		enable_inventory_sync = 'no',
		override_product_images = 'no',
		hide_missing_products = 'no',
		sync_interval = '0.25',
		is_connected = false,
	} = settings;

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

	if ( ! squareSettingsLoaded ) {
		return null;
	}

	return (
		<>
			{ is_connected && ( <Section>
				<SectionTitle title={ __( 'Configure Sync Settings', 'woocommerce-square' ) } />
				<SectionDescription>
					{ __( 'Choose how you want your product data to flow between WooCommerce and Square to keep your inventory and listings perfectly aligned. Select from the options below to best match your business operations:', 'woocommerce-square' ) }
				</SectionDescription>

                <div className='woo-square-wizard__fields'>
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
                            onChange={ ( system_of_record ) => setSquareSettingData( { system_of_record } ) }
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
                                label={ __( 'Sync Inventory2', 'woocommerce-square' ) }
                                indent = { indent }
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
                                    onChange={ ( enable_inventory_sync ) => setSquareSettingData( { enable_inventory_sync: enable_inventory_sync ? 'yes' : 'no' } ) }
                                    label={ __( 'Enable to push inventory changes to Square', 'woocommerce-square' ) }
                                />
                            </InputWrapper>
                        )
                    }

                    {
                        'square' === system_of_record && (
                            <>
                                <InputWrapper
                                    label={ __( 'Sync Inventory', 'woocommerce-square' ) }
                                    indent = { indent }
                                    description={ __( 'Inventory is fetched from Square periodically and updated in WooCommerce.', 'woocommerce-square' ) }
                                >
                                    <SquareCheckboxControl
                                        checked={ 'yes' === enable_inventory_sync }
                                        onChange={ ( enable_inventory_sync ) => setSquareSettingData( { enable_inventory_sync: enable_inventory_sync ? 'yes' : 'no' } ) }
                                        label={ __( 'Enable to fetch inventory changes from Square', 'woocommerce-square' ) }
                                    />
                                </InputWrapper>

                                <InputWrapper
                                    label={ __( 'Override product images', 'woocommerce-square' ) }
                                    indent = { indent }
                                    description={ __( 'Product images that have been updated in Square will also be updated within WooCommerce during a sync.', 'woocommerce-square' ) }
                                >
                                    <SquareCheckboxControl
                                        checked={ 'yes' === override_product_images }
                                        onChange={ ( override_product_images ) => setSquareSettingData( { override_product_images: override_product_images ? 'yes' : 'no' } ) }
                                        label={ __( 'Enable to override Product images from Square', 'woocommerce-square' ) }
                                    />
                                </InputWrapper>

                                <InputWrapper
                                    label={ __( 'Handle missing products', 'woocommerce-square' ) }
                                    indent = { indent }
                                    description={ __( 'Products not found in Square will be hidden in the WooCommerce product catalog.', 'woocommerce-square' ) }
                                >
                                    <SquareCheckboxControl
                                        checked={ 'yes' === hide_missing_products }
                                        onChange={ ( hide_missing_products ) => setSquareSettingData( { hide_missing_products: hide_missing_products ? 'yes' : 'no' } ) }
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
                                indent = { indent }
                            >
                                <SelectControl
                                    value={ sync_interval }
                                    options={ sync_interval_options }
                                    onChange={ ( sync_interval ) => setSquareSettingData( { sync_interval } ) }
                                />
                            </InputWrapper>
                        )
                    }
                </div>
			</Section> ) }
		</>
	)
};
