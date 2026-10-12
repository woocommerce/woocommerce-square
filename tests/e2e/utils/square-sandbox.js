import fetch from 'node-fetch';
import { v4 as uuidv4 } from 'uuid';
import multiVariations from '../dummy-data/multi-variations.json';

const squareVersion = '2024-03-20';

/**
 * Returns an object that contains an array of catalog objects.
 *
 * @return {Object} Response object.
 */
export async function listCatalog() {
	const url =
		'https://connect.squareupsandbox.com/v2/catalog/list?types=ITEM';
	const headers = {
		'Square-Version': squareVersion,
		Authorization: `Bearer ${ process.env.SQUARE_ACCESS_TOKEN }`,
		'Content-Type': 'application/json',
	};

	const response = await fetch( url, {
		method: 'GET',
		headers,
	} );

	return await response.json();
}

/**
 * Returns an object that contains an array of category objects.
 *
 * @return {Object} Response object.
 */
export async function listCategories() {
	const url =
		'https://connect.squareupsandbox.com/v2/catalog/list?types=CATEGORY';
	const headers = {
		'Square-Version': squareVersion,
		Authorization: `Bearer ${ process.env.SQUARE_ACCESS_TOKEN }`,
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
 * @return {Object} Response object.
 */
export async function batchDeleteCatalogItem( ids = [] ) {
	const url = `https://connect.squareupsandbox.com/v2/catalog/batch-delete`;
	const headers = {
		'Square-Version': squareVersion,
		Authorization: `Bearer ${ process.env.SQUARE_ACCESS_TOKEN }`,
		'Content-Type': 'application/json',
	};

	const data = {
		object_ids: ids,
	};

	const response = await fetch( url, {
		method: 'POST',
		headers,
		body: JSON.stringify( data ),
	} );

	return await response.json();
}

/**
 * Deletes all catalog objects.
 *
 * @return Response object.
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
 * @param {number} variationId ID of the variation.
 * @return Response object.
 */
export async function retrieveInventoryCount( variationId ) {
	const url = `https://connect.squareupsandbox.com/v2/inventory/${ variationId }?location_ids=${ process.env.SQUARE_LOCATION_ID }`;
	const headers = {
		'Square-Version': squareVersion,
		Authorization: `Bearer ${ process.env.SQUARE_ACCESS_TOKEN }`,
		'Content-Type': 'application/json',
	};

	const response = await fetch( url, {
		method: 'GET',
		headers,
	} );

	return await response.json();
}

/**
 * Extracts necessary information from a catalog object for ease of use.
 *
 * @param {Object} catalogObject Square catalog object.
 * @return {Object} Catalog info.
 */
export function extractCatalogInfo( catalogObject = {} ) {
	const catalogId = catalogObject.id;
	const name = catalogObject.item_data.name;
	const description =
		catalogObject.item_data.description ||
		catalogObject.item_data.description_html ||
		'';
	let category = catalogObject.item_data.reporting_category?.id;
	if ( ! category ) {
		category = catalogObject.categories[ 0 ]?.id;
	}

	const categories =
		( catalogObject.item_data?.categories || [] )
			.map( ( cat ) => cat?.id || '' )
			.filter( ( cat ) => cat !== '' );

	const variations = catalogObject.item_data.variations.map(
		( variation ) => {
			return {
				id: variation.id,
				name: variation.item_variation_data.name || '',
				sku: variation.item_variation_data.sku,
				price: variation.item_variation_data.price_money.amount,
				item_option_values:
					variation.item_variation_data.item_option_values || [],
			};
		}
	);

	return {
		catalogId,
		name,
		category,
		categories,
		description,
		variations,
	};
}

/**
 *
 * @param {string} name  Name of the variation.
 * @param {string} sku   SKU.
 * @param {string} price Price of the variation.
 * @param {string} description Description of the variation.
 * @param {string} categoryId Category ID.
 * @param {string} categoryId2 Category ID 2.
 * @return {Object} Response object.
 */
export async function createCatalogObject(
	name,
	sku,
	price,
	description = '',
	categoryId = '',
	categoryId2 = ''
) {
	const url = 'https://connect.squareupsandbox.com/v2/catalog/object';
	const method = 'POST';
	const headers = {
		'Square-Version': squareVersion,
		Authorization: `Bearer ${ process.env.SQUARE_ACCESS_TOKEN }`,
		'Content-Type': 'application/json',
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
								currency: 'USD',
							},
							pricing_type: 'FIXED_PRICING',
							sku: `${ sku }-regular`,
						},
						id: `#${ sku }-regular`,
					},
				],
			},
			id: `#${ sku }`,
		},
	};

	if ( categoryId ) {
		const categories = [ { id: categoryId } ];
		if ( categoryId2 ) {
			categories.push( { id: categoryId2 } );
		}
		data.object.item_data.categories = categories;
		data.object.item_data.reporting_category = { id: categoryId };
	}

	const response = await fetch( url, {
		method,
		headers,
		body: JSON.stringify( data ),
	} );

	return await response.json();
}

/**
 * Create a Category to be used in the catalog.
 *
 * @param {string} name Name of the category.
 * @return {Object}
 */
