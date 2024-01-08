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

namespace WooCommerce\Square\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Privacy admin handler.
 *
 * @since 2.0.0
 */
class Privacy extends \WC_Abstract_Privacy {


	/**
	 * Privacy class constructor.
	 *
	 * @since 2.0.0
	 */
	public function __construct() {

		parent::__construct( __( 'Square', 'woocommerce-square' ) );

		$this->add_eraser( 'woocommerce-square-customer-data', __( 'WooCommerce Square Customer Data', 'woocommerce-square' ), array( $this, 'customer_data_eraser' ) );
	}


	/**
	 * Gets the message to display.
	 *
	 * @since 2.0.0
	 */
	public function get_message() {

		return wpautop(
			sprintf(
				/* translators: Placeholder: %1$s - <a> tag, %2$s - </a> tag */
				__( 'By using this extension, you may be storing personal data or sharing data with an external service. %1$sLearn more about how this works, including what you may want to include in your privacy policy.%2$s', 'woocommerce-square' ),
				'<a href="https://docs.woocommerce.com/document/privacy-payments/#woocommerce-square" target="_blank">',
				'</a>'
			)
		);
	}


	/**
	 * Finds and erases customer data by email address.
	 *
	 * @since 2.0.0
	 *
	 * @param string $email_address the user email address
	 * @param int $page page
	 * @return array an array of personal data in name => value pairs
	 */
	public function customer_data_eraser( $email_address, $page ) {

		// check if user has an ID to load stored personal data
		$user               = get_user_by( 'email', $email_address );
		$square_customer_id = get_user_meta( $user->ID, 'wc_square_customer_id', true );

		$items_removed = false;
		$messages      = array();

		if ( ! empty( $square_customer_id ) ) {

			$items_removed = true;

			delete_user_meta( $user->ID, 'wc_square_customer_id' );

			$messages[] = __( 'Square User Data Erased.', 'woocommerce-square' );
		}

		return array(
			'items_removed'  => $items_removed,
			'items_retained' => false,
			'messages'       => $messages,
			'done'           => true,
		);
	}


}

