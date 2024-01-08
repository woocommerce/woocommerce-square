import { chromium } from '@playwright/test';
import {
	revokeWooApiKey,
} from '../utils/setup';

module.exports = async config => {
	const { baseURL } = config.projects[0].use;
	const browser = await chromium.launch();
	const page = await browser.newPage();

	await page.goto( `${ baseURL }/wp-login.php` );
	await page.locator( '#user_login' ).type( 'sid' );
	await page.locator( '#user_pass' ).type( 'sid' );
	await page.locator( '#wp-submit' ).click();

	await revokeWooApiKey( page );
}
