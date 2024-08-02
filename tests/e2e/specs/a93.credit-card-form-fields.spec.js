import { test, expect } from '@playwright/test';
import { chromium } from 'playwright';

import {
	visitCheckout,
	doesProductExist,
	createProduct
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

test( 'Verify square credit card form fields', async ( { page } ) => {
	await page.goto( '/product/simple-product' );
	await page.locator( '.single_add_to_cart_button' ).click();
	// Confirm that the Credit card is not visible on checkout page.
	await visitCheckout(page, true);
	
	if ( await page.locator( '#radio-control-wc-payment-method-options-square_credit_card' ).isVisible() ) {
		await page.locator( '#radio-control-wc-payment-method-options-square_credit_card' ).check();
	}

	const frame = '.sq-card-iframe-container .sq-card-component';
	
	// Fill credit card details.
	const creditCardInputField = await page
		.frameLocator( frame )
		.locator('#cardNumber');
	const expiryDateInputField = await page
		.frameLocator( frame )
		.locator('#expirationDate');
	const cvvInputField = await page
		.frameLocator( frame )
		.locator('#cvv');
	const postalCodeInputField = await page
		.frameLocator( frame )
		.locator('#postalCode');

	await expect(creditCardInputField).toBeVisible();
	await expect(expiryDateInputField).toBeVisible();
	await expect(cvvInputField).toBeVisible();
	
	await creditCardInputField.fill('4111 1111 1111 1111');
	await page.waitForTimeout(1000);
	await expect(postalCodeInputField).toBeVisible();

	await creditCardInputField.fill('3569 9900 1009 5841');
	await page.waitForTimeout(1000);
	await expect(postalCodeInputField).toBeDisabled();
} );
