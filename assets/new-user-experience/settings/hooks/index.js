import { useState } from '@wordpress/element';

export const useSettingsForm = ( initialState = {} ) => {
	const [ formState, setFormState ] = useState( null === initialState ? {} : initialState );

	const setFieldValue = ( newValue ) => {
		setFormState( prevState => ( {
			...prevState,
			...newValue
		} ) );
	};

	return [ formState, setFieldValue ];
};
