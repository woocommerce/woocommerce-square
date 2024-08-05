import { test, expect } from '@playwright/test';
import { chromium } from 'playwright';

import {
	fillAddressFields,
	fillCreditCardFields,
	visitCheckout,
	doesProductExist,
	createProduct,
	placeOrder,
	deleteAllPaymentMethods,
	savePaymentGatewaySettings,
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

	await page.goto(
		'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=square_credit_card'
	);

	await page
		.getByTestId( 'credit-card-tokenization-field' )
		.check();

	await savePaymentGatewaySettings( page );
	await browser.close();
} );

const isBlockCheckout = [ true, false ];

for ( const isBlock of isBlockCheckout ) {
	const title = isBlock ? '[Block]:' : '[non-Block]:';

	test( title + 'Payment Gateway - Customer Profiles', async ( { page } ) => {
		await deleteAllPaymentMethods( page );
		await page.goto( '/product/simple-product' );
		await page.locator( '.single_add_to_cart_button' ).click();
		await visitCheckout( page, isBlock );

		await fillAddressFields( page, isBlock );
		await fillCreditCardFields( page, true, isBlock );

		if ( isBlock ) {
			await page
				.locator( '.wc-block-components-payment-methods__save-card-info label' )
				.click();
		} else {
			await page
				.locator( '#wc-square-credit-card-tokenize-payment-method' )
				.check();
			await page.waitForTimeout( 2000 );
		}

		await placeOrder( page, isBlock );
		await expect(
			await page.locator( '.entry-title' )
		).toHaveText( 'Order received' );

		await page.goto( '/my-account/payment-methods' );
		await expect( await page.locator( 'tr.payment-method' ) ).toHaveCount( 1 );
		await expect(
			await page.locator(
				'tr.payment-method td.woocommerce-PaymentMethod span'
			)
			.first()
		).toHaveText( '• • •1111' );
	} );

	test( title + 'Checkout using saved card', async ( { page } ) => {
		await page.goto( '/product/simple-product' );
		await page.locator( '.single_add_to_cart_button' ).click();
		await visitCheckout( page, isBlock );

		if ( isBlock ) {
			await page
				.locator( '.wc-block-checkout__payment-method .wc-block-components-radio-control' )
				.first()
				.locator( 'label' )
				.first()
				.click();
		} else {
			await page
				.locator( 'input[id^="wc-square-credit-card-payment-token-"]' )
				.first()
				.check();
		}
		await placeOrder( page, isBlock );
		await expect(
			await page.locator( '.woocommerce-thankyou-order-received' )
		).toHaveText( 'Thank you. Your order has been received.' );

		await deleteAllPaymentMethods( page );
	} );
}

