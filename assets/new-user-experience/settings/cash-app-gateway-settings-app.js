/**
 * External dependencies.
 */
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';

import { CashAppSetup } from '../../new-user-experience/onboarding/steps';
import { useCashAppData } from '../onboarding/hooks';
import { saveCashAppSettings } from '../utils';

export const CashAppSettingsApp = () => {
	const { cashAppData, settingsLoaded } = useCashAppData();
	const [ saveInProgress, setSaveInProgress ] = useState( false );
	const { createSuccessNotice } = useDispatch( noticesStore );

	const saveSettings = async () => {
		setSaveInProgress( true );
		const response = await saveCashAppSettings( 'cash_app_settings', cashAppData );

		if ( response.success ) {
			createSuccessNotice( __( 'Settings saved!', 'woocommerce-square' ), {
				type: 'snackbar',
			} )
		}

		setSaveInProgress( false );
	};

	const style = {
		width: '100%',
		maxWidth: '780px',
		marginTop: '50px',
		marginLeft: '50px',
	};

	if ( ! settingsLoaded ) {
		return null;
	}

	return (
		<div style={ style }>
			<CashAppSetup />
			<Button
				variant='primary'
				onClick={ () => saveSettings() }
				isBusy={ saveInProgress }
			>
				{ __( 'Save Changes', 'woocommerce-square' ) }
			</Button>
		</div>
	)
};
