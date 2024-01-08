import { test, expect } from '@playwright/test';
import { chromium } from 'playwright';

import {
	createProduct,
	doesProductExist,
	clearCart,
} from '../utils/helper';
import dummy from '../dummy-data';

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

	if ( ! ( await doesProductExist( baseURL, 'gift-card-product' ) ) ) {
		await createProduct(
			page,
			{
				name: 'Gift Card Product',
				regularPrice: '25',
				sku: 'gift-card-product',
			},
			false
		);

		await page.locator( '#_square_gift_card' ).check();
		await page.waitForTimeout( 2000 );
		await page.locator( '#publish' ).click();
	}

	await clearCart( page );
	await browser.close();
} );

test( 'Wrong gift card number during reload', async ( { page } ) => {
	const { giftCard } = dummy;
	await page.goto( '/shop' );
	await page.getByText( 'Buy Gift Card' ).click();
	await page.locator( '#square-gift-card-buying-option__reload' ).check();
	await page
		.locator( 'input[name="square-gift-card-gan"]' )
		.fill( giftCard.invalid );
	await page.locator( '.single_add_to_cart_button' ).click();
	await expect( await page.getByText( 'The gift card number is either invalid or does not exist.' ) ).toBeVisible();
} );
