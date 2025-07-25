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
	placeOrder,
	visitCheckout,
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
		.getByTestId( 'credit-card-gateway-virtual-order-only-field' )
		.uncheck();

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

test.afterAll( async () => {
	const browser = await chromium.launch();
	const page = await browser.newPage();

	await clearCart( page );
	await browser.close();
} );

const isBlockCheckout = [ true, false ];

for ( const isBlock of isBlockCheckout ) {
	const title = isBlock ? '[Block]:' : '[non-Block]:';

	for ( const isSCA of [ true, false ] ) {
		const subTitle = isSCA ? ' [SCA]:' : ' ';

		test(
			title +
				subTitle +
				'Payment Gateway > Transaction Type > Authorization @general',
			async ( { page } ) => {
				await addOneOrMoreProductToCart( page, 'simple-product' );

				await visitCheckout( page, isBlock );
				await fillAddressFields( page, isBlock );
				await fillCreditCardFields( page, true, isBlock, isSCA );
				await placeOrder( page, isBlock, isSCA );

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
					page.getByText(
						'Square Test Authorization Approved for an amount of $14.99: Visa ending in '
					)
				).toBeVisible();

				page.on( 'dialog', ( dialog ) => dialog.accept() );
				await page
					.locator( 'button.wc-square-credit-card-capture' )
					.click();

				// Verify order status and capture status.
				await expect( page.locator( '#order_status' ) ).toHaveValue(
					'wc-processing'
				);
				await expect(
					page.getByText( 'Square Capture total of' )
				).toBeVisible();
			}
		);
	}
}
