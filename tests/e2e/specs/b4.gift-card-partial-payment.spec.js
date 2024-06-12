import { test, expect } from '@playwright/test';
import { chromium } from 'playwright';

import {
	createProduct,
	doesProductExist,
	fillAddressFields,
	fillCreditCardFields,
	clearCart,
	fillGiftCardField,
	deleteSessions,
	selectPaymentMethod,
} from '../utils/helper';

test.beforeAll( 'Setup', async ( { baseURL } ) => {
	const browser = await chromium.launch();
	const page = await browser.newPage();

	await page.goto(
		'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=square_credit_card'
	);
	await page
		.locator( '#woocommerce_square_credit_card_enable_gift_cards' )
		.check();
	await page.locator( '.woocommerce-save-button' ).click();

	if ( ! ( await doesProductExist( baseURL, 'dollar-product' ) ) ) {
		await createProduct( page, {
			name: 'Dollar Product',
			regularPrice: '1',
			sku: 'dollar-product',
		} );
		await expect( await page.getByText( 'Product published' ) ).toBeVisible();
	}

	await deleteSessions( page );
	await clearCart( page );
	await browser.close();
} );

let orderId = 0;

test( 'Gift card - Partial payment', async ( { page } ) => {
	await page.goto( '/product/simple-product' );
	await page.locator( '.single_add_to_cart_button' ).click();

	await page.goto( '/checkout-old' );
	await fillAddressFields( page, false );
	await fillGiftCardField( page );

	await page.locator( '#square-gift-card-apply-btn' ).click();
	await expect( page.locator( '.wc_payment_methods' ) ).toHaveCount( 1, { timeout: 80000 } );

	await expect(
		await page.getByText( '$1.00 will be applied from the gift card.' )
	).toBeVisible();
	await expect(
		page.locator( '.square-gift-card-response__content' )
	).toContainText(
		"Your gift card doesn't have enough funds to cover the order total. The remaining amount of $13.99 would need to be paid with a credit card"
	);
	
	await selectPaymentMethod(page, 'square_credit_card', false);
	await fillCreditCardFields( page, true, false );

	await page.locator( '#place_order' ).click();
	await expect(
		page.locator( '.woocommerce-order-overview__payment-method strong' )
	).toHaveText( 'Square Gift Card ($1.00) and Credit Card ($13.99)' );
	await expect(
		page.getByText(
			'$14.99 — Total split between gift card ($1.00) and credit card ($13.99)'
		)
	).toBeVisible();

	orderId = await page
		.locator( '.woocommerce-order-overview__order strong' )
		.innerText();
} );
