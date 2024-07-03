/**
 * External dependencies.
 */
import { SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { InputWrapper } from '../../components';
import { useSquareSettings } from '../../settings/hooks';

export const DebugMode = () => {
	const { settings, setSquareSettingData } = useSquareSettings();

	const { debug_mode } = settings;

	return (
		<InputWrapper label={ __( 'Debug Mode', 'woocommerce' ) }>
			<SelectControl
				value={ debug_mode }
				onChange={ ( value ) =>
					setSquareSettingData( { debug_mode: value } )
				}
				options={ [
					{
						label: __( 'Off', 'woocommerce' ),
						value: 'off',
					},
					{
						label: __( 'Show on Checkout Page', 'woocommerce' ),
						value: 'checkout',
					},
					{
						label: __( 'Save to Log', 'woocommerce' ),
						value: 'log',
					},
					{
						label: __( 'Both', 'woocommerce' ),
						value: 'both',
					},
				] }
			/>
		</InputWrapper>
	);
};
