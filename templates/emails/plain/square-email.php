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

defined( 'ABSPATH' ) || exit;

/**
 * Square plain email template.
 *
 * @type string $email_heading email heading
 * @type string $email_body email body
 * @type \WooCommerce\Square\Emails\Sync_Completed $email email object
 *
 * @version 2.0.0
 * @since 2.0.0
 */

echo esc_html( wp_strip_all_tags( $email_heading ) ) . "\n\n";

echo "----------\n\n";

echo esc_html( wp_strip_all_tags( wptexturize( $email_body ) ) );

echo "\n\n----------\n\n";

/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ( $additional_content ) {
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) );
	echo "\n\n----------------------------------------\n\n";
}

/**
 * Filters the email footer text.
 *
 * @since 2.0.0
 *
 * @param string $footer_text Footer text. Default empty.
 */
echo wp_kses_post( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text', '' ) ) );
