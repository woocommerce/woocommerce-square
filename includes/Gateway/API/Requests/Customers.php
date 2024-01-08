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

namespace WooCommerce\Square\Gateway\API\Requests;

use WooCommerce\Square\API;

defined( 'ABSPATH' ) || exit;

/**
 * The customers API request class.
 *
 * @since 2.0.0
 */
class Customers extends API\Requests\Customers {


	/**
	 * Sets the data for creating a new customer.
	 *
	 * @since 2.0.0
	 *
	 * @param \WC_Order $order order object
	 */
	public function set_create_customer_data( \WC_Order $order ) {

		$this->square_api_method = 'createCustomer';

		// set the customer email as the WP user email, if available
		try {

			if ( ! $order->get_user_id() ) {
				throw new \Exception( 'No user account' );
			}

			$customer = new \WC_Customer( $order->get_user_id() );

			$email = $customer->get_email();

		} catch ( \Exception $exception ) { // otherwise, use the order billing email

			$email = $order->get_billing_email();
		}

		$customer_request = new \Square\Models\CreateCustomerRequest();
		$customer_request->setGivenName( $order->get_billing_first_name() );
		$customer_request->setFamilyName( $order->get_billing_last_name() );
		$customer_request->setCompanyName( $order->get_billing_company() );
		$customer_request->setEmailAddress( $email );
		$customer_request->setPhoneNumber( $order->get_billing_phone() );

		if ( $order->get_user_id() ) {
			$customer_request->setReferenceId( (string) $order->get_user_id() );
		}

		$customer_request->setAddress( self::get_address_from_order( $order ) );

		$this->square_request = $customer_request;

		$this->square_api_args = array(
			$this->square_request,
		);
	}

	/**
	 * Gets a billing address model from a WC order.
	 *
	 * @since 2.0.0
	 *
	 * @param \WC_Order $order order object
	 * @return \Square\Models\Address
	 */
	public static function get_address_from_order( \WC_Order $order ) {

		$address = new \Square\Models\Address();
		$address->setFirstName( $order->get_billing_first_name() );
		$address->setLastName( $order->get_billing_last_name() );
		$address->setAddressLine1( $order->get_billing_address_1() );
		$address->setAddressLine2( $order->get_billing_address_2() );
		$address->setLocality( $order->get_billing_city() );
		$address->setAdministrativeDistrictLevel1( $order->get_billing_state() );
		if ( ! empty( $order->payment->postcode ) ) {
			$address->setPostalCode( $order->payment->postcode );
		} else {
			$address->setPostalCode( $order->get_billing_postcode() );
		}

		if ( $order->get_billing_country() ) {
			$address->setCountry( $order->get_billing_country() );
		}

		return $address;
	}


}
