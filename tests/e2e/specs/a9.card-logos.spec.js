import { test, expect } from '@playwright/test';
import { chromium } from 'playwright';

test.beforeAll( 'Setup', async ( { baseURL } ) => {
	const browser = await chromium.launch();
	const page = await browser.newPage();

	await page.goto(
		'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=square_credit_card'
	);
	await page
		.locator( '#woocommerce_square_credit_card_card_types' )
		.selectOption( [
			'VISA',
			'MC',
			'AMEX',
			'DISC',
			'DINERS',
			'JCB',
			'UNIONPAY',
		] );
	await page.locator( '.woocommerce-save-button' ).click();
	await browser.close();
} );

test( 'Payment Gateway - Accepted Card Logos', async ( { page } ) => {
	await page.goto( '/product/simple-product' );
	await page.locator( '.single_add_to_cart_button' ).click();
	await page.goto( '/checkout-old' );

	await expect(
		await page.locator( '.wc-square-credit-card-payment-gateway-icon' )
	).toHaveCount( 7 );

	await page.goto(
		'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=square_credit_card'
	);
	await page
		.locator(
			'li.select2-selection__choice[title="Discover"] .select2-selection__choice__remove'
		)
		.click();
	await page
		.locator(
			'li.select2-selection__choice[title="Diners"] .select2-selection__choice__remove'
		)
		.click();
	await page
		.locator(
			'li.select2-selection__choice[title="JCB"] .select2-selection__choice__remove'
		)
		.click();
	await page
		.locator(
			'li.select2-selection__choice[title="UnionPay"] .select2-selection__choice__remove'
		)
		.click();

	await page.locator( '.woocommerce-save-button' ).click();
	await page.locator( '.woocommerce-save-button' ).click();

	await expect( await page.locator( '.select2-selection__choice' ) ).toHaveCount( 3 );

	await page.goto( '/checkout-old' );
	await expect(
		await page.locator( '.wc-square-credit-card-payment-gateway-icon' )
	).toHaveCount( 3 );
} );
