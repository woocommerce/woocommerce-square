import { test, expect } from '@playwright/test';
import {
	saveCashAppPaySettings,
	savePaymentGatewaySettings,
	saveSquareSettings,
} from '../utils/helper';

const ConnectSquareAccount = async ( page ) => {
	await page.goto( '/wp-admin/' );
	await page.goto( '/wp-admin/admin.php?page=wc-settings&tab=square' );

	await new Promise( ( resolve ) => setTimeout( resolve, 1000 ) );

	await page
		.getByTestId( 'environment-selection-field' )
		.selectOption( { label: 'Sandbox' } );

	await page
		.getByTestId( 'sandbox-application-id-field' )
		.fill( process.env.SQUARE_APPLICATION_ID );
	await page
		.getByTestId( 'sandbox-token-field' )
		.fill( process.env.SQUARE_ACCESS_TOKEN );

	await saveSquareSettings( page );

	await expect(
		await page.getByTestId( 'business-location-field' )
	).toBeVisible();

	await page
		.getByTestId( 'business-location-field' )
		.selectOption( { label: 'Default Test Account' } );

	await page
		.getByTestId( 'sync-settings-field' )
		.selectOption( { label: 'WooCommerce' } );

	await saveSquareSettings( page );

	await expect( await page.getByTestId( 'sync-settings-field' ) ).toHaveValue(
		'woocommerce'
	);
};

test( 'Connect a Square account @general @cashapp @giftcard @sync', async ( {
	page,
} ) => {
	await ConnectSquareAccount( page );
} );

test( 'Connect a Square account @general', async ( { page } ) => {
	await ConnectSquareAccount( page );
} );

test( 'Setup Payment Gateways @cashapp @giftcard @sync', async ( {
	page,
} ) => {
	// Enable Tokenization and digital wallet.
	await page.goto(
		'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=square_credit_card'
	);
	await page.getByTestId( 'credit-card-tokenization-field' ).check();
	await page.getByTestId( 'digital-wallet-gateway-toggle-field' ).check();
	await savePaymentGatewaySettings( page );

	// Enable Gift Card.
	await page.goto(
		'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=gift_cards_pay'
	);
	await page.getByTestId( 'gift-card-gateway-toggle-field' ).check();
	await savePaymentGatewaySettings( page );

	// Enable Cash App.
	await saveCashAppPaySettings( page, {
		enabled: true,
	} );
} );
