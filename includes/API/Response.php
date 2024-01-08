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

namespace WooCommerce\Square\API;
use \WooCommerce\Square\Framework\Api\API_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Base WooCommerce Square API response object.
 *
 * @since 2.0.0
 */
class Response implements API_Response {
	/** @var mixed raw response data */
	protected $raw_response_data;

	/**
	 * Constructs the response object.
	 *
	 * @since 2.0.0
	 *
	 * @param Object|array $raw_response_data
	 */
	public function __construct( $raw_response_data ) {

		$this->raw_response_data = $raw_response_data;
	}


	/**
	 * Gets the response data.
	 *
	 * @since 2.0.0
	 *
	 * @return Object
	 */
	public function get_data() {

		return $this->raw_response_data ?: null;
	}


	/**
	 * Gets errors returned by the Square API.
	 *
	 * @since 2.0.0
	 *
	 * @return \stdClass[]
	 */
	public function get_errors() {
		if ( is_array( $this->raw_response_data ) && count( $this->raw_response_data ) > 0 ) {
			if ( $this->raw_response_data[0] instanceof \Square\Models\Error ) {
				return $this->raw_response_data;
			}
		}

		return array();
	}


	/**
	 * Determines if the API response contains errors.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function has_errors() {

		return ! empty( $this->get_errors() );
	}


	/**
	 * Determines if the API response contains a particular error code.
	 *
	 * @since 2.1.6
	 * @param $error \Square\Models\Error
	 * @return bool
	 */
	public function has_error_code( $error_code ) {
		foreach ( $this->get_errors() as $error ) {
			if ( $error_code === $error->getCode() ) {
				return true;
			}
		}
		return false;
	}


	/**
	 * Gets the response data as a string.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function to_string() {
		$response_data = $this->get_data();

		if ( is_callable( array( $response_data, '__toString' ) ) ) {
			return $this->get_data();
		} else if ( is_callable( array( $response_data, 'jsonSerialize' ) ) ) {
			return wp_json_encode( $response_data, JSON_PRETTY_PRINT );
		}

		return '';
	}


	/**
	 * Gets the response data a string with all sensitive information masked.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function to_string_safe() {

		return $this->to_string();
	}


}
