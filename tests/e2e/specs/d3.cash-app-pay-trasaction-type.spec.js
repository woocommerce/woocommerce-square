import { test, expect, devices, chromium } from '@playwright/test';
import {
	clearCart,
	createProduct,
	doesProductExist,
	fillAddressFields,
	gotoOrderEditPage,
	placeCashAppPayOrder,
	saveCashAppPaySettings,
	selectPaymentMethod,
	visitCheckout,
} from '../utils/helper';
const iPhone = devices['iPhone 14 Pro Max'];

test.describe('Cash App Pay Tests', () => {
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

		// Create Virtual product.
		if (!(await doesProductExist(baseURL, 'virtual-product'))) {
			await createProduct(
				page,
				{
					name: 'Virtual Product',
					regularPrice: '7.99',
					sku: 'virtual-product',
				},
				false
			);
			await page.locator('#_virtual').check();
			await page.waitForTimeout(2000);
			await page.locator('#publish').click();
			await expect(
				await page.getByText('Product published')
			).toBeVisible();
		}

		// Set authorization transaction type.
		await saveCashAppPaySettings(page, {
			transactionType: 'authorization',
		});

		await clearCart(page);
		await browser.close();
	});

	test.afterAll( async () => {
		const browser = await chromium.launch();
		const page = await browser.newPage();

		// Set authorization transaction type.
		await saveCashAppPaySettings(page, {
			transactionType: 'charge',
		});

		await clearCart( page );
		await browser.close();
	} );

	const isBlockCheckout = [true, false];

	for (const isBlock of isBlockCheckout) {
		const title = isBlock ? '[Block]:' : '[non-Block]:';

		test(
			title +
				'Store owner should able to set transaction type "Authorization" - @foundational',
			async ({ browser }) => {
				const context = await browser.newContext({
					...iPhone,
				});
				const page = await context.newPage();
				await page.goto('/product/simple-product');
				await page.locator('.single_add_to_cart_button').click();
				await visitCheckout(page, isBlock);
				await fillAddressFields(page, isBlock);
				await selectPaymentMethod(page, 'square_cash_app_pay', isBlock);
				const orderId = await placeCashAppPayOrder(page, isBlock);

				await gotoOrderEditPage(page, orderId);
				await expect(page.locator('#order_status')).toHaveValue(
					'wc-on-hold'
				);
				await expect(
					page.getByText(
						'Cash App Pay (Square) Test Authorization Approved for an amount of'
					)
				).toBeVisible();

				page.on('dialog', dialog => dialog.accept());
				await page.locator('button.wc-square-cash-app-pay-capture').click();

				// Verify order status and capture status.
				await expect(page.locator('#order_status')).toHaveValue(
					'wc-processing'
				);
				await expect(
					page.getByText('Cash App Pay (Square) Capture total of')
				).toBeVisible();
			}
		);
	}

	test('Store owner should able to set transaction type "Authorization" and charge virtual-only orders - @foundational', async ({
		browser,
	}) => {
		const context = await browser.newContext({
			...iPhone,
		});
		const isBlock = true;
		const page = await context.newPage();

		// Set authorization transaction type.
		await saveCashAppPaySettings(page, {
			transactionType: 'authorization',
			chargeVirtualOrders: true,
		});

		await page.goto('/product/virtual-product');
		await page.locator('.single_add_to_cart_button').click();
		await visitCheckout(page, isBlock);
		await fillAddressFields(page, isBlock);
		await selectPaymentMethod(page, 'square_cash_app_pay', isBlock);
		const orderId = await placeCashAppPayOrder(page, isBlock);

		await gotoOrderEditPage(page, orderId);
		await expect(page.locator('#order_status')).toHaveValue(
			'wc-processing'
		);
		await expect(
			page.getByText(
				'Cash App Pay (Square) Test Charge Approved for an amount of'
			)
		).toBeVisible();	
	});

	test('Store owner should able to set transaction type "Authorization" and capture paid orders - @foundational', async ({
		browser,
	}) => {
		const context = await browser.newContext({
			...iPhone,
		});
		const isBlock = true;
		const page = await context.newPage();

		// Set authorization transaction type.
		await saveCashAppPaySettings(page, {
			transactionType: 'authorization',
			capturePaidOrders: true,
		});

		await page.goto('/product/simple-product');
		await page.locator('.single_add_to_cart_button').click();
		await visitCheckout(page, isBlock);
		await fillAddressFields(page, isBlock);
		await selectPaymentMethod(page, 'square_cash_app_pay', isBlock);
		const orderId = await placeCashAppPayOrder(page, isBlock);

		await gotoOrderEditPage(page, orderId);
		await expect(page.locator('#order_status')).toHaveValue('wc-on-hold');
		await expect(
			page.getByText(
				'Cash App Pay (Square) Test Authorization Approved for an amount of'
			)
		).toBeVisible();

		// Update order status to processing.
		await page.locator('#order_status').selectOption('wc-processing');
		await page.locator('button.save_order').click();

		// Verify order status.
		await expect(page.locator('#order_status')).toHaveValue(
			'wc-processing'
		);
		await expect(
			page.getByText('Cash App Pay (Square) Capture total of')
		).toBeVisible();
	});
});
