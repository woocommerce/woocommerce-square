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

use SquareConnect\Configuration;
use \WooCommerce\Square\Framework\Api\API_Request;

defined( 'ABSPATH' ) || exit;

/**
 * Base WooCommerce Square API request object.
 *
 * @since 2.0.0
 */
class Request implements API_Request {


	/** @var Configuration the configuration object */
	protected $configuration;

	/** @var mixed the Square API class instance being used for this request */
	protected $square_api;

	/** @var string method name on the square API to call */
	protected $square_api_method;

	/** @var array arguments for the method call */
	protected $square_api_args = array();

	/** @var null|object the square API request model */
	protected $square_request;

	/** @var bool whether the request is being made 'WithHTTPInfo' or not */
	protected $with_http_info = true;


	/**
	 * Gets the Square API instance to use for this request.
	 *
	 * @since 2.0.0
	 *
	 * @return mixed
	 */
	public function get_square_api() {

		return $this->square_api;
	}


	/**
	 * Get the method to call on the Square API instance.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_square_api_method() {
		return $this->square_api_method;
	}


	/**
	 * Gets the arguments to call the Square API method with.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	public function get_square_api_args() {

		return $this->square_api_args;
	}


	/**
	 * Returns whether this request is set to get HTTP info.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function get_with_http_info() {

		return $this->with_http_info;
	}


	/**
	 * Gets the HTTP method.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_method() {
		// TODO: Implement get_method() method.
	}


	/**
	 * Gets the query parameters.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	public function get_params() {
		// TODO: Implement get_params() method.
	}


	/**
	 * Gets the path.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_path() {
		// TODO: Implement get_path() method.
	}


	/**
	 * Gets the request data.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	public function get_data() {
		// TODO: Implement get_data() method.
	}


	/**
	 * Gets the request data as a string.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function to_string() {

		$body = '';

		if ( is_callable( array( $this->square_request, '__toString' ) ) ) {

			$body = $this->square_request->__toString();

		} elseif ( is_array( $this->square_api_args ) ) {

			$body = array();

			foreach ( $this->square_api_args as $key => $arg ) {

				if ( is_callable( array( $arg, '__toString' ) ) ) {
					$body[ $key ] = $arg->__toString();
				}
			}

			$body = implode( ',', $body );
		}

		return $body;
	}


	/**
	 * Gets the request data as a string with all sensitive information masked.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function to_string_safe() {

		return $this->to_string();
	}


}
