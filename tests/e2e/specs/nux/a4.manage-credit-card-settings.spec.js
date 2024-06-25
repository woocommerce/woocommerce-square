import { test, expect } from '@playwright/test';
import { visitOnboardingPage, setStepsLocalStorage } from '../../utils/helper';

test( 'Can configure credit card settings via Onboarding', async ( { page } ) => {
	await visitOnboardingPage( page );
	await setStepsLocalStorage( page );

	await page.getByTestId( 'credit-card-settings-button' ).click();

	// Transaction type: charge
	await page.getByTestId( 'credit-card-transaction-type-field' ).selectOption( { label: 'Charge' } );
	await expect( await page.getByTestId( 'credit-card-gateway-virtual-order-only-field' ) ).toHaveCount( 0 );
	await expect( await page.getByTestId( 'credit-card-gateway-capture-paid-orders-field' ) ).toHaveCount( 0 );

	// Transaction type: authorization
	await page.getByTestId( 'credit-card-transaction-type-field' ).selectOption( { label: 'Authorization' } );
	await expect( await page.getByTestId( 'credit-card-gateway-virtual-order-only-field' ) ).toHaveCount( 1 );
	await expect( await page.getByTestId( 'credit-card-gateway-capture-paid-orders-field' ) ).toHaveCount( 1 );
	await page.getByTestId( 'credit-card-gateway-virtual-order-only-field' ).check();
	await page.getByTestId( 'credit-card-gateway-capture-paid-orders-field' ).check();
	await page.getByTestId( 'credit-card-tokenization-field' ).check();
	await page.getByTestId( 'credit-card-gateway-title-field' ).fill( 'Credit Card + E2E' );
	await page.getByTestId( 'credit-card-gateway-description-field' ).fill( 'ay securely using your credit card + E2E' );

	// save settings.
	await page.getByTestId( 'credit-card-settings-save-button' ).click();
	await expect( await page.getByText( 'Your Payment Setup is Complete!' ) ).toBeVisible();
	await page.reload();

	await page.getByTestId( 'credit-card-settings-button' ).click();
	await expect( await page.getByTestId( 'credit-card-gateway-virtual-order-only-field' ) ).toBeChecked();
	await expect( await page.getByTestId( 'credit-card-gateway-capture-paid-orders-field' ) ).toBeChecked();
	await expect( await page.getByTestId( 'credit-card-tokenization-field' ) ).toBeChecked();
	await expect( await page.getByTestId( 'credit-card-gateway-title-field' ) ).toHaveValue( 'Credit Card + E2E' );
	await expect( await page.getByTestId( 'credit-card-gateway-description-field' ) ).toHaveValue( 'ay securely using your credit card + E2E' );
} );
