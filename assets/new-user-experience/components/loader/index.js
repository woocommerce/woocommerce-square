/**
 * External dependencies.
 */
import {
	Spinner,
} from '@wordpress/components';

export const Loader = () => {
    return (
		<div className="woo-square-loader">
			<Spinner />
		</div>
	);
}
