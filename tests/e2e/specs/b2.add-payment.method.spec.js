import { test, expect } from '@playwright/test';

import { fillCreditCardFields } from '../utils/helper';

test( 'Payment Gateway - Add payment method', async ( { page } ) => {
	await page.goto( '/my-account/payment-methods' );
	await expect(
		await page.getByText( 'No saved methods found.' )
	).toBeVisible();
	await page.locator( '.woocommerce-MyAccount-content .button' ).click();
	await fillCreditCardFields( page );
	await page.locator( '#place_order' ).click();
	await expect(
		page.getByText( 'Nice! New payment method added: Visa ending in 1111' )
	).toBeVisible();
	await expect( await page.locator( 'tr.payment-method' ) ).toHaveCount( 1 );
	await expect(
		await page.locator(
			'tr.payment-method td.woocommerce-PaymentMethod span'
		)
	).toHaveText( '• • •1111' );
	await page.locator( '.button.delete' ).click();
	await expect(
		await page.getByText( 'Payment method deleted.' )
	).toBeVisible();
} );
