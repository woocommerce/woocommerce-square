import { test, expect } from '@playwright/test';
import { visitOnboardingPage } from '../../utils/helper';

test( 'Can configure sync settings via Onboarding', async () => {
	await visitOnboardingPage( page );

	await page.getByTestId( 'configure-sync-button' ).click();

	// Sync setting: disabled
	await page.getByTestId( 'sync-settings-field' ).selectOption( { label: 'Disabled' } );
	await expect( await page.getByTestId( 'pull-inventory-field' ) ).toHaveCount( 0 );
	await expect( await page.getByTestId( 'push-inventory-field' ) ).toHaveCount( 0 );
	await expect( await page.getByTestId( 'override-images-field' ) ).toHaveCount( 0 );
	await expect( await page.getByTestId( 'hide-missing-products-field' ) ).toHaveCount( 0 );
	await expect( await page.getByTestId( 'sync-interval-field' ) ).toHaveCount( 0 );
	await expect( await page.getByTestId( 'import-products-button' ) ).toHaveCount( 0 );

	// Sync setting: square
	await page.getByTestId( 'sync-settings-field' ).selectOption( { label: 'Square' } );
	await expect( await page.getByTestId( 'pull-inventory-field' ) ).toHaveCount( 1 );
	await expect( await page.getByTestId( 'push-inventory-field' ) ).toHaveCount( 0 );
	await expect( await page.getByTestId( 'override-images-field' ) ).toHaveCount( 1 );
	await expect( await page.getByTestId( 'hide-missing-products-field' ) ).toHaveCount( 1 );
	await expect( await page.getByTestId( 'sync-interval-field' ) ).toHaveCount( 1 );
	await expect( await page.getByTestId( 'import-products-button' ) ).toHaveCount( 1 );

	// Sync setting: woocommerce
	await page.getByTestId( 'sync-settings-field' ).selectOption( { label: 'WooCommerce' } );
	await expect( await page.getByTestId( 'pull-inventory-field' ) ).toHaveCount( 0 );
	await expect( await page.getByTestId( 'push-inventory-field' ) ).toHaveCount( 1 );
	await expect( await page.getByTestId( 'override-images-field' ) ).toHaveCount( 0 );
	await expect( await page.getByTestId( 'hide-missing-products-field' ) ).toHaveCount( 0 );
	await expect( await page.getByTestId( 'sync-interval-field' ) ).toHaveCount( 1 );
	await expect( await page.getByTestId( 'import-products-button' ) ).toHaveCount( 1 );

	// Change settings
	await page.getByTestId( 'sync-settings-field' ).selectOption( { label: 'Square' } );
	await page.getByTestId( 'pull-inventory-field' ).check();
	await page.getByTestId( 'override-images-field' ).check();
	await page.getByTestId( 'hide-missing-products-field' ).check();
	await page.getByTestId( 'sync-interval-field' ).selectOption( { label: '45 minutes' } );

	// save settings
	await page.getByTestId( 'square-settings-save-button' ).click();
	await expect( await page.getByText( 'Your Payment Setup is Complete!' ) ).toBeVisible();

	// Check for sync setting: square
	await page.getByTestId( 'configure-sync-button' ).click();
	await page.reload();
	await expect( await page.getByTestId( 'sync-settings-field' ) ).toHaveText( 'Square' );
	await expect( await page.getByTestId( 'pull-inventory-field' ) ).toBeChecked();
	await expect( await page.getByTestId( 'override-images-field' ) ).toBeChecked();
	await expect( await page.getByTestId( 'hide-missing-products-field' ) ).toBeChecked();
	await expect( await page.getByTestId( 'sync-interval-field' ) ).toHaveText( '45 minutes' );
} );
