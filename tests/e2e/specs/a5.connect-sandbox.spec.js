import { test, expect } from '@playwright/test';
import { isToggleChecked, saveSquareSettings } from '../utils/helper';

test( 'Connect a Square account', async ( { page } ) => {
	await page.goto( '/wp-admin/admin.php?page=wc-settings&tab=square' );

	await new Promise( ( resolve ) => setTimeout( resolve, 1000 ) );

	await page
		.getByTestId( 'environment-selection-field' )
		.selectOption( { label: 'Sandbox' } );

	await page
		.getByTestId( 'sandbox-application-id-field' )
		.fill( process.env.SQUARE_APPLICATION_ID );
	await page
		.getByTestId( 'sandbox-token-field' )
		.fill( process.env.SQUARE_ACCESS_TOKEN );

	await saveSquareSettings( page );

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
