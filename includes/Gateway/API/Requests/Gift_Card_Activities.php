<?php
/**
 * WooCommerce Square
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is bundled with this package in the file license.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.html
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@woocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade WooCommerce Square to newer
 * versions in the future. If you wish to customize WooCommerce Square for your
 * needs please refer to https://docs.woocommerce.com/document/woocommerce-square/
 */

namespace WooCommerce\Square\Gateway\API\Requests;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Square\API;

/**
 * The Gift Card Activities request class.
 *
 * @since 4.2.0
 */
class Gift_Card_Activities extends API\Request {
	/**
	 * Location ID.
	 *
	 * @since 4.2.0
	 *
	 * @var string
	 */
	public $location_id;

	/**
	 * Initializes a new Catalog request.
	 *
	 * @since 4.2.0
	 *
	 * @param \Square\SquareClient $api_client the API client
	 */
	public function __construct( $location_id, $api_client ) {
		$this->square_api  = $api_client->getGiftCardActivitiesApi();
		$this->location_id = $location_id;
	}

	/**
	 * Sets data required to activate a Gift card.
	 *
	 * @since 4.2.0
	 *
	 * @param string $gift_card_id The ID of the inactive Gift Card.
	 * @param string $order_id     Square Order ID associated with the Gift Card.
	 * @param string $line_item_id Line Item ID for the Gift Card.
	 */
	public function set_activate_gift_card_data( $gift_card_id, $order_id, $line_item_id ) {
		$activate_activity_details = new \Square\Models\GiftCardActivityActivate();
		$activate_activity_details->setOrderId( $order_id );
		$activate_activity_details->setLineItemUid( $line_item_id );

		$gift_card_activity = new \Square\Models\GiftCardActivity(
			\Square\Models\GiftCardActivityType::ACTIVATE,
			$this->location_id
		);

		$gift_card_activity->setGiftCardId( $gift_card_id );
		$gift_card_activity->setActivateActivityDetails( $activate_activity_details );

		$this->square_request = new \Square\Models\CreateGiftCardActivityRequest(
			wc_square()->get_idempotency_key( $gift_card_id ),
			$gift_card_activity
		);

		$this->square_api_method = 'createGiftCardActivity';
		$this->square_api_args   = array( $this->square_request );
	}

	/**
	 * Loads an existing gift card with an amount.
	 *
	 * @since 4.2.0
	 *
	 * @param string    $gan The gift card number.
	 * @param \WC_Order $order WooCommerce order.
	 */
	public function set_load_gift_card_data( $gan, $order ) {
		$gift_card_line_item_id = wc_square()->get_gateway( $order->get_payment_method() )->get_order_meta( $order, 'gift_card_line_item_id' );
		$square_order_id        = wc_square()->get_gateway( $order->get_payment_method() )->get_order_meta( $order, 'square_order_id' );

		$load_activity_details = new \Square\Models\GiftCardActivityLoad();
		$gift_card_activity    = new \Square\Models\GiftCardActivity(
			\Square\Models\GiftCardActivityType::LOAD,
			wc_square()->get_settings_handler()->get_location_id()
		);

		$load_activity_details->setOrderId( $square_order_id );
		$load_activity_details->setLineItemUid( $gift_card_line_item_id );

		$gift_card_activity->setGiftCardGan( $gan );
		$gift_card_activity->setLoadActivityDetails( $load_activity_details );

		$this->square_request = new \Square\Models\CreateGiftCardActivityRequest(
			wc_square()->get_idempotency_key( $order->unique_transaction_ref ),
			$gift_card_activity
		);

		$this->square_api_method = 'createGiftCardActivity';
		$this->square_api_args   = array( $this->square_request );
	}

	/**
	 * Refunds/adjusts decrement when a Gift Card purchase/reload is refunded.
	 *
	 * @since 4.2.0
	 *
	 * @param string               $gan          Gift card number.
	 * @param \Square\Models\Money $amount_money The amount to be refunded.
	 * @param \WC_Order            $order        WooCommerce order.
	 */
	public function set_gift_card_refund_data( $gan, $amount_money, $order ) {
		$adjust_decrement_activity_details = new \Square\Models\GiftCardActivityAdjustDecrement(
			$amount_money,
			\Square\Models\GiftCardActivityAdjustDecrementReason::PURCHASE_WAS_REFUNDED
		);

		$gift_card_activity = new \Square\Models\GiftCardActivity(
			\Square\Models\GiftCardActivityType::ADJUST_DECREMENT,
			$this->location_id
		);

		$gift_card_activity->setGiftCardGan( $gan );
		$gift_card_activity->setAdjustDecrementActivityDetails( $adjust_decrement_activity_details );

		$this->square_request = new \Square\Models\CreateGiftCardActivityRequest(
			wc_square()->get_idempotency_key(),
			$gift_card_activity
		);

		$this->square_api_method = 'createGiftCardActivity';
		$this->square_api_args   = array( $this->square_request );
	}
}
