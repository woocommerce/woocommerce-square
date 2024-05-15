import { test, expect } from '@playwright/test';
import { visitOnboardingPage, setStepsLocalStorage } from '../../utils/helper';

test( 'Can configure cash app settings via Onboarding', async ( { page } ) => {
	await visitOnboardingPage( page );
	await setStepsLocalStorage( page );

	await page.getByTestId( 'cash-app-settings-button' ).click();

	// Transaction type: charge
	await page.getByTestId( 'cash-app-gateway-transaction-type-field' ).selectOption( { label: 'Charge' } );
	await expect( await page.getByTestId( 'cash-app-gateway-virtual-order-only-field' ) ).toHaveCount( 0 );
	await expect( await page.getByTestId( 'cash-app-gateway-capture-paid-orders-field' ) ).toHaveCount( 0 );

	// Transaction type: authorization
	await page.getByTestId( 'cash-app-gateway-transaction-type-field' ).selectOption( { label: 'Authorization' } );
	await expect( await page.getByTestId( 'cash-app-gateway-virtual-order-only-field' ) ).toHaveCount( 1 );
	await expect( await page.getByTestId( 'cash-app-gateway-capture-paid-orders-field' ) ).toHaveCount( 1 );
	await page.getByTestId( 'cash-app-gateway-virtual-order-only-field' ).check();
	await page.getByTestId( 'cash-app-gateway-capture-paid-orders-field' ).check();
	await page.getByTestId( 'cash-app-gateway-title-field' ).fill( 'Cash App Pay + E2E' );
	await page.getByTestId( 'cash-app-gateway-description-field' ).fill( 'Pay securely using Cash App Pay + E2E' );
	await page.getByTestId( 'cash-app-gateway-button-theme-field' ).selectOption( { label: 'Light' } );
	await page.getByTestId( 'cash-app-gateway-button-shape-field' ).selectOption( { label: 'Round' } );


	// save settings.
	await page.getByTestId( 'cash-app-settings-save-button' ).click();
	await expect( await page.getByText( 'Your Payment Setup is Complete!' ) ).toBeVisible();
	await page.reload();

	await page.getByTestId( 'cash-app-settings-button' ).click();
	await expect( await page.getByTestId( 'cash-app-gateway-virtual-order-only-field' ) ).toBeChecked();
	await expect( await page.getByTestId( 'cash-app-gateway-capture-paid-orders-field' ) ).toBeChecked();
	await expect( await page.getByTestId( 'cash-app-gateway-title-field' ) ).toHaveValue( 'Cash App Pay + E2E' );
	await expect( await page.getByTestId( 'cash-app-gateway-description-field' ) ).toHaveValue( 'Pay securely using Cash App Pay + E2E' );
	await expect( await page.getByTestId( 'cash-app-gateway-button-theme-field' ) ).toHaveValue( 'light' );
	await expect( await page.getByTestId( 'cash-app-gateway-button-shape-field' ) ).toHaveValue( 'round' );
} );
