import fetch from 'node-fetch';
import dummy from '../dummy-data';
import { expect } from '@playwright/test';
const { promisify } = require('util');
const execAsync = promisify(require('child_process').exec);

/**
 * Wait for blockUI to disappear.
 *
 * @param {Object} page Playwright page object.
 */
export async function waitForUnBlock(page) {
	const blockUI = await page.locator('.blockUI.blockOverlay').last();
	const visible = await blockUI.isVisible();
	if (visible) {
		await blockUI.waitFor({ state: 'detached' });
	}
}

/**
 * Clears WooCommerce cart.
 *
 * @param {Object} page Playwright page object.
 */
export async function clearCart( page ) {
	await page.goto( '/cart' );

	if ( await page.locator( '.wp-block-woocommerce-cart-items-block' ).isVisible() ) {
		const removeBtns = await page.$$( '.wc-block-cart-item__remove-link' );

		for ( const button of removeBtns ) {
			await button.click();
			await page.waitForTimeout( 1000 );
		}
	}

	if ( await page.locator( '.woocommerce-cart-form' ).isVisible() ) {
		const removeBtns = await page.$$( 'td.product-remove .remove' );

		for ( const button of removeBtns ) {
			await button.click();
		}
	}
}

export async function visitCheckout( page, isBlock = true ) {
	if ( isBlock ) {
		await page.goto( '/checkout' );
	} else {
		await page.goto( '/checkout-old' );
	}

}

/**
 * Creates a product in WooCommerce.
 *
 * @param {Object} page Playwright page object.
 * @param {Object} product Product object.
 * @param {Boolean} save Indicates if the product should be published.
 */
export async function createProduct( page, product, save = true ) {
	await page.goto( '/wp-admin/post-new.php?post_type=product' );
	await page.locator( '#title' ).fill( product.name );
	await page.locator( '#_regular_price' ).fill( product.regularPrice );
	await page.locator( '.inventory_options' ).click();
	await page.locator( '#_sku' ).fill( product.sku );

	if ( save ) {
		await page.waitForTimeout( 2000 );
		await page.locator( '#publish' ).click();
	}
}

/**
 * Fills credit card fields.
 *
 * @param {Object} baseUrl Base URL.
 * @param {String} slug Product slug.
 */
export async function doesProductExist( baseURL, slug ) {
	const response = await fetch( `${baseURL}/product/${ slug }` );
	return response.status === 200;
}

/**
 * Fills Checkout address fields.
 *
 * @param {Object} page Playwright page object.
 */
export async function fillAddressFields( page, isBlock = true ) {
	const { customer } = dummy;

	if ( isBlock ) {
		await page.waitForTimeout( 1500 );
		await page
			.locator( '#email' )
			.fill( customer.email );
		await page
			.locator( '#billing-first_name' )
			.fill( customer.firstname );
		await page
			.locator( '#billing-last_name' )
			.fill( customer.lastname );
		await page
			.locator( '#billing-address_1' )
			.fill( customer.addr1 );
		await page
			.locator( '#billing-country' )
			.locator( 'input' )
			.fill( customer.countryBlock );
		await page.waitForTimeout( 1500 );
		await page
			.locator( '#billing-city' )
			.fill( customer.city );
		await page.waitForTimeout( 1500 );
		await page
			.locator( '#billing-state' )
			.locator( 'input' )
			.fill( customer.stateBlock );
		await page.waitForTimeout( 1500 );
		await page
			.locator( '#billing-postcode' )
			.fill( customer.postcode );
		await page.waitForTimeout( 1500 );
		await page
			.locator( '#billing-phone' )
			.fill( customer.phone );
	} else {
		await waitForUnBlock( page );
		await page
			.locator( '#billing_first_name' )
			.fill( customer.firstname );
		await waitForUnBlock( page );
		await page
			.locator( '#billing_last_name' )
			.fill( customer.lastname );
		await waitForUnBlock( page );
		await page
			.locator( '#billing_country' )
			.selectOption( customer.country );
		await page.locator( '#billing_country' ).blur();
		await waitForUnBlock( page );
		await page
			.locator( '#billing_address_1' )
			.fill( customer.addr1 );
		await page.locator( '#billing_address_1' ).blur();
		await waitForUnBlock( page );
		await page
			.locator( '#billing_city' )
			.fill( customer.city );
		await page.locator( '#billing_city' ).blur();
		await waitForUnBlock( page );
		await page
			.locator( '#billing_state' )
			.selectOption( customer.state );
		await page.locator( '#billing_state' ).blur();
		await waitForUnBlock( page );
		await page
			.locator( '#billing_postcode' )
			.fill( customer.postcode );
		await page.locator( '#billing_postcode' ).blur();
		await waitForUnBlock( page );
		await page
			.locator( '#billing_phone' )
			.fill( customer.phone );
		await waitForUnBlock( page );
		await page
			.locator( '#billing_email' )
			.fill( customer.email );
	}
}

