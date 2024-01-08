

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

<div id="wc_payment_gateway_<?php echo esc_attr( $plugin_id ); ?>_user_settings" class="sv_wc_payment_gateway_user_settings woocommerce">

	<h3><?php echo esc_html( $section_title ); ?></h3>

	<?php if ( ! empty( $section_description ) ) : ?>
		<p><?php echo wp_kses_post( $section_description ); ?></p>
	<?php endif; ?>

	<table class="form-table">

		<tbody>

			<?php
			/** Fire inside the payment gateway user settings section.
			 *
			 * @since 3.0.0
			 * @param \WP_User $user the current user object
			 */
			do_action( 'wc_payment_gateway_' . $plugin_id . '_user_profile', $user );
			?>

		</tbody>

	</table>

</div>
