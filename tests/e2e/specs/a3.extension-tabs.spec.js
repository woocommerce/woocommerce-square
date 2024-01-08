import { test, expect } from '@playwright/test';

test( 'Check Square extension related tabs and settings should appear.', async ( {
	page,
} ) => {
	await page.goto( '/wp-admin/admin.php?page=wc-settings' );
	const navTabs = await page.$$( '.nav-tab' );
	const hasSquareTab = await Promise.all(
		navTabs.map( async ( tab ) => {
			const text = await tab.innerText();
			return text.includes( 'Square' );
		} )
	);
	expect( hasSquareTab ).toContain( true );

	await page.goto(
		'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=square_credit_card'
	);
	await expect(
		page.getByText(
			'Allow customers to use Square to securely pay with their credit cards'
		)
	).toHaveCount( 1 );
} );
