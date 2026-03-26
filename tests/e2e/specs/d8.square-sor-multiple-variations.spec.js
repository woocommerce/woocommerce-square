import { test, expect } from '@playwright/test';
import { chromium } from 'playwright';

import {
	deleteAllProductAttributes,
	deleteAllProducts,
	doesProductExist,
	runWpCliCommand,
	saveSquareSettings,
} from '../utils/helper';
import {
	deleteAllCatalogItems,
	clearSync,
	createVariableProductsInSquare,
	importProducts,
} from '../utils/square-sandbox';

test.beforeAll( 'Setup', async () => {
	test.slow();
	const browser = await chromium.launch();
	const page = await browser.newPage();

	await page.goto(
		'/wp-admin/admin.php?page=wc-settings&tab=square&section'
	);

	await page.goto( '/wp-admin/admin.php?page=wc-settings&tab=square' );
	await page
		.getByTestId( 'sync-settings-field' )
		.selectOption( { label: 'Square' } );
	await page.getByTestId( 'pull-inventory-field' ).check();
	await saveSquareSettings( page );

	await deleteAllProductAttributes( page );
	await deleteAllProducts( page );
	await deleteAllCatalogItems();
	await clearSync( page );

	await createVariableProductsInSquare();

	// Keep using classic editor.
	await runWpCliCommand(
		'wp option update woocommerce_feature_product_block_editor_enabled "no"'
	);

	await browser.close();
} );

test( '[Square SOR] Import multiple variations products from Square @sync', async ( {
	page,
	baseURL,
} ) => {
	test.slow();
	page.on( 'dialog', ( dialog ) => dialog.accept() );
	await importProducts( page );

	await new Promise( ( resolve ) => {
		const intervalId = setInterval( async () => {
			if (
				await doesProductExist(
					baseURL,
					'multiple-variations-product-3'
				)
			) {
				clearInterval( intervalId );
				resolve();
			}
		}, 4000 );
	} );

	await page.goto( '/wp-admin/edit.php?post_type=product' );
	await page
		.getByRole( 'link', {
			name: 'MULTIPLE VARIATIONS PRODUCT 1',
			exact: true,
		} )
		.click();
	await expect( page.locator( '#product-type' ) ).toHaveValue( 'simple' );
	await expect( page.locator( '#_regular_price' ) ).toHaveValue( '9.99' );
	await page.locator( 'li.inventory_tab a' ).click();
	await expect( page.locator( '#_sku' ) ).toHaveValue(
		'multiple-variations-product-1-red'
	);

	await page.goto( '/wp-admin/edit.php?post_type=product' );
	await page
		.getByRole( 'link', {
			name: 'MULTIPLE VARIATIONS PRODUCT 2',
			exact: true,
		} )
		.click();

	await page.waitForTimeout( 1000 );
	await expect( page.locator( '#product-type' ) ).toHaveValue( 'variable' );
	await page.locator( 'li.attribute_tab a' ).waitFor();
	await page.locator( 'li.attribute_tab a' ).click();
	await expect(
		page.locator( '.woocommerce_attribute h3', { hasText: 'Color' } )
	).toBeVisible();
	await expect(
		page.locator( '.woocommerce_attribute h3', { hasText: 'Size' } )
	).toBeVisible();

	await page.locator( 'li.variations_tab a' ).click();
	await expect(
		page.locator( 'select[name="attribute_pa_color[0]"]' )
	).toHaveValue( 'red' );
	await expect(
		page.locator( 'select[name="attribute_pa_size[0]"]' )
	).toHaveValue( 'm' );
	await expect(
		page.locator( 'select[name="attribute_pa_color[1]"]' )
	).toHaveValue( 'red' );
	await expect(
		page.locator( 'select[name="attribute_pa_size[1]"]' )
	).toHaveValue( 's' );
	await expect(
		page.locator( 'select[name="attribute_pa_color[2]"]' )
	).toHaveValue( 'blue' );
	await expect(
		page.locator( 'select[name="attribute_pa_size[2]"]' )
	).toHaveValue( 'm' );
	await expect(
		page.locator( 'select[name="attribute_pa_color[3]"]' )
	).toHaveValue( 'blue' );
	await expect(
		page.locator( 'select[name="attribute_pa_size[3]"]' )
	).toHaveValue( 's' );

	await page.goto( '/wp-admin/edit.php?post_type=product' );
	await page
		.getByRole( 'link', {
			name: 'MULTIPLE VARIATIONS PRODUCT 3',
			exact: true,
		} )
		.click();

	await page.waitForTimeout( 1000 );
	await expect( page.locator( '#product-type' ) ).toHaveValue( 'variable' );
	await page.locator( 'li.attribute_tab a' ).waitFor();
	await page.locator( 'li.attribute_tab a' ).click();
	await expect(
		page.locator( '.woocommerce_attribute h3', { hasText: 'Color' } )
	).toBeVisible();
	await expect(
		page.locator( '.woocommerce_attribute h3', { hasText: 'Size' } )
	).toBeVisible();
	await expect(
		page.locator( '.woocommerce_attribute h3', {
			hasText: 'Custom Material',
		} )
	).toBeVisible();
	await page.locator( 'li.variations_tab a' ).click();
	await expect(
		page.locator( 'select[name="attribute_pa_color[0]"]' )
	).toHaveValue( 'red' );
	await expect(
		page.locator( 'select[name="attribute_pa_size[0]"]' )
	).toHaveValue( 'm' );
	await expect(
		page.locator( 'select[name="attribute_custom-material[0]"]' )
	).toHaveValue( 'Cotton' );
	await expect(
		page.locator( 'select[name="attribute_pa_color[1]"]' )
	).toHaveValue( 'red' );
	await expect(
		page.locator( 'select[name="attribute_pa_size[1]"]' )
	).toHaveValue( 's' );
	await expect(
		page.locator( 'select[name="attribute_custom-material[1]"]' )
	).toHaveValue( 'Cotton' );
	await expect(
		page.locator( 'select[name="attribute_pa_color[2]"]' )
	).toHaveValue( 'blue' );
	await expect(
		page.locator( 'select[name="attribute_pa_size[2]"]' )
	).toHaveValue( 'm' );
	await expect(
		page.locator( 'select[name="attribute_custom-material[2]"]' )
	).toHaveValue( 'Cotton' );
	await expect(
		page.locator( 'select[name="attribute_pa_color[3]"]' )
	).toHaveValue( 'blue' );
	await expect(
		page.locator( 'select[name="attribute_pa_size[3]"]' )
	).toHaveValue( 's' );
	await expect(
		page.locator( 'select[name="attribute_custom-material[3]"]' )
	).toHaveValue( 'Cotton' );
} );
