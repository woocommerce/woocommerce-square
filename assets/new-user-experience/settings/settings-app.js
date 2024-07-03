/**
 * External dependencies.
 */
import { __, sprintf } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import parse from 'html-react-parser';
import { SelectControl, Button } from '@wordpress/components';

/**
 * Internal dependencies.
 */
import {
	Section,
	SectionTitle,
	SectionDescription,
	InputWrapper,
	SquareSettingsSaveButton,
	Loader,
} from '../components';
import { ConfigureSync, AdvancedSettings, SandboxSettings } from '../modules'; // eslint-disable-line import/named
import { useSquareSettings } from './hooks';

export const SettingsApp = () => {
	const {
		settings,
		isSquareSettingsSaving,
		squareSettingsLoaded,
		setSquareSettingData,
	} = useSquareSettings(true);

	const [initialState, setInitialState] = useState(false);
	const [isFormDirty, setIsFormDirty] = useState(false);
	const [envUpdated, setEnvUpdated] = useState(false);

	const {
		enable_sandbox = 'no',
		sandbox_location_id = '',
		production_location_id = '',
		is_connected = false,
		connection_url = '',
		disconnection_url = '',
		locations = [],
	} = settings;

	const _location_id =
		enable_sandbox === 'yes' ? sandbox_location_id : production_location_id;

	// Set the initial state.
	useEffect(() => {
		if (!squareSettingsLoaded) {
			return;
		}

		setInitialState(settings);
	}, [squareSettingsLoaded]);

	// We set the state for `isFormDirty` here.
	useEffect(() => {
		if (initialState === false) {
			return;
		}

		setIsFormDirty(
			!Object.keys(initialState).every(
				(key) => initialState[key] === settings[key]
			)
		);
	}, [settings]);

	// We disable the "Import products" button when the form is dirty
	// and re-enable it when we form is submitted / saved.
	useEffect(() => {
		if (isSquareSettingsSaving !== null) {
			return;
		}

		setInitialState(settings);
		setIsFormDirty(false);
	}, [isSquareSettingsSaving]);

	if (!squareSettingsLoaded) {
		return <Loader />;
	}

	return (
		<>
			<SectionTitle
				title={__('Connect to Square', 'woocommerce-square')}
			/>
			<SectionDescription>
				{__(
					'Activate Square integration to securely manage and process transactions for your WooCommerce store. Choose between connecting to a live production account for real transactions or a sandbox account for testing purposes. This setup ensures your payment processing is seamless, whether you are in a development stage or ready to go live.',
					'woocommerce-square'
				)}
			</SectionDescription>

			<InputWrapper
				label={__('Environment Selection', 'woocommerce-square')}
			>
				<SelectControl
					data-testid="environment-selection-field"
					required
					value={enable_sandbox}
					onChange={(value) => {
						setEnvUpdated(true);
						setSquareSettingData({ enable_sandbox: value });
					}}
					options={[
						{
							label: __(
								'Please choose an environment',
								'woocommerce-square'
							),
							value: '',
						},
						{
							label: __('Production', 'woocommerce-square'),
							value: 'no',
						},
						{
							label: __('Sandbox', 'woocommerce-square'),
							value: 'yes',
						},
					]}
				/>
			</InputWrapper>

			{enable_sandbox === 'yes' && <SandboxSettings showToggle={false} />}

			{enable_sandbox === 'no' && (
				<InputWrapper
					label={__('Connection', 'woocommerce-square')}
					variant="boxed"
					className="square-settings__connection"
				>
					<Button
						data-testid="connect-to-square-button"
						variant="button-primary"
						className="button-primary"
						href={
							is_connected && !envUpdated
								? disconnection_url
								: connection_url
						}
						isBusy={isSquareSettingsSaving}
						disabled={wcSquareSettings.depsCheck > 0}
					>
						{is_connected && !envUpdated
							? __('Disconnect from Square', 'woocommerce-square')
							: __('Connect to Square', 'woocommerce-square')}
					</Button>
				</InputWrapper>
			)}

			{is_connected && (
				<Section>
					<SectionTitle
						title={__(
							'Select your business location',
							'woocommerce-square'
						)}
					/>
					<SectionDescription>
						{parse(
							sprintf(
								/* translators: %1$s and %2$s are placeholders for the link to the documentation */
								__(
									'Please select the location you wish to link with this WooCommerce store. Only active %1$slocations%2$s that support credit card processing in Square can be linked.'
								),
								'<a target="_blank" href="https://docs.woocommerce.com/document/woocommerce-square/#section-4">',
								'</a>'
							)
						)}
					</SectionDescription>

					<InputWrapper
						label={__('Business location', 'woocommerce-square')}
					>
						<SelectControl
							data-testid="business-location-field"
							value={_location_id}
							onChange={(value) => {
								if (enable_sandbox === 'yes') {
									setSquareSettingData({
										sandbox_location_id: value,
									});
								} else {
									setSquareSettingData({
										production_location_id: value,
									});
								}
							}}
							options={[
								{
									label: __(
										'Please choose a location',
										'woocommerce-square'
									),
									value: '',
								},
								...locations,
							]}
						/>
					</InputWrapper>
				</Section>
			)}

			{is_connected && <ConfigureSync indent={2} isDirty={isFormDirty} />}

			<AdvancedSettings />

			<SquareSettingsSaveButton
				label={__('Save changes', 'woocommerce-square')}
				afterSaveLabel={__('Changes Saved!', 'woocommerce-square')}
				afterSaveCallback={() => window.location.reload()}
			/>
		</>
	);
};
