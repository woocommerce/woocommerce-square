import { test, expect, devices, chromium } from '@playwright/test';
import {
	clearCart,
	createProduct,
	doSquareRefund,
	doesProductExist,
	fillAddressFields,
	fillGiftCardField,
	gotoOrderEditPage,
	placeCashAppPayOrder,
	saveCashAppPaySettings,
	selectPaymentMethod,
	visitCheckout,
} from '../utils/helper';
const iPhone = devices['iPhone 14 Pro Max'];

test.describe('Cash App Pay - Gift Card Tests', () => {
	const isBlock = false;
	test.beforeAll('Setup', async ({ baseURL }) => {
		const browser = await chromium.launch();
		const page = await browser.newPage();

		// Create a product if it doesn't exist.
		if (!(await doesProductExist(baseURL, 'simple-product'))) {
			await createProduct(page, {
				name: 'Simple Product',
				regularPrice: '14.99',
				sku: 'simple-product',
			});

			await expect(
				await page.getByText('Product published')
			).toBeVisible();
		}

		// Set charge transaction type.
		await saveCashAppPaySettings(page, {
			transactionType: 'charge',
		});

		await clearCart(page);
		await browser.close();
	});

	test.afterAll( async () => {
		const browser = await chromium.launch();
		const page = await browser.newPage();

		// Set charge transaction type.
		await saveCashAppPaySettings(page, {
			transactionType: 'charge',
		});

		await clearCart( page );
		await browser.close();
	} );

	test('Customer should able to split payment between a Square Gift and a Cash App Pay - @foundational', async ({
		browser,
	}) => {
		const context = await browser.newContext({
			...iPhone,
		});
		const page = await context.newPage();
		await page.goto('/product/simple-product');
		await page.locator('.single_add_to_cart_button').click();
		await visitCheckout(page, isBlock);
		await fillAddressFields(page, isBlock);
		await fillGiftCardField( page );

		await page.locator( '#square-gift-card-apply-btn' ).click();
		await expect( page.locator( '.wc_payment_methods' ) ).toHaveCount( 1, { timeout: 80000 } );

		await expect(
			await page.getByText( '$1.00 will be applied from the gift card.' )
		).toBeVisible();
		await expect(
			page.locator( '.square-gift-card-response__content' )
		).toContainText(
			"Your gift card doesn't have enough funds to cover the order total. The remaining amount of $13.99 would need to be paid with a credit card or cash app pay."
		);
		
		await selectPaymentMethod(page, 'square_cash_app_pay', isBlock);
		const orderId = await placeCashAppPayOrder(page, isBlock);

		await expect(
			page.locator( '.woocommerce-order-overview__payment-method strong' )
		).toHaveText( 'Square Gift Card ($1.00) and Cash App Pay ($13.99)' );
		await expect(
			page.getByText(
				'$14.99 — Total split between gift card ($1.00) and cash app pay ($13.99)'
			)
		).toBeVisible();
	});

	test('Store owner should able to refund a order paid by split payment - @foundational', async ({
		browser,
	}) => {
		const context = await browser.newContext({
			...iPhone,
		});
		const page = await context.newPage();
		await page.goto('/product/simple-product');
		await page.locator('.single_add_to_cart_button').click();
		await visitCheckout(page, isBlock);
		await fillAddressFields(page, isBlock);
		await fillGiftCardField( page );

		await page.locator( '#square-gift-card-apply-btn' ).click();
		await expect( page.locator( '.wc_payment_methods' ) ).toHaveCount( 1, { timeout: 80000 } );

		await selectPaymentMethod(page, 'square_cash_app_pay', isBlock);
		const orderId = await placeCashAppPayOrder(page, isBlock);

		await gotoOrderEditPage( page, orderId );
		page.on('dialog', dialog => dialog.accept());
		await doSquareRefund( page, '0.25' );
		await expect( await page.getByText( 'Square Gift Card Refund in the amount of $0.25 of total $0.25 approved.' ) ).toBeVisible();
		await expect( page.locator( '#order_status' ) ).toHaveValue( 'wc-processing' );

		await doSquareRefund( page, '1.75' );
		await expect( await page.getByText( 'Square Gift Card Refund in the amount of $0.75 of total $1.75 approved.' ) ).toBeVisible();
		await expect( await page.getByText( 'Cash App Pay (Square) Refund in the amount of $1.00 of total $1.75 approved.' ) ).toBeVisible();
		await expect( page.locator( '#order_status' ) ).toHaveValue( 'wc-processing' );

		await doSquareRefund( page, '12.99' );
		await expect( page.locator( '#order_status' ) ).toHaveValue( 'wc-refunded' );
	});

	test('Store owner should able to capture order paid with split payment - @foundational', async ({
		browser,
	}) => {
		const context = await browser.newContext({
			...iPhone,
		});
		const page = await context.newPage();
		// Set authorization transaction type.
		await saveCashAppPaySettings(page, {
			transactionType: 'authorization',
		});

		await page.goto('/product/simple-product');
		await page.locator('.single_add_to_cart_button').click();
		await visitCheckout(page, isBlock);
		await fillAddressFields(page, isBlock);
		await fillGiftCardField( page );

		await page.locator( '#square-gift-card-apply-btn' ).click();
		await expect( page.locator( '.wc_payment_methods' ) ).toHaveCount( 1, { timeout: 80000 } );

		await selectPaymentMethod(page, 'square_cash_app_pay', isBlock);
		const orderId = await placeCashAppPayOrder(page, isBlock);

		await gotoOrderEditPage(page, orderId);
		await expect(page.locator('#order_status')).toHaveValue('wc-on-hold');
		
		page.on('dialog', dialog => dialog.accept());
		await page.locator('button.wc-square-cash-app-pay-capture').click();

		// Verify order status and capture status.
		await expect(page.locator('#order_status')).toHaveValue(
			'wc-processing'
		);
		await expect(
			page.getByText('Cash App Pay (Square) Capture total of')
		).toBeVisible();
	});
});
