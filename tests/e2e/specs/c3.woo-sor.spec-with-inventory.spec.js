import { test, expect } from '@playwright/test';
import { chromium } from 'playwright';

import {
	createProduct,
	doesProductExist,
	saveSquareSettings,
	runWpCliCommand,
	deleteAllProducts,
} from '../utils/helper';
import {
	listCatalog,
	deleteAllCatalogItems,
	retrieveInventoryCount,
	extractCatalogInfo,
	clearSync,
} from '../utils/square-sandbox';

test.describe.configure({ mode: 'serial' });
test.beforeAll( 'Setup', async ( { baseURL } ) => {
	const browser = await chromium.launch();
	const page = await browser.newPage();

	await deleteAllProducts( page );
	await clearSync( page );
	await deleteAllCatalogItems();
	await page.goto( '/wp-admin/admin.php?page=wc-settings&tab=square&section' );
	await page.getByTestId( 'sync-settings-field' ).selectOption( { label: 'WooCommerce' } );
	await page.getByTestId( 'push-inventory-field' ).check();
	await saveSquareSettings( page );

	if ( ! ( await doesProductExist( baseURL, 'oneplus-8' ) ) ) {
		await createProduct(
			page, {
				name: 'OnePlus 8',
				regularPrice: '299',
				sku: 'oneplus-8',
			},
			false
		);

		await page.locator( '#_manage_stock' ).check();
		await page.locator( '#_stock' ).fill( '56' );
		await page.locator( '.general_tab' ).click();
		await page.locator( '#_wc_square_synced' ).check();
		await page.waitForTimeout( 2000 );
		await page.locator( '#publish' ).click();
	}

	await browser.close();
} );

test( 'OnePlus 8 pushed to Square with inventory', async ( { page } ) => {
	test.slow();

	await page.goto( '/wp-admin/admin.php?page=wc-settings&tab=square&section=update' );

	const result = await new Promise( ( resolve ) => {
		let intervalId = setInterval( async () => {
			await page.goto( '/wp-admin/admin.php?page=wc-settings&tab=square&section=update' );
			const __result = await listCatalog();
			if ( __result.objects ) {
				clearInterval( intervalId );
				resolve( __result );
			}
		}, 3000 );
	} );

	const {
		name,
		variations,
	} = extractCatalogInfo( result.objects[0] );

	expect( name ).toEqual( 'OnePlus 8' );
	expect( variations[ 0 ].sku ).toEqual( 'oneplus-8' );
	expect( variations[ 0 ].price ).toEqual( 29900 );

	let inventory = await retrieveInventoryCount( variations[ 0 ].id );

	if ( ! inventory.counts ) {
		await new Promise( ( resolve ) => {
			const inventoryIntervalId = setInterval( async () => {
				inventory = await retrieveInventoryCount( variations[ 0 ].id );
				console.log( inventory );
				if ( inventory.counts ) {
					clearInterval( inventoryIntervalId );
					resolve();
				}
			}, 4000 );
		} );
	}

	expect( inventory ).toHaveProperty( 'counts' );
	expect( inventory ).toHaveProperty( 'counts[0].quantity', '56' );
} );


test('Sync Inventory stock from Square on the product edit screen - (SOR WooCommerce - Stock management enabled)', async ({
	page,
}) => {
	await page.goto('/product/oneplus-8/');
	await page.locator('#wpadminbar li#wp-admin-bar-edit a').click();
	await expect(page.locator('#title')).toBeVisible();
	await page.waitForTimeout(2000);
	await page.locator('#woocommerce-product-data .blockUI.blockOverlay').first().waitFor({ state: 'detached' });

	const productId = await page.locator("#post_ID").inputValue();

	await page.locator('.inventory_tab').click();
	await expect(page.locator('#_stock')).not.toBeEditable();
	await expect(await page.locator('#fetch-stock-with-square')).toBeVisible();
	await expect(await page.locator('#fetch-stock-with-square')).toHaveText(
		'Fetch stock from Square'
	);
	await page.locator('#fetch-stock-with-square').click();
	await page.waitForTimeout(5000); // This is required to wait for the ajax request.
	await expect(page.locator('#_stock')).toBeEditable();
	await expect(await page.locator('#_stock').inputValue()).toEqual('56');
	await page.locator('#_stock').fill('60');
	await page.locator('#publish').click();

	// Validate the stock count is updated.
	await page.goto(`/wp-admin/post.php?post=${productId}&action=edit`);
	await expect(page.locator('#title')).toBeVisible();
	await page.waitForTimeout(2000);
	await page.locator('#woocommerce-product-data .blockUI.blockOverlay').first().waitFor({ state: 'detached' });
	await page.locator('.inventory_tab').click();
	await expect(await page.locator('#_stock').inputValue()).toEqual('60');

	// Validate the stock count is updated in Square.
	const result = await listCatalog();

	const { variations } = extractCatalogInfo(result.objects[0]);
	expect(variations[0].sku).toEqual('oneplus-8');
	const inventory = await retrieveInventoryCount(variations[0].id);
	expect(inventory).toHaveProperty('counts[0].quantity', '60');
});


test('Sync Inventory stock from Square on the product edit screen - (SOR WooCommerce - Stock management disabled)', async ({
	page,
}) => {
	await page.goto('/product/oneplus-8/');
	await page.locator('#wpadminbar li#wp-admin-bar-edit a').click();
	await expect(page.locator('#title')).toBeVisible();
	await page.waitForTimeout(2000);
	await page.locator('#woocommerce-product-data .blockUI.blockOverlay').first().waitFor({ state: 'detached' });

	const productId = await page.locator("#post_ID").inputValue();

	// Disable stock management for the product to test the sync inventory feature.
	await runWpCliCommand(`wp post meta update ${productId} _manage_stock "no"`);

	// Sync inventory from Square on the product edit screen.
	await page.goto(`/wp-admin/post.php?post=${productId}&action=edit`);
	await expect(page.locator('#title')).toBeVisible();
	await page.waitForTimeout(2000);
	await page.locator('#woocommerce-product-data .blockUI.blockOverlay').first().waitFor({ state: 'detached' });
	await page.locator('.inventory_tab').click();
	await expect(await page.locator('.sync-stock-from-square')).toBeVisible();
	await expect(await page.locator('.sync-stock-from-square')).toHaveText(
		'Sync inventory'
	);
	await page.locator('.sync-stock-from-square').click();
	await page.waitForTimeout(6000); // This is required to wait for the ajax request.
	await page.goto(`/wp-admin/post.php?post=${productId}&action=edit`);
	await expect(page.locator('#title')).toBeVisible();
	await page.waitForTimeout(2000);
	await page.locator('#woocommerce-product-data .blockUI.blockOverlay').first().waitFor({ state: 'detached' });
	await page.locator('.inventory_tab').click();
	await expect(page.locator('#_stock')).not.toBeEditable();
	await expect(await page.locator('#fetch-stock-with-square')).toBeVisible();
	await expect(await page.locator('#fetch-stock-with-square')).toHaveText(
		'Fetch stock from Square'
	);
});
