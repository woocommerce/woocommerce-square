import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { check } from '@wordpress/icons';

import { useSquareSettings } from '../../settings/hooks';

const withSaveSquareSettingsButton = ( WrappedComponent ) => {
	return ( props ) => {
		const { label = __( 'Apply Changes', 'woocommerce-square' ) } = props;

		const {
			afterSaveLabel = __( 'Changes Saved!', 'woocommerce-square' ),
			afterSaveCallback,
			icon = check,
			...remainingProps
		} = props;

		const { isSquareSettingsSaving, settings, saveSquareSettings } =
			useSquareSettings();

		return (
			<WrappedComponent
				data-testid="square-settings-save-button"
				{ ...( isSquareSettingsSaving === null && { icon } ) }
				isBusy={ isSquareSettingsSaving }
				variant="button-primary"
				className="button-primary"
				onClick={ () => {
					( async () => {
						// Check of required fields.
						const requiredFields =
							document.querySelectorAll( '[required]' );
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

						if ( afterSaveCallback ) {
							afterSaveCallback();
						}
					} )();
				} }
				{ ...remainingProps }
			>
				{ isSquareSettingsSaving === null ? afterSaveLabel : label }
			</WrappedComponent>
		);
	};
};

export const SquareSettingsSaveButton = withSaveSquareSettingsButton( Button );
