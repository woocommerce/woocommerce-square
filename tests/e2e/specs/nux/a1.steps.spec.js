import { test, expect } from '@playwright/test';
import {
	visitOnboardingPage,
	saveSquareSettings,
	isToggleChecked,
} from '../../utils/helper';

test( 'Start onboarding', async ( { page } ) => {
	await visitOnboardingPage( page );
	await page.getByTestId( 'business-location-field' ).selectOption( { label: 'Default Test Account' } );
	await saveSquareSettings( page );

	await expect( await page.getByText( 'Enable Payment Methods' ) ).toBeVisible();

	if ( ! await isToggleChecked( page, '.payment-gateway-toggle__credit-card' ) ) {
		await page.locator( '.payment-gateway-toggle__credit-card' ).click();
	}

	if ( ! await isToggleChecked( page, '.payment-gateway-toggle__digital-wallet' ) ) {
		await page.locator( '.payment-gateway-toggle__digital-wallet' ).click();
	}

	if ( ! await isToggleChecked( page, '.payment-gateway-toggle__cash-app' ) ) {
		await page.locator( '.payment-gateway-toggle__cash-app' ).click();
	}

	if ( ! await isToggleChecked( page, '.payment-gateway-toggle__gift-card' ) ) {
		await page.locator( '.payment-gateway-toggle__gift-card' ).click();
	}

	await page.getByTestId( 'next-step-button' ).click();
	await expect( await page.getByText( 'Your Payment Setup is Complete!' ) ).toBeVisible();
} );
