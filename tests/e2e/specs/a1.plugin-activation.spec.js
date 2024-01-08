import { test, expect } from '@playwright/test';

test( 'Can deactivate the plugin without any error', async ( { page } ) => {
	await page.goto( '/wp-admin/plugins.php' );
	await page.locator( '#deactivate-woocommerce-square' ).click();
	await expect( await page.getByText( 'Plugin deactivated.' ) ).toHaveCount(
		1
	);
} );

test( 'Can activate the plugin without any error', async ( { page } ) => {
	await page.goto( '/wp-admin/plugins.php' );
	await page.locator( '#activate-woocommerce-square' ).click();
	await expect( await page.getByText( 'Plugin activated.' ) ).toHaveCount(
		1
	);
} );
