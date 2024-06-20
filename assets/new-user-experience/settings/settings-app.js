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
import { ConfigureSync, AdvancedSettings, SandboxSettings } from '../modules';
import { useSquareSettings } from './hooks';
import { connectToSquare, filterBusinessLocations } from '../utils';

export const SettingsApp = () => {
	const {
		settings,
		isSquareSettingsSaving,
		squareSettingsLoaded,
		setSquareSettingData,
		saveSquareSettings,
	} = useSquareSettings(true);

	const [initialState, setInitialState] = useState(false);
	const [isFormDirty, setIsFormDirty] = useState(false);

	const {
		enable_sandbox = 'no',
		sandbox_location_id = '',
		is_connected = false,
		connection_url = '',
		disconnection_url = '',
		locations = [],
	} = settings;

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

	const initiateConnection = async () => {
		const response = await saveSquareSettings();

		if (enable_sandbox !== 'yes') {
			window.location.assign(connection_url);
			return;
		}

		if (!response?.success) {
			return;
		}

		const businessLocations = await connectToSquare();

		if (businessLocations.success) {
			const filteredBusinessLocations = filterBusinessLocations(
				businessLocations.data
			);
			setSquareSettingData({ locations: filteredBusinessLocations });
			setSquareSettingData({ is_connected: true });
		}
	};

	if (!squareSettingsLoaded) {
		return <Loader />;
	}

	return (
		<>
			<SandboxSettings indent={2} />

			<InputWrapper
				label={__('Connection', 'woocommerce-square')}
				variant="boxed"
				className="square-settings__connection"
			>
				<Button
					data-testid="connect-to-square-button"
					variant="primary"
					{...(is_connected && { href: disconnection_url })}
					onClick={() => initiateConnection()}
					isBusy={isSquareSettingsSaving}
					disabled={isSquareSettingsSaving}
				>
					{is_connected
						? __('Disconnect from Square', 'woocommerce-square')
						: __('Connect to Square', 'woocommerce-square')}
				</Button>
			</InputWrapper>

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
							value={sandbox_location_id}
							onChange={(location_id) =>
								setSquareSettingData({
									sandbox_location_id: location_id,
								})
							}
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
			/>
		</>
	);
};
