/**
 * Sandbox Application ID + Access Token using DataForm (@wordpress/dataviews).
 */
import { __, sprintf } from '@wordpress/i18n';
import { useMemo } from '@wordpress/element';
import parse from 'html-react-parser';
import { TextControl } from '@wordpress/components';
import { DataForm } from '@wordpress/dataviews/wp';

import { useSquareSettings } from './hooks';

export const SandboxCredentialsDataForm = () => {
	const { settings, setSquareSettingData, squareSettingsLoaded } =
		useSquareSettings( true );

	const {
		sandbox_application_id = '',
		sandbox_token = '',
	} = settings;

	const data = useMemo(
		() => ( {
			id: 'wc-square-sandbox-credentials',
			sandbox_application_id,
			sandbox_token,
		} ),
		[ sandbox_application_id, sandbox_token ]
	);

	const fields = useMemo(
		() => [
			{
				id: 'sandbox_application_id',
				label: __( 'Sandbox Application ID', 'woocommerce-square' ),
				getValue: ( { item } ) => item.sandbox_application_id ?? '',
				Edit: ( { data: itemData, field, onChange } ) => {
					const { id, getValue, label } = field;
					return (
						<div data-testid="sandbox-application-id-field">
							<TextControl
								__nextHasNoMarginBottom
								label={ label }
								help={
									<span className="wc-square-general-settings__field-help">
										{ parse(
											sprintf(
												/* translators: %1$s: opening anchor tag, %2$s: closing anchor tag */
												__(
													'Application ID for the Sandbox Application, see the details in the %1$sMy Applications%2$s section.',
													'woocommerce-square'
												),
												'<a target="_blank" rel="noreferrer noopener" href="https://developer.squareup.com/console/en/apps">',
												'</a>'
											)
										) }
									</span>
								}
								required
								value={ getValue( { item: itemData } ) }
								onChange={ ( value ) =>
									onChange( { [ id ]: value } )
								}
							/>
						</div>
					);
				},
			},
			{
				id: 'sandbox_token',
				label: __( 'Sandbox Access Token', 'woocommerce-square' ),
				getValue: ( { item } ) => item.sandbox_token ?? '',
				Edit: ( { data: itemData, field, onChange } ) => {
					const { id, getValue, label } = field;
					return (
						<div data-testid="sandbox-token-field">
							<TextControl
								__nextHasNoMarginBottom
								label={ label }
								help={
									<span className="wc-square-general-settings__field-help">
										{ parse(
											sprintf(
												/* translators: %1$s: opening anchor tag, %2$s: closing anchor tag */
												__(
													'Access Token for the Sandbox Test Account, see the details in the %1$sSandbox Test Account%2$s section. Make sure you use the correct Sandbox Access Token for your application. For a given Sandbox Test Account, each Authorized Application is assigned a different Access Token.',
													'woocommerce-square'
												),
												'<a target="_blank" rel="noreferrer noopener" href="https://developer.squareup.com/console/en/sandbox-test-accounts">',
												'</a>'
											)
										) }
									</span>
								}
								required
								value={ getValue( { item: itemData } ) }
								onChange={ ( value ) =>
									onChange( { [ id ]: value } )
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
			labelPosition: 'top',
			fields: [ 'sandbox_application_id', 'sandbox_token' ],
		} ),
		[]
	);

	if ( ! squareSettingsLoaded ) {
		return null;
	}

	return (
		<div className="woo-square-sandbox-credentials-dataform wc-square-general-dataform">
			<DataForm
				data={ data }
				fields={ fields }
				form={ form }
				onChange={ ( edits ) => {
					const next = {};
					if ( edits.sandbox_application_id !== undefined ) {
						next.sandbox_application_id =
							edits.sandbox_application_id;
					}
					if ( edits.sandbox_token !== undefined ) {
						next.sandbox_token = edits.sandbox_token;
					}
					if ( Object.keys( next ).length ) {
						setSquareSettingData( next );
					}
				} }
			/>
		</div>
	);
};
