/**
 * External dependencies.
 */
import { __ } from '@wordpress/i18n';
import { useMemo, useState } from '@wordpress/element';
import { DataForm } from '@wordpress/dataviews/wp';

/**
 * Demo: one field rendered with WordPress DataForm (DataViews stack).
 * Local state only until wired to REST and the gateway save flow.
 */
export const PaymentGatewayModernDataFormDemo = () => {
	const [ data, setData ] = useState( {
		id: 'square-payment-settings-demo',
		wc_square_modern_settings_demo_note: '',
	} );

	const fields = useMemo(
		() => [
			{
				id: 'wc_square_modern_settings_demo_note',
				type: 'text',
				label: __(
					'Modern settings (DataForm) demo note',
					'woocommerce-square'
				),
				description: __(
					'This field uses WordPress DataForm from @wordpress/dataviews. It is not persisted until hooked to your REST API and save flow.',
					'woocommerce-square'
				),
			},
		],
		[]
	);

	const form = useMemo(
		() => ( {
			layout: {
				type: 'regular',
				labelPosition: 'top',
			},
			fields: [ 'wc_square_modern_settings_demo_note' ],
		} ),
		[]
	);

	return (
		<DataForm
			data={ data }
			fields={ fields }
			form={ form }
			onChange={ ( edits ) => {
				setData( ( prev ) => ( { ...prev, ...edits } ) );
			} }
		/>
	);
};
