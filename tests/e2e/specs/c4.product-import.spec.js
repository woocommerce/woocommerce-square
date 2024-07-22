import { test, expect } from '@playwright/test';
import { chromium } from 'playwright';

import {
	doesProductExist,
	deleteAllProducts,
	saveSquareSettings,
	runWpCliCommand,
} from '../utils/helper';
import {
	deleteAllCatalogItems,
	createCatalogObject,
	updateCatalogItemInventory,
	importProducts,
	clearSync
} from '../utils/square-sandbox';

let itemId = 0;

test.beforeAll( 'Setup', async () => {
	const browser = await chromium.launch();
	const page = await browser.newPage();

	await deleteAllProducts( page );
	await deleteAllCatalogItems();
	const response = await createCatalogObject( 'Cap', 'cap', 1350, 'This is a very good cap, no cap.' );
	itemId = response.catalog_object.item_data.variations[0].id;

	await updateCatalogItemInventory( itemId, '53' );
	await clearSync( page );

	await page.goto( '/wp-admin/admin.php?page=wc-settings&tab=square' );
	await page.getByTestId( 'sync-settings-field' ).selectOption( { label: 'Square' } );
	await page.getByTestId( 'pull-inventory-field' ).check();
	await saveSquareSettings( page );

	await browser.close();
} );

test( 'Import Cap from Square', async ( { page, baseURL } ) => {
	test.slow();
	page.on('dialog', dialog => dialog.accept());
	await importProducts( page );

	await new Promise( ( resolve ) => {
		let intervalId = setInterval( async () => {	
			if ( await doesProductExist( baseURL, 'cap' ) ) {
				clearInterval( intervalId );
				resolve();
			}
		}, 4000 );
	} );

	const nRetries = 5;
	for (let i = 0; i < nRetries; i++) {
		await page.goto( '/product/cap' );
		const stockLocator = await page.locator( '.stock.in-stock' );
		if ( await stockLocator.isVisible() ) {
			break;
		} else {
			await page.waitForTimeout(5000); // wait for import inventory to be completed.
		}
	}

	await page.goto( '/product/cap' );
	await expect( await page.locator( '.entry-summary .woocommerce-Price-amount' ) ).toHaveText( '$13.50' );
	await expect( await page.locator( '.entry-summary .sku_wrapper' ) ).toHaveText( 'SKU: cap-regular' );
	await expect( await page.getByText( 'This is a very good cap, no cap.' ) ).toBeVisible();
	await expect( await page.getByText( '53 in stock' ) ).toBeVisible();
} );

test('Sync Inventory stock from Square on the product edit screen - (SOR Square)', async ({
	page,
}) => {
	await page.goto('/product/cap/');
	await page.locator('#wpadminbar li#wp-admin-bar-edit a').click();
	await expect(page.locator('#title')).toBeVisible();
	await page.locator('#woocommerce-product-data .blockUI.blockOverlay').first().waitFor({ state: 'detached' });

	const productId = await page.locator("#post_ID").inputValue();

	// Disable stock management for the product to test the sync inventory feature.
	await runWpCliCommand(`wp post meta update ${productId} _stock "23"`);

	// Sync inventory from Square on the product edit screen.
	await page.goto(`/wp-admin/post.php?post=${productId}&action=edit`);
	await expect(page.locator('#title')).toBeVisible();
	await page.locator('#woocommerce-product-data .blockUI.blockOverlay').first().waitFor({ state: 'detached' });
	await page.locator('.inventory_tab').click();
	await expect(page.locator('#_stock')).not.toBeEditable();
	await expect(await page.locator('#_stock').inputValue()).toEqual('23');
	await expect(await page.locator('.sync-stock-from-square')).toBeVisible();
	await expect(await page.locator('.sync-stock-from-square')).toHaveText(
		'Sync stock from Square'
	);
	await page.locator('.sync-stock-from-square').click();
	await page.waitForTimeout(5000); // This is required to wait for the ajax request.
	await page.goto(`/wp-admin/post.php?post=${productId}&action=edit`);
	await expect(page.locator('#title')).toBeVisible();
	await page.locator('#woocommerce-product-data .blockUI.blockOverlay').first().waitFor({ state: 'detached' });
	await page.locator('.inventory_tab').click();
	await expect(await page.locator('#_stock').inputValue()).toEqual('53');
});


test( 'Handle missing products', async ( { page } ) => {
	await deleteAllCatalogItems();
	await clearSync( page );
	await page.goto( '/wp-admin/admin.php?page=wc-settings&tab=square&section' );
	await page.getByTestId( 'sync-settings-field' ).selectOption( { label: 'Square' } );
	await page.getByTestId( 'hide-missing-products-field' ).check();
	await saveSquareSettings( page );
	await page.reload();

	await page.goto( '/wp-admin/admin.php?page=wc-settings&tab=square&section=update' );
	await page.locator( '#wc-square-sync' ).click();
	await page.locator( '#btn-ok' ).click();
	await expect( await page.getByText( 'Syncing now' ) ).toBeVisible();

	await page.goto( '/shop' );

	await new Promise( ( resolve ) => {
		let intervalId = setInterval( async () => {
			if ( await page.getByText( 'No products were found matching your selection.' ).isVisible() ) {
				clearInterval( intervalId );
				resolve();
			} else {
				await page.reload();
			}
		}, 4000 );
	} );

	await expect( await page.getByText( 'No products were found matching your selection.' ) ).toBeVisible();
} );
