/**
 * Internal dependencies
 */
import Content from './content';
import MockButtons from './mock-buttons';
import { getSquareServerData } from '../square-utils';

const squareDigitalWalletsMethod = {
	name: 'square-credit-card',
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
