/**
 * External dependencies
 */
const { test, expect, devices, chromium } = require('@playwright/test');

/**
 * Internal dependencies
 */
import {
    createPreOrderProduct,
	fillAddressFields,
    fillCreditCardFields,
    placeOrder,
	visitCheckout,
    completePreOrder,
	saveCashAppPaySettings,
	gotoOrderEditPage,
	selectPaymentMethod,
	placeCashAppPayOrder,
} from '../utils/helper';
const iPhone = devices['iPhone 14 Pro Max'];

test.describe('Pre-Orders Tests', () => {
	test.beforeAll('Setup', async ({ baseURL }) => {
		const browser = await chromium.launch();
		const page = await browser.newPage();

		// Set authorization transaction type.
		await page.goto(
			'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=square_credit_card'
		);
		await page
			.locator( '#woocommerce_square_credit_card_transaction_type' )
			.selectOption( { label: 'Charge' } );
		await page.locator( '.woocommerce-save-button' ).click();

		// Set authorization transaction type.
		await saveCashAppPaySettings(page, {
			transactionType: 'charge',
		});
	});

	test('[Charge upon release] Square Credit Card should work with Pre-Orders', async ({
        page,
	}) => {
        const isBlock = true;
		const productId = await createPreOrderProduct(page, {
			whenToCharge: 'upon_release',
		});
		await page.goto('/?p=' + productId);
		await page.locator('.single_add_to_cart_button').click();
		await expect(
			page.getByRole('link', { name: 'View cart' }).first()
		).toBeVisible();
		await visitCheckout(page, isBlock);
        await fillAddressFields(page, isBlock);
        await fillCreditCardFields( page, true, isBlock );
		await placeOrder(page, isBlock);

        // verify order received page
        await expect(
            page.getByRole('heading', { name: 'Order received' })
        ).toBeVisible();
        const orderId = await page
            .locator('li.woocommerce-order-overview__order strong')
            .textContent();

		// Verify order status is Pre-Ordered.
		await page.goto(`/wp-admin/post.php?post=${orderId}&action=edit`);
		const orderStatus = await page.locator(
			'select[name="order_status"]'
		);
		await expect(await orderStatus.evaluate((el) => el.value)).toBe(
			'wc-pre-ordered'
		);

        // Complete pre-order.
		await completePreOrder(page, orderId);

		// Verify order status is Processing.
		await page.goto(`/wp-admin/post.php?post=${orderId}&action=edit`);
		await expect(
			await page.locator('#order_status').evaluate((el) => el.value)
		).toEqual('wc-processing');

        // Verify order note is added.
        await expect(
			page
				.locator('#woocommerce-order-notes ul.order_notes li', {
					hasText: 'Pre-Order Release Payment Approved:',
				})
				.first()
		).toBeVisible();
	});

	test('[Upfront Charge] Square Credit Card should work with Pre-Orders', async ({
		page,
	}) => {
        const isBlock = true;
		const productId = await createPreOrderProduct(page, {
			whenToCharge: 'upfront',
		});
		await page.goto('/?p=' + productId);
		await page.locator('.single_add_to_cart_button').click();
		await expect(
			page.getByRole('link', { name: 'View cart' }).first()
		).toBeVisible();
		await visitCheckout(page, isBlock);
        await fillAddressFields(page, isBlock);
        await fillCreditCardFields( page, true, isBlock );
		await placeOrder(page, isBlock);

        // verify order received page
        await expect(
            page.getByRole('heading', { name: 'Order received' })
        ).toBeVisible();
        const orderId = await page
            .locator('li.woocommerce-order-overview__order strong')
            .textContent();



		// Verify order status is Pre-Ordered.
		await page.goto(
            `/wp-admin/post.php?post=${orderId}&action=edit`
        );
		const orderStatus = await page
			.locator('select[name="order_status"]')
			.evaluate((el) => el.value);
		await expect(orderStatus).toBe('wc-pre-ordered');

		// Complete pre-order.
		await completePreOrder(page, orderId);

		// Verify order status is processing.
		await page.goto(`/wp-admin/post.php?post=${orderId}&action=edit`);
		await expect(
			await page.locator('#order_status').evaluate((el) => el.value)
		).toEqual('wc-processing');
	});

	test('[Upfront Charge] Square Cash App Pay should work with Pre-Orders', async ({
		browser,
		page: adminPage,
	}) => {
		const context = await browser.newContext({
			...iPhone,
		});
		const page = await context.newPage();
        const isBlock = true;
		const productId = await createPreOrderProduct(page, {
			whenToCharge: 'upfront',
		});
		await page.goto('/?p=' + productId);
		await page.locator('.single_add_to_cart_button').click();
		await expect(
			page.getByRole('link', { name: 'View cart' }).first()
		).toBeVisible();
		await visitCheckout(page, isBlock);
		await fillAddressFields(page, isBlock);
		await selectPaymentMethod(page, 'square_cash_app_pay', isBlock);
		const orderId = await placeCashAppPayOrder(page, isBlock);

		await gotoOrderEditPage(adminPage, orderId);
		await expect(adminPage.locator('#order_status')).toHaveValue(
			'wc-pre-ordered'
		);

		// Complete pre-order.
		await completePreOrder(adminPage, orderId);

		// Verify order status is processing.
		await gotoOrderEditPage(adminPage, orderId);
		await expect(adminPage.locator('#order_status')).toHaveValue(
			'wc-processing'
		);
	});
});