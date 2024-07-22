import { chromium } from 'playwright';
import { test, expect } from '@playwright/test';
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

test( 'Payment Gateway > Transaction Type > Authorization', async ( {
	page,
} ) => {
	await page.goto( '/product/simple-product' );
	await page.locator( '.single_add_to_cart_button' ).click();

	await visitCheckout( page, false );
	await fillAddressFields( page, false );
	await fillCreditCardFields( page, true, false );
	await placeOrder( page, false );

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
			'Square Test Charge Approved for an amount of $14.99: Visa ending in 1111'
		)
	).toBeVisible();
} );
