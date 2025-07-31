import { test, expect } from '@playwright/test';
import { chromium } from 'playwright';

import {
	createProduct,
	doesProductExist,
	saveSquareSettings,
	deleteAllProducts,
} from '../utils/helper';
import {
	listCatalog,
	deleteAllCatalogItems,
	retrieveInventoryCount,
	extractCatalogInfo,
	clearSync,
} from '../utils/square-sandbox';

test.describe.configure( { mode: 'serial' } );
test.beforeAll( 'Setup', async ( ) => {
	const browser = await chromium.launch();
	const page = await browser.newPage();

	await deleteAllProducts( page );
	await deleteAllCatalogItems();

	await page.goto( '/wp-admin/admin.php?page=wc-settings&tab=square&section' );

	await page.getByTestId( 'sync-settings-field' ).selectOption( { label: 'WooCommerce' } );
	await page.getByTestId( 'push-inventory-field' ).check();
	await saveSquareSettings( page );

	await clearSync( page );
	await browser.close();
} );

test( 'Update inventory from Woo to Square @sync', async ( { page } ) => {
	await createProduct(
		page, {
			name: 'OnePlus 8',
			regularPrice: '299',
			sku: 'oneplus-8',
		},
		false
	);

	await page.locator( '#woocommerce-product-tab__inventory' ).click();
	await expect( await page.locator( '[name="stock_quantity"]' ) ).toBeDisabled();

	await page
		.locator( 'button.components-button' )
		.filter( { hasText: 'Fetch stock from Square' } )
		.click();

	await expect( await page.locator( '[name="stock_quantity"]' ) ).toBeEnabled();
	await page.locator( '[name="stock_quantity"]' ).fill( '' );
	await page.locator( '[name="stock_quantity"]' ).fill( '84' );

	await page
		.locator( '.woocommerce-button-with-dropdown-menu .components-button' )
		.filter( { hasText: 'Update' } )
		.click();

	const result = await new Promise( ( resolve ) => {
		let intervalId = setInterval( async () => {
			const __result = await listCatalog();
			if ( __result.objects ) {
				clearInterval( intervalId );
				resolve( __result );
			}
		}, 3000 );
	} );

	const { variations } = extractCatalogInfo( result.objects[0] );
	let inventory = await retrieveInventoryCount( variations[ 0 ].id );

	if ( ! inventory.counts ) {
		await new Promise( ( resolve ) => {
			const inventoryIntervalId = setInterval( async () => {
				inventory = await retrieveInventoryCount( variations[ 0 ].id );
				if ( inventory.counts ) {
					clearInterval( inventoryIntervalId );
					resolve();
				}
			}, 4000 );
		} );
	}

	expect( inventory ).toHaveProperty( 'counts' );
	expect( inventory ).toHaveProperty( 'counts[0].quantity', '84' );
} );
