import { chromium } from 'playwright';
import { test, expect } from '@playwright/test';
import { addOneOrMoreProductToCart } from '@woocommerce/e2e-utils-playwright';
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
		await expect( page.getByText( 'Product published' ) ).toBeVisible();
	}

	await clearCart( page );
	await browser.close();
} );

for ( const isSCA of [ true, false ] ) {
	const title = isSCA ? ' [SCA]:' : ' ';
	test(
		title +
			'Payment Gateway > Transaction Type > Authorization + Virtual Only @general',
		async ( { page } ) => {
			await page.goto(
				'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=square_credit_card'
			);
			await page
				.getByTestId( 'credit-card-gateway-virtual-order-only-field' )
				.uncheck();
			await savePaymentGatewaySettings( page );
			await addOneOrMoreProductToCart( page, 'virtual-product' );

			await visitCheckout( page, false );
			await fillAddressFields( page, false );
			await fillCreditCardFields( page, true, false, isSCA );
			await placeOrder( page, false, isSCA );

			await expect(
				page.locator( '.woocommerce-order-overview__total strong' )
			).toHaveText( '$7.99' );
			const orderId = await page
				.locator( '.woocommerce-order-overview__order strong' )
				.innerText();

			await gotoOrderEditPage( page, orderId );

			await expect( page.locator( '#order_status' ) ).toHaveValue(
				'wc-on-hold'
			);
			await expect(
				page.getByText(
					'Square Test Authorization Approved for an amount of $7.99: Visa ending in '
				)
			).toBeVisible();
		}
	);

	test(
		title +
			'Payment Gateway > Transaction Type > Authorization + Virtual Only but charge @general',
		async ( { page } ) => {
			await page.goto(
				'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=square_credit_card'
			);

			await page
				.getByTestId( 'credit-card-gateway-virtual-order-only-field' )
				.check();

			await savePaymentGatewaySettings( page );

			await addOneOrMoreProductToCart( page, 'virtual-product' );

			await visitCheckout( page, false );
			await fillAddressFields( page, false );
			await fillCreditCardFields( page, null, false, isSCA );
			await placeOrder( page, false, isSCA );

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
					'Square Test Charge Approved for an amount of $7.99: Visa ending in '
				)
			).toBeVisible();
		}
	);
}
