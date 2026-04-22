/**
 * External dependencies.
 */
import { __ } from '@wordpress/i18n';
import { useMemo } from '@wordpress/element';
import { ToggleControl } from '@wordpress/components';
import { DataForm } from '@wordpress/dataviews/wp';

/**
 * Credit card gateway enable/disable using DataForm, backed by the same
 * payment gateway settings store and POST /wc/v3/wc_square/payment_settings
 * flow as the rest of the credit card UI.
 *
 * @param {Object}   props
 * @param {string}   props.enabled        Gateway `enabled` flag (`yes` | `no`).
 * @param {Function} props.setCreditCardData Partial updater from `usePaymentGatewaySettings`.
 */
export const CreditCardEnabledDataForm = ( { enabled, setCreditCardData } ) => {
	const data = useMemo(
		() => ( {
			id: 'woocommerce-square-credit-card',
			enabled: enabled === 'yes',
		} ),
		[ enabled ]
	);

	const fields = useMemo(
		() => [
			{
				id: 'enabled',
				type: 'integer',
				label: __( 'Enable / Disable', 'woocommerce-square' ),
				Edit: ( { data: itemData, field, onChange } ) => {
					const { id, getValue } = field;
					const toggleLabel = __(
						'Enable this payment method.',
						'woocommerce-square'
					);
					return (
						<div data-testid="credit-card-gateway-toggle-field">
							<ToggleControl
								__nextHasNoMarginBottom
								label={ toggleLabel }
								checked={ getValue( { item: itemData } ) }
								onChange={ ( checked ) =>
									onChange( { [ id ]: checked } )
								}
							/>
						</div>
					);
				},
			},
		],
		[]
	);

	const form = useMemo(
		() => ( {
			type: 'regular',
			labelPosition: 'side',
			fields: [ 'enabled' ],
		} ),
		[]
	);

	return (
		<div className="woo-square-credit-card-enabled-dataform">
			<DataForm
				data={ data }
				fields={ fields }
				form={ form }
				onChange={ ( edits ) => {
					if ( typeof edits.enabled === 'boolean' ) {
						setCreditCardData( {
							enabled: edits.enabled ? 'yes' : 'no',
						} );
					}
				} }
			/>
		</div>
	);
};
