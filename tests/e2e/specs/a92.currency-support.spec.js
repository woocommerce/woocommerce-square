import { test, expect } from '@playwright/test';
import { chromium } from 'playwright';

import {
	visitCheckout,
	doesProductExist,
	createProduct,
	runWpCliCommand
} from '../utils/helper';

test.beforeAll( 'Setup', async ( { baseURL } ) => {
	const browser = await chromium.launch();
	const page = await browser.newPage();

	if ( ! ( await doesProductExist( baseURL, 'simple-product' ) ) ) {
		await createProduct( page, {
			name: 'Simple Product',
			regularPrice: '14.99',
			sku: 'simple-product',
		} );

		await expect( await page.getByText( 'Product published' ) ).toBeVisible();
	}
} );

test( 'Square credit card should available only for the supported currencies', async ( { page } ) => {
	// Update currency to INR
	await runWpCliCommand('wp option update woocommerce_currency "INR"');
	await page.goto( '/product/simple-product' );
	await page.locator( '.single_add_to_cart_button' ).click();
	// Confirm that the Credit card is not visible on checkout page.
	await visitCheckout(page, false);
	await expect(
		await page.locator(
			'ul.wc_payment_methods li.payment_method_square_credit_card'
		)
	).not.toBeVisible();
	// Confirm that the Cash App Pay is not visible on block-checkout page.
	await visitCheckout(page, true);
	await expect(
		await page.locator(
			'label[for="radio-control-wc-payment-method-options-square_credit_card"]'
		)
	).not.toBeVisible();

	// Update currency to USD
	await runWpCliCommand('wp option update woocommerce_currency "USD"');
	// Confirm that the Credit card is not visible on checkout page.
	await visitCheckout(page, false);
	await expect(
		await page.locator(
			'ul.wc_payment_methods li.payment_method_square_credit_card'
		)
	).toBeVisible();
	// Confirm that the Cash App Pay is not visible on block-checkout page.
	await visitCheckout(page, true);
	await expect(
		await page.locator(
			'label[for="radio-control-wc-payment-method-options-square_credit_card"]'
		)
	).toBeVisible();
} );
