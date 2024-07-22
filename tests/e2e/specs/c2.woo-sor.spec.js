import { test, expect } from '@playwright/test';
import { chromium } from 'playwright';

import {
	createProduct,
	doesProductExist,
	saveSquareSettings,
} from '../utils/helper';
import {
	listCatalog,
	deleteAllCatalogItems,
	retrieveInventoryCount,
	extractCatalogInfo,
	clearSync,
	listCategories,
} from '../utils/square-sandbox';

test.beforeAll( 'Setup', async ( { baseURL } ) => {
	const browser = await chromium.launch();
	const page = await browser.newPage();

	await deleteAllCatalogItems();
	await page.goto( '/wp-admin/admin.php?page=wc-settings&tab=square&section' );
	await page.getByTestId( 'sync-settings-field' ).selectOption( { label: 'WooCommerce' } );
	await saveSquareSettings( page );

	await clearSync( page );

	if ( ! ( await doesProductExist( baseURL, 'iphone' ) ) ) {
		await createProduct(
			page, {
				name: 'iPhone Pro',
				content: 'iPhone Pro content',
				category: 'Mobiles',
				regularPrice: '499',
				sku: 'iphone',
			},
			false
		);

		await page.locator( '#_manage_stock' ).uncheck();
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
		description,
		category,
	} = extractCatalogInfo( result.objects[0] );

	expect( name ).toEqual( 'iPhone Pro' );
	expect( variations[ 0 ].sku ).toEqual( 'iphone' );
	expect( variations[ 0 ].price ).toEqual( 49900 );
	expect(description).toEqual('iPhone Pro content');

	let categoryName = '';
	if (category) {
		const categories = await listCategories();
		if (categories.objects) {
			categoryName = categories.objects
				.filter((cat) => cat.id === category)
				.map((cat) => cat.category_data.name)[0];
		}
	}
	expect(categoryName).toEqual('Mobiles');

	const inventory = await retrieveInventoryCount( variations[ 0 ].id );

	expect( inventory ).not.toHaveProperty( 'counts' );
} );
