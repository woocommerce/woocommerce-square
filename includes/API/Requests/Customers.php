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

namespace WooCommerce\Square\API\Requests;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Square\API;

/**
 * The customers API request class.
 *
 * @since 2.0.0
 */
class Customers extends API\Request {


	/**
	 * Initializes a new customer request.
	 *
	 * @since 2.0.0
	 *
	 * @param \Square\SquareClient $api_client the API client
	 */
	public function __construct( $api_client ) {
		$this->square_api = $api_client->getCustomersApi();
	}


	/**
	 * Sets the data for getting an existing customer.
	 *
	 * @since 2.0.0
	 *
	 * @param string $customer_id customer ID
	 */
	public function set_get_customer_data( $customer_id ) {

		$this->square_api_method = 'retrieveCustomer';

		$this->square_api_args = array( $customer_id );
	}


	/**
	 * Sets the data for getting all existing customer.
	 *
	 * @since 2.0.0
	 *
	 * @param string $cursor pagination cursor
	 */
	public function set_get_customers_data( $cursor = '' ) {

		$this->square_api_method = 'listCustomers';

		if ( $cursor ) {
			$this->square_api_args = array( $cursor );
		}
	}


}
