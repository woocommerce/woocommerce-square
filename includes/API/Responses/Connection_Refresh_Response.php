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

namespace WooCommerce\Square\API\Responses;
use WooCommerce\Square\Framework\Api\API_JSON_Response;

defined( 'ABSPATH' ) || exit;

/**
 * The connection refresh response class.
 *
 * Note that this is handled by Woo's proxy connection server, not Square's API.
 *
 * @since 2.0.0
 */
class Connection_Refresh_Response extends API_JSON_Response {

	/**
	 * Gets the access token, if any.
	 *
	 * @since 2.0.0
	 *
	 * @return string|null
	 */
	public function get_token() {

		return $this->access_token;
	}

	/**
	 * Gets the refresh token, if any.
	 *
	 * @since 2.0.0
	 *
	 * @return string|null
	 */
	public function get_refresh_token() {
		return $this->refresh_token;
	}


	/**
	 * Gets the error message, if any.
	 *
	 * @since 2.0.0
	 *
	 * @return string|null
	 */
	public function get_error_message() {

		$message = $this->reason;

		if ( empty( $message ) ) {
			$message = $this->type;
		}

		if ( empty( $message ) ) {
			$message = 'Unknown error';
		}

		return $message;
	}


	/**
	 * Determines if there was an error returned.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function has_error() {

		return ! empty( $this->response_data->error );
	}
}