/**
 * Returns a random 4 digit expiry date for testing a credit card.
 *
 * @returns string
 */
export function getRandomExpiryDate() {
	const month = Math.floor(Math.random() * 11 + 1);
	const year = (new Date().getFullYear() % 100) + 1;

	return month.toString().padStart(2, '0') + year.toString();
}

/**
 * Fills credit card fields.
 *
 * @param {Object} page Playwright page object.
 * @param {Boolean} isCheckout Indicates if page is Checkout page.
 */
export async function fillCreditCardFields( page, isCheckout = true, isBlock = true ) {
	const { creditCard } = dummy;

	// Wait for overlay to disappear
	await waitForUnBlock(page);

	if ( isCheckout ) {
		if ( isBlock ) {
			if ( await page.locator( '#radio-control-wc-payment-method-options-square_credit_card' ).isVisible() ) {
				await page.locator( '#radio-control-wc-payment-method-options-square_credit_card' ).click();
			}
		} else {
			const paymentMethod = await page.locator(
				'ul.wc_payment_methods li.payment_method_square_credit_card'
			);
	
			// Check if we already have a saved payment method, then check new payment method.
			const visible = await paymentMethod
				.locator('input#wc-square-credit-card-use-new-payment-method')
				.isVisible();
	
			if (visible) {
				await paymentMethod
					.locator('input#wc-square-credit-card-use-new-payment-method')
					.check();
			}
		}
	}

	let frame = '';

	if ( isBlock ) {
		frame = '.sq-card-iframe-container .sq-card-component'
	} else {
		frame = '#wc-square-credit-card-container .sq-card-component';
	}

	// Fill credit card details.
	const creditCardInputField = await page
		.frameLocator( frame )
		.locator('#cardNumber');

	await creditCardInputField.fill(creditCard.valid);

	await page
		.frameLocator( frame )
		.locator('#expirationDate')
		.fill(getRandomExpiryDate());

	await page
		.frameLocator( frame )
		.locator('#cvv')
		.fill(creditCard.cvv);

	const postalCodeInputField = await page
		.frameLocator( frame )
		.locator('#postalCode');

	await postalCodeInputField.waitFor({ state: 'visible' });
	await postalCodeInputField.fill(creditCard.postalCode);
}

export async function placeOrder( page, isBlock = true ) {
	if ( isBlock ) {
		await page.waitForTimeout( 2000 );
		await page.locator( '.wc-block-components-checkout-place-order-button' ).click();
	} else {
		await page.locator( '#place_order' ).click();
	}
}

export async function deleteAllPaymentMethods( page ) {
	await page.goto( '/my-account/payment-methods/' );

	if ( await page.locator( '.woocommerce-MyAccount-paymentMethods' ).isVisible() ) {
		const removeBtns = await page.$$( '.payment-method-actions .delete' );

		for ( const button of removeBtns ) {
			await button.click();
			await expect( await page.getByText( 'Payment method deleted.' ) ).toBeVisible();
		}
	}
}

