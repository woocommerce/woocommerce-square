import { test, expect } from '@playwright/test';
import { firefox } from 'playwright';

import {
	doesProductExist,
	createProduct
} from '../utils/helper';
import { doGooglePay } from '../utils/square-sandbox';

test.beforeAll( 'Setup', async ( { baseURL } ) => {
	const browser = await firefox.launch();
	const page = await browser.newPage();

	await page.goto( '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=square_credit_card' );
	await page.locator( '#woocommerce_square_credit_card_enable_digital_wallets' ).check();
	await page.locator( '.woocommerce-save-button' ).click();

	if ( ! ( await doesProductExist( baseURL, 'simple-product' ) ) ) {
		await createProduct( page, {
			name: 'Simple Product',
			regularPrice: '14.99',
			sku: 'simple-product',
		} );
	}

	await browser.close();
} );

test( 'Digital Wallet - Single Product Page', async () => {
	test.slow();
	const browser = await firefox.launch();
	const page = await browser.newPage();

	await page.goto( 'simple-product' );
	await page.waitForSelector( '.gpay-card-info-container-fill', { state: 'visible' } );
	await page.locator( '#wc-square-google-pay' ).click();

	const popupPromise = page.waitForEvent('popup');
	const popup = await popupPromise;

	await doGooglePay( popup );
	await expect(
		page.locator( '.woocommerce-order-overview__total strong' )
	).toHaveText( '$14.99' );
} );
