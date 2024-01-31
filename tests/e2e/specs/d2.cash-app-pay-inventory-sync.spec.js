import { test, expect, devices, chromium } from '@playwright/test';
import {
	deleteAllProducts,
	doSquareRefund,
	doesProductExist,
	fillAddressFields,
	gotoOrderEditPage,
	placeCashAppPayOrder,
	selectPaymentMethod,
	visitCheckout,
} from '../utils/helper';
import {
	clearSync,
	createCatalogObject,
	deleteAllCatalogItems,
	importProducts,
	retrieveInventoryCount,
	updateCatalogItemInventory,
} from '../utils/square-sandbox';
const iPhone = devices['iPhone 14 Pro Max'];

/**
 * Marked test skip because:
 *  1. It is flaky and flakiness depends on the Square sandbox environment.
 *  2. It takes a long time to run. (more than 1 minute)
 * 
 *  We can run this test locally by removing the skip during the smoke testing or support WP/WC version bumps.
 */
test.describe('Cash App Pay Inventory Sync Tests', () => {
	let itemId;
	const quantity = 100;
	test.beforeAll('Setup', async ({ baseURL }) => {
		const browser = await chromium.launch();
		const page = await browser.newPage();

		await deleteAllProducts(page);
		await deleteAllCatalogItems();
		const response = await createCatalogObject(
			'Sample product with inventory',
			'sample-product-with-inventory',
			1900,
			'This is a sample product with inventory.'
		);
		itemId = response.catalog_object.item_data.variations[0].id;

		await updateCatalogItemInventory(itemId, `${quantity}`);
		await clearSync(page);

		await page.goto('/wp-admin/admin.php?page=wc-settings&tab=square');
		await page
			.locator('#wc_square_system_of_record')
			.selectOption({ label: 'Square' });
		await page.locator('.woocommerce-save-button').click();
		await expect(
			await page.getByText('Your settings have been saved.')
		).toBeVisible();

		await browser.close();
	});

	test.skip('Product inventory should be sync for order placed by Cash App Pay', async ({
		browser,
		page,
		baseURL,
	}) => {
		// Sync product with inventory to Square first.
		test.slow();

		page.on('dialog', (dialog) => dialog.accept());
		await importProducts(page);

		const nRetries = 8;
		let isProductExist = false;
		for (let i = 0; i < nRetries; i++) {
			isProductExist = await doesProductExist(
				baseURL,
				'sample-product-with-inventory/	'
			);
			if ( isProductExist ) {
				break;
			} else {
				await page.waitForTimeout(4000); // wait for import inventory to be completed.
			}
		}

		// Skip the test if the product is not imported.
		if ( !isProductExist ) {
			test.skip();
		}

		// Place order with Cash App Pay.
		const isBlock = true;
		const context = await browser.newContext({
			...iPhone,
		});
		const mobilePage = await context.newPage();
		await mobilePage.goto('/product/sample-product-with-inventory/');
		mobilePage.on('dialog', (dialog) => dialog.accept());
		await mobilePage.locator('.single_add_to_cart_button').click();
		await visitCheckout(mobilePage, isBlock);
		await fillAddressFields(mobilePage, isBlock);
		await selectPaymentMethod(mobilePage, 'square_cash_app_pay', isBlock);
		const orderId = await placeCashAppPayOrder(mobilePage, isBlock);

		// Confirm that the inventory is deducted.
		await mobilePage.waitForTimeout(6000);
		const updatedInventory = await retrieveInventoryCount(itemId);
		const updatedQty =
			updatedInventory.counts &&
			updatedInventory.counts[0] &&
			updatedInventory.counts[0].quantity;
		await expect(updatedQty).toBe(`${quantity - 1}`);

		await gotoOrderEditPage(mobilePage, orderId);
		await doSquareRefund(mobilePage, '19');
		await expect(
			await mobilePage.getByText(
				'Cash App Pay (Square) Order completely refunded.'
			)
		).toBeVisible();
		await expect(mobilePage.locator('#order_status')).toHaveValue(
			'wc-refunded'
		);

		// Confirm that the inventory is restored after refund.
		await mobilePage.waitForTimeout(6000);
		const updatedInventory2 = await retrieveInventoryCount(itemId);
		const updatedQty2 =
			updatedInventory2.counts &&
			updatedInventory2.counts[0] &&
			updatedInventory2.counts[0].quantity;
		await expect(updatedQty2).toBe(`${quantity}`);
	});
});
