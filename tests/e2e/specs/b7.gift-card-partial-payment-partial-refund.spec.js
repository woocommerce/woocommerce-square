import { test, expect } from '@playwright/test';
import { chromium } from 'playwright';

import {
	createProduct,
	doesProductExist,
	fillAddressFields,
	clearCart,
	fillGiftCardField,
	fillCreditCardFields,
	deleteSessions,
	gotoOrderEditPage,
	waitForUnBlock,
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

test( 'Gift card - Partial payment', async ( { page } ) => {
	page.on('dialog', dialog => dialog.accept());
	await page.goto( '/product/simple-product' );
	await page.locator( '.single_add_to_cart_button' ).click();

	await page.goto( '/checkout-old' );
	await fillAddressFields( page, false );
	await fillGiftCardField( page );
	await waitForUnBlock( page );

	await page.locator( '#square-gift-card-apply-btn' ).click();

	await fillCreditCardFields( page, null, false );

	await page.locator( '#place_order' ).click();

	let orderId = await page
		.locator( '.woocommerce-order-overview__order strong' )
		.innerText();

	await gotoOrderEditPage( page, orderId );
	await doSquareRefund( page, '0.25' );
	await expect( await page.getByText( 'Square Gift Card Refund in the amount of $0.25 of total $0.25 approved.' ) ).toBeVisible();
	await expect( page.locator( '#order_status' ) ).toHaveValue( 'wc-processing' );

	await doSquareRefund( page, '1.75' );
	await expect( await page.getByText( 'Square Gift Card Refund in the amount of $0.75 of total $1.75 approved.' ) ).toBeVisible();
	await expect( await page.getByText( 'Square Credit Card Refund in the amount of $1.00 of total $1.75 approved.' ) ).toBeVisible();
	await expect( page.locator( '#order_status' ) ).toHaveValue( 'wc-processing' );

	await doSquareRefund( page, '12.99' );
	await expect( page.locator( '#order_status' ) ).toHaveValue( 'wc-refunded' );
} );
