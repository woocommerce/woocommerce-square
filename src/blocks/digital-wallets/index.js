/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import Content from './content';
import MockButtons from './mock-buttons';
import { getSquareServerData } from '../square-utils';

const squareDigitalWalletsMethod = {
	name: 'square-credit-card',
	title: 'Square',
	description: __(
		'This will show users the Apple Pay and/or Google Pay buttons depending on their browser and logged in status.',
		'woocommerce-square'
	),
	gatewayId: 'square_credit_card',
	paymentMethodId: 'square_credit_card',
	content: <Content />,
	edit: <MockButtons />,
	canMakePayment: () => {
		const isSquareConnected =
			getSquareServerData().applicationId &&
			getSquareServerData().locationId
				? true
				: false;
		const isDigitalWalletsEnabled =
			getSquareServerData().isDigitalWalletsEnabled;

		return isSquareConnected && isDigitalWalletsEnabled;
	},
	supports: {
		features: getSquareServerData().supports,
	},
};

export default squareDigitalWalletsMethod;
