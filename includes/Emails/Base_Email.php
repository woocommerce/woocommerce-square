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

use WooCommerce\Square\Framework\Square_Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Base email class.
 *
 * @since 2.1.0
 */
class Base_Email extends \WC_Email {
	/**
	 * Whether the email is enabled by default.
	 *
	 * @var string
	 */
	protected $enabled_default = 'no';

	/**
	 * Plain text template path.
	 *
	 * @var string
	 */
	public $template_plain = 'emails/plain/square-email.php';

	/**
	 * HTML template path.
	 *
	 * @var string
	 */
	public $template_html = 'emails/square-email.php';

	/**
	 * Template path.
	 *
	 * @var string
	 */
	public $template_base;

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
		$this->template_base = wc_square()->get_plugin_path() . '/templates/';

		// call parent constructor
		parent::__construct();

		// set default recipient
		$this->recipient = $this->get_option( 'recipient', get_option( 'admin_email' ) );
	}

	/**
	 * Initializes the email settings form fields.
	 *
	 * Extends and overrides parent method.
	 *
	 * @since 2.1.0
	 */
	public function init_form_fields() {
		// initialize the default fields from parent email object
		parent::init_form_fields();

		$form_fields = $this->form_fields;

		// set email disabled by default
		if ( isset( $form_fields['enabled'] ) ) {
			$form_fields['enabled']['default'] = $this->enabled_default;
		}

		// the email has no customizable body or heading via input field
		unset( $form_fields['body'], $form_fields['heading'] );

		// adjust email subject field
		if ( isset( $form_fields['subject'] ) ) {
			/* translators: Placeholder: %s - default email subject text */
			$form_fields['subject']['description'] = sprintf( __( 'This controls the email subject line. Leave blank to use the default subject: %s', 'woocommerce-square' ), '<code>' . $this->get_default_subject() . '</code>' );
			$form_fields['subject']['desc_tip']    = false;
			$form_fields['subject']['default']     = $this->subject;
		}

		if ( ! $this->is_customer_email() ) {
			// add a recipient field
			$form_fields = Square_Helper::array_insert_after(
				$form_fields,
				isset( $form_fields['enabled'] ) ? 'enabled' : key( $form_fields ),
				array(
					'recipient' => array(
						'title'       => __( 'Recipient(s)', 'woocommerce-square' ),
						'type'        => 'text',
						/* translators: %s default email address */
						'description' => sprintf( __( 'Enter recipients (comma separated) for this email. Defaults to admin email: %s', 'woocommerce-square' ), '<code>' . esc_attr( get_option( 'admin_email' ) ) . '</code>' ),
						'placeholder' => get_bloginfo( 'admin_email' ),
						'default'     => get_bloginfo( 'admin_email' ),
					),
				)
			);
		}

		// set the updated fields
		$this->form_fields = $form_fields;
	}

	/**
	 * Gets the default email subject.
	 *
	 * @since 2.1.0
	 *
	 * @return string
	 */
	public function get_default_subject() {
		return $this->subject;
	}

	/**
	 * Gets the email body.
	 *
	 * @since 2.1.0
	 *
	 * @return string may contain HTML
	 */
	protected function get_default_body() {
		return $this->body;
	}

	/**
	 * Determines if the email has valid recipients.
	 *
	 * @since 2.1.0
	 *
	 * @return bool
	 */
	protected function has_recipients() {
		return ! empty( $this->get_recipient() );
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
		return array_merge(
			$args,
			array(
				'email'              => $this,
				'email_body'         => '',
				'email_heading'      => '',
				'additional_content' => '',
			)
		);
	}

	/**
	 * Gets the email HTML content.
	 *
	 * @since 2.0.0
	 *
	 * @return string HTML
	 */
	public function get_content_html() {
		$args = array( 'plain_text' => false );
		return wc_get_template_html( $this->template_html, array_merge( $args, $this->get_template_args( $args ) ) );
	}

	/**
	 * Gets the email plain text content.
	 *
	 * @since 2.0.0
	 *
	 * @return string plain text
	 */
	public function get_content_plain() {
		$args = array( 'plain_text' => true );
		return wc_get_template_html( $this->template_plain, array_merge( $args, $this->get_template_args( $args ) ) );
	}
}
