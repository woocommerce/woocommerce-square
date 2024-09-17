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
export async function createProduct( page, product, save = true, newEditor = false ) {
	if ( newEditor ) {
		await page.goto( '/wp-admin/admin.php?page=wc-settings&tab=advanced&section=features' );
		await page.locator( '#woocommerce_feature_product_block_editor_enabled' ).check();

		const saveButton = await page.locator( '.woocommerce-save-button' );
		if ( ! await saveButton.isDisabled() ) {
			saveButton.click();
			await expect( await page.getByText( 'Your settings have been saved' ) ).toBeVisible();
		}

		await page.goto( '/wp-admin/admin.php?page=wc-admin&path=%2Fadd-product' );

		await page.locator( '#woocommerce-product-tab__general' ).click();

		await page.locator( '[data-template-block-id="product-name"] input[name="name"]' ).fill( product.name );

		await page.locator( 'input[name="regular_price"]' ).fill( product.regularPrice );

		await page.locator( '#woocommerce-product-tab__inventory' ).click();
		await page.locator( 'input[name="woocommerce-product-sku"]' ).fill( product.sku );

		if ( save ) {
			await page
				.locator( '.woocommerce-product-header__actions .components-button' )
				.filter( { hasText: 'Publish' } )
				.click();

			await page
				.locator( '.woocommerce-product-publish-panel__header .components-button' )
				.filter( { hasText: 'Publish' } )
				.click();

			await expect( await page.getByText( 'Product published.' ) ).toBeVisible();
		}
	} else {
		let url = '/wp-admin/post-new.php?post_type=product';

		if ( product.content ) {
			url += '&content=' + encodeURIComponent( product.content );
		}
		await page.goto( url );
		await page.locator( '#title' ).fill( product.name );
		await page.locator( '#_regular_price' ).fill( product.regularPrice );
		await page.locator( '.inventory_options' ).click();
		await page.locator( '#_sku' ).fill( product.sku );

		if ( product.category ) {
			await page.locator('#product_cat-add-toggle').click();
			await page.locator('#newproduct_cat').fill( product.category );
			await page.locator('#product_cat-add-submit').click();
			await page.waitForTimeout( 2000 );
		}

		if ( save ) {
			await page.waitForTimeout( 2000 );
			await page.locator( '#publish' ).click();
		}
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
		if ( await page.locator( '.wc-block-components-address-card__edit' ).isVisible() ) {
			await page.locator( '.wc-block-components-address-card__edit' ).click();
		}
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
			.selectOption( customer.country );
		await page.waitForTimeout( 1500 );
		await page
			.locator( '#billing-city' )
			.fill( customer.city );
		await page.waitForTimeout( 1500 );
		await page
			.locator( '#billing-state' )
			.selectOption( customer.state );
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
		frame = '.sq-card-iframe-container .sq-card-component';
		if ( await page.locator( 'label[for="radio-control-wc-payment-method-options-square_credit_card"]' ).isVisible() ) {
			await page.locator( 'label[for="radio-control-wc-payment-method-options-square_credit_card"]' ).click();
		}
	} else {
		frame = '#wc-square-credit-card-container .sq-card-component';
		if ( await page.locator( 'label[for="payment_method_square_credit_card"]' ).isVisible() ) {
			await page.locator( 'label[for="payment_method_square_credit_card"]' ).click();
		}
	}

	// Fill credit card details.
	const frameLocator = await page.frameLocator(frame).first();
	const creditCardInputField = await frameLocator.locator('#cardNumber');
	await creditCardInputField.waitFor({ state: 'visible' });

	await creditCardInputField.fill(creditCard.valid);

	await frameLocator.locator('#expirationDate').fill(getRandomExpiryDate());

	await frameLocator.locator('#cvv').fill(creditCard.cvv);

	const postalCodeInputField = await frameLocator.locator('#postalCode');

	await postalCodeInputField.waitFor({ state: 'visible' });
	await postalCodeInputField.fill(creditCard.postalCode);
}

export async function placeOrder( page, isBlock = true ) {
	if ( isBlock ) {
		await page.waitForTimeout( 2000 );
	}
	await page.locator( '.wc-block-components-checkout-place-order-button, #place_order' ).first().click();
}

