import { test, expect } from '@playwright/test';

test( 'Enable/Disable Payment Gateway in WooCommerce Settings', async ( {
	page,
} ) => {
	await page.goto( '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=square_credit_card' );
	await page.getByTestId( 'credit-card-gateway-toggle-field' ).uncheck();
	await page.getByTestId( 'payment-gateway-settings-save-button' ).click();
	await expect( await page.getByText( 'Changes Saved' ) ).toBeVisible();
	await page.goto( '/wp-admin/admin.php?page=wc-settings&tab=checkout' );
	await expect(
		page.locator( 'tr[data-gateway_id="square_credit_card"] .action' )
	).toHaveText( 'Finish set up' );

	await page.goto( '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=square_credit_card' );
	await page.getByTestId( 'credit-card-gateway-toggle-field' ).check();
	await page.getByTestId( 'payment-gateway-settings-save-button' ).click();
	await expect( await page.getByText( 'Changes Saved' ) ).toBeVisible();
	await page.goto( '/wp-admin/admin.php?page=wc-settings&tab=checkout' );
	await expect(
		page.locator( 'tr[data-gateway_id="square_credit_card"] .action' )
	).toHaveText( 'Manage' );
} );
