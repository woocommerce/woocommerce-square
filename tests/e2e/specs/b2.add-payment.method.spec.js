import { test, expect } from '@playwright/test';

import {
	deleteAllPaymentMethods,
	fillCreditCardFields,
	placeOrder,
} from '../utils/helper';

for ( const isSCA of [ true, false ] ) {
	const title = isSCA ? ' [SCA]:' : ' ';
	test(
		title + 'Payment Gateway - Add payment method @general',
		async ( { page } ) => {
			await deleteAllPaymentMethods( page );
			await page.goto( '/my-account/payment-methods' );
			await expect(
				page.getByText( 'No saved methods found.' )
			).toBeVisible();
			await page
				.locator( '.woocommerce-MyAccount-content .button' )
				.click();
			await fillCreditCardFields( page, true, false, isSCA );
			await placeOrder( page, false, isSCA );
			await expect(
				page.getByText(
					'Nice! New payment method added: Visa ending in '
				)
			).toBeVisible();
			await expect(
				await page.locator( 'tr.payment-method' )
			).toHaveCount( 1 );
			await expect(
				await page.locator(
					'tr.payment-method td.woocommerce-PaymentMethod span'
				)
			).toHaveText( isSCA ? '• • •1019' : '• • •1111' );
			await page.locator( '.button.delete' ).click();
			await expect(
				page.getByText( 'Payment method deleted.' )
			).toBeVisible();
			if ( isSCA ) {
				// TO AVOID ERROR: You cannot add a new payment method so soon after the previous one. Please wait for 20 seconds.
				await page.waitForTimeout( 20000 );
			}
		}
	);
}
