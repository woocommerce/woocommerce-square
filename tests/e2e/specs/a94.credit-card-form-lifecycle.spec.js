import { test, expect } from '@playwright/test';
import { chromium } from 'playwright';
import { addOneOrMoreProductToCart } from '@woocommerce/e2e-utils-playwright';

import {
	fillAddressFields,
	fillCreditCardFields,
	visitCheckout,
	doesProductExist,
	createProduct,
	placeOrder,
	waitForUnBlock,
} from '../utils/helper';

const cardFrame = '#wc-square-credit-card-container .sq-card-component';

/**
 * Recreates the conditions that rendered the card field twice: Square is slow to build the card,
 * and another plugin (e.g. a delivery date picker) triggers a checkout update as the page loads.
 *
 * @param {Object} page Playwright page object.
 */
async function slowCardWithCheckoutUpdate( page ) {
	await page.route( /squarecdn\.com|squareupsandbox\.com/, async ( route ) => {
		await new Promise( ( resolve ) => setTimeout( resolve, 1500 ) );
		await route.continue().catch( () => {} );
	} );

	await page.addInitScript( () => {
		document.addEventListener( 'DOMContentLoaded', () => {
			jQuery( document.body ).one( 'updated_checkout', () => {
				jQuery( document.body ).trigger( 'update_checkout' );
			} );
		} );
	} );
}

test.beforeAll( 'Setup', async ( { baseURL } ) => {
	const browser = await chromium.launch();
	const page = await browser.newPage();

	if ( ! ( await doesProductExist( baseURL, 'simple-product' ) ) ) {
		await createProduct( page, {
			name: 'Simple Product',
			regularPrice: '14.99',
			sku: 'simple-product',
		} );

		await expect( page.getByText( 'Product published' ) ).toBeVisible();
	}

	await browser.close();
} );

test( '[non-Block]: Checkout update while the card is loading renders a single card field @general', async ( {
	page,
} ) => {
	await slowCardWithCheckoutUpdate( page );
	await addOneOrMoreProductToCart( page, 'simple-product' );
	await visitCheckout( page, false );
	await fillAddressFields( page, false );

	await expect( page.locator( cardFrame ) ).toHaveCount( 1 );
	await fillCreditCardFields( page, true, false );

	// Give a late duplicate time to mount before checking again.
	await page.waitForTimeout( 3000 );
	await expect( page.locator( cardFrame ) ).toHaveCount( 1 );

	await placeOrder( page, false );
	await expect( page.locator( '.entry-title' ) ).toHaveText( 'Order received' );
} );

test( '[non-Block]: Placing an order before the card field is ready shows an error and can be retried @general', async ( {
	page,
} ) => {
	await slowCardWithCheckoutUpdate( page );
	await addOneOrMoreProductToCart( page, 'simple-product' );
	await visitCheckout( page, false );

	await page.locator( 'label[for="payment_method_square_credit_card"]' ).click();
	await expect( page.locator( cardFrame ) ).toHaveCount( 0 );
	await page.locator( '#place_order' ).click();

	await expect(
		page
			.getByText( 'An error occurred, please try again or try an alternate form of payment.' )
			.first()
	).toBeVisible();

	await waitForUnBlock( page );
	await fillAddressFields( page, false );
	await fillCreditCardFields( page, true, false );
	await expect( page.locator( cardFrame ) ).toHaveCount( 1 );

	await placeOrder( page, false );
	await expect( page.locator( '.entry-title' ) ).toHaveText( 'Order received' );
} );
