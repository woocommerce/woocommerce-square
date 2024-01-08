<?php
/**
 * WooCommerce Square
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is bundled with this package in the file license.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0 or later
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@woocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade WooCommerce Square to newer
 * versions in the future. If you wish to customize WooCommerce Square for your
 * needs please refer to https://docs.woocommerce.com/document/woocommerce-square/
 *
 * @author    WooCommerce
 * @copyright Copyright: (c) 2019, Automattic, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0 or later
 */

namespace WooCommerce\Square\Gateway\API\Requests;

defined( 'ABSPATH' ) || exit;

/**
 * The Cards API request class.
 *
 * @since 3.0.0
 */
class Card extends \WooCommerce\Square\API\Request {

	/**
	 * Initializes a new Card request.
	 *
	 * @since 3.0.0
	 * @param \Square\SquareClient $api_client the API client
	 */
	public function __construct( $api_client ) {
		$this->square_api = $api_client->getCardsApi();
	}

	/**
	 * Sets the data for creating a new customer card.
	 *
	 * @since 3.0.0
	 *
	 * @param \WC_Order $order order object
	 */
	public function set_create_card_data( \WC_Order $order ) {

		$this->square_api_method = 'createCard';

		$card = new \Square\Models\Card();
		$card->setBillingAddress( \WooCommerce\Square\Gateway\API\Requests\Customers::get_address_from_order( $order ) );
		$card->setCardholderName( $order->get_formatted_billing_full_name() );
		$card->setCustomerId( $order->customer_id );

		$request = new \Square\Models\CreateCardRequest(
			wc_square()->get_idempotency_key( '', false ),
			! empty( $order->payment->token ) ? $order->payment->token : $order->payment->nonce->credit_card,
			$card
		);

		// 3DS / SCA verification token (from JS)
		if ( ! empty( $order->payment->verification_token ) ) {
			$request->setVerificationToken( $order->payment->verification_token );
		}

		$this->square_request = $request;

		$this->square_api_args = array(
			$this->square_request,
		);
	}

	/**
	 * Sets the data for deleting an existing card.
	 *
	 * @since 3.0.0
	 *
	 * @param string $card_id Square card ID
	 */
	public function set_delete_card_data( $card_id ) {

		$this->square_api_method = 'disableCard';

		$this->square_api_args = array(
			$card_id,
		);
	}
}
