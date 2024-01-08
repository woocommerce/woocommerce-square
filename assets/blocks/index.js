/**
 * External dependencies
 */
import { registerPaymentMethod, registerExpressPaymentMethod } from '@woocommerce/blocks-registry';

/**
 * Internal dependencies
 */
import squareCreditCardMethod from './credit-card';
import squareDigitalWalletsMethod from './digital-wallets';
import { getSquareServerData } from './square-utils';

// Register Square Credit Card.
registerPaymentMethod( squareCreditCardMethod );

const hiddenButtons = getSquareServerData().hideButtonOptions;

if ( ! ( hiddenButtons.includes( 'google' ) && hiddenButtons.includes( 'apple' ) ) ) {
	registerExpressPaymentMethod( squareDigitalWalletsMethod );
}