/**
 * Fills gift card fields.
 *
 * @param {Object} page Playwright page object.
 */
export async function fillGiftCardField( page ) {
	const { giftCard } = dummy;
	if ( await page.locator( '#square-gift-card-remove' ).isVisible() ) {
		await page.locator( '#square-gift-card-remove' ).click();
		await waitForUnBlock( page );
	}
	await page
		.frameLocator('#square-gift-card-fields-input .sq-card-component')
		.locator('#giftCardNumber')
		.fill(giftCard.valid);
}

/**
 * Deletes all customer sessions.
 *
 * @param {Object} page Playwright page object.
 */
export async function deleteSessions( page ) {
	await page.goto( '/wp-admin/admin.php?page=wc-status&tab=tools' );
	await page.locator( 'input[form="form_clear_sessions"]' ).click();
}

/**
 * Utility to navigate to order edit page by order ID.
 *
 * @param {Object} page Playwright page object.
 * @param {Number} orderId Order ID
 */
export async function gotoOrderEditPage( page, orderId ) {
	await page.goto(
		`/wp-admin/admin.php?page=wc-orders&action=edit&id=${ orderId }`
	);
}

export async function doSquareRefund( page, amount = '' ) {
	await page.locator( '.refund-items' ).click();
	await page.locator( '.refund_order_item_qty' ).fill( '1' );
	if ( await page.locator( '#refund_amount' ).isEditable() ) {
		await page.locator( '#refund_amount' ).fill( '' );
	}
	await page.locator( '.refund_line_total' ).fill('');
	await page.locator( '.refund_line_total' ).fill( amount );
	await page.locator( '.do-api-refund' ).click();
}

/**
 * Deletes all products from WooCommerce.
 *
 * @param {Object} page Playwright page object.
 * @param {*} permanent Set to true to delete products permanently.
 */
export async function deleteAllProducts( page, permanent = true ) {
	await page.goto( '/wp-admin/edit.php?post_type=product' );
	if ( ! await page.locator( '#cb-select-all-1' ).isVisible() ) {
		return;
	}
	await page.locator( '#cb-select-all-1' ).check();
	await page.locator( '#bulk-action-selector-top' ).selectOption( { value: 'trash' } );
	await page.locator( '#doaction' ).click();

	if ( permanent ) {
		await page.goto( '/wp-admin/edit.php?post_status=trash&post_type=product' );
		await page.locator( '#delete_all' ).first().click();
	}
}


/**
 * Save Cash App Pay payment settings
 *
 * @param {Page}    page     Playwright page object
 * @param {Object}  options  Cash App Pay payment settings
 */
