import { test, expect } from '@playwright/test';
import { isToggleChecked, saveSquareSettings } from '../utils/helper';

test( 'Connect a Square account', async ( { page } ) => {
	await page.goto( '/wp-admin/admin.php?page=wc-settings&tab=square' );

	await new Promise( ( resolve ) => setTimeout( resolve, 1000 ) );

	// Skip test if already connected to sandbox.
	test.skip( await isToggleChecked( page, '.enable-sandbox-mode-field' ) );

	if ( ! await isToggleChecked( page, '.enable-sandbox-mode-field' ) ) {
		await page.locator( '.enable-sandbox-mode-field' ).click();
	}

	await page
		.getByTestId( 'sandbox-application-id-field' )
		.fill( process.env.SQUARE_APPLICATION_ID );
	await page
		.getByTestId( 'sandbox-token-field' )
		.fill( process.env.SQUARE_ACCESS_TOKEN );

	
	await page.getByTestId( 'connect-to-square-button' ).click();

	await expect( await page.getByTestId( 'business-location-field' ) ).toBeVisible();

	await page
		.getByTestId( 'business-location-field' )
		.selectOption( { label: 'Default Test Account' } );

	await page
		.getByTestId( 'sync-settings-field' )
		.selectOption( { label: 'WooCommerce' } );

	await saveSquareSettings( page );

	await page.reload();
	await expect( await page.getByTestId( 'sync-settings-field' ) ).toHaveValue( 'woocommerce' );
} );
