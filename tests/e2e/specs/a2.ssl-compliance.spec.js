import { test, expect } from '@playwright/test';

test( 'Verify WooCommerce Square Integration for SSL Compliance', async ( {
	page,
} ) => {
	const sslError = `WooCommerce Square: WooCommerce is not being forced over SSL; your customers' payment data may be at risk.`;
	await page.goto( '/wp-admin/plugins.php' );
	await expect( await page.getByText( sslError ) ).toHaveCount( 1 );
} );
