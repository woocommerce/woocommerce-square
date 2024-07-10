import fetch from 'node-fetch';
import { v4 as uuidv4 } from 'uuid';

const squareVersion = '2024-03-20';

/**
 * Returns an object that contains an array of catalog objects.
 *
 * @returns {Object} Response object.
 */
export async function listCatalog() {
	const url = 'https://connect.squareupsandbox.com/v2/catalog/list?types=ITEM';
	const headers = {
		'Square-Version': squareVersion,
		'Authorization': `Bearer ${process.env.SQUARE_ACCESS_TOKEN}`,
		'Content-Type': 'application/json'
	};

	const response = await fetch( url, {
		method: 'GET',
		headers: headers
	} );

	return await response.json();
}

/**
 * Returns an object that contains an array of category objects.
 *
 * @returns {Object} Response object.
 */
export async function listCategories() {
	const url = 'https://connect.squareupsandbox.com/v2/catalog/list?types=CATEGORY';
	const headers = {
		'Square-Version': squareVersion,
		'Authorization': `Bearer ${process.env.SQUARE_ACCESS_TOKEN}`,
		'Content-Type': 'application/json',
	};

	const response = await fetch( url, {
		method: 'GET',
		headers,
	} );

	return await response.json();
}

/**
 * Deletes catalog objects by ID.
 *
 * @param {Array} ids Deletes catalog objects by ID.
 * @returns {Object} Response object.
 */
export async function batchDeleteCatalogItem( ids = [] ) {
	const url = `https://connect.squareupsandbox.com/v2/catalog/batch-delete`;
	const headers = {
		'Square-Version': squareVersion,
		'Authorization': `Bearer ${process.env.SQUARE_ACCESS_TOKEN}`,
		'Content-Type': 'application/json'
	};

	const data = {
		object_ids: ids
	};

	const response = await fetch( url, {
		method: 'POST',
		headers: headers,
		body: JSON.stringify( data )
	} );

	return await response.json();
}

/**
 * Deletes all catalog objects.
 *
 * @returns Response object.
 */
export async function deleteAllCatalogItems() {
	const catalog = await listCatalog();

	if ( ! catalog.objects ) {
		return;
	}

	const ids = catalog.objects.map( ( item ) => item.id );

	return await batchDeleteCatalogItem( ids );
}

/**
 * Retrieves inventory count for a variation.
 *
 * @param {Number} variationId ID of the variation.
 * @returns Response object.
 */
export async function retrieveInventoryCount( variationId ) {
	const url = `https://connect.squareupsandbox.com/v2/inventory/${variationId}?location_ids=${process.env.SQUARE_LOCATION_ID}`;
	const headers = {
		'Square-Version': squareVersion,
		'Authorization': `Bearer ${process.env.SQUARE_ACCESS_TOKEN}`,
		'Content-Type': 'application/json'
	};

	const response = await fetch( url, {
		method: 'GET',
		headers: headers
	} );

	return await response.json();
}

/**
 * Extracts necessary information from a catalog object for ease of use.
 *
 * @param {Object} catalogObject Square catalog object.
 * @returns {Object} Catalog info.
 */
export function extractCatalogInfo( catalogObject = {} ) {
	const catalogId = catalogObject.id;
	const name = catalogObject.item_data.name;
	const description =
		catalogObject.item_data.description ||
		catalogObject.item_data.description_html ||
		'';
	let category = catalogObject.item_data.reporting_category?.id;
	if (!category) {
		category = catalogObject.categories[0]?.id;
	}

	const variations = catalogObject.item_data.variations.map( variation => {
		return {
			id: variation.id,
			sku: variation.item_variation_data.sku,
			price: variation.item_variation_data.price_money.amount,
		}
	} );

	return {
		catalogId,
		name,
		category,
		description,
		variations,
	}
}

/**
 * 
 * @param {String} name Name of the variation.
 * @param {String} sku SKU.
 * @param {String} price Price of the variation.
 * @returns {Object}
 */
