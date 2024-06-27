import { test, expect, devices } from '@playwright/test';
import { chromium } from 'playwright';
import {
	createProduct,
	doesProductExist,
	fillAddressFields,
	clearCart,
	visitCheckout,
	selectPaymentMethod,
	gotoOrderEditPage,
	savePaymentGatewaySettings,
	saveCashAppPaySettings,
	placeCashAppPayOrder,
} from '../utils/helper';
import dummy from '../dummy-data';
const iPhone = devices['iPhone 14 Pro Max'];

test.describe('Cash App Pay - Gift Card Product Tests', () => {
	const isBlock = false;
	test.beforeAll( 'Setup', async ( { baseURL } ) => {
		const browser = await chromium.launch();
		const page = await browser.newPage();

		await page.goto(
			'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=gift_cards_pay'
		);

		await page.getByTestId( 'gift-card-gateway-toggle-field' ).check();

		await savePaymentGatewaySettings( page );

		if ( ! ( await doesProductExist( baseURL, 'gift-card-product' ) ) ) {
			await createProduct(
				page,
				{
					name: 'Gift Card Product',
					regularPrice: '25',
					sku: 'gift-card-product',
				},
				false
			);

			await page.locator( '#_square_gift_card' ).check();
			await page.waitForTimeout( 2000 );
			await page.locator( '#publish' ).click();
		}

		// Set charge transaction type.
		await saveCashAppPaySettings(page);
		await clearCart(page);

		await browser.close();
	} );

	test.beforeEach( async () => {
		const browser = await chromium.launch();
		const page = await browser.newPage();

		await clearCart( page );
	} );

	test( 'Purchase Gift card product', async ( { browser } ) => {
		const context = await browser.newContext({
			...iPhone,
		});
		const page = await context.newPage();
		const { giftCardSender } = dummy;

		await page.goto( '/shop' );
		await page.getByText( 'Buy Gift Card' ).click();
		await page
			.locator( 'input[name="square-gift-card-sender-name"]' )
			.fill( giftCardSender.senderName );
		await page
			.locator( 'input[name="square-gift-card-send-to-email"]' )
			.fill( giftCardSender.recipientEmail );
		await page
			.locator( 'input[name="square-gift-card-sent-to-first-name"]' )
			.fill( giftCardSender.recipientName );
		await page
			.locator( 'textarea[name="square-gift-card-sent-to-message"]' )
			.fill( giftCardSender.message );
		await page.locator( '.single_add_to_cart_button' ).click();

		await visitCheckout(page, isBlock);
		await expect(
			await page.locator( '#square-gift-card-wrapper' )
		).not.toBeVisible();
		const productData = await page.locator( '.woocommerce-checkout-review-order-table' );
		await expect( await productData.getByText( /John Doe/i ) ).toBeVisible();
		await expect( await productData.getByText( /emily@example.com/i ) ).toBeVisible();
		await expect( await productData.getByText( /Emily Doe/i ) ).toBeVisible();
		await expect( await productData.getByText( /Happy Birthday!/i ) ).toBeVisible();
		await fillAddressFields( page, isBlock );
		await selectPaymentMethod(page, 'square_cash_app_pay', isBlock);
		const orderId = await placeCashAppPayOrder(page, isBlock);

		await expect( page.getByText( "Sender's name: John Doe" ) ).toBeVisible();
		await expect(
			page.getByText( "Recipient's email: emily@example.com" )
		).toBeVisible();
		await expect(
			page.getByText( "Recipient's name: Emily Doe" )
		).toBeVisible();
		await expect( page.getByText( 'Message: Happy Birthday!' ) ).toBeVisible();

		await gotoOrderEditPage(page, orderId);
		await expect( page.locator( '#order_status' ) ).toHaveValue(
			'wc-completed'
		);
		await expect( page.getByText( "Sender's name: John Doe" ) ).toBeVisible();
		await expect(
			page.getByText( "Recipient's email: emily@example.com" )
		).toBeVisible();
		await expect(
			page.getByText( "Recipient's name: Emily Doe" )
		).toBeVisible();
		await expect( page.getByText( 'Message: Happy Birthday!' ) ).toBeVisible();
		await expect(
			page.getByText( /Gift card with number: \d+ created and activated./ )
		).toBeVisible();

		const result = await page
			.getByText( /Gift card with number: \d+ created and activated./ )
			.textContent();
		const match = result.match( /(\d+)/ );

		if ( ! match ) {
			return;
		}

		process.env.PURCHASED_GAN = match[ 0 ];
	} );

	test( 'Gift card recipient email', async ( { page } ) => {
		await page.goto( '/wp-admin/admin.php?page=email-log' );
		await page.locator( '.view-content a' ).first().dispatchEvent( 'click' );
		await expect( await page.locator( '#template_container' ).getByText( 'woocommerce-square Gift Card received!' ) ).toBeVisible();
		await expect( await page.locator( '#template_container' ).getByText( 'Hey Emily Doe, you just received a gift card!' ) ).toBeVisible();
		await expect( await page.locator( '#wc-square-gift-card-email__card-balance' ) ).toContainText( '$25.00' )
		await expect( await page.locator( '#wc-square-gift-card-email__card-number' ) ).toContainText( process.env.PURCHASED_GAN )
		await expect( await page.locator( '#template_container' ).getByText( 'We look forward to seeing you soon at http://localhost:8889' ) ).toBeVisible();
	} );

	test( 'Reload Gift Card', async ( { browser } ) => {
		if ( ! process.env.PURCHASED_GAN ) {
			test.skip();
		}
		const context = await browser.newContext({
			...iPhone,
		});
		const page = await context.newPage();

		await page.goto( '/shop' );
		await page.getByText( 'Buy Gift Card' ).click();
		await page.locator( '#square-gift-card-buying-option__reload' ).check();
		await page
			.locator( 'input[name="square-gift-card-gan"]' )
			.fill( process.env.PURCHASED_GAN );
		await page.locator( '.single_add_to_cart_button' ).click();

		await visitCheckout(page, isBlock);
		await expect( page.getByText( process.env.PURCHASED_GAN ) ).toBeVisible();

		await fillAddressFields( page, isBlock );
		await selectPaymentMethod(page, 'square_cash_app_pay', isBlock);
		const orderId = await placeCashAppPayOrder(page, isBlock);
		await expect(
			page.getByText( `Gift card number: ${ process.env.PURCHASED_GAN }` )
		).toBeVisible();

		await gotoOrderEditPage(page, orderId);
		await expect( page.locator( '#order_status' ) ).toHaveValue(
			'wc-completed'
		);
	} );
});
