import { test, expect } from '@playwright/test';
import { chromium } from 'playwright';

import {
	createProduct,
	doesProductExist,
	fillAddressFields,
	fillCreditCardFields,
	clearCart,
	waitForUnBlock,
	gotoOrderEditPage,
	doSquareRefund,
	placeOrder,
	isToggleChecked,
	savePaymentGatewaySettings,
} from '../utils/helper';
import { getGiftCard } from '../utils/square-sandbox';
import dummy from '../dummy-data';

test.beforeAll( 'Setup', async ( { baseURL } ) => {
	const browser = await chromium.launch();
	const page = await browser.newPage();

	await page.goto(
		'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=gift_cards_pay'
	);

	if ( ! await isToggleChecked( page, '.gift-card-gateway-toggle-field' ) ) {
		await page
			.locator( '.gift-card-gateway-toggle-field' )
			.click();
	}

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

	await clearCart( page );
	await browser.close();
} );

test( 'Purchase Gift card product', async ( { page } ) => {
	page.on('dialog', dialog => dialog.accept());
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

	await page.goto( '/checkout-old' );

	await fillAddressFields( page, false );
	await fillCreditCardFields( page, null, false );
	await waitForUnBlock( page );
	await placeOrder(page, false);

	const orderId = await page
		.locator( '.woocommerce-order-overview__order strong' )
		.innerText();

	await gotoOrderEditPage( page, orderId)

	const result = await page
		.getByText( /Gift card with number: \d+ created and activated./ )
		.textContent();
	const match = result.match( /(\d+)/ );

	if ( ! match ) {
		return;
	}

	const gan = match[ 0 ];

	await doSquareRefund( page, '4' );
	await page.waitForTimeout( 5000 ); // wait for the changes to be reflected in the sandbox.

	const giftCardObject = await getGiftCard( gan );
	const amount = giftCardObject.gift_card.balance_money.amount;
	await expect( amount ).toBe( 2100 );
} );