export async function createCatalogObject( name, sku, price, description = '' ) {
	const url = 'https://connect.squareupsandbox.com/v2/catalog/object';
	const method = 'POST';
	const headers = {
		'Square-Version': squareVersion,
		'Authorization': `Bearer ${process.env.SQUARE_ACCESS_TOKEN}`,
		'Content-Type': 'application/json'
	};

	const data = {
		idempotency_key: uuidv4(),
		object: {
			type: 'ITEM',
			item_data: {
				name,
				product_type: 'REGULAR',
				description_html: description,
				variations: [
					{
						type: 'ITEM_VARIATION',
						item_variation_data: {
							price_money: {
								amount: price,
								currency: 'USD'
							},
							pricing_type: 'FIXED_PRICING',
							sku: `${sku}-regular`
						},
						id: `#${sku}-regular`
					}
				]
			},
			id: `#${sku}`
		}
	};

	const response = await fetch(url, {
		method: method,
		headers: headers,
		body: JSON.stringify(data)
	});

	return await response.json();
}

export async function updateCatalogItemInventory( catalogId, inventoryCount ) {
	const url = 'https://connect.squareupsandbox.com/v2/inventory/changes/batch-create';
	const method = 'POST';
	const headers = {
		'Square-Version': squareVersion,
		'Authorization': `Bearer ${process.env.SQUARE_ACCESS_TOKEN}`,
		'Content-Type': 'application/json'
	};

	const data = {
		idempotency_key: uuidv4(),
		changes: [
			{
				type: 'PHYSICAL_COUNT',
				physical_count: {
					catalog_object_id: catalogId,
					location_id: process.env.SQUARE_LOCATION_ID,
					state: 'IN_STOCK',
					quantity: inventoryCount,
					occurred_at: new Date().toISOString(),
				}
			}
		],
	};

	const response = await fetch(url, {
		method: method,
		headers: headers,
		body: JSON.stringify(data)
	});

	return await response.json();
}

/**
 * Clears the Square sync queue.
 *
 * @param {Object} page Playwright page object.
 */
export async function clearSync( page ) {
	page.on('dialog', dialog => dialog.accept());
	await page.goto( '/wp-admin/admin.php?page=wc-status&tab=tools' );
	await page.locator( 'input[form="form_wc_square_clear_background_jobs"]' ).click();
}

/**
 * Imports products.
 *
 * @param {Object} page Playwright page object.
 * @param {*} update Says if the products should be updated during import.
 */
export async function importProducts( page, update = false ) {
	await page.goto( '/wp-admin/admin.php?page=wc-settings&tab=square' );
	await page.getByTestId( 'import-products-button' ).click();

	if ( update ) {
		await page.getByTestId( 'update-during-import-field' ).check();
	}

	await page.getByTestId( 'import-products-button-confirm' ).click();
	await page.waitForTimeout( 2000 );
}

/**
 * Get gift card information.
 *
 * @param {String} gan Gift card account number
 * @returns {Object} Response object.
 */
export async function getGiftCard( gan = '' ) {
	const url = 'https://connect.squareupsandbox.com/v2/gift-cards/from-gan';
	const headers = {
		'Square-Version': squareVersion,
		'Authorization': `Bearer ${process.env.SQUARE_ACCESS_TOKEN}`,
		'Content-Type': 'application/json'
	};

	const data = {
		gan
	};

	const response = await fetch( url, {
		method: 'POST',
		headers: headers,
		body: JSON.stringify(data)
	} );

	return await response.json();
}

/**
 * Pays using GPay.
 *
 * @param {Object} popup Popup locator object
 */
export async function doGooglePay( popup ) {
	await popup.waitForLoadState();
	await popup.locator( '#identifierId' ).fill( process.env.GMAIL_USERNAME );
	await popup.locator( '#identifierNext' ).click();
	await popup.locator( 'input[name="Passwd"]' ).fill( process.env.GMAIL_PASSWORD );
	await popup.locator( '#passwordNext' ).click();
	const frame = await popup.frameLocator( '.bootstrapperIframeContainerElement iframe' ).first();
	await frame.locator( '.goog-inline-block.jfk-button:has-text("PAY")' ).click();
}
