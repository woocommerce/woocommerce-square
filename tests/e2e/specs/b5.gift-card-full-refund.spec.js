import { test, expect } from '@playwright/test';
import { chromium } from 'playwright';

import {
	createProduct,
	doesProductExist,
	fillAddressFields,
	clearCart,
	fillGiftCardField,
	deleteSessions,
	gotoOrderEditPage,
	doSquareRefund,
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
	}

	await deleteSessions( page );
	await clearCart( page );
	await browser.close();
} );

test( 'Full Refund Gift card order', async ( { page } ) => {
	page.on('dialog', dialog => dialog.accept());
	await page.goto( '/product/dollar-product' );
	await page.locator( '.single_add_to_cart_button' ).click();

	await page.goto( '/checkout-old' );
	await fillAddressFields( page, false );
	await fillGiftCardField( page );

	await page.locator( '#square-gift-card-apply-btn' ).click();
	await expect( page.locator( '.wc_payment_methods' ) ).toHaveCount( 0, { timeout: 80000 } );

	await expect(
		await page.getByText( '$1.00 will be applied from the gift card.' )
	).toBeVisible();
	await expect(
		await page.getByText(
			'The remaining gift card balance after placing this order will be $0.00'
		)
	).toBeVisible();
	await page.locator( '#place_order' ).click();

	await expect(
		page.locator( '.woocommerce-order-overview__payment-method strong' )
	).toHaveText( 'Square Gift Card ($1.00)' );

	let orderId = await page
		.locator( '.woocommerce-order-overview__order strong' )
		.innerText();

	await gotoOrderEditPage( page, orderId );

	await expect( await page.locator( '.square_gift_card' ) ).toBeVisible();
	await expect( page.locator( '#order_status' ) ).toHaveValue( 'wc-processing' );
	await expect(
		page.getByText(
			'Square Gift Card Test Charge Approved for an amount of $1.00: Gift Card ending in 0000'
		)
	).toBeVisible();

	await doSquareRefund( page, '1' );
	await expect( page.locator( '#order_status' ) ).toHaveValue( 'wc-refunded' );
	await expect( await page.getByText( 'Square Refund in the amount of $1.00 approved' ) ).toBeVisible();
	await expect( await page.getByText( 'Square Order completely refunded.' ) ).toBeVisible();
} );
