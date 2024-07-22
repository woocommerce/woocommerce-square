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
	waitForUnBlock,
	doSquareRefund,
	savePaymentGatewaySettings,
} from '../utils/helper';

test.beforeAll( 'Setup', async ( { baseURL } ) => {
	const browser = await chromium.launch();
	const page = await browser.newPage();

	await page.goto(
		'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=gift_cards_pay'
	);

	await page.getByTestId( 'gift-card-gateway-toggle-field' ).check();

	await savePaymentGatewaySettings( page );

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

test( 'Partial Refund – Gift card order', async ( { page } ) => {
	page.on('dialog', dialog => dialog.accept());
	await page.goto( '/product/dollar-product' );
	await page.locator( '.single_add_to_cart_button' ).click();

	await page.goto( '/checkout-old' );
	await fillAddressFields( page, false );
	await fillGiftCardField( page );

	await page.locator( '#square-gift-card-apply-btn' ).click();
	await waitForUnBlock( page );
	await page.locator( '#place_order' ).click();

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

	await doSquareRefund( page, '0.45' );
	await expect( await page.getByText( 'Square Refund in the amount of $0.45 approved' ) ).toBeVisible();
	await expect( await page.getByText( 'Order status changed from Pending payment to Processing.' ) ).toBeVisible();
} );
