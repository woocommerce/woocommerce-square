import { test, expect } from '@playwright/test';
import { visitOnboardingPage } from '../../utils/helper';

test( 'Can configure digital wallet settings via Onboarding', async ( { page } ) => {
	await visitOnboardingPage( page );

	await page.getByTestId( 'digital-wallet-settings-button' ).click();

	// Transaction type: authorization
	await page.getByTestId( 'digital-wallet-gatewaybutton-type-field' ).selectOption( { label: 'No Text' } );
	await page.getByTestId( 'digital-wallet-gatewayapple-pay-button-color-field' ).selectOption( { label: 'White with outline' } );
	await page.getByTestId( 'digital-wallet-gatewaygoogle-pay-button-color-field' ).selectOption( { label: 'White' } );

	// save settings.
	await page.getByTestId( 'digital-wallets-settings-save-button' ).click();
	await expect( await page.getByText( 'Your Payment Setup is Complete!' ) ).toBeVisible();
	await page.reload();

	await page.getByTestId( 'digital-wallet-settings-button' ).click();
	await expect( await page.getByTestId( 'digital-wallet-gatewaybutton-type-field' ) ).toHaveValue( 'plain' );
	await expect( await page.getByTestId( 'digital-wallet-gatewayapple-pay-button-color-field' ) ).toHaveValue( 'white-outline' );
	await expect( await page.getByTestId( 'digital-wallet-gatewaygoogle-pay-button-color-field' ) ).toHaveValue( 'white' );
} );
