import { __ } from '@wordpress/i18n';
import useSquareSettings from '../use-square-settings';

const getActiveLocations = ( locations = [] ) =>
	locations
		.filter( ( l ) => l.status === 'ACTIVE' )
		.map( ( l ) => ( { label: l.name, value: l.id } ) );

export default function LocationPicker( { value, onChange } ) {
	const { loading, data } = useSquareSettings();

	if ( loading || ! data ) {
		return null;
	}

	const locations = getActiveLocations( data.locations ?? [] );

	if ( locations.length === 0 ) {
		return null;
	}

	// The selected value lives in SDK form state and is persisted by the Save
	// button (via squareSaveHandler), keeping it consistent with the rest of the
	// General tab instead of saving immediately on change.
	return (
		<div>
			<select
				aria-label={ __( 'Business location', 'woocommerce-square' ) }
				value={ value ?? '' }
				onChange={ ( e ) => onChange( e.target.value ) }
			>
				<option value="">
					{ __(
						'- Please select a location -',
						'woocommerce-square'
					) }
				</option>
				{ locations.map( ( loc ) => (
					<option key={ loc.value } value={ loc.value }>
						{ loc.label }
					</option>
				) ) }
			</select>
		</div>
	);
}
