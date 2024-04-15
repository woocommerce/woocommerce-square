import { useState } from '@wordpress/element';

export const useSettingsForm = ( initialState = {} ) => {
	const defaultState = {
		enable_sandbox: 'yes',
		sandbox_application_id: '',
		sandbox_token: '',
		debug_logging_enabled: 'no',
		sandbox_location_id: '',
		system_of_record: 'disabled',
		enable_inventory_sync: 'no',
		override_product_images: 'no',
		hide_missing_products: 'no',
		sync_interval: '0.25',
		is_connected: false,
		disconnection_url: '',
		locations: [],
	};

	const [ formState, setFormState ] = useState( null === initialState ? defaultState : Object.assign( defaultState, initialState ) );

	const setFieldValue = ( newValue ) => {
		setFormState( prevState => ( {
			...prevState,
			...newValue
		} ) );
	};

	return [ formState, setFieldValue ];
};
