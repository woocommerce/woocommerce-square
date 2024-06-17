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
} from '../utils/helper';

test.beforeAll( 'Setup', async ( { baseURL } ) => {
	const browser = await chromium.launch();
	const page = await browser.newPage();

	// Set authorization transaction type.
	await page.goto(
		'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=square_credit_card'
	);
	await page
		.locator( '#woocommerce_square_credit_card_transaction_type' )
		.selectOption( { label: 'Authorization' } );
	await page
		.locator( '#woocommerce_square_credit_card_enable_paid_capture' )
		.check();
	await page.locator( '.woocommerce-save-button' ).click();

	// Create product.
	if ( ! ( await doesProductExist( baseURL, 'simple-product' ) ) ) {
		await createProduct( page, {
			name: 'Simple Product',
			regularPrice: '14.99',
			sku: 'simple-product',
		} );

		await expect( await page.getByText( 'Product published' ) ).toBeVisible();
	}

	await clearCart( page );
	await browser.close();
} );

test( 'Payment Gateway > Transaction Type > Authorization + Capture Paid Orders', async ( {
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
	await expect( page.locator( '#order_status' ) ).toHaveValue( 'wc-on-hold' );
	await expect(
		page.getByText(
			'Square Test Authorization Approved for an amount of'
		)
		.first()
	).toBeVisible();
	
	// Update order status to processing.
	await page.locator( '#order_status' ).selectOption("wc-processing");
	await page.locator( 'button.save_order' ).click();

	// Validate order payment captured.
	await expect(
		page.getByText(
			'Square Capture total of $14.99 Approved'
		)
		.first()
	).toBeVisible();
	await expect( page.locator( '#order_status' ) ).toHaveValue( 'wc-processing' );
} );
