import { test, expect, devices, chromium } from '@playwright/test';
import {
	clearCart,
	createProduct,
	doSquareRefund,
	doesProductExist,
	fillAddressFields,
	gotoOrderEditPage,
	placeCashAppPayOrder,
	saveCashAppPaySettings,
	selectPaymentMethod,
	visitCheckout,
	waitForUnBlock,
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
		await browser.close();
	});

	test('Store owner can see Cash App Pay in payment methods list - @foundational', async ({
		page,
	}) => {
		await page.goto('/wp-admin/admin.php?page=wc-settings&tab=checkout');
		const creditCard = await page.locator(
			'table.wc_gateways tr[data-gateway_id="square_cash_app_pay"]'
		);
		await expect(creditCard).toBeVisible();
		await expect(creditCard.locator('td.name a')).toContainText(
			'Cash App Pay (Square)'
		);
	});

	test('Store owner can configure Cash App Pay payment gateway - @foundational', async ({
		page,
	}) => {
		await saveCashAppPaySettings(page, {
			enabled: false,
		});

		await page.goto('/product/simple-product');
		await page.locator('.single_add_to_cart_button').click();

		// Confirm that the Cash App Pay is not visible on checkout page.
		await visitCheckout(page, false);
		await fillAddressFields(page, false);
		await expect(
			await page.locator(
				'ul.wc_payment_methods li.payment_method_square_cash_app_pay'
			)
		).not.toBeVisible();
		// Confirm that the Cash App Pay is not visible on block-checkout page.
		await visitCheckout(page, true);
		await expect(
			await page.locator(
				'label[for="radio-control-wc-payment-method-options-square_cash_app_pay"]'
			)
		).not.toBeVisible();

		const cashAppTitle = 'Cash App Pay TEST';
		const cashAppDescription = 'Cash App Pay TEST Description';
		await saveCashAppPaySettings(page, {
			enabled: true,
			title: cashAppTitle,
			description: cashAppDescription,
		});

		// Confirm that the Cash App Pay is visible on checkout page.
		await visitCheckout(page, false);
		const paymentMethod = await page.locator(
			'ul.wc_payment_methods li.payment_method_square_cash_app_pay'
		);
		await expect(paymentMethod).toBeVisible();
		await selectPaymentMethod(page, 'square_cash_app_pay', false);
		await expect(paymentMethod.locator('label').first()).toContainText(
			cashAppTitle
		);
		await expect(
			paymentMethod
				.locator('.payment_method_square_cash_app_pay p', {
					hasText: cashAppDescription,
				})
				.first()
		).toBeVisible();

		// Confirm that the Cash App Pay is visible on block-checkout page.
		await visitCheckout(page, true);
		const cashAppMethod = await page.locator(
			'label[for="radio-control-wc-payment-method-options-square_cash_app_pay"]'
		);
		await expect(cashAppMethod).toBeVisible();
		await expect(cashAppMethod).toContainText(cashAppTitle);
		await selectPaymentMethod(page, 'square_cash_app_pay', true);
		await expect(
			page
				.locator(
					'.wc-block-components-radio-control-accordion-content p',
					{
						hasText: cashAppDescription,
					}
				)
				.first()
		).toBeVisible();
	});

	test('Store owner can configure Cash App Pay Button Appearance - @foundational', async ({
		page,
	}) => {
		await saveCashAppPaySettings(page, {
			enabled: true,
			buttonTheme: 'light',
			buttonShape: 'round',
		});

		// Confirm button styles on checkout page.
		await visitCheckout(page, false);
		const paymentMethod = await page.locator(
			'ul.wc_payment_methods li.payment_method_square_cash_app_pay'
		);
		await expect(paymentMethod).toBeVisible();
		await selectPaymentMethod(page, 'square_cash_app_pay', false);
		const cashAppPayButton = await page
			.locator('#wc-square-cash-app')
			.getByTestId('cap-btn');
		await expect(cashAppPayButton).toBeVisible();
		await expect(cashAppPayButton).toHaveClass(/rounded-3xl/);
		await expect(cashAppPayButton).toHaveClass(/bg-white/);

		// Confirm button styles on block-checkout page.
		await visitCheckout(page, true);
		const cashAppMethod = await page.locator(
			'label[for="radio-control-wc-payment-method-options-square_cash_app_pay"]'
		);
		await expect(cashAppMethod).toBeVisible();
		await selectPaymentMethod(page, 'square_cash_app_pay', true);
		const cashAppButton = await page
			.locator('#wc-square-cash-app-pay')
			.getByTestId('cap-btn');
		await expect(cashAppButton).toBeVisible();
		await expect(cashAppButton).toHaveClass(/rounded-3xl/);
		await expect(cashAppButton).toHaveClass(/bg-white/);

		await saveCashAppPaySettings(page, {
			enabled: true,
			buttonTheme: 'dark',
			buttonShape: 'semiround',
		});

		// Confirm button styles on checkout page.
		await visitCheckout(page, false);
		const payMethod = await page.locator(
			'ul.wc_payment_methods li.payment_method_square_cash_app_pay'
		);
		await expect(payMethod).toBeVisible();
		await selectPaymentMethod(page, 'square_cash_app_pay', false);
		const button = await page
			.locator('#wc-square-cash-app')
			.getByTestId('cap-btn');
		await expect(button).toBeVisible();
		await expect(button).toHaveClass(/rounded-md/);
		await expect(button).toHaveClass(/bg-black/);

		// Confirm button styles on block-checkout page.
		await visitCheckout(page, true);
		const blockPayMethod = await page.locator(
			'label[for="radio-control-wc-payment-method-options-square_cash_app_pay"]'
		);
		await expect(blockPayMethod).toBeVisible();
		await selectPaymentMethod(page, 'square_cash_app_pay', true);
		const buttonBlock = await page
			.locator('#wc-square-cash-app-pay')
			.getByTestId('cap-btn');
		await expect(buttonBlock).toBeVisible();
		await expect(buttonBlock).toHaveClass(/rounded-md/);
		await expect(buttonBlock).toHaveClass(/bg-black/);
	});

	test('Cash App Pay should be only available for US based sellers - @foundational', async ({
		page,
	}) => {
		await page.goto('/wp-admin/admin.php?page=wc-settings&tab=general');
		await page
			.locator('select[name="woocommerce_default_country"]')
			.selectOption('IN:GJ');
		await page.locator('.woocommerce-save-button').click();

		await expect(
			page
				.locator('.notice.notice-error p', {
					hasText: /Cash App Pay/,
				})
				.first()
		).toContainText(
			'Your base country is IN, but Cash App Pay can’t accept transactions from merchants outside of US.'
		);

		// Confirm that the Cash App Pay is not visible on block-checkout page.
		await visitCheckout(page, true);
		await expect(
			await page.locator(
				'label[for="radio-control-wc-payment-method-options-square_cash_app_pay"]'
			)
		).not.toBeVisible();

		await page.goto('/wp-admin/admin.php?page=wc-settings&tab=general');
		await page
			.locator('select[name="woocommerce_default_country"]')
			.selectOption('US:CA');
		await page.locator('.woocommerce-save-button').click();
	});

	test('Cash App Pay should be only available for US based buyers - @foundational', async ({
		page,
	}) => {
		// Confirm that the Cash App Pay is not visible on checkout page.
		await visitCheckout(page, false);
		await fillAddressFields(page, false);

		// non-US buyer.
		await page.locator('#billing_country').selectOption('IN');
		await page.locator('#billing_country').blur();
		await waitForUnBlock(page);
		const payMethod = await page.locator(
			'ul.wc_payment_methods li.payment_method_square_cash_app_pay'
		);
		await expect(payMethod).not.toBeVisible();

		// US based buyer.
		await fillAddressFields(page, false);
		await expect(payMethod).toBeVisible();

		// Confirm that the Cash App Pay is not visible on block-checkout page.
		await visitCheckout(page, true);
		await fillAddressFields(page, true);
		await page.locator('#billing-country').selectOption('IN');
		await page.locator('#billing-country').blur();
		await page.waitForTimeout(1500);
		const blockPayMethod = await page.locator(
			'label[for="radio-control-wc-payment-method-options-square_cash_app_pay"]'
		);
		await expect(blockPayMethod).not.toBeVisible();

		// US based buyer.
		await fillAddressFields(page, true);
		await expect(blockPayMethod).toBeVisible();
	});

	test('Cash App Pay should be only available for USD currency - @foundational', async ({
		page,
	}) => {
		await page.goto('/wp-admin/admin.php?page=wc-settings&tab=general');
		await page
			.locator('select[name="woocommerce_currency"]')
			.selectOption('INR');
		await page.locator('.woocommerce-save-button').click();
		
		// Confirm that the Cash App Pay is not visible on checkout page.
		await visitCheckout(page, false);
		await fillAddressFields(page, false);
		await expect(
			await page.locator(
				'ul.wc_payment_methods li.payment_method_square_cash_app_pay'
			)
		).not.toBeVisible();
		// Confirm that the Cash App Pay is not visible on block-checkout page.
		await visitCheckout(page, true);
		await expect(
			await page.locator(
				'label[for="radio-control-wc-payment-method-options-square_cash_app_pay"]'
			)
		).not.toBeVisible();

		await page.goto('/wp-admin/admin.php?page=wc-settings&tab=general');
		await page
			.locator('select[name="woocommerce_currency"]')
			.selectOption('USD');
		await page.locator('.woocommerce-save-button').click();
	});

	const isBlockCheckout = [true, false];

	for (const isBlock of isBlockCheckout) {
		const title = isBlock ? '[Block]:' : '[non-Block]:';

		test(
			title + 'Customers can pay using Cash App Pay - @foundational',
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
					'wc-processing'
				);
				await expect(
					page.getByText(
						'Cash App Pay (Square) Test Charge Approved for an amount of'
					)
				).toBeVisible();
			}
		);
	}

	test( '[Block]: Customers can pay using Cash App Pay after decline transcation once - @foundational',
		async ({ browser }) => {
			test.slow();
			const context = await browser.newContext({
				...iPhone,
			});
			const page = await context.newPage();
			await page.goto('/product/simple-product');
			await page.locator('.single_add_to_cart_button').click();
			await visitCheckout(page, true);
			await fillAddressFields(page, true);
			await selectPaymentMethod(page, 'square_cash_app_pay', true);
			// Decline transcation once.
			await placeCashAppPayOrder(page, true, true);
			await page.waitForLoadState('networkidle');
			const orderId = await placeCashAppPayOrder(page, true);

			await gotoOrderEditPage(page, orderId);
			await expect(page.locator('#order_status')).toHaveValue(
				'wc-processing'
			);
			await expect(
				page.getByText(
					'Cash App Pay (Square) Test Charge Approved for an amount of'
				)
			).toBeVisible();
		}
	);

	test('Store owners can fully refund Cash App Pay orders - @foundational', async ({
		browser,
	}) => {
		const isBlock = true;
		const context = await browser.newContext({
			...iPhone,
		});
		const page = await context.newPage();
		await clearCart( page );
		await page.goto('/product/simple-product');
		page.on('dialog', dialog => dialog.accept());
		await page.locator('.single_add_to_cart_button').click();
		await visitCheckout(page, isBlock);
		await fillAddressFields(page, isBlock);
		await selectPaymentMethod(page, 'square_cash_app_pay', isBlock);
		const orderId = await placeCashAppPayOrder(page, isBlock);
		await gotoOrderEditPage(page, orderId);
		await doSquareRefund( page, '14.99' );
		await expect( page.locator( '#order_status' ) ).toHaveValue( 'wc-refunded' );
		await expect( await page.getByText( 'Cash App Pay (Square) Refund in the amount of $14.99 approved' ) ).toBeVisible();
		await expect( await page.getByText( 'Cash App Pay (Square) Order completely refunded.' ) ).toBeVisible();
	});

	test('Store owners can partially refund Cash App Pay orders - @foundational', async ({
		browser,
	}) => {
		const isBlock = true;
		const context = await browser.newContext({
			...iPhone,
		});
		const page = await context.newPage();
		await page.goto('/product/simple-product');
		page.on('dialog', dialog => dialog.accept());
		await page.locator('.single_add_to_cart_button').click();
		await visitCheckout(page, isBlock);
		await fillAddressFields(page, isBlock);
		await selectPaymentMethod(page, 'square_cash_app_pay', isBlock);
		const orderId = await placeCashAppPayOrder(page, isBlock);
		await gotoOrderEditPage(page, orderId);
		await doSquareRefund( page, '1' );
		await expect( await page.getByText( 'Cash App Pay (Square) Refund in the amount of $1.00 approved' ) ).toBeVisible();
		await expect( page.locator( '#order_status' ) ).toHaveValue( 'wc-processing' );

		await doSquareRefund( page, '13.99' );
		await expect( page.locator( '#order_status' ) ).toHaveValue( 'wc-refunded' );
		await expect( await page.getByText( 'Cash App Pay (Square) Order completely refunded.' ) ).toBeVisible();
	});
});
