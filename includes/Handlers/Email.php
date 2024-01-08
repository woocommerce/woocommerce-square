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

namespace WooCommerce\Square\Handlers;

use WooCommerce\Square\Emails;

defined( 'ABSPATH' ) || exit;

/**
 * Emails handler class.
 *
 * @since 2.0.0
 */
class Email {
	/** @var Emails\Sync_Completed instance */
	private $square_sync_completed;

	/** @var Emails\Access_Token_Email instance */
	private $square_access_token_email;

	/** @var Emails\Gift_Card_Sent instance */
	private $square_gift_card_sent;

	/**
	 * Sets up Square emails.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// add email handlers to WooCommerce core
		add_action( 'woocommerce_loaded', array( $this, 'init_emails' ) );
		add_filter( 'woocommerce_email_classes', array( $this, 'get_email_classes' ) );
	}

	/**
	 * Ensures the WooCommerce email handlers are loaded.
	 *
	 * @since 2.0.0
	 */
	private function init_mailer() {
		// loads the base WooCommerce Email base class
		if ( ! class_exists( 'WC_Email' ) ) {
			WC()->mailer();
		}
	}

	/**
	 * Initializes Square email classes.
	 *
	 * @internal
	 *
	 * @since 2.0.0
	 */
	public function init_emails() {
		$this->init_mailer();

		if ( null === $this->square_sync_completed ) {
			$this->square_sync_completed = new Emails\Sync_Completed();
		}

		if ( null === $this->square_access_token_email ) {
			$this->square_access_token_email = new Emails\Access_Token_Email();
		}

		if ( null === $this->square_gift_card_sent ) {
			$this->square_gift_card_sent = new Emails\Gift_Card_Sent();
		}
	}

	/**
	 * Adds WooCommerce Square email handlers.
	 *
	 * @internal
	 *
	 * @since 2.0.0
	 *
	 * @param \WC_Email[] $emails associative array of email IDs and objects
	 * @return \WC_Email[]
	 */
	public function get_email_classes( $emails = array() ) {
		// init emails if uninitialized
		$this->init_emails();

		if ( ! array_key_exists( 'wc_square_sync_completed', $emails ) || ! $emails['wc_square_sync_completed'] instanceof Emails\Sync_Completed ) {
			$emails['wc_square_sync_completed'] = $this->square_sync_completed;
		}

		if ( ! array_key_exists( 'wc_square_access_token_email', $emails ) || ! $emails['wc_square_access_token_email'] instanceof Emails\Sync_Completed ) {
			$emails['wc_square_access_token_email'] = $this->square_access_token_email;
		}

		if ( ! array_key_exists( 'wc_square_gift_card_sent', $emails ) || ! $emails['wc_square_gift_card_sent'] instanceof Emails\Sync_Completed ) {
			$emails['wc_square_gift_card_sent'] = $this->square_gift_card_sent;
		}

		return $emails;
	}

	/**
	 * Gets the Square sync completed email instance.
	 *
	 * @since 2.0.0
	 *
	 * @return Emails\Sync_Completed
	 */
	public function get_sync_completed_email() {
		$this->init_emails();
		return $this->square_sync_completed;
	}

	/**
	 * Gets the Square access token email instance.
	 *
	 * @since 2.1.0
	 *
	 * @return Emails\Access_Token_Email
	 */
	public function get_access_token_email() {
		$this->init_emails();
		return $this->square_access_token_email;
	}

	/**
	 * Gets the Square Gift Card email sent instance.
	 *
	 * @since 4.2.0
	 *
	 * @return Emails\Gift_Card_Sent
	 */
	public function get_gift_card_sent() {
		$this->init_emails();
		return $this->square_gift_card_sent;
	}
}
