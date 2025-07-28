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

defined( 'ABSPATH' ) || exit;

use Square\Models\Address;
use Square\Models\ChargeRequest;
use Square\Models\CreateRefundRequest;
use WooCommerce\Square\Framework\Compatibility\Order_Compatibility;
use WooCommerce\Square\Framework\Square_Helper;
use WooCommerce\Square\Utilities\Money_Utility;

class Transactions extends \WooCommerce\Square\API\Request {


	/** @var string location ID */
	protected $location_id;


	/**
	 * Initializes a new transactions request.
	 *
	 * @since 2.0.0
	 *
	 * @param string $location_id location ID
	 * @param \Square\SquareClient $api_client the API client
	 */
	public function __construct( $location_id, $api_client ) {

		$this->location_id = $location_id;
		$this->square_api  = $api_client->getTransactionsApi();
	}


	/**
	 * Sets the data for an authorization/delayed capture.
	 *
	 * @since 2.0.0
	 *
	 * @param \WC_Order $order order object
	 */
	public function set_authorization_data( \WC_Order $order ) {

		$this->set_charge_data( $order, false );
	}


	/**
	 * Sets the data for a charge.
	 *
	 * @since 2.0.0
	 *
	 * @param \WC_Order $order
	 * @param bool $capture whether to immediately capture the charge
	 */
	public function set_charge_data( \WC_Order $order, $capture = true ) {

		$this->square_api_method = 'charge';
		$this->square_request    = new ChargeRequest(
			wc_square()->get_idempotency_key( $order->unique_transaction_ref ),
			Money_Utility::amount_to_money( $order->payment_total, $order->get_currency() )
		);

		$this->square_request->setReferenceId( $order->get_order_number() );

		/**
		 * Filters the Square payment order note (legacy filter).
		 *
		 * @since 1.0.0
		 *
		 * @param string $description the order note (description)
		 * @param \WC_Order $order the order object
		 */
		$description = (string) apply_filters( 'wc_square_payment_order_note', $order->description, $order );

		$this->square_request->setNote( Square_Helper::str_truncate( $description, 60 ) );

		$this->square_request->setDelayCapture( ! $capture );

		if ( ! empty( $order->square_customer_id ) ) {
			$this->square_request->setCustomerId( $order->square_customer_id );
		}

		// payment token (card ID) or card nonce (from JS)
		if ( ! empty( $order->payment->token ) ) {
			$this->square_request->setCustomerCardId( $order->payment->token );
		} else {
			$this->square_request->setCardNonce( $order->payment->nonce->credit_card );
		}

		// 3DS / SCA verification token (from JS)
		if ( ! empty( $order->payment->verification_token ) ) {
			$this->square_request->setVerificationToken( $order->payment->verification_token );
		}

		$billing_address = new Address();
		$billing_address->setFirstName( $order->get_billing_first_name() );
		$billing_address->setLastName( $order->get_billing_last_name() );
		$billing_address->setAddressLine1( $order->get_billing_address_1() );
		$billing_address->setAddressLine2( $order->get_billing_address_2() );
		$billing_address->setLocality( $order->get_billing_city() );
		$billing_address->setAdministrativeDistrictLevel1( $order->get_billing_state() );
		$billing_address->setPostalCode( $order->get_billing_postcode() );
		$billing_address->setCountry( $order->get_billing_country() );

		$this->square_request->setBillingAddress( $billing_address );

		if ( Order_Compatibility::has_shipping_address( $order ) ) {

			$shipping_address = new Address();
			$shipping_address->setFirstName( $order->get_shipping_first_name() );
			$shipping_address->setLastName( $order->get_shipping_last_name() );
			$shipping_address->setAddressLine1( $order->get_shipping_address_1() );
			$shipping_address->setAddressLine2( $order->get_shipping_address_2() );
			$shipping_address->setLocality( $order->get_shipping_city() );
			$shipping_address->setAdministrativeDistrictLevel1( $order->get_shipping_state() );
			$shipping_address->setPostalCode( $order->get_shipping_postcode() );
			$shipping_address->setCountry( $order->get_shipping_country() );

			$this->square_request->setShippingAddress( $shipping_address );
		}

		$this->square_request->setBuyerEmailAddress( $order->get_billing_email() );

		if ( ! empty( $order->square_order_id ) ) {
			$this->square_request->setOrderId( $order->square_order_id );
		}

		$this->square_api_args = array(
			$this->get_location_id(),
			$this->square_request,
		);
	}


	/**
	 * Sets the data for capturing a transaction.
	 *
	 * @since 2.0.0
	 *
	 * @param \WC_Order $order order object
	 */
	public function set_capture_data( \WC_Order $order ) {

		$this->square_api_method = 'captureTransaction';

		$this->square_api_args = array(
			$this->get_location_id(),
			$order->capture->trans_id,
		);
	}


	/**
	 * Sets the data for refund a transaction.
	 *
	 * @since 2.0.0
	 *
	 * @param \WC_Order $order order object
	 */
	public function set_refund_data( \WC_Order $order ) {

		$this->square_api_method = 'createRefund';

		// The refund objects are sorted by date DESC, so the last one created will be at the start of the array
		$refunds    = $order->get_refunds();
		$refund_obj = $refunds[0];

		$this->square_request = new CreateRefundRequest(
			wc_square()->get_idempotency_key( $order->get_id() . ':' . $refund_obj->get_id() ),
			$order->refund->tender_id,
			Money_Utility::amount_to_money( $order->refund->amount, $order->get_currency() )
		);

		$this->square_request->setReason( $order->refund->reason );

		$this->square_api_args = array(
			$this->get_location_id(),
			$order->refund->trans_id,
			$this->square_request,
		);
	}


	/**
	 * Sets the data for voiding a transaction.
	 *
	 * @since 2.0.0
	 *
	 * @param \WC_Order $order order object
	 */
	public function set_void_data( \WC_Order $order ) {

		$this->square_api_method = 'voidTransaction';

		$this->square_api_args = array(
			$this->get_location_id(),
			$order->refund->trans_id,
		);
	}


	/**
	 * Sets the data for getting a transaction.
	 *
	 * @since 2.0.0
	 *
	 * @param string $transaction_id transaction ID
	 */
	public function set_get_transaction_data( $transaction_id ) {

		$this->square_api_method = 'retrieveTransaction';

		$this->square_api_args = array(
			$this->get_location_id(),
			$transaction_id,
		);
	}


	/** Getter methods ************************************************************************************************/


	/** Gets the location ID for this request.
	 *
	 * All requests in this type must have a location ID.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	protected function get_location_id() {

		return $this->location_id;
	}


}
