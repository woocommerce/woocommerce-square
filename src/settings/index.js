import { registerSettingsExtension } from '@woocommerce/modern-settings-sdk';
import OAuthConnect from './general/oauth-connect';
import LocationPicker from './general/location-picker';
import EnvironmentSelector from './general/environment-selector';
import SectionHeader from './general/section-header';
import Divider from './general/divider';
import squareSaveHandler from './save-handler';

registerSettingsExtension( {
	scope: { page: 'square' },
	components: {
		'square/oauth-connect': OAuthConnect,
		'square/location-picker': LocationPicker,
		'square/environment-selector': EnvironmentSelector,
		'square/section-header': SectionHeader,
		'square/divider': Divider,
	},
	fieldVisibility: {
		// Sandbox credential fields are only shown when sandbox is selected.
		// The radio component stores 'yes' (sandbox) or 'no' (production).
		sandbox_application_id: ( { values } ) => values.enable_sandbox === 'yes',
		sandbox_token: ( { values } ) => values.enable_sandbox === 'yes',
	},
	saveHandlers: {
		'square/save': squareSaveHandler,
	},
} );
