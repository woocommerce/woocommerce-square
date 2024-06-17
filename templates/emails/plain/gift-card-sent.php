<?php
defined( 'ABSPATH' ) || exit;

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html( wp_strip_all_tags( $email_heading ) );
echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

printf(
	/* translators: %1$s - Gift card recipient's name, %2$s - Gift card sender's name */
	esc_html__( 'Hey %1$s, %2$s sent you a %3$s gift card!', 'woocommerce-square' ),
	esc_html( $email->gift_card_email_data->recipient_name ),
	esc_html( $email->gift_card_email_data->sender_name ),
	esc_html( get_bloginfo( 'name' ) )
);

echo "\n\n----------\n\n";

echo esc_html(
	sprintf(
		/* translators: %1$s - Gift card account number, %2$s - Gift card balance */
		__( 'Your gift card with the number %1$s is loaded with an amount of %2$s.', 'woocommerce-square' ),
		esc_html( $gift_card_number ),
		wc_price( $gift_card_balance ),
	)
);

echo "\n\n----------\n\n";

if ( $email->gift_card_email_data->sender_message ) {
	printf(
		/* translators: %1$s - Gift card sender's name, %2$s - Gift card sender's message */
		esc_html__( '%1$s has a message for you: "%2$s"', 'woocommerce-square' ),
		esc_html( $email->gift_card_email_data->sender_name ),
		esc_html( wp_strip_all_tags( wptexturize( $email->gift_card_email_data->sender_message ) ) )
	);
	echo "\n\n----------\n\n";
}


/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ( $additional_content ) {
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) );
	echo "\n\n----------------------------------------\n\n";
}

// Documented in Woo Core.
// phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment
echo wp_kses_post( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
