import { registerSettingsExtension } from '@woocommerce/modern-settings-sdk';
import OAuthConnect from './general/oauth-connect';
import EnvironmentSelector from './general/environment-selector';
import squareSaveHandler from './save-handler';

registerSettingsExtension( {
	scope: { page: 'square' },
	components: {
		'square/oauth-connect': OAuthConnect,
		'square/environment-selector': EnvironmentSelector,
	},
	fieldVisibility: {
		// Sandbox credential fields are only shown when sandbox is selected.
		// The radio component stores 'yes' (sandbox) or 'no' (production).
		sandbox_application_id: ( { values } ) =>
			values.enable_sandbox === 'yes',
		sandbox_token: ( { values } ) => values.enable_sandbox === 'yes',
	},
	saveHandlers: {
		'square/save': squareSaveHandler,
	},
} );
