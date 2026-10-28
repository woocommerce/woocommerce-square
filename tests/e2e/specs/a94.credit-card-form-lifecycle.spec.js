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
const generalError =
	'An error occurred, please try again or try an alternate form of payment.';

/**
 * Wraps the Square SDK so `payments.card()`, `card.attach()` and `card.destroy()` can be
 * delayed from the test, making the card setup and teardown windows deterministic.
 *
 * @param {Object} delays Initial delays in ms, keyed by `card`, `attach` and `destroy`.
 */
function delaySquareCardLifecycle( delays ) {
	window.__squareCardDelays = { card: 0, attach: 0, destroy: 0, ...delays };
	window.__squareCardCalls = { card: 0, attach: 0, destroy: 0 };
	window.__squareCardSettled = { card: 0, attach: 0, destroy: 0 };

	// Re-reads the delay while waiting, so the test can release a pending call early.
	const wait = async ( name ) => {
		const start = Date.now();

		while ( Date.now() - start < window.__squareCardDelays[ name ] ) {
			await new Promise( ( resolve ) => setTimeout( resolve, 100 ) );
		}
	};

	const delayed = ( name, fn ) => async ( ...args ) => {
		window.__squareCardCalls[ name ]++;

		try {
			await wait( name );
			return await fn( ...args );
		} finally {
			window.__squareCardSettled[ name ]++;
		}
	};

	const wrapSquare = ( square ) => {
		if ( ! square || square.__delayed ) {
			return square;
		}

		const payments = square.payments.bind( square );

		square.payments = ( ...paymentsArgs ) => {
			const instance = payments( ...paymentsArgs );
			const card = instance.card.bind( instance );

			instance.card = delayed( 'card', async ( ...cardArgs ) => {
				const cardInstance = await card( ...cardArgs );

				cardInstance.attach = delayed(
					'attach',
					cardInstance.attach.bind( cardInstance )
				);
				cardInstance.destroy = delayed(
					'destroy',
					cardInstance.destroy.bind( cardInstance )
				);

				return cardInstance;
			} );

			return instance;
		};

		square.__delayed = true;
		return square;
	};

	let square;

	Object.defineProperty( window, 'Square', {
		configurable: true,
		get: () => square,
		set: ( value ) => {
			square = wrapSquare( value );
		},
	} );
}

/**
 * Triggers a classic checkout update and waits for WooCommerce to finish applying it.
 *
 * @param {Object} page Playwright page object.
 */
async function updateCheckout( page ) {
	await page.evaluate(
		() =>
			new Promise( ( resolve ) => {
				jQuery( document.body ).one( 'updated_checkout', resolve );
				jQuery( document.body ).trigger( 'update_checkout' );
			} )
	);
}

async function setDelays( page, delays ) {
	await page.evaluate( ( value ) => {
		Object.assign( window.__squareCardDelays, value );
	}, delays );
}

async function getCalls( page ) {
	return page.evaluate( () => window.__squareCardCalls );
}

/**
 * Waits until every wrapped Square call has finished and the card form is no longer being built,
 * so a late duplicate mount can't slip in after the card count is asserted.
 *
 * @param {Object} page Playwright page object.
 */
async function waitForCardFormToSettle( page ) {
	await page.waitForFunction(
		() =>
			[ 'card', 'attach', 'destroy' ].every(
				( name ) =>
					window.__squareCardCalls[ name ] ===
					window.__squareCardSettled[ name ]
			) && ! window.wc_square_credit_card_payment_form_handler?.is_building_payment_form
	);
	await waitForUnBlock( page );
}

async function placeOrderAndExpectSuccess( page ) {
	await waitForCardFormToSettle( page );
	await expect( page.locator( cardFrame ) ).toHaveCount( 1 );
	await fillCreditCardFields( page, true, false );
	await expect( page.locator( cardFrame ) ).toHaveCount( 1 );
	await placeOrder( page, false );
	await expect( page.locator( '.entry-title' ) ).toHaveText( 'Order received' );
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

test( '[non-Block]: Checkout updates during card setup render a single card field @general', async ( {
	page,
} ) => {
	await addOneOrMoreProductToCart( page, 'simple-product' );
	await page.addInitScript( delaySquareCardLifecycle, {
		card: 1500,
		attach: 1500,
	} );
	await visitCheckout( page, false );

	// Overlapping updates while the first card is still being created and mounted.
	await page.waitForFunction( () => window.__squareCardCalls?.card > 0 );
	await updateCheckout( page );
	await updateCheckout( page );

	// The queued refresh must not have created a second card alongside the first.
	await setDelays( page, { card: 0, attach: 0 } );
	await waitForCardFormToSettle( page );
	expect( ( await getCalls( page ) ).attach ).toBeGreaterThan( 0 );
	await expect( page.locator( cardFrame ) ).toHaveCount( 1 );

	await fillAddressFields( page, false );
	await placeOrderAndExpectSuccess( page );
} );

test( '[non-Block]: Checkout updates during card teardown render a single card field @general', async ( {
	page,
} ) => {
	await addOneOrMoreProductToCart( page, 'simple-product' );
	await page.addInitScript( delaySquareCardLifecycle, {} );
	await visitCheckout( page, false );
	await expect( page.locator( cardFrame ) ).toHaveCount( 1 );

	// The next update re-renders the payment box, so the card is destroyed and rebuilt.
	// Slow the teardown down and fire further updates while it is in progress.
	await setDelays( page, { destroy: 2000 } );
	await updateCheckout( page );
	await page.waitForFunction( () => window.__squareCardCalls.destroy > 0 );
	await updateCheckout( page );
	await updateCheckout( page );

	await setDelays( page, { destroy: 0 } );
	await waitForCardFormToSettle( page );
	await expect( page.locator( cardFrame ) ).toHaveCount( 1 );

	await fillAddressFields( page, false );
	await placeOrderAndExpectSuccess( page );
} );

test( '[non-Block]: Placing an order before the card field is ready shows an error and can be retried @general', async ( {
	page,
} ) => {
	await addOneOrMoreProductToCart( page, 'simple-product' );
	await page.addInitScript( delaySquareCardLifecycle, { attach: 60000 } );
	await visitCheckout( page, false );
	await fillAddressFields( page, false );

	await page.waitForFunction( () => window.__squareCardCalls?.attach > 0 );
	await page.locator( 'label[for="payment_method_square_credit_card"]' ).click();
	await page.locator( '#place_order' ).click();

	await expect( page.getByText( generalError ) ).toBeVisible();
	await expect( page.locator( '.entry-title' ) ).not.toHaveText( 'Order received' );

	// Let the card finish mounting, then retry with the same form.
	await setDelays( page, { attach: 0 } );
	await placeOrderAndExpectSuccess( page );
} );