export async function createCategory( name ) {
	const url = 'https://connect.squareupsandbox.com/v2/catalog/object';
	const method = 'POST';
	const headers = {
		'Square-Version': squareVersion,
		Authorization: `Bearer ${ process.env.SQUARE_ACCESS_TOKEN }`,
		'Content-Type': 'application/json',
	};

	const data = {
		idempotency_key: uuidv4(),
		object: {
			type: 'CATEGORY',
			category_data: {
				name,
			},
			id: `#${ name }Category`,
			present_at_all_locations: true,
		},
	};

	const response = await fetch( url, {
		method,
		headers,
		body: JSON.stringify( data ),
	} );

	return await response.json();
}

export async function updateCatalogItemInventory( catalogId, inventoryCount ) {
	const url =
		'https://connect.squareupsandbox.com/v2/inventory/changes/batch-create';
	const method = 'POST';
	const headers = {
		'Square-Version': squareVersion,
		Authorization: `Bearer ${ process.env.SQUARE_ACCESS_TOKEN }`,
		'Content-Type': 'application/json',
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
				},
			},
		],
	};

	const response = await fetch( url, {
		method,
		headers,
		body: JSON.stringify( data ),
	} );

	return await response.json();
}

/**
 * Clears the Square sync queue.
 *
 * @param {Object} page Playwright page object.
 */
export async function clearSync( page ) {
	page.on( 'dialog', ( dialog ) => dialog.accept() );
	await page.goto( '/wp-admin/admin.php?page=wc-status&tab=tools' );
	await page
		.locator( 'input[form="form_wc_square_clear_background_jobs"]' )
		.click();
}

/**
 * Imports products.
 *
 * @param {Object} page   Playwright page object.
 * @param {*}      update Says if the products should be updated during import.
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
 * @param {string} gan Gift card account number
 * @return {Object} Response object.
 */
export async function getGiftCard( gan = '' ) {
	const url = 'https://connect.squareupsandbox.com/v2/gift-cards/from-gan';
	const headers = {
		'Square-Version': squareVersion,
		Authorization: `Bearer ${ process.env.SQUARE_ACCESS_TOKEN }`,
		'Content-Type': 'application/json',
	};

	const data = {
		gan,
	};

	const response = await fetch( url, {
		method: 'POST',
		headers,
		body: JSON.stringify( data ),
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
	await popup
		.locator( 'input[name="Passwd"]' )
		.fill( process.env.GMAIL_PASSWORD );
	await popup.locator( '#passwordNext' ).click();
	const frame = await popup
		.frameLocator( '.bootstrapperIframeContainerElement iframe' )
		.first();
	await frame
		.locator( '.goog-inline-block.jfk-button:has-text("PAY")' )
		.click();
}

export async function getItemOptions() {
	const url =
		'https://connect.squareupsandbox.com/v2/catalog/list?types=ITEM_OPTION';
	const headers = {
		'Square-Version': squareVersion,
		Authorization: `Bearer ${ process.env.SQUARE_ACCESS_TOKEN }`,
		'Content-Type': 'application/json',
	};

	const response = await fetch( url, {
		method: 'GET',
		headers,
	} );

	const result = await response.json();
	const objects = result.objects.map( ( item ) => {
		return {
			id: item.id,
			name: item.item_option_data.name,
			values: item.item_option_data.values.map( ( value ) => {
				return {
					id: value.id,
					name: value.item_option_value_data.name,
				};
			} ),
		};
	} );

	const options = objects.reduce( ( acc, item ) => {
		if ( item.name === 'pa_color' ) {
			acc.COLOR_OPTION_ID = item.id;
			item.values.forEach( ( value ) => {
				acc[ value.name.toUpperCase() + '_COLOR_OPTION_VALUE' ] =
					value.id;
			} );
		} else if ( item.name === 'pa_size' ) {
			acc.SIZE_OPTION_ID = item.id;
			item.values.forEach( ( value ) => {
				acc[ value.name.toUpperCase() + '_SIZE_OPTION_VALUE' ] =
					value.id;
			} );
		} else if ( item.name === 'Custom Material' ) {
			acc.CUSTOM_MATERIAL_OPTION_ID = item.id;
			item.values.forEach( ( value ) => {
				acc[ value.name.toUpperCase() + '_MATERIAL_OPTION_VALUE' ] =
					value.id;
			} );
		}
		return acc;
	}, {} );
	return options;
}

export async function createVariableProductsInSquare() {
	let jsonString = JSON.stringify( multiVariations );
	const options = await getItemOptions();

	Object.keys( options ).forEach( ( key ) => {
		jsonString = jsonString.replace(
			new RegExp( key, 'g' ),
			options[ key ]
		);
	} );
	const variations = JSON.parse( jsonString );

	const url = 'https://connect.squareupsandbox.com/v2/catalog/batch-upsert';
	const method = 'POST';
	const headers = {
		'Square-Version': squareVersion,
		Authorization: `Bearer ${ process.env.SQUARE_ACCESS_TOKEN }`,
		'Content-Type': 'application/json',
	};

	const data = {
		idempotency_key: uuidv4(),
		batches: [ variations ],
	};

	const response = await fetch( url, {
		method,
		headers,
		body: JSON.stringify( data ),
	} );

	const res = await response.json();
	return res;
}
