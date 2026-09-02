import { chromium } from 'playwright';
import { test, expect } from '@playwright/test';
import { addOneOrMoreProductToCart } from '@woocommerce/e2e-utils-playwright';

import {
	clearCart,
	createProduct,
	doesProductExist,
	fillAddressFields,
	fillCreditCardFields,
	placeOrder,
	visitCheckout,
} from '../utils/helper';

const CARD_IFRAME = '#wc-square-credit-card-container iframe.sq-card-component';

/**
 * Re-triggers `update_checkout` from inside the first `updated_checkout`.
 *
 * This is what third-party checkout plugins (delivery date pickers and the like) do on page
 * load, and it lands the second refresh while Square's `payments.card()` promise is still
 * pending - the window in which the payment form used to attach a second card to the
 * container. Registered via `addInitScript` so it is in place before WooCommerce fires its
 * first checkout update.
 */
function queueCheckoutRaceTrigger( page ) {
	return page.addInitScript( () => {
		window.__squareUpdatedCheckoutCount = 0;

		document.addEventListener( 'DOMContentLoaded', () => {
			if ( ! window.jQuery ) {
				return;
			}

			window.jQuery( ( $ ) => {
				$( document.body ).on( 'updated_checkout', () => {
					window.__squareUpdatedCheckoutCount++;
				} );

				$( document.body ).one( 'updated_checkout', () => {
					$( document.body ).trigger( 'update_checkout' );
				} );
			} );
		} );
	} );
}

/**
 * Waits for both checkout cycles to finish, then counts the mounted card fields.
 *
 * The count is taken as a single non-retrying snapshot: a retrying matcher would pass the
 * instant the count reached 1, which on a duplicating build is true for a moment before the
 * second card attaches.
 *
 * @param {Object} page Playwright page object.
 * @return {Promise<Object>} Card iframe and wrapper counts.
 */
async function countCardFields( page ) {
	// Both the initial update and the one the trigger queued must have completed.
	await page.waitForFunction(
		() => window.__squareUpdatedCheckoutCount >= 2,
		null,
		{ timeout: 30000 }
	);

	await page.locator( CARD_IFRAME ).first().waitFor( { state: 'attached' } );

	// Let any further attach settle so the snapshot below is of the final DOM.
	await page.waitForTimeout( 3000 );

	return page.evaluate( () => ( {
		iframes: document.querySelectorAll(
			'#wc-square-credit-card-container iframe.sq-card-component'
		).length,
		wrappers: document.querySelectorAll(
			'#wc-square-credit-card-container .sq-card-wrapper'
		).length,
	} ) );
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

	await clearCart( page );
	await browser.close();
} );

test.afterAll( async () => {
	const browser = await chromium.launch();
	const page = await browser.newPage();

	await clearCart( page );
	await browser.close();
} );

test( '[non-Block]: Credit card field is not duplicated by a checkout update during form init @general', async ( {
	page,
} ) => {
	await addOneOrMoreProductToCart( page, 'simple-product' );
	await queueCheckoutRaceTrigger( page );
	await visitCheckout( page, false );

	const counts = await countCardFields( page );

	expect( counts.iframes ).toBe( 1 );
	expect( counts.wrappers ).toBe( 1 );
} );

test( '[non-Block]: Order can be placed after a checkout update during form init @general', async ( {
	page,
} ) => {
	await addOneOrMoreProductToCart( page, 'simple-product' );
	await queueCheckoutRaceTrigger( page );
	await visitCheckout( page, false );

	const counts = await countCardFields( page );
	expect( counts.iframes ).toBe( 1 );

	// The surviving card must still be the one the handler tokenizes with.
	await fillAddressFields( page, false );
	await fillCreditCardFields( page, true, false );
	await placeOrder( page, false );

	await expect(
		page.locator( '.woocommerce-thankyou-order-received' )
	).toHaveText( 'Thank you. Your order has been received.', {
		timeout: 30000,
	} );
} );
