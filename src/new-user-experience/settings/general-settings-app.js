/**
 * General tab — environment, connection, business location (Payments > Square > General).
 */
import { __ } from '@wordpress/i18n';
import { useCallback, useEffect, useRef } from '@wordpress/element';

import { SectionTitle, SectionDescription, Loader } from '../components';
import { useSquareSettings } from './hooks';
import { SandboxCredentialsDataForm } from './sandbox-credentials-dataform';
import {
	GeneralConnectSection,
	GeneralLocationDataForm,
	GeneralLocationIntro,
} from './general-settings-dataform-sections';

const HUB_SAVE_BUTTON_ID = 'wc-square-settings-hub__save-general';

export const GeneralSettingsApp = () => {
	const {
		settings,
		isSquareSettingsSaving,
		squareSettingsLoaded,
		setSquareSettingData,
		saveSquareSettings,
	} = useSquareSettings( true );

	const performGeneralSave = useCallback( async () => {
		const requiredFields = document.querySelectorAll( '[required]' );
		let isValid = true;
		requiredFields.forEach( ( field ) => {
			if ( ! field.value ) {
				field.classList.add( 'required-error' );
				isValid = false;
			} else {
				field.classList.remove( 'required-error' );
			}
		} );
		if ( ! isValid ) {
			return;
		}
		await saveSquareSettings( settings );
		const wcBtn = document.querySelector( '.woocommerce-save-button' );
		if ( wcBtn ) {
			wcBtn.click();
		}
	}, [ settings, saveSquareSettings ] );

	const isProd = useRef( null );

	const {
		enable_sandbox = 'no',
		is_connected = false,
		connection_url = '',
		disconnection_url = '',
		access_tokens = [],
	} = settings;

	async function connectToProd() {
		if ( ! isProd.current ) {
			await saveSquareSettings();
		}

		window.location.href =
			isProd.current && access_tokens.production
				? disconnection_url
				: connection_url;
	}

	useEffect( () => {
		if ( ! squareSettingsLoaded ) {
			return;
		}

		isProd.current = enable_sandbox === 'no';
	}, [ squareSettingsLoaded, enable_sandbox ] );

	// Hub header Save (PHP); same validation + REST flow as legacy SquareSettingsSaveButton.
	useEffect( () => {
		if ( ! squareSettingsLoaded ) {
			return;
		}
		const hubBtn = document.getElementById( HUB_SAVE_BUTTON_ID );
		if ( ! hubBtn ) {
			return;
		}
		const onClick = ( e ) => {
			e.preventDefault();
			performGeneralSave();
		};
		hubBtn.addEventListener( 'click', onClick );
		return () => hubBtn.removeEventListener( 'click', onClick );
	}, [ squareSettingsLoaded, performGeneralSave ] );

	useEffect( () => {
		const hubBtn = document.getElementById( HUB_SAVE_BUTTON_ID );
		if ( ! hubBtn ) {
			return;
		}
		const busy = isSquareSettingsSaving === true;
		hubBtn.disabled = ! wcSquareSettings.depsCheck || busy;
		hubBtn.setAttribute( 'aria-busy', busy ? 'true' : 'false' );
		hubBtn.classList.toggle( 'wc-square-settings-hub__save--busy', busy );
	}, [ isSquareSettingsSaving, squareSettingsLoaded ] );

	if ( ! squareSettingsLoaded ) {
		return <Loader />;
	}

	return (
		<div className="wc-square-general-settings">
			<div className="wc-square-general-settings__card wc-square-general-settings__card--connect">
				<SectionTitle
					title={ __( 'Connect to Square', 'woocommerce-square' ) }
				/>
				<SectionDescription>
					{ __(
						'Connect this store to Square using a production account for live payments, or a sandbox account for testing.',
						'woocommerce-square'
					) }
				</SectionDescription>

				<GeneralConnectSection
					settings={ settings }
					setSquareSettingData={ setSquareSettingData }
					isSquareSettingsSaving={ isSquareSettingsSaving }
					connectToProd={ connectToProd }
					isProd={ isProd }
				/>

				{ enable_sandbox === 'yes' && <SandboxCredentialsDataForm /> }
			</div>

			{ is_connected && (
				<div className="wc-square-general-settings__card wc-square-general-settings__card--location">
					<SectionTitle
						title={ __(
							'Business location',
							'woocommerce-square'
						) }
					/>
					<GeneralLocationIntro />
					<GeneralLocationDataForm
						settings={ settings }
						setSquareSettingData={ setSquareSettingData }
					/>
				</div>
			) }
		</div>
	);
};
