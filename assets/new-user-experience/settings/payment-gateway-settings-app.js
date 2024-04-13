/**
 * External dependencies.
 */
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';
import parse from 'html-react-parser';
import {
	TextControl,
	ToggleControl,
	SelectControl,
	Button,
} from '@wordpress/components';

/**
 * Internal dependencies.
 */
import {
	Section,
	SectionTitle,
	SectionDescription,
	InputWrapper,
	SquareCheckboxControl,
} from '../components';

import { useSettingsForm } from './hooks';
import { CreditCardSetup, DigitalWalletsSetup } from '../../new-user-experience/onboarding/steps';

export const PaymentGatewaySettingsApp = () => {
	return (
		<CreditCardSetup />
	)
};
