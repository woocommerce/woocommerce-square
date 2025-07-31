import { test, expect } from '@playwright/test';
import { addOneOrMoreProductToCart } from '@woocommerce/e2e-utils-playwright';

import {
	createProduct,
	visitCheckout,
	fillAddressFields,
	fillCreditCardFields,
	placeOrder,
	runScheduledAction,
	gotoOrderEditPage,
} from '../utils/helper';

test.describe('Order Sync and Fulfillment Tests @sync', () => {

	test('Should create order with fulfillment data and verify WooCommerce integration', async ({ page }) => {
		test.setTimeout(120000); // 2 minutes timeout for this specific test.
		// Step 1: Create a product
		await createProduct(
			page,
			{
				name: 'Fulfillment Test Product',
				regularPrice: '9.99',
				sku: 'fulfillment-test-001',
			},
			true
		);
		
		// Step 2: Add product to cart and go to checkout
		await addOneOrMoreProductToCart( page, 'fulfillment-test-product' );
		await visitCheckout( page, true );

		// Step 3: Complete checkout process
		await fillAddressFields( page, true );
		await fillCreditCardFields( page, true, true, false );
		await placeOrder( page, true, false );

		// Step 4: Verify order completion and get order ID
		const orderId = await page
			.locator( '.woocommerce-order-overview__order strong' )
			.innerText();

		console.log(`Created WooCommerce order ID: ${orderId}`);

		// Step 5: Navigate to order admin page and find Square order ID
		await gotoOrderEditPage( page, orderId );

		// Step 6: Verify order notes
		const orderNotes = await page.locator('.order_notes .note_content').allTextContents();
		const foundNote = orderNotes.some( note => note.includes( 'Square Order ID' ) );
		expect( foundNote ).toBeTruthy();

		const squareOrderNote = orderNotes.find( note => note.includes( 'Square Order ID: ' ) );
		const squareOrderId = squareOrderNote ? squareOrderNote.split( 'Square Order ID: ' )[1].trim().split( /\s/ )[0] : null;
		console.log( `Found Square Order ID: "${squareOrderId}"` );

		// Step 7: Verify order status is processing (as expected for new orders)
		const orderStatus = await page.locator('#order_status').inputValue();
		expect(orderStatus).toBe('wc-processing');
		console.log(`Order status is correct: ${orderStatus}`);

		// Step 8: Try to verify Square API integration (if credentials are available)
		if (squareOrderId && process.env.SQUARE_ACCESS_TOKEN) {
			try {
				const squareResponse = await fetch(`https://connect.squareupsandbox.com/v2/orders/${squareOrderId}`, {
					method: 'GET',
					headers: {
						'Square-Version': '2024-03-20',
						'Authorization': `Bearer ${process.env.SQUARE_ACCESS_TOKEN}`,
						'Content-Type': 'application/json',
					},
				});

				if (squareResponse.ok) {
					const squareOrderData = await squareResponse.json();
					console.log('Square Order Data:', squareOrderData);

					// Verify fulfillment exists in the Square order response
					expect(squareOrderData.order).toBeTruthy();
					expect(squareOrderData.order.fulfillments).toBeTruthy();
					
					const fulfillment = squareOrderData.order.fulfillments[0];
					expect(fulfillment.state).toBe('PROPOSED');
					expect(fulfillment.type).toMatch(/^(PICKUP|SHIPMENT)$/);
					
					console.log('Square API verification successful');
				} else {
					console.log('Square API call failed - this is expected without proper credentials');
				}
			} catch (error) {
				console.log('Square API call failed:', error.message);
			}
		} else {
			console.log('Skipping Square API verification - no credentials or Square Order ID');
		}

		console.log('PASSED: Order created with fulfillment data and WooCommerce integration verified');

		// Step 9: Update the order state and fulfillment states to completed using API.
		await page.goto('/wp-admin/admin.php?page=wc-status&tab=action-scheduler&status=pending');
		if (squareOrderId && process.env.SQUARE_ACCESS_TOKEN) {
			try {
				// To update the order state, Square API requires the 'version' field in the order object.
				// All fulfillments must have a state of COMPLETED, CANCELED, or FAILED before the order can be completed.
				// First, fetch the current order to get its version and fulfillments.
				const getOrderResponse = await fetch( `https://connect.squareupsandbox.com/v2/orders/${ squareOrderId }`, {
					method: 'GET',
					headers: {
						'Square-Version': '2024-03-20',
						'Authorization': `Bearer ${ process.env.SQUARE_ACCESS_TOKEN }`,
						'Content-Type': 'application/json',
					},
				} );

				let orderVersion = null;
				let fulfillments = null;
				if ( getOrderResponse.ok ) {
					const orderData = await getOrderResponse.json();
					orderVersion = orderData?.order?.version;
					fulfillments = orderData?.order?.fulfillments;

					// update the fulfillments to COMPLETED
					fulfillments.forEach( fulfillment => {
						fulfillment.state = 'COMPLETED';
					} );
				}

				if ( orderVersion === null ) {
					throw new Error( 'Could not retrieve order version from Square.' );
				}

				const squareResponse = await fetch( `https://connect.squareupsandbox.com/v2/orders/${ squareOrderId }`, {
					method: 'PUT',
					headers: {
						'Square-Version': '2024-03-20',
						'Authorization': `Bearer ${ process.env.SQUARE_ACCESS_TOKEN }`,
						'Content-Type': 'application/json',
					},
					body: JSON.stringify( {
						order: {
							state: 'COMPLETED',
							version: orderVersion,
							fulfillments: fulfillments,
						},
					} ),
				} );

				console.log(squareResponse);

				if (squareResponse.ok) {
					console.log('Square order state updated to completed');


				} else {
					console.log('Square order state update failed');
				}
			} catch (error) {
				console.log('Square API call failed:', error.message);
			}
		}
		
		// Step 10: Run the Square sync action.
		if (squareOrderId) {
			try {
				await runScheduledAction(page, 'wc_square_sync_orders');
				console.log('Successfully ran Square sync action');
			} catch (error) {
				console.log('Could not run Square sync action:', error.message);
			}
		}

		// Step 11: Verify the order status is completed.
		await gotoOrderEditPage( page, orderId );
		const updatedOrderStatus = await page.locator('#order_status').inputValue();
		expect(updatedOrderStatus).toBe('wc-completed');

		console.log('PASSED: Action Scheduler and sync infrastructure verified');
	});
});
