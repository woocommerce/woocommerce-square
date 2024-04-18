import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { check } from '@wordpress/icons';

import { useSquareSettings } from '../../settings/hooks';

const withSaveSquareSettingsButton = ( WrappedComponent ) => {
	return ( props ) => {
		const {
			label = __( 'Apply Changes', 'woocommerce-square' ),
			afterSaveLabel = __( 'Changes applied' ),
			nextStep,
			setStep
		} = props;

		const {
			isSquareSettingsSaving,
			settings,
			saveSquareSettings,
		} = useSquareSettings();

		return (
			<WrappedComponent
				{ ...props }
				{ ...( null === isSquareSettingsSaving && { icon: check } ) }
				isBusy={ isSquareSettingsSaving }
				disabled={ isSquareSettingsSaving }
				variant="primary"
				onClick={ () => {
					saveSquareSettings( settings ).then( () => {
						if ( nextStep ) {
							setStep( nextStep );
						}
					} );
				} }
			>
				{ null === isSquareSettingsSaving ? afterSaveLabel : label }
			</WrappedComponent>
		)
	};
};

export const SquareSettingsSaveButton = withSaveSquareSettingsButton( Button );

