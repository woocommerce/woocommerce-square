import { test, expect } from '@playwright/test';
import { visitOnboardingPage } from '../../utils/helper';

test( 'Can configure gift card settings via Onboarding', async () => {
	await visitOnboardingPage( page );

	await page.getByTestId( 'gift-card-settings-button' ).click();
	await page.locator( '.gift-card-gateway-toggle-field' ).first().click();

	// save settings.
	await page.getByTestId( 'gift-card-settings-save-button' ).click();
	await expect( await page.getByText( 'Your Payment Setup is Complete!' ) ).toBeVisible();

	await page.getByTestId( 'gift-card-settings-button' ).click();
	await page.reload();
} );
