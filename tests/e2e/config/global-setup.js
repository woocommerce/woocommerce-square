import { chromium } from '@playwright/test';

module.exports = async ( config ) => {
	const { baseURL } = config.projects[ 0 ].use;
	const browser = await chromium.launch();
	const context = await browser.newContext();
	const page = await context.newPage();

	await page.goto( `${ baseURL }/my-account/` );
	await page.locator( '#username' ).type( process.env.WP_ADMIN_USERNAME );
	await page.locator( '#password' ).type( process.env.WP_ADMIN_PASSWORD );
	await page.locator( '.woocommerce-form-login__submit' ).click();
	await page.context().storageState( { path: 'state.json' } );

	const nRetries = 5;
	for ( let i = 0; i < nRetries; i++ ) {
		try {
			console.log( 'Trying to add consumer token...' );
			await page.goto(
				`${ baseURL }/wp-admin/admin.php?page=wc-settings&tab=advanced&section=keys&create-key=1`
			);
			await page
				.locator( '#key_description' )
				.fill( 'Key for API access' );
			await page
				.locator( '#key_permissions' )
				.selectOption( 'read_write' );
			await page.locator( 'text=Generate API key' ).click();
			process.env.CONSUMER_KEY = await page
				.locator( '#key_consumer_key' )
				.inputValue();
			process.env.CONSUMER_SECRET = await page
				.locator( '#key_consumer_secret' )
				.inputValue();
			console.log( 'Added consumer token successfully.' );
			break;
		} catch ( e ) {
			console.log(
				`Failed to add consumer token. Retrying... ${ i }/${ nRetries }`
			);
			console.log( e );
		}
	}
};
