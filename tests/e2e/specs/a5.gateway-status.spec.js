import { test, expect } from '@playwright/test';

test( 'Enable/Disable Payment Gateway in WooCommerce Settings @general', async ( {
	page,
} ) => {
	await page.goto(
		'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=square_credit_card'
	);
	await page
		.getByTestId( 'credit-card-gateway-toggle-field' )
		.getByRole( 'checkbox' )
		.uncheck();
	await page.getByTestId( 'payment-gateway-settings-save-button' ).click();
	await expect( await page.getByText( 'Changes Saved!' ) ).toBeVisible();
	await page.goto( '/wp-admin/admin.php?page=wc-settings&tab=checkout' );

	const creditCardGateway = page.locator(
		'tr[data-gateway_id="square_credit_card"]'
	);
	if ( await creditCardGateway.isVisible() ) {
		await expect(
			page.locator( 'tr[data-gateway_id="square_credit_card"] .action' )
		).toHaveText( 'Finish setup' );
	} else {
		await expect(
			page
				.locator(
					'.settings-payment-gateways #square_credit_card .woocommerce-status-badge'
				)
				.first()
		).toHaveText( /Inactive|Action needed/ );
	}

	await page.goto(
		'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=square_credit_card'
	);
	await page
		.getByTestId( 'credit-card-gateway-toggle-field' )
		.getByRole( 'checkbox' )
		.check();
	await page.getByTestId( 'payment-gateway-settings-save-button' ).click();
	await expect( await page.getByText( 'Changes Saved!' ) ).toBeVisible();
	await page.goto( '/wp-admin/admin.php?page=wc-settings&tab=checkout' );

	if (
		await page
			.locator( 'tr[data-gateway_id="square_credit_card"]' )
			.isVisible()
	) {
		await expect(
			page.locator( 'tr[data-gateway_id="square_credit_card"] .action' )
		).toHaveText( 'Manage' );
	} else {
		await expect(
			page
				.locator(
					'.settings-payment-gateways #square_credit_card .woocommerce-status-badge'
				)
				.first()
		).toHaveText( /Test mode|Active/ );
	}
} );
