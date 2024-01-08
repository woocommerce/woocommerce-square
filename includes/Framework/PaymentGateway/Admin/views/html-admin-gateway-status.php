<?php
/**
 * WooCommerce Plugin Framework
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is bundled with this package in the file license.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0 or later
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@skyverge.com so we can send you a copy immediately.
 *
 * @since     3.0.0
 * @author    WooCommerce / SkyVerge
 * @copyright Copyright (c) 2013-2019, SkyVerge, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0 or later
 *
 * Modified by WooCommerce on 03 January 2022.
 */
?>

<table class="wc_status_table widefat" cellspacing="0">

	<thead>
		<tr>
			<th colspan="3" data-export-label="">
				<?php echo esc_html( $gateway->get_method_title() ); ?>
				<?php echo wc_help_tip( __( 'This section contains configuration settings for this gateway.', 'woocommerce-square' ) ); ?>
			</th>
		</tr>
	</thead>

	<tbody>

		<?php
			/**
			 * Payment Gateway System Status Start Action.
			 *
			 * Allow actors to add info the start of the gateway system status section.
			 *
			 * @since 3.0.0
			 *
			 * @param Payment_Gateway $gateway
			 */
			do_action( 'wc_payment_gateway_' . $gateway->get_id() . '_system_status_start', $gateway );
		?>

		<tr>
			<td data-export-label="Environment"><?php esc_html_e( 'Environment', 'woocommerce-square' ); ?>:</td>
			<td class="help"><?php echo wc_help_tip( __( 'The transaction environment for this gateway.', 'woocommerce-square' ) ); ?></td>
			<td><?php echo esc_html( $environment ); ?></td>
		</tr>

		<?php if ( $gateway->supports_tokenization() ) : ?>

			<tr>
				<td data-export-label="Tokenization Enabled"><?php esc_html_e( 'Tokenization Enabled', 'woocommerce-square' ); ?>:</td>
				<td class="help"><?php echo wc_help_tip( __( 'Displays whether or not tokenization is enabled for this gateway.', 'woocommerce-square' ) ); ?></td>
				<td>
					<?php if ( $gateway->tokenization_enabled() ) : ?>
						<mark class="yes">&#10004;</mark>
					<?php else : ?>
						<mark class="no">&ndash;</mark>
					<?php endif; ?>
				</td>
			</tr>

		<?php endif; ?>

		<tr>
			<td data-export-label="Debug Mode"><?php esc_html_e( 'Debug Mode', 'woocommerce-square' ); ?>:</td>
			<td class="help"><?php echo wc_help_tip( __( 'Displays whether or not debug logging is enabled for this gateway.', 'woocommerce-square' ) ); ?></td>
			<td>
				<?php if ( $gateway->debug_log() && $gateway->debug_checkout() ) : ?>
					<?php echo esc_html__( 'Display at Checkout & Log', 'woocommerce-square' ); ?>
				<?php elseif ( $gateway->debug_checkout() ) : ?>
					<?php echo esc_html__( 'Display at Checkout', 'woocommerce-square' ); ?>
				<?php elseif ( $gateway->debug_log() ) : ?>
					<?php echo esc_html__( 'Save to Log', 'woocommerce-square' ); ?>
				<?php else : ?>
					<?php echo esc_html__( 'Off', 'woocommerce-square' ); ?>
				<?php endif; ?>
			</td>
		</tr>

		<?php
			/**
			 * Payment Gateway System Status End Action.
			 *
			 * Allow actors to add info the end of the gateway system status section.
			 *
			 * @since 3.0.0
			 * @param \Payment_Gateway $gateway
			 */
			do_action( 'wc_payment_gateway_' . $gateway->get_id() . '_system_status_end', $gateway );
		?>

	</tbody>

</table>
