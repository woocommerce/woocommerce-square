import { test, expect } from '@playwright/test';
import { chromium } from 'playwright';
import { get, update } from '../utils/api';

import {
	deleteAllProductAttributes,
	deleteAllProducts,
	getCatalogData,
	runWpCliCommand,
} from '../utils/helper';
import {
	deleteAllCatalogItems,
	extractCatalogInfo,
	clearSync,
	listCatalog,
} from '../utils/square-sandbox';
import {
	createVariableProduct,
	createVariations,
	updateVariableProduct,
} from '../utils/variable-products';

let colorAttributeId;
let sizeAttributeId;
test.beforeAll( 'Setup', async () => {
	test.slow();
	const browser = await chromium.launch();
	const page = await browser.newPage();

	await page.goto(
		'/wp-admin/admin.php?page=wc-settings&tab=square&section'
	);
	await page
		.getByTestId( 'sync-settings-field' )
		.selectOption( { label: 'WooCommerce' } );
	await page.getByTestId( 'push-inventory-field' ).check();
	await page.getByTestId( 'square-settings-save-button' ).click();
	await expect( page.getByText( 'Changes Saved!' ) ).toBeVisible();

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

test.slow();

test( '[1 Global Attribute] Variable product pushed to Square @sync', async ( {
	page,
} ) => {
	const randomNum = Math.floor( Math.random() * 1000 );
	const productName = `Variable Product ${ randomNum } (1 Global Attribute)`;
	const product = {
		name: productName,
		description: 'This is a variable product',
	};
	const attributes = [
		{
			id: colorAttributeId,
			visible: true,
			variation: true,
			options: [ 'Red' ],
		},
	];

	const productId = await createVariableProduct( product, attributes );
	const variationIds = await createVariations( productId, [
		{
			regular_price: '9.99',
			sku: `variable-product-${ productId }-red`,
			attributes: [
				{
					id: colorAttributeId,
					option: 'Red',
				},
			],
		},
	] );

	await page.goto( `/wp-admin/post.php?post=${ productId }&action=edit` );
	await page.locator( '.general_tab' ).click();
	await page.locator( '#_wc_square_synced' ).check();
	await page.locator( '#publish' ).click();
	await expect( page.getByText( 'Product updated' ) ).toBeVisible();

	await page.goto(
		'/wp-admin/admin.php?page=wc-settings&tab=square&section=update'
	);

	const catalogData = await getCatalogData( page );
	const { name, variations } = extractCatalogInfo( catalogData.objects[ 0 ] );

	expect( name ).toEqual( productName );
	expect( variations[ 0 ].sku ).toEqual(
		`variable-product-${ productId }-red`
	);
	expect( variations.length ).toEqual( 1 );
	expect( variations[ 0 ].price ).toEqual( 999 );
	expect( variations[ 0 ].name ).toEqual( 'red' );
} );

test( '[1 Custom Attribute] Variable product pushed to Square @sync', async ( {
	page,
} ) => {
	const randomNum = Math.floor( Math.random() * 1000 );
	const productName = `Variable Product ${ randomNum } (1 Custom Attribute)`;
	const product = {
		name: productName,
		description: 'This is a variable product',
	};
	const attributes = [
		{
			name: 'Custom Color',
			visible: true,
			variation: true,
			options: [ 'Green' ],
		},
	];

	const productId = await createVariableProduct( product, attributes );

	const variationIds = await createVariations( productId, [
		{
			regular_price: '10.99',
			sku: `variable-product-${ productId }-green`,
			attributes: [
				{
					name: 'Custom Color',
					option: 'Green',
				},
			],
		},
	] );

	await page.goto( `/wp-admin/post.php?post=${ productId }&action=edit` );
	await page.locator( '.general_tab' ).click();
	await page.locator( '#_wc_square_synced' ).check();
	await page.locator( '#publish' ).click();
	await expect( page.getByText( 'Product updated' ) ).toBeVisible();

	await page.goto(
		'/wp-admin/admin.php?page=wc-settings&tab=square&section=update'
	);

	const catalogData = await getCatalogData( page, 120000 );
	const { name, variations } = extractCatalogInfo( catalogData.objects[ 0 ] );

	expect( name ).toEqual( productName );
	expect( variations.length ).toEqual( 1 );
	expect( variations[ 0 ].sku ).toEqual(
		`variable-product-${ productId }-green`
	);
	expect( variations[ 0 ].price ).toEqual( 1099 );
	expect( variations[ 0 ].name ).toEqual( 'Green' );
} );

test( '[2 Global Attributes] Variable product pushed to Square @sync', async ( {
	page,
} ) => {
	const randomNum = Math.floor( Math.random() * 1000 );
	const productName = `Variable Product ${ randomNum } (2 Global Attributes)`;
	const product = {
		name: productName,
		description: 'This is a variable product',
	};
	const attributes = [
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
	];

	const productId = await createVariableProduct( product, attributes );

	const productAttributes = [
		{
			regular_price: '9.99',
			sku: `variable-product-${ productId }-red-s`,
			attributes: [
				{
					id: colorAttributeId,
					option: 'Red',
				},
				{
					id: sizeAttributeId,
					option: 'S',
				},
			],
		},
		{
			regular_price: '10.99',
			sku: `variable-product-${ productId }-red-m`,
			attributes: [
				{
					id: colorAttributeId,
					option: 'Red',
				},
				{
					id: sizeAttributeId,
					option: 'M',
				},
			],
		},
		{
			regular_price: '11.99',
			sku: `variable-product-${ productId }-blue-s`,
			attributes: [
				{
					id: colorAttributeId,
					option: 'Blue',
				},
				{
					id: sizeAttributeId,
					option: 'S',
				},
			],
		},
		{
			regular_price: '12.99',
			sku: `variable-product-${ productId }-blue-m`,
			attributes: [
				{
					id: colorAttributeId,
					option: 'Blue',
				},
				{
					id: sizeAttributeId,
					option: 'M',
				},
			],
		},
	];
	const variationIds = await createVariations( productId, productAttributes );

	await page.goto( `/wp-admin/post.php?post=${ productId }&action=edit`, {
		waitUntil: 'networkidle',
	} );
	await page.locator( '.general_tab' ).click();
	await page.locator( '#_wc_square_synced' ).check();
	await page.locator( '#publish' ).click();
	await expect( page.getByText( 'Product updated' ) ).toBeVisible();

	await page.goto(
		'/wp-admin/admin.php?page=wc-settings&tab=square&section=update'
	);

	const catalogData = await getCatalogData( page );
	const { name, variations } = extractCatalogInfo( catalogData.objects[ 0 ] );
	expect( name ).toEqual( productName );
	expect( variations.length ).toEqual( 4 );
	const skus = productAttributes.map( ( attribute ) => attribute.sku );
	variations.forEach( ( variation ) => {
		expect( skus.includes( variation.sku ) ).toBeTruthy();
		expect( variation.item_option_values.length ).toBe( 2 );
	} );
} );

test( '[2 Custom Attributes] Variable product pushed to Square @sync', async ( {
	page,
} ) => {
	const randomNum = Math.floor( Math.random() * 1000 );
	const productName = `Variable Product ${ randomNum } (2 Custom Attributes)`;
	const product = {
		name: productName,
		description: 'This is a variable product',
	};
	const attributes = [
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
	];

	const productId = await createVariableProduct( product, attributes );

	const productAttributes = [
		{
			regular_price: '9.99',
			sku: `variable-product-${ productId }-red-s`,
			attributes: [
				{
					name: 'Custom Color',
					option: 'Red',
				},
				{
					name: 'Custom Size',
					option: 'S',
				},
			],
		},
		{
			regular_price: '10.99',
			sku: `variable-product-${ productId }-red-m`,
			attributes: [
				{
					name: 'Custom Color',
					option: 'Red',
				},
				{
					name: 'Custom Size',
					option: 'M',
				},
			],
		},
		{
			regular_price: '11.99',
			sku: `variable-product-${ productId }-blue-s`,
			attributes: [
				{
					name: 'Custom Color',
					option: 'Blue',
				},
				{
					name: 'Custom Size',
					option: 'S',
				},
			],
		},
		{
			regular_price: '12.99',
			sku: `variable-product-${ productId }-blue-m`,
			attributes: [
				{
					name: 'Custom Color',
					option: 'Blue',
				},
				{
					name: 'Custom Size',
					option: 'M',
				},
			],
		},
	];
	const variationIds = await createVariations( productId, productAttributes );

	await page.goto( `/wp-admin/post.php?post=${ productId }&action=edit`, {
		waitUntil: 'networkidle',
	} );
	await page.locator( '.general_tab' ).click();
	await page.locator( '#_wc_square_synced' ).check();
	await page.locator( '#publish' ).click();
	await expect( page.getByText( 'Product updated' ) ).toBeVisible();

	await page.goto(
		'/wp-admin/admin.php?page=wc-settings&tab=square&section=update'
	);

	const catalogData = await getCatalogData( page );
	const { name, variations } = extractCatalogInfo( catalogData.objects[ 0 ] );
	expect( name ).toEqual( productName );
	expect( variations.length ).toEqual( 4 );
	const skus = productAttributes.map( ( attribute ) => attribute.sku );
	variations.forEach( ( variation ) => {
		expect( skus.includes( variation.sku ) ).toBeTruthy();
		expect( variation.item_option_values.length ).toBe( 2 );
	} );
} );

test.describe( '[1 Global Attribute + 1 Custom Attribute]', () => {
	let productId = 142;
	test( 'Variable product pushed to Square @sync', async ( { page } ) => {
		const randomNum = Math.floor( Math.random() * 1000 );
		const productName = `Variable Product ${ randomNum } (1 Global Attribute + 1 Custom Attribute)`;
		const product = {
			name: productName,
			description: 'This is a variable product',
		};
		const attributes = [
			{
				id: colorAttributeId,
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
		];

		productId = await createVariableProduct( product, attributes );

		const productAttributes = [
			{
				regular_price: '9.99',
				sku: `variable-product-${ productId }-red-s`,
				attributes: [
					{
						id: colorAttributeId,
						option: 'Red',
					},
					{
						name: 'Custom Size',
						option: 'S',
					},
				],
			},
			{
				regular_price: '10.99',
				sku: `variable-product-${ productId }-red-m`,
				attributes: [
					{
						id: colorAttributeId,
						option: 'Red',
					},
					{
						name: 'Custom Size',
						option: 'M',
					},
				],
			},
			{
				regular_price: '11.99',
				sku: `variable-product-${ productId }-blue-s`,
				attributes: [
					{
						id: colorAttributeId,
						option: 'Blue',
					},
					{
						name: 'Custom Size',
						option: 'S',
					},
				],
			},
			{
				regular_price: '12.99',
				sku: `variable-product-${ productId }-blue-m`,
				attributes: [
					{
						id: colorAttributeId,
						option: 'Blue',
					},
					{
						name: 'Custom Size',
						option: 'M',
					},
				],
			},
		];
		const variationIds = await createVariations(
			productId,
			productAttributes
		);

		await page.goto( `/wp-admin/post.php?post=${ productId }&action=edit`, {
			waitUntil: 'networkidle',
		} );
		await page.locator( '.general_tab' ).click();
		await page.locator( '#_wc_square_synced' ).check();
		await page.locator( '#publish' ).click();
		await expect( page.getByText( 'Product updated' ) ).toBeVisible();

		await page.goto(
			'/wp-admin/admin.php?page=wc-settings&tab=square&section=update'
		);

		const catalogData = await getCatalogData( page );
		const { name, variations } = extractCatalogInfo(
			catalogData.objects[ 0 ]
		);
		expect( name ).toEqual( productName );
		expect( variations.length ).toEqual( 4 );
		const skus = productAttributes.map( ( attribute ) => attribute.sku );
		variations.forEach( ( variation ) => {
			expect( skus.includes( variation.sku ) ).toBeTruthy();
			expect( variation.item_option_values.length ).toBe( 2 );
		} );
	} );

	test( 'Variable product updated and pushed to Square @sync', async ( {
		page,
	} ) => {
		if ( ! productId ) {
			throw new Error( 'Product ID is required to update a product.' );
		}

		const attributes = [
			{
				id: sizeAttributeId,
				visible: true,
				variation: true,
				options: [ 'S', 'M' ],
			},
		];

		await updateVariableProduct( { id: productId }, attributes );
		const productVariations = await get.productVariations( productId );

		for ( let i = 0; i < productVariations.length; i++ ) {
			const variation = productVariations[ i ];
			await update.productVariation( productId, variation.id, {
				attributes: [
					...variation.attributes,
					{
						id: sizeAttributeId,
						option: 'S',
					},
				],
			} );
		}

		const newAttributes = [
			{
				regular_price: '13.99',
				sku: `variable-product-${ productId }-blue-m-m`,
				attributes: [
					{
						id: colorAttributeId,
						option: 'Blue',
					},
					{
						id: sizeAttributeId,
						option: 'M',
					},
					{
						name: 'Custom Size',
						option: 'M',
					},
				],
			},
		];
		await createVariations( productId, newAttributes );

		await page.goto(
			'/wp-admin/admin.php?page=wc-settings&tab=square&section=update'
		);

		const MAX_PROCESSING_TIME = 120000;
		const POLLING_INTERVAL_BETWEEN_RETRIES = 3000;

		const getCatalogDataInner = async () => {
			const result = await listCatalog();
			return result;
		};

		// Expose getCatalogData function to page context
		await page.exposeFunction( 'getCatalogDataInner', getCatalogDataInner );

		const startTime = Date.now();
		let catalogData = null;

		while ( Date.now() - startTime < MAX_PROCESSING_TIME ) {
			const result = await getCatalogDataInner();
			if (
				result?.objects?.length > 0 &&
				result.objects[ 0 ]?.item_data?.variations.length === 5
			) {
				catalogData = result;
				break;
			}
			await page.waitForTimeout( POLLING_INTERVAL_BETWEEN_RETRIES );
		}

		if ( ! catalogData ) {
			throw new Error(
				`Catalog item not updated after ${ MAX_PROCESSING_TIME }ms of polling`
			);
		}

		const { variations } = extractCatalogInfo( catalogData.objects[ 0 ] );
		expect( variations.length ).toEqual( 5 );
		variations.forEach( ( variation ) => {
			expect( variation.item_option_values.length ).toBe( 3 );
		} );
	} );
} );

test( '[2 Global Attributes + 1 Custom Attribute] Variable product pushed to Square @sync', async ( {
	page,
} ) => {
	const randomNum = Math.floor( Math.random() * 1000 );
	const productName = `Variable Product ${ randomNum } (2 Global Attributes + 1 Custom Attribute)`;
	const product = {
		name: productName,
		description: 'This is a variable product',
	};
	const attributes = [
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
	];

	const productId = await createVariableProduct( product, attributes );

	const productAttributes = [
		{
			regular_price: '9.99',
			sku: `variable-product-${ productId }-red-s-cotton`,
			attributes: [
				{
					id: colorAttributeId,
					option: 'Red',
				},
				{
					id: sizeAttributeId,
					option: 'S',
				},
				{
					name: 'Custom Material',
					option: 'Cotton',
				},
			],
		},
		{
			regular_price: '10.99',
			sku: `variable-product-${ productId }-red-m-cotton`,
			attributes: [
				{
					id: colorAttributeId,
					option: 'Red',
				},
				{
					id: sizeAttributeId,
					option: 'M',
				},
				{
					name: 'Custom Material',
					option: 'Cotton',
				},
			],
		},
		{
			regular_price: '11.99',
			sku: `variable-product-${ productId }-blue-s-cotton`,
			attributes: [
				{
					id: colorAttributeId,
					option: 'Blue',
				},
				{
					id: sizeAttributeId,
					option: 'S',
				},
				{
					name: 'Custom Material',
					option: 'Cotton',
				},
			],
		},
		{
			regular_price: '12.99',
			sku: `variable-product-${ productId }-blue-m-cotton`,
			attributes: [
				{
					id: colorAttributeId,
					option: 'Blue',
				},
				{
					id: sizeAttributeId,
					option: 'M',
				},
				{
					name: 'Custom Material',
					option: 'Cotton',
				},
			],
		},
	];
	const variationIds = await createVariations( productId, productAttributes );

	await page.goto( `/wp-admin/post.php?post=${ productId }&action=edit`, {
		waitUntil: 'networkidle',
	} );
	await page.locator( '.general_tab' ).click();
	await page.locator( '#_wc_square_synced' ).check();
	await page.locator( '#publish' ).click();
	await expect( page.getByText( 'Product updated' ) ).toBeVisible();

	await page.goto(
		'/wp-admin/admin.php?page=wc-settings&tab=square&section=update'
	);

	const catalogData = await getCatalogData( page );
	const { name, variations } = extractCatalogInfo( catalogData.objects[ 0 ] );
	expect( name ).toEqual( productName );
	expect( variations.length ).toEqual( 4 );
	const skus = productAttributes.map( ( attribute ) => attribute.sku );
	variations.forEach( ( variation ) => {
		expect( skus.includes( variation.sku ) ).toBeTruthy();
		expect( variation.item_option_values.length ).toBe( 3 );
	} );
} );
