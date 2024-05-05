import { test, expect } from '@playwright/test';
import { visitOnboardingPage, isToggleChecked, setStepsLocalStorage } from '../../utils/helper';

test( 'Can configure gift card settings via Onboarding', async ( { page } ) => {
	await visitOnboardingPage( page );
	await setStepsLocalStorage( page );

	await page.getByTestId( 'gift-card-settings-button' ).click();
	const isChecked = await isToggleChecked( page, '.gift-card-gateway-toggle-field' );

	if ( ! isChecked ) {
		await page.getByTestId( 'gift-card-settings-button' ).click();
	}

	// save settings.
	await page.getByTestId( 'gift-card-settings-save-button' ).click();
	await expect( await page.getByText( 'Your Payment Setup is Complete!' ) ).toBeVisible();
	await page.reload();

	await page.getByTestId( 'gift-card-settings-button' ).click();
	await expect( await isToggleChecked( page, '.gift-card-gateway-toggle-field' ) ).toBe( true );
} );
