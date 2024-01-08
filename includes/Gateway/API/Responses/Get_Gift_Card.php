<?php

namespace WooCommerce\Square\Gateway\API\Responses;

defined( 'ABSPATH' ) || exit;

/**
 * Get Gift_Card response.
 *
 * @since 3.7.0
 *
 * @method \Square\Models\CreateGiftCardResponse|array get_data()
 */
class Get_Gift_Card extends \WooCommerce\Square\Gateway\API\Response {
	/**
	 * Gets the Square Gift Card object.
	 *
	 * @since 4.2.0
	 *
	 * @return \Square\Models\GiftCard|null
	 */
	public function get_gift_card() {
		return ! $this->has_errors() && $this->get_data()->getGiftCard() ? $this->get_data()->getGiftCard() : null;
	}

	/**
	 * Get the gift card ID.
	 *
	 * @since 4.2.0
	 *
	 * @return string
	 */
	public function get_id() {
		return $this->get_gift_card() ? $this->get_gift_card()->getId() : null;
	}

	/**
	 * Get the gift card state.
	 *
	 * @since 4.2.0
	 *
	 * @return string
	 */
	public function get_state() {
		return $this->get_gift_card() ? $this->get_gift_card()->getState() : null;
	}

	/**
	 * Get the gift card number.
	 *
	 * @since 4.2.0
	 *
	 * @return string
	 */
	public function get_gan() {
		return $this->get_gift_card() ? $this->get_gift_card()->getGan() : null;
	}

	/**
	 * Get the gift card amount.
	 *
	 * @since 4.2.0
	 *
	 * @return string
	 */
	public function get_amount() {
		return $this->get_gift_card() ? $this->get_gift_card()->getBalanceMoney()->getAmount() : null;
	}
}
