import { test, expect } from '@playwright/test';
import { chromium } from 'playwright';

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
} from '../utils/square-sandbox';
import {
	createVariableProduct,
	createVariations,
} from '../utils/variable-products';

let colorAttributeId;
let sizeAttributeId;
test.beforeAll( 'Setup', async () => {
	const browser = await chromium.launch();
	const page = await browser.newPage();

	await deleteAllProducts( page );
	await deleteAllProductAttributes( page );
	await deleteAllCatalogItems();
	await clearSync( page );

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

	await browser.close();
} );

test.slow();
test( 'Variable product (1 Global Attribute) pushed to Square @sync', async ( {
	page,
} ) => {
	await page.goto(
		'/wp-admin/admin.php?page=wc-settings&tab=square&section'
	);
	await page
		.getByTestId( 'sync-settings-field' )
		.selectOption( { label: 'WooCommerce' } );
	await page.getByTestId( 'push-inventory-field' ).check();
	await page.getByTestId( 'square-settings-save-button' ).click();
	await expect( page.getByText( 'Changes Saved!' ) ).toBeVisible();

	const randomNum = Math.floor( Math.random() * 1000 );
	const productName = `Variable Product ${ randomNum }`;
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
	expect( variations[ 0 ].price ).toEqual( 999 );
	expect( variations[ 0 ].name ).toEqual( 'red' );
} );

test( 'Variable product (1 Custom Attribute) pushed to Square @sync', async ( {
	page,
} ) => {
	await deleteAllProducts( page );
	await deleteAllCatalogItems();
	await clearSync( page );

	const randomNum = Math.floor( Math.random() * 1000 );
	const productName = `Variable Product ${ randomNum }`;
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
	expect( variations[ 0 ].sku ).toEqual(
		`variable-product-${ productId }-green`
	);
	expect( variations[ 0 ].price ).toEqual( 1099 );
	expect( variations[ 0 ].name ).toEqual( 'Green' );
} );

test( 'Variable product (2 Global Attributes) pushed to Square @sync', async ( {
	page,
	baseURL,
} ) => {
	await deleteAllProducts( page );
	await deleteAllCatalogItems();
	await clearSync( page );

	await page.goto(
		'/wp-admin/admin.php?page=wc-settings&tab=square&section'
	);
	await page
		.getByTestId( 'sync-settings-field' )
		.selectOption( { label: 'WooCommerce' } );
	await page.getByTestId( 'push-inventory-field' ).check();
	await page.getByTestId( 'square-settings-save-button' ).click();
	await expect( page.getByText( 'Changes Saved!' ) ).toBeVisible();

	const randomNum = Math.floor( Math.random() * 1000 );
	const productName = `Variable Product ${ randomNum }`;
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
	const skus = productAttributes.map( ( attribute ) => attribute.sku );
	variations.forEach( ( variation ) => {
		expect( skus.includes( variation.sku ) ).toBeTruthy();
		expect( variation.item_option_values.length ).toBe( 2 );
	} );
} );
