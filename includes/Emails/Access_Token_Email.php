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

namespace WooCommerce\Square\Emails;

defined( 'ABSPATH' ) || exit;

/**
 * Sync completed email.
 *
 * @since 2.1.0
 */
class Access_Token_Email extends Base_Email {
	/**
	 * Email body.
	 *
	 * @var string
	 **/
	public $body;

	/**
	 * Email constructor.
	 *
	 * @since 2.1.0
	 */
	public function __construct() {
		// set properties
		$this->id             = 'wc_square_access_token_email';
		$this->customer_email = false;
		$this->title          = __( 'Square Access Token problems', 'woocommerce-square' );
		$this->description    = __( 'This email is sent when problems with Access Token are encountered', 'woocommerce-square' );
		$this->subject        = _x( '[WooCommerce] There was a problem with your Square Access Token', 'Email subject', 'woocommerce-square' );
		$this->heading        = _x( 'There was a problem with your Square Access Token', 'Email heading', 'woocommerce-square' );
		$this->body           = _x( 'Heads up! There may be a problem with your connection to Square.', 'Square connection problems email body.', 'woocommerce-square' );

		$this->enabled_default = 'yes';

		// call parent constructor
		parent::__construct();

		// set default recipient
		$this->recipient = $this->get_option( 'recipient', get_option( 'admin_email' ) );
	}

	/**
	 * Triggers the email.
	 *
	 * @since 2.1.0
	 */
	public function trigger() {
		if ( ! $this->is_enabled() || ! $this->has_recipients() ) {
			return;
		}

		// send the email at most once a day
		$already_sent = get_transient( 'wc_square_access_token_email_sent' );
		if ( false !== $already_sent ) {
			return;
		}
		set_transient( 'wc_square_access_token_email_sent', true, DAY_IN_SECONDS );

		$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
	}

	/**
	 * Gets the arguments that should be passed to an email template.
	 *
	 * @since 2.1.0
	 *
	 * @param array $args optional associative array with additional arguments
	 * @return array
	 */
	protected function get_template_args( $args = array() ) {
		$html = empty( $args['plain_text'] );

		$email_body = $this->body;

		$square       = wc_square();
		$settings_url = $square->get_settings_url();
		$logs_url     = esc_url( admin_url( 'admin.php?page=wc-status&tab=logs' ) );
		if ( $html ) {
			$email_body .= ' ' . sprintf(
				/* translators: Placeholders: %1$s - opening <a> HTML link tag, %2$s - closing </a> HTML link tag */
				esc_html__( 'In order to continue accepting payments, please %1$sdisconnect and re-connect your site%2$s.', 'woocommerce-square' ),
				'<a href="' . esc_url( $settings_url ) . '">',
				'</a>'
			);

			if ( $square->get_settings_handler()->is_debug_enabled() ) {
				$email_body .= '<br/><br/>' . sprintf(
					/* translators: Placeholders: %1$s - opening <a> HTML link tag, %2$s - closing </a> HTML link tag */
					esc_html__( '%1$sInspect status logs%2$s', 'woocommerce-square' ),
					'<a href="' . $logs_url . '">',
					'</a>'
				);
			}
		} else {
			$email_body .= ' ' . esc_html__( 'In order to continue accepting payments, please disconnect and re-connect your site at ', 'woocommerce-square' ) . esc_url( $settings_url );
		}

		return array_merge(
			$args,
			array(
				'email'              => $this,
				'email_heading'      => $this->heading,
				'email_body'         => $email_body,
				'additional_content' => $this->get_additional_content(),
			)
		);
	}
}
