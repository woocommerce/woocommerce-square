/**
 * General tab sections: connect (Figma radios + OAuth) and location (DataForm).
 */
import { __ } from '@wordpress/i18n';
import { useMemo } from '@wordpress/element';
import { SelectControl, Button } from '@wordpress/components';
import { Icon, external } from '@wordpress/icons';
import { DataForm } from '@wordpress/dataviews/wp';

/**
 * Environment radios + divider + Connect (production) — Figma layout.
 *
 * @param {Object}   props
 * @param {Object}   props.settings
 * @param {Function} props.setSquareSettingData
 * @param {*}        props.isSquareSettingsSaving
 * @param {Function} props.connectToProd
 * @param {import('react').MutableRefObject<boolean|null>} props.isProd
 */
export const GeneralConnectSection = ( {
	settings,
	setSquareSettingData,
	isSquareSettingsSaving,
	connectToProd,
	isProd,
} ) => {
	const { enable_sandbox = 'no', access_tokens = [] } = settings;

	const env = enable_sandbox === 'yes' ? 'yes' : 'no';

	return (
		<div className="wc-square-general-connect">
			<fieldset className="wc-square-general-connect__environment">
				<legend className="wc-square-general-connect__legend">
					{ __( 'Environment Selection', 'woocommerce-square' ) }
				</legend>
				<div
					className="wc-square-general-connect__radios"
					data-testid="environment-selection-field"
				>
					<label className="wc-square-general-connect__radio-label">
						<input
							type="radio"
							className="wc-square-general-connect__radio-input"
							name="wc_square_enable_sandbox"
							value="no"
							checked={ env === 'no' }
							onChange={ () =>
								setSquareSettingData( { enable_sandbox: 'no' } )
							}
						/>
						<span className="wc-square-general-connect__radio-body">
							<span className="wc-square-general-connect__radio-title">
								{ __( 'Production', 'woocommerce-square' ) }
							</span>
							<span className="wc-square-general-connect__radio-hint">
								{ __(
									'Connect to a live production account for real transactions.',
									'woocommerce-square'
								) }
							</span>
						</span>
					</label>
					<label className="wc-square-general-connect__radio-label">
						<input
							type="radio"
							className="wc-square-general-connect__radio-input"
							name="wc_square_enable_sandbox"
							value="yes"
							checked={ env === 'yes' }
							onChange={ () =>
								setSquareSettingData( { enable_sandbox: 'yes' } )
							}
						/>
						<span className="wc-square-general-connect__radio-body">
							<span className="wc-square-general-connect__radio-title">
								{ __( 'Sandbox', 'woocommerce-square' ) }
							</span>
							<span className="wc-square-general-connect__radio-hint">
								{ __(
									'Connect to a sandbox account for testing purposes.',
									'woocommerce-square'
								) }
							</span>
						</span>
					</label>
				</div>
			</fieldset>

			<hr className="wc-square-general-settings__divider" />

			{ env === 'no' && (
				<div
					className="wc-square-general-connect__actions"
					data-testid="square-connection-field"
				>
					<Button
						data-testid="connect-to-square-button"
						variant="button-primary"
						className="button-primary"
						onClick={ connectToProd }
						isBusy={ isSquareSettingsSaving }
						disabled={ ! wcSquareSettings.depsCheck }
					>
						{ isProd.current && access_tokens.production
							? __( 'Disconnect from Square', 'woocommerce-square' )
							: __( 'Connect to Square', 'woocommerce-square' ) }
					</Button>
				</div>
			) }
		</div>
	);
};

/** @deprecated Use GeneralConnectSection */
export const GeneralConnectDataForm = GeneralConnectSection;

/**
 * Business location — DataForm (shown when connected).
 *
 * @param {Object}   props
 * @param {Object}   props.settings
 * @param {Function} props.setSquareSettingData
 */
export const GeneralLocationDataForm = ( { settings, setSquareSettingData } ) => {
	const {
		enable_sandbox = 'no',
		sandbox_location_id = '',
		production_location_id = '',
		locations = [],
	} = settings;

	const locationId =
		enable_sandbox === 'yes' ? sandbox_location_id : production_location_id;

	const data = useMemo(
		() => ( {
			id: 'wc-square-general-location',
			location_id: locationId,
		} ),
		[ locationId ]
	);

	const fields = useMemo( () => {
		const options = [
			{
				label: __( 'Please choose a location', 'woocommerce-square' ),
				value: '',
			},
			...locations,
		];

		return [
			{
				id: 'location_id',
				label: __( 'Business location', 'woocommerce-square' ),
				getValue: ( { item } ) => item.location_id ?? '',
				Edit: ( { data: itemData, field, onChange } ) => {
					const { id, getValue, label } = field;
					return (
						<SelectControl
							__nextHasNoMarginBottom
							label={ label }
							data-testid="business-location-field"
							value={ getValue( { item: itemData } ) }
							onChange={ ( value ) =>
								onChange( { [ id ]: value } )
							}
							options={ options }
						/>
					);
				},
			},
		];
	}, [ locations ] );

	const form = useMemo(
		() => ( {
			type: 'regular',
			labelPosition: 'top',
			fields: [ 'location_id' ],
		} ),
		[]
	);

	return (
		<div className="wc-square-general-dataform wc-square-general-dataform--location">
			<DataForm
				data={ data }
				fields={ fields }
				form={ form }
				onChange={ ( edits ) => {
					if ( edits.location_id === undefined ) {
						return;
					}
					if ( enable_sandbox === 'yes' ) {
						setSquareSettingData( {
							sandbox_location_id: edits.location_id,
						} );
					} else {
						setSquareSettingData( {
							production_location_id: edits.location_id,
						} );
					}
				} }
			/>
		</div>
	);
};

/**
 * Intro copy for the location card (docs link — SQUARE-279).
 */
export const GeneralLocationIntro = () => (
	<p className="wc-square-general-settings__location-desc">
		{ __(
			'Choose which Square location to use for this store. Only locations that support card processing can be selected.',
			'woocommerce-square'
		) }{ ' ' }
		<a
			className="wc-square-general-settings__doc-link"
			href="https://woocommerce.com/document/woocommerce-square/sync-settings/#woocommerce-square-sync-general-settings"
			target="_blank"
			rel="noreferrer noopener"
		>
			{ __( 'Learn more about active locations', 'woocommerce-square' ) }
			<Icon
				icon={ external }
				size={ 16 }
				className="wc-square-general-settings__external-icon"
			/>
		</a>
	</p>
);
