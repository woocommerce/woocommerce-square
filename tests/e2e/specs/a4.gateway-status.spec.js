import { test, expect } from '@playwright/test';

test( 'Enable/Disable Payment Gateway in WooCommerce Settings', async ( {
	page,
} ) => {
	await page.goto( '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=square_credit_card' );
	await page.locator( '#woocommerce_square_credit_card_enabled' ).uncheck();
	await page.locator( '.woocommerce-save-button' ).click();
	await page.goto( '/wp-admin/admin.php?page=wc-settings&tab=checkout' );
	await expect(
		page.locator( 'tr[data-gateway_id="square_credit_card"] .action' )
	).toHaveText( 'Finish set up' );

	await page.goto( '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=square_credit_card' );
	await page.locator( '#woocommerce_square_credit_card_enabled' ).check();
	await page.locator( '.woocommerce-save-button' ).click();
	await page.goto( '/wp-admin/admin.php?page=wc-settings&tab=checkout' );
	await expect(
		page.locator( 'tr[data-gateway_id="square_credit_card"] .action' )
	).toHaveText( 'Manage' );
} );
