import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { check } from '@wordpress/icons';

import { useSquareSettings } from '../../settings/hooks';
import { useSteps } from '../../onboarding/hooks';

const withSaveSquareSettingsButton = ( WrappedComponent ) => {
	return ( props ) => {
		const {
			label = __( 'Apply Changes', 'woocommerce-square' ),
		} = props;

		const {
			afterSaveLabel,
			...remainingProps
		} = props;

		const {
			isSquareSettingsSaving,
			settings,
			saveSquareSettings,
		} = useSquareSettings();

		const {
			setStep,
		} = useSteps();

		return (
			<WrappedComponent
				{ ...remainingProps }
				{ ...( null === isSquareSettingsSaving && { icon: check } ) }
				isBusy={ isSquareSettingsSaving }
				disabled={ isSquareSettingsSaving }
				variant="primary"
				onClick={ () => {
					( async () => {
						await saveSquareSettings( settings );

						setStep( 'payment-complete' );
					} )()
				} }
			>
				{ null === isSquareSettingsSaving ? afterSaveLabel : label }
			</WrappedComponent>
		)
	};
};

export const SquareSettingsSaveButton = withSaveSquareSettingsButton( Button );
