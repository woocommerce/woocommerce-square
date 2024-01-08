import { test, expect } from '@playwright/test';

test( 'Connect a Square account', async ( { page } ) => {
	await page.goto( '/wp-admin/admin.php?page=wc-settings&tab=square' );

	// Skip test if already connected to sandbox.
	test.skip( await page.locator( '#wc_square_enable_sandbox' ).isChecked() );

	await page.locator( '#wc_square_enable_sandbox' ).check();
	await page.locator( '.woocommerce-save-button' ).click();

	await page
		.locator( '#wc_square_sandbox_application_id' )
		.fill( process.env.SQUARE_APPLICATION_ID );
	await page
		.locator( '#wc_square_sandbox_token' )
		.fill( process.env.SQUARE_ACCESS_TOKEN );
	await page.locator( '.woocommerce-save-button' ).click();

	await expect(
		page.getByText(
			'You are connected to Square! To get started, set your business location.'
		)
	).toHaveCount( 1 );

	await page
		.locator( '#wc_square_sandbox_location_id' )
		.selectOption( { label: 'Default Test Account' } );
	await page
		.locator( '#wc_square_system_of_record' )
		.selectOption( { label: 'WooCommerce' } );
	await page.locator( '.woocommerce-save-button' ).click();
} );
