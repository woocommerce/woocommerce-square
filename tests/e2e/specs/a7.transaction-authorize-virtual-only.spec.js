import { chromium } from 'playwright';
import { test, expect } from '@playwright/test';
import {
	createProduct,
	doesProductExist,
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

	// Set authorization transaction type.
	await page.goto(
		'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=square_credit_card'
	);
	await page
		.getByTestId( 'credit-card-transaction-type-field' )
		.selectOption( { label: 'Authorization' } );
	await page
		.getByTestId( 'credit-card-gateway-capture-paid-orders-field' )
		.check();

	await savePaymentGatewaySettings( page );

	// Create product.
	if ( ! ( await doesProductExist( baseURL, 'virtual-product' ) ) ) {
		await createProduct(
			page,
			{
				name: 'Virtual Product',
				regularPrice: '7.99',
				sku: 'virtual-product',
			},
			false
		);
		await page.locator( '#_virtual' ).check();
		await page.waitForTimeout( 2000 );
		await page.locator( '#publish' ).click();
		await expect( await page.getByText( 'Product published' ) ).toBeVisible();
	}

	await clearCart( page );
	await browser.close();
} );

test( 'Payment Gateway > Transaction Type > Authorization + Virtual Only', async ( {
	page,
} ) => {
	await page.goto( '/product/virtual-product' );
	await page.locator( '.single_add_to_cart_button' ).click();

	await visitCheckout( page, false );
	await fillAddressFields( page, false );
	await fillCreditCardFields( page, null, false );
	await placeOrder( page, false );

	await expect(
		page.locator( '.woocommerce-order-overview__total strong' )
	).toHaveText( '$7.99' );
	const orderId = await page
		.locator( '.woocommerce-order-overview__order strong' )
		.innerText();

	await gotoOrderEditPage( page, orderId );

	await expect( page.locator( '#order_status' ) ).toHaveValue(
		'wc-processing'
	);
	await expect(
		page.getByText(
			'Square Test Charge Approved for an amount of $7.99: Visa ending in 1111'
		)
	).toBeVisible();
} );
