import { chromium } from '@playwright/test';

module.exports = async config => {
	const { baseURL } = config.projects[0].use;
	const browser = await chromium.launch();
	const context = await browser.newContext();
	const page = await context.newPage();

	await page.goto( `${ baseURL }/my-account/` );
	await page.locator( '#username' ).type( process.env.WP_ADMIN_USERNAME );
	await page.locator( '#password' ).type( process.env.WP_ADMIN_PASSWORD );
	await page.locator( '.woocommerce-form-login__submit' ).click();
	await page.context().storageState( { path: 'state.json' } );
}
