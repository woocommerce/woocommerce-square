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

namespace WooCommerce\Square\Gateway\API;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Square\Framework\PaymentGateway\Api\Payment_Gateway_API_Response;
use WooCommerce\Square\Gateway;

class Response extends \WooCommerce\Square\API\Response implements Payment_Gateway_API_Response {


	/**
	 * Determines if the transaction was approved.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function transaction_approved() {

		return ! $this->has_errors();
	}


	/**
	 * Determines if the transaction was held.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function transaction_held() {

		return false; // TODO: make sure there are no held responses
	}


	/** Getter methods ************************************************************************************************/


	/**
	 * Gets the transaction ID.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_transaction_id() {

		return '';
	}


	/**
	 * Gets the response status message.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_status_message() {

		$message = '';

		foreach ( $this->get_errors() as $error ) {

			$message = $error->getDetail();
			break;
		}

		return $message;
	}


	/**
	 * Gets the response status code.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_status_code() {

		$code = '';

		foreach ( $this->get_errors() as $error ) {

			$code = $error->getCode();
			break;
		}

		return $code;
	}

	/**
	 * Gets array of response status codes.
	 *
	 * @since 3.5.0
	 *
	 * @return array
	 */
	public function get_status_codes() {

		$codes = array();

		foreach ( $this->get_errors() as $error ) {

			$codes[] = $error->getCode();
		}

		return $codes;
	}


	/**
	 * Gets the message to display to the user.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_user_message() {

		return '';
	}


	/**
	 * Gets the payment type.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_payment_type() {

		return Gateway::PAYMENT_TYPE_CREDIT_CARD;
	}


}
