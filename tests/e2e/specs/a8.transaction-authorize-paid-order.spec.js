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
	if ( ! ( await doesProductExist( baseURL, 'simple-product' ) ) ) {
		await createProduct( page, {
			name: 'Simple Product',
			regularPrice: '14.99',
			sku: 'simple-product',
		} );

		await expect( page.getByText( 'Product published' ) ).toBeVisible();
	}

	await clearCart( page );
	await browser.close();
} );

for ( const isSCA of [ true, false ] ) {
	const title = isSCA ? ' [SCA]:' : ' ';
	test(
		title +
			'Payment Gateway > Transaction Type > Authorization + Capture Paid Orders @general',
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
				'wc-on-hold'
			);
			await expect(
				page
					.getByText(
						'Square Test Authorization Approved for an amount of'
					)
					.first()
			).toBeVisible();

			// Update order status to processing.
			await page
				.locator( '#order_status' )
				.selectOption( 'wc-processing' );
			await page.locator( 'button.save_order' ).click();

			// Validate order payment captured.
			await expect(
				page
					.getByText( 'Square Capture total of $14.99 Approved' )
					.first()
			).toBeVisible();
			await expect( page.locator( '#order_status' ) ).toHaveValue(
				'wc-processing'
			);
		}
	);
}
