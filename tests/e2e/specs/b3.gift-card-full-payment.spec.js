import { test, expect } from '@playwright/test';
import { chromium } from 'playwright';

import {
	createProduct,
	doesProductExist,
	fillAddressFields,
	clearCart,
	fillGiftCardField,
	deleteSessions,
	isToggleChecked,
	savePaymentGatewaySettings,
} from '../utils/helper';

test.beforeAll( 'Setup', async ( { baseURL } ) => {
	const browser = await chromium.launch();
	const page = await browser.newPage();

	await page.goto(
		'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=gift_cards_pay'
	);

	if ( ! await isToggleChecked( page, '.gift-card-gateway-toggle-field' ) ) {
		await page
			.locator( '.gift-card-gateway-toggle-field' )
			.click();
	}

	await savePaymentGatewaySettings( page );

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

let orderId = 38;

test( 'Gift card - Full payment', async ( { page } ) => {
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

	orderId = await page
		.locator( '.woocommerce-order-overview__order strong' )
		.innerText();
} );
