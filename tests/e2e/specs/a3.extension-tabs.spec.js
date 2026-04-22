import { test, expect } from '@playwright/test';

test( 'Check Square extension related tabs and settings should appear. @general', async ( {
	page,
} ) => {
	// Square consolidated under WooCommerce > Settings > Payments > Square (no top-level Square tab).
	await page.goto(
		'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=square'
	);
	await expect(
		page.getByRole( 'navigation', { name: 'Square settings sections' } )
	).toBeVisible();
	await expect( page.getByRole( 'link', { name: 'General' } ) ).toBeVisible();
	await expect( page.getByRole( 'link', { name: 'Payment methods' } ) ).toBeVisible();

	await page.goto(
		'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=square_credit_card'
	);
	await expect(
		page.getByText(
			'Allow customers to use Square to securely pay with their credit cards'
		)
	).toHaveCount( 1 );

	await page.goto(
		'wp-admin/admin.php?page=wc-settings&tab=checkout&section=square_cash_app_pay'
	);
	await expect(
		page.getByText(
			'Allow customers to securely pay with Cash App'
		)
	).toHaveCount( 1 );
} );