export async function deleteAllPaymentMethods( page ) {
	await page.goto( '/my-account/payment-methods/' );

	if ( await page.locator( '.woocommerce-MyAccount-paymentMethods' ).isVisible() ) {
		const rows = await page.locator(".payment-method-actions .delete");
		const count = await rows.count();

		for (let i = 0; i < count; i++) {
			await rows.nth(0).click();
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
		await page.getByTestId('cash-app-gateway-toggle-field').uncheck();
	} else {
		await page.getByTestId('cash-app-gateway-toggle-field').check();
	}

	// Title and Description
	await page
		.getByTestId('cash-app-gateway-title-field')
		.fill(settings.title);
	await page
		.getByTestId('cash-app-gateway-description-field')
		.fill(settings.description);

	// Transaction Type
	await page
		.getByTestId('cash-app-gateway-transaction-type-field')
		.selectOption(settings.transactionType);
	if ( settings.transactionType === 'authorization' ) {
		const chargeVirtualOrders = await page.getByTestId('cash-app-gateway-virtual-order-only-field');
		const capturePaidOrders = await page.getByTestId('cash-app-gateway-capture-paid-orders-field');
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

	// Button customization
	await page
		.getByTestId('cash-app-gateway-button-theme-field')
		.selectOption(settings.buttonTheme);
	await page
		.getByTestId('cash-app-gateway-button-shape-field')
		.selectOption(settings.buttonShape);

	await page.getByTestId( 'payment-gateway-settings-save-button' ).click();
	await expect( await page.getByText( 'Changes Saved!' ) ).toBeVisible();
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

export async function visitOnboardingPage( page ) {
	await page.goto( '/wp-admin/admin.php?page=woocommerce-square-onboarding' );
	await page.locator( '.woo-square-loader' ).waitFor( { state: 'detached' } );
}

export async function isToggleChecked( page, selector ) {

	return await page
		.locator( `${selector} .components-form-toggle` )
		.evaluate( node => node.classList.contains( 'is-checked' ) );
}

export async function saveSquareSettings( page ) {
	await page.getByTestId( 'square-settings-save-button' ).click();
	await expect( await page.getByText( 'Changes Saved!' ) ).toBeVisible();
}

export async function savePaymentGatewaySettings( page ) {
	await page.getByTestId( 'payment-gateway-settings-save-button' ).click();
	await expect( await page.getByText( 'Changes Saved!' ) ).toBeVisible();
}

export async function setStepsLocalStorage( page ) {
	await page.evaluate( ( val ) => localStorage.setItem( 'step', val ), 'payment-complete' );
	await page.evaluate( ( val ) => localStorage.setItem( 'backStep', val ), 'payment-methods' );
	await page.reload();
}

/**
 * Create Pre-Order Product.
 *
 * @param {Page}   page    Playwright page object
 * @param {Object} options Product options
 *
 * @returns {number} Product ID
 */
export async function createPreOrderProduct(page, options = {}) {
	await page.goto('/wp-admin/post-new.php?post_type=product');
	const product = {
		regularPrice: '10',
		preOrderFee: '5',
		whenToCharge: 'upon_release',
		availabilityDate: getNextDay(),
		...options,
	};

	// Set product title.
	await page.locator('#title').fill('Pre-Order Product');
	await page.locator('#title').blur();
	await page.locator('#sample-permalink').waitFor();

	// Set product data.
	await page.locator('.wc-tabs > li > a', { hasText: 'General' }).click();
	await page.locator('#_regular_price').fill(product.regularPrice);

	// Enable Deposits.
	await page.locator('.wc-tabs > li > a', { hasText: 'Pre-orders' }).click();
	await page.locator('#_wc_pre_orders_enabled').check();
	await page
		.locator('#_wc_pre_orders_availability_datetime')
		.fill(product.availabilityDate);
	await page.locator('#_wc_pre_orders_fee').fill(product.preOrderFee);
	await page
		.locator('#_wc_pre_orders_when_to_charge')
		.selectOption(product.whenToCharge);

	await page.locator('#publish').waitFor();
	await page.locator('#publish').click();
	await expect(
		page.getByText('Product published. View Product')
	).toBeVisible();
	const productId = await page.locator('#post_ID').inputValue();
	return productId;
}

/**
 * Get next day date.
 */
function getNextDay() {
	const date = new Date();
	date.setDate(date.getDate() + 1);
	const year = date.getFullYear();
	const month = String(date.getMonth() + 1).padStart(2, '0');
	const day = String(date.getDate()).padStart(2, '0');
	const hours = String(date.getHours()).padStart(2, '0');
	const minutes = String(date.getMinutes()).padStart(2, '0');
	return `${year}-${month}-${day} ${hours}:${minutes}`;
}

/**
 * Complete the Pre-Order.
 *
 * @param {Page}   page    Playwright page object
 * @param {string} orderId Order ID
 */
export async function completePreOrder(page, orderId) {
	await page.goto(`/wp-admin/admin.php?page=wc_pre_orders`);
	await page
		.locator(
			`#the-list th.check-column input[name="order_id[]"][value="${orderId}"]`
		)
		.check();
	await page.locator('#bulk-action-selector-top').selectOption('complete');
	await page.locator('#doaction').click();
}

/**
 * Subscription renewal.
 * 
 * @param {Page} page Playwright page object.
 */
export async function renewSubscription(page) {
	await page.on('dialog', (dialog) => dialog.accept());
	await page.locator("select[name='wc_order_action']").selectOption('wcs_process_renewal');
	await page.locator('#actions button.wc-reload').click();
	await expect(
		page.locator('#message.updated.notice.notice-success').first()
	).toContainText('Subscription updated.');
	await expect(page.locator('#order_status')).toHaveValue('wc-active');
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
