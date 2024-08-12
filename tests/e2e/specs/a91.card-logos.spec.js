import { test, expect } from '@playwright/test';
import { chromium } from 'playwright';
import { savePaymentGatewaySettings } from '../utils/helper';


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
			'.codeamp-components-multi-select-control__token[title="Visa"]'
		)
		.locator( 'button[aria-label="Remove item"]' )
		.click();
	await page
		.locator(
			'.codeamp-components-multi-select-control__token[title="Discover"]'
		)
		.locator( 'button[aria-label="Remove item"]' )
		.click();
	await page
		.locator(
			'.codeamp-components-multi-select-control__token[title="JCB"]'
		)
		.locator( 'button[aria-label="Remove item"]' )
		.click();
	await page
		.locator(
			'.codeamp-components-multi-select-control__token[title="UnionPay"]'
		)
		.locator( 'button[aria-label="Remove item"]' )
		.click();

	await savePaymentGatewaySettings( page );

	await expect( await page.locator( '.credit-card-gateway-card-logos-field .codeamp-components-multi-select-control__token' ) ).toHaveCount( 3 );

	await page.goto( '/checkout-old' );
	await expect(
		await page.locator( '.wc-square-credit-card-payment-gateway-icon' )
	).toHaveCount( 3 );
} );
