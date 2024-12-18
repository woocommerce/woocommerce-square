import { test, expect } from '@playwright/test';
import { chromium } from 'playwright';
import { get } from '../utils/api';

import {
	deleteAllProductAttributes,
	deleteAllProducts,
	getCatalogData,
	runScheduledAction,
	runWpCliCommand,
	saveSquareSettings,
} from '../utils/helper';
import {
	deleteAllCatalogItems,
	extractCatalogInfo,
	clearSync,
} from '../utils/square-sandbox';
import {
	createVariableProduct,
	createVariations,
} from '../utils/variable-products';

let colorAttributeId;
let sizeAttributeId;

test.beforeAll( 'Setup', async () => {
	test.setTimeout( 240000 );
	const browser = await chromium.launch();
	const page = await browser.newPage();

	await page.goto(
		'/wp-admin/admin.php?page=wc-settings&tab=square&section'
	);
	await page
		.getByTestId( 'sync-settings-field' )
		.selectOption( { label: 'Square' } ); // This is for prevent push products to Square when we create products.
	await saveSquareSettings( page );

	await page.goto(
		'/wp-admin/edit.php?post_type=product&page=product_attributes'
	);
	if (
		! (
			( await page
				.locator( 'table.attributes-table tr td strong a', {
					hasText: 'Color',
				} )
				.isVisible() ) &&
			( await page
				.locator( 'table.attributes-table tr td strong a', {
					hasText: 'Size',
				} )
				.isVisible() ) &&
			( await page
				.locator( 'table.attributes-table tr td.attribute-terms', {
					hasText: 'Blue, Green, Red',
				} )
				.isVisible() ) &&
			( await page
				.locator( 'table.attributes-table tr td.attribute-terms', {
					hasText: 'M, S',
				} )
				.isVisible() )
		)
	) {
		// Delete all product attributes
		await deleteAllProductAttributes( page );

		// Create color attribute
		colorAttributeId = await runWpCliCommand(
			'wp wc product_attribute create --name=Color --user=admin --porcelain'
		);
		// Create color attribute terms
		await runWpCliCommand(
			`wp wc product_attribute_term create ${ colorAttributeId?.trim() } --name=Red --user=admin`
		);
		await runWpCliCommand(
			`wp wc product_attribute_term create ${ colorAttributeId?.trim() } --name=Green --user=admin`
		);

		// Create size attribute
		sizeAttributeId = await runWpCliCommand(
			'wp wc product_attribute create --name=Size --user=admin --porcelain'
		);
		// Create size attribute terms
		await runWpCliCommand(
			`wp wc product_attribute_term create ${ sizeAttributeId?.trim() } --name=S --user=admin`
		);
		await runWpCliCommand(
			`wp wc product_attribute_term create ${ sizeAttributeId?.trim() } --name=M --user=admin`
		);
	} else {
		const attributes = await get.productAttributes();
		colorAttributeId =
			attributes.find( ( attribute ) => attribute.name === 'Color' )
				?.id || '';
		sizeAttributeId =
			attributes.find( ( attribute ) => attribute.name === 'Size' )?.id ||
			'';
	}

	// Keep using classic editor.
	await runWpCliCommand(
		'wp option update woocommerce_feature_product_block_editor_enabled "no"'
	);

	await browser.close();
} );

test.beforeEach( 'Clear sync', async ( { page }, testInfo ) => {
	if (
		testInfo.title === 'Variable product updated and pushed to Square @sync'
	) {
		return;
	}
	await deleteAllProducts( page );
	await deleteAllCatalogItems();
	await clearSync( page );
} );

/**
 * Generate product attributes for a variable product.
 *
 * @param {number} productId  Product ID
 * @param {Array}  attributes List of attributes
 * @return {Array} List of product attributes
 */
const generateProductAttributes = ( productId, attributes ) => {
	let productAttributes = [];
	attributes.forEach( ( attribute ) => {
		const attributeId = attribute.id || attribute.name || '';
		const attributeKey = attribute.id ? 'id' : 'name';

		const options = attribute.options;

		if ( productAttributes.length === 0 ) {
			options.forEach( ( option ) => {
				const variation = {
					sku: `variable-product-${ productId }-${ option.toLowerCase() }`,
					attributes: [
						{
							[ attributeKey ]: attributeId,
							option,
						},
					],
				};
				productAttributes.push( variation );
			} );
		} else {
			const newProductAttributes = [];
			productAttributes.forEach( ( productAttribute ) => {
				options.forEach( ( option ) => {
					const variation = {
						sku: `${
							productAttribute.sku
						}-${ option.toLowerCase() }`,
						attributes: [
							...productAttribute.attributes,
							{
								[ attributeKey ]: attributeId,
								option,
							},
						],
					};
					newProductAttributes.push( variation );
				} );
			} );

			productAttributes = [ ...newProductAttributes ];
		}
	} );

	let regularPrice = 0.99;
	let stock = 0;
	return productAttributes.map( ( productAttribute ) => {
		regularPrice += 10;
		stock += 10;
		return {
			regular_price: regularPrice,
			manage_stock: true,
			stock_quantity: stock,
			...productAttribute,
		};
	} );
};

/**
 * Create a variable product with variations.
 *
 * @param {Object} product Product object
 * @return {Object} Product and variation IDs
 */
const createProduct = async ( product ) => {
	const productId = await createVariableProduct( product );
	const productAttributes = generateProductAttributes(
		productId,
		product.attributes
	);

	const variationIds = await createVariations( productId, productAttributes );
	return {
		productId,
		variationIds,
		productAttributes,
	};
};

/**
 * Create multiple variable products with variations.
 *
 * @param {Object} page     Page object
 * @param {Array}  products List of products
 * @return {Array} List of product IDs
 */
