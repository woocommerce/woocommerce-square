/**
 * External dependencies
 */
import { useContext, useRef, useEffect } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { SquareWebContext } from './square-web-context';

/**
 * Renders the wrapper to load the Square Credit Card fields.
 */
export const ComponentCardFields = () => {
	/**
	 * The Square card object.
	 */
	const { card } = useContext( SquareWebContext );

	/**
	 * A ref to reference the HTML wrapper that will host the
	 * credit card fields.
	 */
	const cardContainer = useRef( false );

	useEffect( () => {
		if ( ! card ) {
			return;
		}

		/**
		 * Attaching the card fields to the container.
		 */
		const attachCard = async () => {
			card.attach( cardContainer.current );
		};

		attachCard();
	}, [ card ] );

	return <div ref={ cardContainer }></div>;
};
