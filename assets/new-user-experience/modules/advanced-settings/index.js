/**
 * External dependencies.
 */
import { __, sprintf } from '@wordpress/i18n';
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

import { useSquareSettings } from '../../settings/hooks';

export const AdvancedSettings = () => {
	const {
		settings,
		squareSettingsLoaded,
		setSquareSettingData,
	} = useSquareSettings( true );

	const {
		debug_logging_enabled = 'no',
	} = settings;

	if ( ! squareSettingsLoaded ) {
		return null;
	}

	return (
		<>
			<Section>
				<SectionTitle title={ __( 'Advanced Settings', 'woocommerce-square' ) } />
				<SectionDescription>
					{ __( 'Adjust these options to provide your customers with additional clarity and troubleshoot any issues more effectively', 'woocommerce-square' ) }
				</SectionDescription>

                <div className='woo-square-wizard__fields'>
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
                </div>
			</Section>
		</>
	)
};
