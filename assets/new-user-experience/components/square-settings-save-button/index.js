import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { check } from '@wordpress/icons';

import { useSquareSettings } from '../../settings/hooks';

const withSaveSquareSettingsButton = ( WrappedComponent ) => {
	return ( props ) => {
		const {
			label = __( 'Apply Changes', 'woocommerce-square' ),
		} = props;

		const {
			afterSaveLabel = __( 'Changes Saved!' ),
			afterSaveCallback,
			...remainingProps
		} = props;

		const {
			isSquareSettingsSaving,
			settings,
			saveSquareSettings,
		} = useSquareSettings();

		return (
			<WrappedComponent
				data-testid="square-settings-save-button"
				{ ...remainingProps }
				{ ...( null === isSquareSettingsSaving && { icon: check } ) }
				isBusy={ isSquareSettingsSaving }
				disabled={ isSquareSettingsSaving }
				variant="primary"
				onClick={ () => {
					( async () => {
						await saveSquareSettings( settings );

						if ( afterSaveCallback ) {
							afterSaveCallback();
						}
					} )()
				} }
			>
				{ null === isSquareSettingsSaving ? afterSaveLabel : label }
			</WrappedComponent>
		)
	};
};

export const SquareSettingsSaveButton = withSaveSquareSettingsButton( Button );
