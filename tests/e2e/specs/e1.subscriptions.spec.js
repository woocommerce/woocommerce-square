/**
 * External dependencies
 */
const { test, expect, chromium } = require('@playwright/test');

/**
 * Internal dependencies
 */
import {
	deleteSessions,
    fillAddressFields,
    fillCreditCardFields,
    placeOrder,
	visitCheckout,
	gotoOrderEditPage,
	saveCashAppPaySettings,
	renewSubscription,
} from '../utils/helper';

test.describe('Subscriptions Tests', () => {
	test.beforeAll('Setup', async () => {
		const browser = await chromium.launch();
		const page = await browser.newPage();

		// Set authorization transaction type.
		await page.goto(
			'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=square_credit_card'
		);
		await page
			.locator( '#woocommerce_square_credit_card_transaction_type' )
			.selectOption( { label: 'Charge' } );
		await page
			.locator( '#woocommerce_square_credit_card_tokenization' )
			.check();
		await page.locator( '.woocommerce-save-button' ).click();
		await page.waitForEvent( 'load' );
	});

	test.beforeEach(async ({ page }) => {
		await deleteSessions( page );
	});

	const isBlockCheckout = [ true, false ];

	for ( const isBlock of isBlockCheckout ) {
		const title = isBlock ? '[Block]: ' : '[non-Block]: ';

		test(title + 'Customer can sign up to subscription using Square CreditCard payment gateway', async ({
			page,
		}) => {
			await page.goto( '/product/simple-subscription-product' );
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

			await gotoOrderEditPage(page, orderId);
			await expect(page.locator('#order_status')).toHaveValue(
				'wc-processing'
			);

			await page
				.locator('.woocommerce_subscriptions_related_orders tr td a')
				.first()
				.click();

			await expect(page.locator('#order_status')).toHaveValue(
				'wc-active'
			);

			// Test subscription renewal
			await renewSubscription(page);
		});

		test(title + 'Customer can sign up to subscription using Saved CreditCard', async ({
			page,
		}) => {
			await page.goto( '/product/simple-subscription-product' );
			await page.locator('.single_add_to_cart_button').click();
			await expect(
				page.getByRole('link', { name: 'View cart' }).first()
			).toBeVisible();
			await visitCheckout(page, isBlock);

			await page
				.locator( '.wc-block-checkout__payment-method .wc-block-components-radio-control' )
				.locator( 'input.wc-block-components-radio-control__input' )
				.first()
				.check();
			await placeOrder(page, isBlock);

			// verify order received page
			await expect(
				page.getByRole('heading', { name: 'Order received' })
			).toBeVisible();
			const orderId = await page
				.locator('li.woocommerce-order-overview__order strong')
				.textContent();

			await gotoOrderEditPage(page, orderId);
			await expect(page.locator('#order_status')).toHaveValue(
				'wc-processing'
			);

			await page
				.locator('.woocommerce_subscriptions_related_orders tr td a')
				.first()
				.click();

			await expect(page.locator('#order_status')).toHaveValue(
				'wc-active'
			);

			// Test subscription renewal
			await renewSubscription(page);
		});

		test(title + 'Customer can early renew the subscription using Square CreditCard', async ({
			page,
		}) => {
			await page.goto( '/my-account/subscriptions/' );
			const subscriptionId = await page.locator('table.my_account_subscriptions tr td.subscription-id a').first().textContent();
			await page.locator('table.my_account_subscriptions tr td.subscription-actions a').first().click();

			await page.locator('table.subscription_details a.subscription_renewal_early').first().click();
			await page
				.locator( '.wc-block-checkout__payment-method .wc-block-components-radio-control' )
				.locator( 'input.wc-block-components-radio-control__input' )
				.first()
				.check();
			await placeOrder(page, isBlock);

			// verify order received page
			await expect(
				page.getByRole('heading', { name: 'Order received' })
			).toBeVisible();

			await page.goto('my-account/view-subscription/'+subscriptionId.replace('#', ''));
			await expect(page.locator('table.subscription_details tr').first().locator('td').last()).toHaveText('Active');
		});

		test(title + 'Customer can change payment method of the subscription', async ({
			page,
		}) => {
			await page.goto( '/my-account/subscriptions/' );
			await page.locator('table.my_account_subscriptions tr td.subscription-actions a').first().click();

			await page.locator('table.subscription_details a.change_payment_method').first().click();
			await page.locator('input[name="wc-square-credit-card-payment-token"]').first().waitFor( { state: 'visible' } );
			if( await page.locator('input#wc-square-credit-card-use-new-payment-method').isVisible() ){
				await page.locator('input#wc-square-credit-card-use-new-payment-method').check();
			}
			await fillCreditCardFields( page, true, isBlock );
			await placeOrder(page, isBlock);

			// verify order received page
			await expect(
				await page.locator('.woocommerce .woocommerce-message').first()
			).toBeVisible();
			await expect(
				await page.locator('.woocommerce .woocommerce-message').first()
			).toHaveText(/Payment method updated/);
		});
	}

	test('Customer can cancel the subscription', async ({
		page,
	}) => {
		await page.goto( '/my-account/subscriptions/' );
		await page.locator('table.my_account_subscriptions tr td.subscription-actions a').first().click();

		await page.locator('table.subscription_details a.cancel').first().click();
		await expect(
			await page.locator('.woocommerce .woocommerce-message').first()
		).toBeVisible();
		await expect(
			await page.locator('.woocommerce .woocommerce-message').first()
		).toHaveText(/Your subscription has been cancelled/);
	});
});
