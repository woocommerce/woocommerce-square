import { Button } from '@wordpress/components';
import { useEffect } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';

import { useSquareSettings } from '../../settings/hooks';

const withSaveSquareSettingsButton = ( WrappedComponent ) => {
	return ( props ) => {
		const {
			label = __( 'Save', 'woocommerce-square' ),
		} = props;

		const {
			isSquareSettingsSaving,
			settings,
			saveSquareSettings,
		} = useSquareSettings();

		const { createSuccessNotice } = useDispatch( noticesStore );

		useEffect( () => {
			if ( null == isSquareSettingsSaving ) {
				createSuccessNotice( __( 'Settings saved!', 'woocommerce-square' ), {
					type: 'snackbar',
				} );
			}
		}, [ isSquareSettingsSaving ] );

		return (
			<WrappedComponent
				{ ...props }
				isBusy={ isSquareSettingsSaving }
				disabled={ isSquareSettingsSaving }
				variant="primary"
				onClick={ () => {
					saveSquareSettings( settings );
				} }
			>
				{ label }
			</WrappedComponent>
		)
	};
};

export const SquareSettingsSaveButton = withSaveSquareSettingsButton( Button );

