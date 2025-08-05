import { chromium } from 'playwright';
import { test, expect } from '@playwright/test';
import { addOneOrMoreProductToCart } from '@woocommerce/e2e-utils-playwright';
import {
	fillAddressFields,
	fillCreditCardFields,
	clearCart,
	gotoOrderEditPage,
	visitCheckout,
	placeOrder,
	savePaymentGatewaySettings,
} from '../utils/helper';

test.beforeAll( 'Setup', async ( { baseURL } ) => {
	const browser = await chromium.launch();
	const page = await browser.newPage();

	// Set capture transaction type.
	await page.goto(
		'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=square_credit_card'
	);
	await page
		.getByTestId( 'credit-card-transaction-type-field' )
		.selectOption( { label: 'Charge' } );

	await savePaymentGatewaySettings( page );

	await clearCart( page );
	await browser.close();
} );

for ( const isSCA of [ true, false ] ) {
	const title = isSCA ? ' [SCA]:' : ' ';
	test(
		title + 'Payment Gateway > Transaction Type > Authorization @general',
		async ( { page } ) => {
			await addOneOrMoreProductToCart( page, 'simple-product' );

			await visitCheckout( page, false );
			await fillAddressFields( page, false );
			await fillCreditCardFields( page, true, false, isSCA );
			await placeOrder( page, false, isSCA );

			await expect(
				page.locator( '.woocommerce-order-overview__total strong' )
			).toHaveText( '$14.99' );
			const orderId = await page
				.locator( '.woocommerce-order-overview__order strong' )
				.innerText();

			await gotoOrderEditPage( page, orderId );

			await expect( page.locator( '#order_status' ) ).toHaveValue(
				'wc-processing'
			);
			await expect(
				page.getByText(
					'Square Test Charge Approved for an amount of $14.99: Visa ending in '
				)
			).toBeVisible();
		}
	);
}
