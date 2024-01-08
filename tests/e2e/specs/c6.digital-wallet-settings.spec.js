import { test, expect } from '@playwright/test';
import { chromium } from 'playwright';

import {
	doesProductExist,
	createProduct,
	clearCart,
} from '../utils/helper';

test.beforeAll( 'Setup', async ( { baseURL } ) => {
	const browser = await chromium.launch();
	const page = await browser.newPage();

	// Create product.
	if ( ! ( await doesProductExist( baseURL, 'simple-product' ) ) ) {
		await createProduct( page, {
			name: 'Simple Product',
			regularPrice: '14.99',
			sku: 'simple-product',
		} );
	}

	await clearCart( page );
	await browser.close();
} );

test( 'Verify the Digital Wallet Button type and color settings', async ( { page } ) => {
	await page.goto( '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=square_credit_card' );
	await page.locator( '#woocommerce_square_credit_card_enable_digital_wallets' ).check();
	await page.locator( '#woocommerce_square_credit_card_digital_wallets_google_pay_button_color' ).selectOption( { value: 'black' } );
	await page.locator( '.woocommerce-save-button' ).click();

	await page.goto( '/simple-product/' );
	await expect( await page.locator( '.gpay-card-info-container' ) ).toHaveClass( /black/ );

	await page.goto( '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=square_credit_card' );
	await page.locator( '#woocommerce_square_credit_card_enable_digital_wallets' ).check();
	await page.locator( '#woocommerce_square_credit_card_digital_wallets_google_pay_button_color' ).selectOption( { value: 'white' } );
	await page.locator( '.woocommerce-save-button' ).click();

	await page.goto( '/simple-product/' );
	await expect( await page.locator( '.gpay-card-info-container' ) ).toHaveClass( /white/ );
} );
