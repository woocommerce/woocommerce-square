/**
 * External dependencies
 */
import { createContext } from '@wordpress/element';

/**
 * The Square Web Payment Context.
 * Used to access the `card` and the `payments` request object.
 */
export const SquareWebContext = createContext( false );