const createProducts = async ( page, products ) => {
	const productData = [];
	const productIds = [];

	for ( const product of products ) {
		const { productId, productAttributes } = await createProduct( product );
		productIds.push( productId );
		productData.push( {
			...product,
			productId,
			productAttributes,
		} );
	}

	for ( const productId of productIds ) {
		await page.goto( `/wp-admin/post.php?post=${ productId }&action=edit` );
		await page.locator( '.general_tab' ).click();
		await page.locator( '#_wc_square_synced' ).check();
		await page.locator( '#publish' ).click();
		await expect( page.getByText( 'Product updated' ) ).toBeVisible();
	}

	return productData;
};

test( '[Woo SOR] Merchant should able to sync products with multiple variations @sync @syncTemp', async ( {
	page,
} ) => {
	// Increase timeout for this test
	test.setTimeout( 240000 );

	const randomNum = Math.floor( Math.random() * 1000 );
	const products = [
		{
			name: `Variable Product ${ randomNum } (1 Global Attribute)`,
			description: 'This is a variable product',
			attributes: [
				{
					id: colorAttributeId,
					visible: true,
					variation: true,
					options: [ 'Red' ],
				},
			],
		},
		{
			name: `Variable Product ${ randomNum } (1 Custom Attribute)`,
			description: 'This is a variable product',
			attributes: [
				{
					name: 'Custom Color',
					visible: true,
					variation: true,
					options: [ 'Green' ],
				},
			],
		},
		{
			name: `Variable Product ${ randomNum } (2 Global Attributes)`,
			description: 'This is a variable product',
			attributes: [
				{
					id: colorAttributeId,
					visible: true,
					variation: true,
					options: [ 'Red', 'Blue' ],
				},
				{
					id: sizeAttributeId,
					visible: true,
					variation: true,
					options: [ 'S', 'M' ],
				},
			],
		},
		{
			name: `Variable Product ${ randomNum } (2 Custom Attributes)`,
			description: 'This is a variable product',
			attributes: [
				{
					name: 'Custom Color',
					visible: true,
					variation: true,
					options: [ 'Red', 'Blue' ],
				},
				{
					name: 'Custom Size',
					visible: true,
					variation: true,
					options: [ 'S', 'M' ],
				},
			],
		},
		{
			name: `Variable Product ${ randomNum } (1 Global Attribute + 1 Custom Attribute)`,
			description: 'This is a variable product',
			attributes: [
				{
					id: colorAttributeId,
					visible: true,
					variation: true,
					options: [ 'Red' ],
				},
				{
					name: 'Custom Size',
					visible: true,
					variation: true,
					options: [ 'S', 'M' ],
				},
			],
		},
		{
			name: `Variable Product ${ randomNum } (2 Global Attribute + 1 Custom Attribute)`,
			description: 'This is a variable product',
			attributes: [
				{
					id: colorAttributeId,
					visible: true,
					variation: true,
					options: [ 'Red', 'Blue' ],
				},
				{
					id: sizeAttributeId,
					visible: true,
					variation: true,
					options: [ 'S', 'M' ],
				},
				{
					name: 'Custom Material',
					visible: true,
					variation: true,
					options: [ 'Cotton', 'Polyester' ],
				},
			],
		},
	];

	const productsData = await createProducts( page, products );
	await page.goto(
		'/wp-admin/admin.php?page=wc-settings&tab=square&section'
	);
	await page
		.getByTestId( 'sync-settings-field' )
		.selectOption( { label: 'WooCommerce' } );
	await page.getByTestId( 'push-inventory-field' ).check();
	await page
		.locator( '.woo-square-setting__input-wrapper--boxed', {
			hasText: 'Enable Logging',
		} )
		.locator( 'input.components-form-toggle__input' )
		.check();
	await saveSquareSettings( page );

	await page.goto(
		'/wp-admin/admin.php?page=wc-settings&tab=square&section=update'
	);
	await page.locator( '#wc-square-sync' ).click();
	await page.locator( '#btn-ok' ).click();
	await page.waitForTimeout( 1000 );

	await runScheduledAction( page );
	await page.goto(
		'/wp-admin/admin.php?page=wc-settings&tab=square&section=update'
	);

	const catalogData = await getCatalogData( page, 120000, 6 );

	for ( const object of catalogData.objects ) {
		const { name, variations } = extractCatalogInfo( object );
		const product = productsData.find( ( p ) => name.includes( p.name ) );

		expect( product ).toBeDefined();
		if ( product.attributes.length === 1 ) {
			expect( variations.length ).toEqual( 1 );
			if ( product.name.includes( 'Custom' ) ) {
				expect( variations[ 0 ].name ).toEqual(
					product.attributes[ 0 ].options[ 0 ]
				);
			} else {
				expect( variations[ 0 ].name ).toEqual(
					product.attributes[ 0 ].options[ 0 ]?.toLowerCase()
				);
			}
		} else {
			let totalVariations = 1;
			product.attributes.forEach( ( attribute ) => {
				totalVariations *= attribute.options.length;
			} );
			expect( variations.length ).toEqual( totalVariations );
			variations.forEach( ( variation ) => {
				// Validate attributes
				expect( variation.item_option_values.length ).toBe(
					product.attributes.length
				);
			} );
		}
		variations.forEach( ( variation ) => {
			// Validate SKU
			const productAttribute = product.productAttributes.find(
				( pa ) => pa.sku === variation.sku
			);
			expect( productAttribute ).toBeDefined();

			// Validate price
			expect( variation.price ).toEqual(
				Math.round( productAttribute.regular_price * 100 )
			);
		} );
	}
} );
