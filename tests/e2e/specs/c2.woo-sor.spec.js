import { test, expect } from '@playwright/test';
import { chromium } from 'playwright';

import {
	createProduct,
	doesProductExist,
	deleteAllProducts,
} from '../utils/helper';
import {
	listCatalog,
	deleteAllCatalogItems,
	retrieveInventoryCount,
	extractCatalogInfo,
	clearSync,
} from '../utils/square-sandbox';

test.beforeAll( 'Setup', async ( { baseURL } ) => {
	const browser = await chromium.launch();
	const page = await browser.newPage();

	await deleteAllCatalogItems();
	await page.goto( '/wp-admin/admin.php?page=wc-settings&tab=square&section' );
	await page.locator( '#wc_square_system_of_record' ).selectOption( { label: 'WooCommerce' } );
	await page.locator( '.woocommerce-save-button' ).click();

	await clearSync( page );

	if ( ! ( await doesProductExist( baseURL, 'iphone' ) ) ) {
		await createProduct(
			page, {
				name: 'iPhone Pro',
				regularPrice: '499',
				sku: 'iphone',
			},
			false
		);

		await page.locator( '#_manage_stock' ).check();
		await page.locator( '#_stock' ).fill( '28' );
		await page.locator( '.general_tab' ).click();
		await page.locator( '#_wc_square_synced' ).check();
		await page.waitForTimeout( 2000 );
		await page.locator( '#publish' ).click();
		await page.goto( '/shop' );
	}

	await browser.close();
} );

test( 'iPhone Pro pushed to Square', async ( { page } ) => {
	test.slow();

	await page.goto( '/wp-admin/admin.php?page=wc-settings&tab=square&section=update' );

	const result = await new Promise( ( resolve ) => {
		let intervalId = setInterval( async () => {
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

	expect( name ).toEqual( 'iPhone Pro' );
	expect( variations[ 0 ].sku ).toEqual( 'iphone' );
	expect( variations[ 0 ].price ).toEqual( 49900 );

	const inventory = await retrieveInventoryCount( variations[ 0 ].id );

	expect( inventory ).not.toHaveProperty( 'counts' );
} );
