/**
 * External dependencies
 */
import { useEffect, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { SquareWebContext } from './square-web-context';
import { getSquareServerData } from '../square-utils';

/**
 * The root container to host Square's credit card related functionality.
 *
 * @param {Object} props The props object.
 */
export const SquareWebPaymentsForm = (props) => {
	const {
		children,
		token = null,
		defaults: { postalCode = '' },
	} = props;

	/**
	 * Square's `payment` request object state.
	 *
	 * @see https://developer.squareup.com/reference/sdks/web/payments/objects/Payments
	 */
	const [payments, setPayments] = useState(false);

	/**
	 * Square's `card` request object state.
	 *
	 * @see https://developer.squareup.com/reference/sdks/web/payments/objects/Card
	 */
	const [card, setCard] = useState(false);

	/**
	 * Parameters to intialize Square payments.
	 */
	const { applicationId, locationId } = getSquareServerData();

	useEffect(() => {
		if (!payments && window.Square) {
			setPayments(window.Square.payments(applicationId, locationId));
		}
	}, [applicationId, locationId, payments]);

	/**
	 * Effect to initialize `card`
	 */
	useEffect(() => {
		if (!payments || card || token) {
			return;
		}

		const initializeCard = async () => {
			const card = await payments.card({ postalCode });
			setCard(card);
		};

		initializeCard();
	}, [payments, card, token, postalCode]);

	if (!payments) {
		return null;
	}

	return (
		<SquareWebContext.Provider value={{ payments, card, token }}>
			{children}
		</SquareWebContext.Provider>
	);
};
