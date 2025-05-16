<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Gift card sent to recipient email.
 *
 * An email sent to the recipient of the gift card.
 *
 * @class       WC_Square_Gift_Card_Sent
 * @version     4.2.0
 * @extends     WC_Email
 */
class WC_Square_Gift_Card_Sent extends \WC_Email {
	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id             = 'wc_square_gift_card_sent';
		$this->title          = __( 'Square Gift Card Sent', 'woocommerce-square' );
		$this->customer_email = true;
		$this->description    = __( 'This email is sent to the recipient of the Square Gift Card.', 'woocommerce-square' );
		$this->template_html  = 'emails/gift-card-sent.php';
		$this->template_plain = 'emails/plain/gift-card-sent.php';
		$this->placeholders   = array(
			'{square_gift_card_sender_name}'    => '',
			'{square_gift_card_number}'         => '',
			'{square_gift_card_balance}'        => '',
			'{square_gift_card_recipient_name}' => '',
			'{square_gift_card_sender_message}' => '',
		);

		// Call parent constructor.
		parent::__construct();

		add_filter( 'woocommerce_email_preview_placeholders', array( $this, 'set_dummy_placeholders' ), 10, 2 );
		add_filter( 'woocommerce_email_preview_dummy_order', array( $this, 'set_dummy_order' ), 10, 2 );
	}

	/**
	 * Set dummy placeholders for the email.
	 *
	 * @since 4.9.0
	 *
	 * @param array  $placeholders Placeholders.
	 * @param string $email_type Email type.
	 * @return array
	 */
	public function set_dummy_placeholders( $placeholders, $email_type ) {
		if ( 'WC_Square_Gift_Card_Sent' !== $email_type ) {
			return $placeholders;
		}

		$placeholders['{square_gift_card_sender_name}'] = 'Kelly';

		return $placeholders;
	}

	/**
	 * Set dummy order for the email.
	 *
	 * @since 4.9.0
	 *
	 * @param \WC_Order $order The WooCommerce order.
	 * @param string    $email_type Email type.
	 * @return \WC_Order
	 */
	public function set_dummy_order( \WC_Order $order, $email_type ) {
		if ( 'WC_Square_Gift_Card_Sent' !== $email_type ) {
			return $order;
		}

		wc_square()->get_gateway()->update_order_meta( $order, 'gift_card_balance', '20' );
		wc_square()->get_gateway()->update_order_meta( $order, 'gift_card_number', '7783320804609121' );

		$items = $order->get_items();

		foreach ( $items as $item ) {
			$item->update_meta_data( '_square-gift-card-purchase-type', 'new' );
			$item->update_meta_data( 'square-gift-card-sender-name', 'Kelly' );
			$item->update_meta_data( 'square-gift-card-sent-to-message', 'Happy Birthday John!' );
			$item->update_meta_data( 'square-gift-card-send-to-email', 'kelly@example.com' );
			$item->update_meta_data( 'square-gift-card-sent-to-first-name', 'John' );
			$item->save();
		}

		return $order;
	}

	/**
	 * Get email subject.
	 *
	 * @since 4.2.0
	 *
	 * @return string
	 */
	public function get_default_subject() {
		return sprintf(
			/* translators: %1$s - Store name */
			__( '{square_gift_card_sender_name} sent you a %1$s Gift Card!', 'woocommerce-square' ),
			esc_html( get_bloginfo( 'name' ) )
		);
	}

	/**
	 * Get email heading.
	 *
	 * @since 4.2.0
	 *
	 * @return string
	 */
	public function get_default_heading() {
		return sprintf(
			/* translators: %1$s - Store name */
			__( '%1$s Gift Card received!', 'woocommerce-square' ),
			esc_html( get_bloginfo( 'name' ) )
		);
	}

	/**
	 * Trigger for the email.
	 *
	 * @since 4.2.0
	 *
	 * @param \WC_Order $order The WooCommerce order.
	 */
	public function trigger( $order ) {
		$this->setup_locale();

		if ( ! $this->is_enabled() ) {
			return;
		}

		if ( ! $this->does_order_contain_gift_card( $order ) ) {
			return;
		}

		$this->object = wc_get_order( $order );
		$items        = $order->get_items();

		/** @var WC_Order_Item_Product $item */
		foreach ( $items as $item ) {
			if ( 'new' !== $item->get_meta( '_square-gift-card-purchase-type' ) ) {
				continue;
			}

			$recipient_email = $item->get_meta( 'square-gift-card-send-to-email' );

			if ( ! $recipient_email ) {
				continue;
			}

			$this->recipient                                      = $recipient_email;
			$this->placeholders['{square_gift_card_sender_name}'] = $item->get_meta( 'square-gift-card-sender-name' ) ? $item->get_meta( 'square-gift-card-sender-name' ) : $order->get_billing_first_name();
			$this->placeholders['{square_gift_card_recipient_name}'] = $item->get_meta( 'square-gift-card-sent-to-first-name' );
			$this->placeholders['{square_gift_card_sender_message}'] = $item->get_meta( 'square-gift-card-sent-to-message' );
			$this->placeholders['{square_gift_card_number}']         = $this->get_gift_card_gan( $order );
			$this->placeholders['{square_gift_card_balance}']        = $this->get_gift_card_amount( $order );

			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}

		$this->restore_locale();
	}

	/**
	 * Returns the number of the gift card purchased.
	 *
	 * @since 4.2.0
	 *
	 * @param \WC_Order $order The Woo Order associated with the gift card purchase.
	 * @return string
	 */
	private function get_gift_card_gan( $order ) {
		return wc_square()->get_gateway( $order->get_payment_method() )->get_order_meta( $order, 'gift_card_number' );
	}

	/**
	 * Returns the amount loaded in the purchased gift card.
	 *
	 * @since 4.2.0
	 *
	 * @param \WC_Order $order The Woo Order associated with the gift card purchase.
	 * @return string
	 */
	private function get_gift_card_amount( $order ) {
		return wc_square()->get_gateway( $order->get_payment_method() )->get_order_meta( $order, 'gift_card_balance' );
	}

	/**
	 * Returns the details of the gift card purchased.
	 *
	 * @since 4.9.0
	 *
	 * @param \WC_Order $order The Woo Order associated with the gift card purchase.
	 * @param string    $key   The key of the gift card detail to retrieve.
	 * @return string
	 */
	private function get_gift_card_details( $order, $key = '' ) {
		if ( empty( $key ) ) {
			return '';
		}

		$items  = $order->get_items();
		$result = array();

		foreach ( $items as $item ) {
			if ( 'new' !== $item->get_meta( '_square-gift-card-purchase-type' ) ) {
				continue;
			}

			$recipient_email = $item->get_meta( 'square-gift-card-send-to-email' );

			if ( ! $recipient_email ) {
				continue;
			}

			$result['sender_name']       = $item->get_meta( 'square-gift-card-sender-name' );
			$result['sender_message']    = $item->get_meta( 'square-gift-card-sent-to-message' );
			$result['recipient_name']    = $item->get_meta( 'square-gift-card-sent-to-first-name' );
			$result['gift_card_number']  = wc_square()->get_gateway( $order->get_payment_method() )->get_order_meta( $order, 'gift_card_number' );
			$result['gift_card_balance'] = wc_square()->get_gateway( $order->get_payment_method() )->get_order_meta( $order, 'gift_card_balance' );
		}

		return $result[ $key ] ?? '';
	}

	/**
	 * Check if order contains a gift card.
	 *
	 * @return boolean
	 */
	private function does_order_contain_gift_card( $order ) {
		return 'yes' === wc_square()->get_gateway( $order->get_payment_method() )->get_order_meta( $order, 'is_gift_card_purchased' );
	}

	/**
	 * Get content html.
	 *
	 * @since 4.2.0
	 *
	 * @return string
	 */
	public function get_content_html() {
		return wc_get_template_html(
			$this->template_html,
			array(
				'order'                    => $this->object,
				'gift_card_number'         => $this->get_gift_card_details( $this->object, 'gift_card_number' ),
				'gift_card_balance'        => $this->get_gift_card_details( $this->object, 'gift_card_balance' ),
				'gift_card_sender_message' => $this->get_gift_card_details( $this->object, 'sender_message' ),
				'gift_card_sender_name'    => $this->get_gift_card_details( $this->object, 'sender_name' ),
				'gift_card_recipient_name' => $this->get_gift_card_details( $this->object, 'recipient_name' ),
				'email_heading'            => $this->get_heading(),
				'additional_content'       => $this->get_additional_content(),
				'sent_to_admin'            => false,
				'plain_text'               => false,
				'email'                    => $this,
			)
		);
	}

	/**
	 * Get content plain.
	 *
	 * @since 4.2.0
	 *
	 * @return string
	 */
	public function get_content_plain() {
		return wc_get_template_html(
			$this->template_plain,
			array(
				'order'                    => $this->object,
				'gift_card_number'         => $this->get_gift_card_details( $this->object, 'gift_card_number' ),
				'gift_card_balance'        => $this->get_gift_card_details( $this->object, 'gift_card_balance' ),
				'gift_card_sender_message' => $this->get_gift_card_details( $this->object, 'sender_message' ),
				'gift_card_sender_name'    => $this->get_gift_card_details( $this->object, 'sender_name' ),
				'gift_card_recipient_name' => $this->get_gift_card_details( $this->object, 'recipient_name' ),
				'email_heading'            => $this->get_heading(),
				'additional_content'       => $this->get_additional_content(),
				'sent_to_admin'            => false,
				'plain_text'               => true,
				'email'                    => $this,
			)
		);
	}

	/**
	 * Default content to show below main email content.
	 *
	 * @since 4.2.0
	 *
	 * @return string
	 */
	public function get_default_additional_content() {
		return sprintf(
			'%1$s <a href="%2$s">%3$s</a>',
			esc_html__( 'We look forward to seeing you soon at', 'woocommerce-square' ),
			esc_url( get_site_url() ),
			esc_url( get_site_url() )
		);
	}
}
