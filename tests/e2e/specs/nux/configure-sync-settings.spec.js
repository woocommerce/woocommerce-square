import { test, expect } from '@playwright/test';
import { visitOnboardingPage } from '../../utils/helper';

test( 'Can configure sync settings via Onboarding', async () => {
	await visitOnboardingPage( page );

	await page.getByTestId( 'configure-sync-button' ).click();
} );
