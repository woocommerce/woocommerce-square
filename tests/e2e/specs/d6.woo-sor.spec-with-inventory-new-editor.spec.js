import { test, expect } from '@playwright/test';
import { chromium } from 'playwright';

import {
	createProduct,
	doesProductExist,
} from '../utils/helper';
import {
	listCatalog,
	deleteAllCatalogItems,
	retrieveInventoryCount,
	extractCatalogInfo,
	clearSync
} from '../utils/square-sandbox';

test.beforeAll( 'Setup', async ( { baseURL } ) => {
	const browser = await chromium.launch();
	const page = await browser.newPage();

	await clearSync( page );
	await deleteAllCatalogItems();
	await page.goto( '/wp-admin/admin.php?page=wc-settings&tab=square&section' );
	await page.locator( '#wc_square_system_of_record' ).selectOption( { label: 'WooCommerce' } );
	await page.locator( '#wc_square_enable_inventory_sync' ).check();
	await page.locator( '.woocommerce-save-button' ).click();

	if ( ! ( await doesProductExist( baseURL, 'oneplus-8' ) ) ) {
		await createProduct(
			page, {
				name: 'OnePlus 8',
				regularPrice: '299',
				sku: 'oneplus-8',
			},
			false,
			true
		);

		if ( await page.locator( '[aria-label="Close Tour"]' ).isVisible() ) {
			await page.locator( '[aria-label="Close Tour"]' ).click();
		}

		await page.locator( '.wc-square-track-quantity .components-form-toggle__input' ).click();
		await page.locator( '[name="stock_quantity"]' ).fill( '62' );

		await page.locator( '#woocommerce-product-tab__general' ).click();
		await page.locator( '[data-template-block-id="_wc_square_synced"] .components-form-toggle__input' ).click();

		await page
			.locator( '.woocommerce-product-header__actions .components-button' )
			.filter( { hasText: 'Publish' } )
			.click();

		await page
			.locator( '.woocommerce-product-publish-panel__header .components-button' )
			.filter( { hasText: 'Publish' } )
			.click();

		await expect( await page.getByText( 'OnePlus 8 is now live.' ) ).toBeVisible();
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
	expect( inventory ).toHaveProperty( 'counts[0].quantity', '62' );
} );