export async function saveCashAppPaySettings(page, options) {
	const settings = {
		enabled: true,
		title: 'Cash App Pay',
		description: 'Pay securely using Cash App Pay.',
		debugMode: 'off',
		buttonTheme: 'dark',
		buttonShape: 'semiround',
		transactionType: 'charge',
		chargeVirtualOrders: false,
		capturePaidOrders: false,
		...options,
	};

	await page.goto(
		'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=square_cash_app_pay'
	);

	// Enable/Disable
	if (!settings.enabled) {
		await page.locator('#woocommerce_square_cash_app_pay_enabled').uncheck();
	} else {
		await page.locator('#woocommerce_square_cash_app_pay_enabled').check();
	}

	// Title and Description
	await page
		.locator('#woocommerce_square_cash_app_pay_title')
		.fill(settings.title);
	await page
		.locator('#woocommerce_square_cash_app_pay_description')
		.fill(settings.description);

	// Transaction Type
	await page
		.locator('#woocommerce_square_cash_app_pay_transaction_type')
		.selectOption(settings.transactionType);
	if ( settings.transactionType === 'authorization' ) {
		const chargeVirtualOrders = await page.locator('#woocommerce_square_cash_app_pay_charge_virtual_orders');
		const capturePaidOrders = await page.locator('#woocommerce_square_cash_app_pay_enable_paid_capture');
		if ( settings.chargeVirtualOrders ) {
			await chargeVirtualOrders.check();
		} else {
			await chargeVirtualOrders.uncheck();
		}

		if ( settings.capturePaidOrders ) {
			await capturePaidOrders.check();
		} else {
			await capturePaidOrders.uncheck();
		}
	}

	// Debug Mode and Environment
	await page
		.locator('#woocommerce_square_cash_app_pay_debug_mode')
		.selectOption(settings.debugMode);
	
	// Button customization
	await page
		.locator('#woocommerce_square_cash_app_pay_button_theme')
		.selectOption(settings.buttonTheme);
	await page
		.locator('#woocommerce_square_cash_app_pay_button_shape')
		.selectOption(settings.buttonShape);

	await page.getByRole('button', { name: 'Save changes' }).click();
	await expect(page.locator('#message.updated.inline').last()).toContainText(
		'Your settings have been saved.'
	);
}

/**
 * Select payment method
 *
 * @param {Page}    page            Playwright page object
 * @param {string}  paymentMethod   Payment method name
 * @param {boolean} isBlockCheckout Is block checkout?
 */
export async function selectPaymentMethod(
	page,
	paymentMethod,
	isBlockCheckout
) {
	if (isBlockCheckout) {
		await page
			.locator(
				`label[for="radio-control-wc-payment-method-options-${paymentMethod}"]`
			)
			.click();
		await page
			.locator('.wc-block-components-loading-mask')
			.first()
			.waitFor({ state: 'detached' });
		return;
	}
	// Wait for overlay to disappear
	await page
		.locator('.blockUI.blockOverlay')
		.last()
		.waitFor({ state: 'detached' });

	// Wait for payment method to appear
	const payMethod = await page
		.locator(
			`ul.wc_payment_methods li.payment_method_${paymentMethod} label`
		)
		.first();
	await expect(payMethod).toBeVisible();

	// Select payment method
	await page
		.locator(`label[for="payment_method_${paymentMethod}"]`)
		.waitFor();
	await payMethod.click();
}

/**
 * Pay using Cash App Pay
 *
 * @param {Object}  page    Playwright page object.
 * @param {Boolean} isBlock Indicates if is block checkout.
 * @param {Boolean} decline Indicates if payment should be declined.
 */
export async function placeCashAppPayOrder( page, isBlock = true, decline = false ) {
	// Wait for overlay to disappear
	await waitForUnBlock(page);
	if ( isBlock ) {
		await page.locator('#wc-square-cash-app-pay').getByTestId('cap-btn').click();
	} else {
		await page.locator('#wc-square-cash-app').getByTestId('cap-btn').click();
	}	
	await page.waitForLoadState('networkidle');
	if ( decline ) {
		await page.getByRole('button', { name: 'Decline' }).click();
	} else {
		await page.getByRole('button', { name: 'Approve' }).click();
	}
	await page.waitForLoadState('networkidle');
	await page.getByRole('button', { name: 'Done' }).click();
	// Early return if declined.
	if ( decline ) {
		return;
	}

	await page.waitForLoadState('networkidle');
	await expect(
		await page.locator( '.entry-title' )
	).toHaveText( 'Order received' );
	const orderId = await page
		.locator( '.woocommerce-order-overview__order strong' )
		.innerText();
	return orderId;
}

/**
 * Run WP CLI command.
 *
 * @param {string} command
 */
export async function runWpCliCommand(command) {
	const { stdout, stderr } = await execAsync(
		`npm --silent run env run tests-cli -- ${command}`
	);

	if (!stderr) {
		return true;
	}
	console.error(stderr);
	return false;
}