<?php
/**
 * Customer note email
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/customer-note.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates\Emails
 * @version 3.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @since 4.2.0
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<style>
	#wc-square-gift-card-email__message {
		border-left: 1px solid;
		padding-left: 1rem;
		margin-bottom: 1.5rem;
		font-style: italic;
	}

	#wc-square-gift-card-email__wrapper {
		padding: 2rem 1rem;
		background-color: #f5f5f5;
		margin: 1.5rem 0;
		text-align: center;
	}

	#wc-square-gift-card-email__card-balance {
		font-weight: bold;
		font-size: 2.5rem;
		line-height: 1.5;
		margin-bottom: 1rem;
		color: #7f54b3;
	}

	#wc-square-gift-card-email__card-number {
		border: 1px solid #000;
		font-family: monospace;
		font-size: 1.5rem;
		padding: 1rem;
		display: inline-block;
	}
</style>

<p>
	<?php
		printf(
			/* translators: %1$s Email sender name. */
			esc_html__( 'Hey %1$s, you just received a gift card!', 'woocommerce-square' ),
			esc_html( $email->gift_card_email_data->recipient_name )
		);
		?>
</p>

<?php if ( $email->gift_card_email_data->sender_message ) : ?>
	<div id="wc-square-gift-card-email__message">
		<?php echo esc_html( $email->gift_card_email_data->sender_message ); ?>
		<br>
		<?php
			/* translators: %1$s Sender's name. */
			printf( esc_html__( 'From: %1$s', 'woocommerce-square' ), $email->gift_card_email_data->sender_name ? esc_html( $email->gift_card_email_data->sender_name ) : esc_html( $order->get_billing_first_name() ) );
		?>
	</div>
<?php endif; ?>

<div id="wc-square-gift-card-email__wrapper">
	<p><?php esc_html_e( 'Amount:', 'woocommerce-square' ); ?></p>
	<div id="wc-square-gift-card-email__card-balance"><?php echo wc_price( $gift_card_balance ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
	<p><?php printf( esc_html__( 'Your gift card number is:', 'woocommerce-square' ) ); ?></p>
	<div id="wc-square-gift-card-email__card-number"><?php echo esc_html( $gift_card_number ); ?></div>
</div>

<?php
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}
?>



<?php

/**
 * @since 4.2.0
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action( 'woocommerce_email_footer', $email );
