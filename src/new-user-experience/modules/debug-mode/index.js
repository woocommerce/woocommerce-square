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

	const options = [
		{
			label: __( 'Off', 'woocommerce-square' ),
			value: 'off',
			desc: __(
				'Disable all debug output. No errors will be shown or logged.',
				'woocommerce-square'
			),
		},
		{
			label: __(
				'Payment Errors — Show on Checkout Page',
				'woocommerce-square'
			),
			value: 'checkout',
			desc: __(
				'Display payment-related error messages directly on the checkout page.',
				'woocommerce-square'
			),
		},
		{
			label: __( 'Payment Errors — Save to Log', 'woocommerce-square' ),
			value: 'log',
			desc: __(
				'Log payment-related errors to the debug log. Errors are not shown on the checkout page.',
				'woocommerce-square'
			),
		},
		{
			label: __(
				'Payment Errors — Show on Checkout and Save to Log',
				'woocommerce-square'
			),
			value: 'both',
			desc: __(
				'Display payment-related errors on the checkout page and also save them to the debug log.',
				'woocommerce-square'
			),
		},
		{
			label: __(
				'Payment Errors — Show on Checkout, Non-Payment Errors — Save to Log',
				'woocommerce-square'
			),
			value: 'payment-show-and-non-payment-save-to-log',
			desc: __(
				'Show payment errors on the checkout page and log non-payment errors, such as API failures or sync issues in the debug log.',
				'woocommerce-square'
			),
		},
		{
			label: __(
				'(Payment + Non-payment) Errors — Save to Log',
				'woocommerce-square'
			),
			value: 'all-errors-save-to-log',
			desc: __(
				'Log all types of errors (payment and non-payment) to the debug log. No errors are shown on the checkout page.',
				'woocommerce-square'
			),
		},
		{
			label: __(
				'Non-Payment Errors — Save to Log',
				'woocommerce-square'
			),
			value: 'non-payment-save-to-log',
			desc: __(
				'Save non-payment errors, such as API failures or sync issues to log. These are not shown on the checkout page.',
				'woocommerce-square'
			),
		},
	];

	return (
		<InputWrapper
			label={ __( 'Debug Mode', 'woocommerce-square' ) }
			description={
				options.find( ( o ) => o.value === debug_mode )?.desc
			}
		>
			<SelectControl
				value={ debug_mode }
				onChange={ ( value ) =>
					setSquareSettingData( { debug_mode: value } )
				}
				options={ options }
			/>
		</InputWrapper>
	);
};
