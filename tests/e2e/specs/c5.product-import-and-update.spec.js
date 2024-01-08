import { test, expect } from '@playwright/test';
import { chromium } from 'playwright';
import fetch from 'node-fetch';
import { v4 as uuidv4 } from 'uuid';

import {
	doesProductExist,
} from '../utils/helper';
import {
	deleteAllCatalogItems,
	createCatalogObject,
	importProducts,
	clearSync,
} from '../utils/square-sandbox';

let createdCatalogObject = {};

test.beforeAll( 'Setup', async () => {
	const browser = await chromium.launch();
	const page = await browser.newPage();

	await deleteAllCatalogItems();
	createdCatalogObject = await createCatalogObject( 'Scarf', 'scarf', 2000 );

	await clearSync( page );

	await browser.close();
} );

test( 'Import Scarf from Square and Update', async ( { page, baseURL } ) => {
	test.setTimeout(250000);
	page.on('dialog', dialog => dialog.accept());
	await importProducts( page );

	await new Promise( ( resolve ) => {
		let intervalId = setInterval( async () => {
			if ( await doesProductExist( baseURL, 'scarf' ) ) {
				clearInterval( intervalId );
				resolve();
			}
		}, 4000 );
	} );

	const url = 'https://connect.squareupsandbox.com/v2/catalog/object';
	const method = 'POST';
	const headers = {
		'Square-Version': '2023-09-25',
		'Authorization': `Bearer ${process.env.SQUARE_ACCESS_TOKEN}`,
		'Content-Type': 'application/json'
	};

	const data = {
		idempotency_key: uuidv4(),
		object: {
			id: createdCatalogObject.catalog_object.id,
			type: 'ITEM',
			item_data: {
				variations: [
					{
						id: createdCatalogObject.catalog_object.item_data.variations[0].id,
						type: 'ITEM_VARIATION',
						item_variation_data: {
							pricing_type: 'FIXED_PRICING',
							price_money: {
								amount: 3500,
								currency: 'USD'
							},
							name: 'Scarf - Red',
							sku: 'scarf-regular'
						},
						version: createdCatalogObject.catalog_object.version,
					}
				],
				name: 'Scarf Cotton',
				product_type: 'REGULAR',
				description: "A soft scarf",
			},
			version: createdCatalogObject.catalog_object.version,
		}
	};

	await fetch(url, {
		method: method,
		headers: headers,
		body: JSON.stringify(data)
	} );

	await importProducts( page, true );

	await new Promise( ( resolve ) => {
		let intervalId = setInterval( async () => {
			if ( await doesProductExist( baseURL, 'scarf' ) ) {
				clearInterval( intervalId );
				resolve();
			}
		}, 4000 );
	} );

	await page.goto( '/product/scarf' );

	let reload = true;

	await new Promise( ( resolve ) => {
		let intervalId = setInterval( async () => {
			if ( await page.getByText( '$35' ).first().isVisible() ) {
				reload = false;
				clearInterval( intervalId );
				resolve();
			}

			if ( reload ) {
				await page.reload();
			}
		}, 4000 );
	} );

	await page.goto( '/product/scarf' );
	await expect( await page.locator( '.entry-summary .woocommerce-Price-amount' ) ).toHaveText( '$35.00' );
	await expect( await page.locator( '.entry-summary .sku_wrapper' ) ).toHaveText( 'SKU: scarf-regular' );
} );
