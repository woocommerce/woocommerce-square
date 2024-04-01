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

namespace WooCommerce\Square\Gateway;

defined( 'ABSPATH' ) || exit;

use Square\Models\Order;
use WooCommerce\Square\WC_Order_Square;

/**
 * The base Square gateway API class.
 *
 * @since 2.0.0
 */
class API extends \WooCommerce\Square\API {


	/** @var string location ID to use for requests */
	protected $location_id;

	/** @var \WC_Order order object associated with a request, if any */
	protected $order;

	/** @var string API ID */
	protected $api_id;

	/**
	 * Constructs the class.
	 *
	 * @since 2.0.0
	 *
	 * @param string $access_token the API access token
	 * @param string $location_id location ID to use for requests
	 */
	public function __construct( $access_token, $location_id, $is_sandbox = null ) {

		parent::__construct( $access_token, $is_sandbox );

		$this->location_id = $location_id;
	}


	/** Transaction methods *******************************************************************************************/


	/**
	 * Performs a credit card authorization for the given order.
	 *
	 * @since 2.0.0
	 *
	 * @param \WC_Order $order order object
	 * @return \WooCommerce\Square\API\Response
	 * @throws \Exception
	 */
	public function credit_card_authorization( \WC_Order $order ) {

		$request = new API\Requests\Payments( $this->get_location_id(), $this->client );

		$request->set_authorization_data( $order );

		$this->set_response_handler( API\Responses\Create_Payment::class );

		return $this->perform_request( $request );
	}


	/**
	 * Performs a credit card charge for the given order.
	 *
	 * @since 2.0.0
	 *
	 * @param \WC_Order $order order object
	 * @return \WooCommerce\Square\API\Response
	 * @throws \Exception
	 */
	public function credit_card_charge( \WC_Order $order ) {

		$request = new API\Requests\Payments( $this->get_location_id(), $this->client );

		$request->set_charge_data( $order );

		$this->set_response_handler( API\Responses\Create_Payment::class );

		return $this->perform_request( $request );
	}


	/**
	 * Performs a capture for a given authorized order.
	 *
	 * @since 4.6.0
	 *
	 * @param \WC_Order $order order object
	 * @return \WooCommerce\Square\API\Response
	 * @throws \Exception
	 */
	public function capture_payment( \WC_Order $order ) {
		$location_id = ! empty( $order->capture->location_id ) ? $order->capture->location_id : $this->get_location_id();

		// use the Payments API to capture orders that were processed with Square v2.2+
		if ( ! empty( $order->square_version ) && version_compare( $order->square_version, '2.2', '>=' ) ) {
			$request = new API\Requests\Payments( $location_id, $this->client );
		} else {
			$request = new API\Requests\Transactions( $location_id, $this->client );
		}

		$request->set_capture_data( $order );

		$this->set_response_handler( API\Response::class );

		return $this->perform_request( $request );
	}


	/**
	 * Performs a credit card capture for a given authorized order.
	 *
	 * @since 2.0.0
	 *
	 * @param \WC_Order $order order object
	 * @return \WooCommerce\Square\API\Response
	 * @throws \Exception
	 */
	public function credit_card_capture( \WC_Order $order ) {
		return $this->capture_payment( $order );
	}

	/**
	 * Performs a gift card charge for a given order.
	 *
	 * @param \WC_Order $order order object
	 * @since 3.7.0
	 * @return \WooCommerce\Square\Gateway\API\Responses\Create_Payment
	 */
	public function gift_card_charge( \WC_Order $order ) {
		$request = new API\Requests\Payments( $this->get_location_id(), $this->client );

		$request->set_gift_card_charge_data( $order );

		$this->set_response_handler( API\Responses\Create_Payment::class );

		return $this->perform_request( $request );
	}

	/**
	 * Performs a cash app pay authorization for the given order.
	 *
	 * @since 4.6.0
	 *
	 * @param \WC_Order $order order object
	 * @return \WooCommerce\Square\Gateway\API\Responses\Create_Payment
	 * @throws \Exception
	 */
	public function cash_app_pay_authorization( \WC_Order $order ) {
		return $this->cash_app_pay_charge( $order, false );
	}

	/**
	 * Performs a Cash App Pay charge for the given order.
	 *
	 * @since 4.5.0
	 *
	 * @param \WC_Order $order   Order object
	 * @param bool      $capture Whether to capture the charge or not.
	 * @return \WooCommerce\Square\Gateway\API\Responses\Create_Payment
	 * @throws \Exception
	 */
	public function cash_app_pay_charge( \WC_Order $order, $capture = true ) {

		$request = new API\Requests\Payments( $this->get_location_id(), $this->client );

		$request->set_charge_data( $order, $capture, true );

		$this->set_response_handler( API\Responses\Create_Payment::class );

		return $this->perform_request( $request );
	}


	/**
	 * Performs a refund for the given order.
	 *
	 * @since 2.0.0
	 *
	 * @param \WC_Order $order        WooCommerce order object.
	 * @param array     $payment_data Array of payment information necessary for a refund.
	 * @return \WooCommerce\Square\API\Response
	 * @throws \Exception
	 */
	public function refund( \WC_Order $order, $payment_data = array() ) {

		$location_id = ! empty( $order->refund->location_id ) ? $order->refund->location_id : $this->get_location_id();

		// only use the Refunds API to refund orders that took payment after Square v2.2
		if ( ! empty( $order->square_version ) && version_compare( $order->square_version, '2.2', '>=' ) ) {
			$request = new API\Requests\Refunds( $this->client );
		} else {
			$request = new API\Requests\Transactions( $location_id, $this->client );
		}

		$request->set_refund_data( $order, $payment_data );

		$this->set_response_handler( API\Responses\Refund::class );

		return $this->perform_request( $request );
	}


	/**
	 * Performs a void for the given order.
	 *
	 * @since 2.0.0
	 *
	 * @param \WC_Order $order order object
	 * @return \WooCommerce\Square\API\Response
	 * @throws \Exception
	 */
	public function void( \WC_Order $order ) {

		$location_id = ! empty( $order->refund->location_id ) ? $order->refund->location_id : $this->get_location_id();

		// use the Payments API to void/cancel orders that were processed after Square v2.2
		if ( ! empty( $order->square_version ) && version_compare( $order->square_version, '2.2', '>=' ) ) {
			$request = new API\Requests\Payments( $location_id, $this->client );
		} else {
			$request = new API\Requests\Transactions( $location_id, $this->client );
		}

		$request->set_void_data( $order );

		$this->set_response_handler( API\Response::class );

		return $this->perform_request( $request );
	}


	/**
	 * Creates a payment token for the given order.
	 *
	 * @since 2.0.0
	 *
	 * @param \WC_Order|WC_Order_Square $order the order object
	 * @return API\Responses\Create_Customer_Card|API\Responses\Create_Customer
	 * @throws \Exception
	 */
	public function tokenize_payment_method( $order ) {

		// a customer ID should've already been created, but there may be cases where the customer id is deleted/corrupted at Square
		if ( ! empty( $order->customer_id ) ) {

			$response = $this->create_customer_card( $order );

			if ( $response->has_error_code( 'NOT_FOUND' ) ) {
				$order->customer_id = '';
			} else {
				return $response;
			}
		}

		$response = $this->create_customer( $order );

		if ( ! $response->transaction_approved() ) {
			return $response;
		}

		// Update the user meta with the new customer id created for further API requests
		update_user_meta( $order->get_user_id(), 'wc_square_customer_id', $response->get_customer_id(), $order->customer_id );

		// Update the customer id on the order as well
		$order->square_customer_id = $order->customer_id = $response->get_customer_id();

		return $this->create_customer_card( $order );
	}


	/**
	 * Creates a payment token for the given order.
	 *
	 * @since 2.0.0
	 *
	 * @param \WC_Order $order the order object
	 * @return API\Responses\Create_Customer_Card
	 * @throws \Exception
	 */
	public function create_customer_card( \WC_Order $order ) {

		$request = new API\Requests\Card( $this->client );

		$request->set_create_card_data( $order );

		$this->set_response_handler( API\Responses\Create_Customer_Card::class );

		return $this->perform_request( $request );
	}


	/**
	 * Creates a new customer based on the given order.
	 *
	 * @since 2.0.0
	 *
	 * @param \WC_Order $order order object
	 * @return API\Responses\Create_Customer
	 * @throws \Exception
	 */
	public function create_customer( \WC_Order $order ) {

		$request = new API\Requests\Customers( $this->client );

		$request->set_create_customer_data( $order );

		$this->set_response_handler( API\Responses\Create_Customer::class );

		return $this->perform_request( $request );
	}


	/**
	 * Gets all tokenized payment methods for the customer.
	 *
	 * @since 2.0.0
	 *
	 * @param string $customer_id unique customer id
	 * @return API\Responses\Get_Customer
	 * @throws \Exception
	 */
	public function get_tokenized_payment_methods( $customer_id ) {

		$request = new API\Requests\Customers( $this->client );

		$request->set_get_customer_data( $customer_id );

		$this->set_response_handler( API\Responses\Get_Customer::class );

		return $this->perform_request( $request );
	}


	/**
	 * Removes the tokenized payment method.
	 *
	 * @since 2.0.0
	 *
	 * @param string $token the payment method token
	 * @param string $customer_id unique customer id
	 * @return API\Response
	 * @throws \Exception
	 */
	public function remove_tokenized_payment_method( $token, $customer_id ) {

		$request = new API\Requests\Card( $this->client );

		$request->set_delete_card_data( $token );

		$this->set_response_handler( API\Response::class );

		return $this->perform_request( $request );
	}


	/**
	 * Creates a new Square order from a WooCommerce order.
	 *
	 * @since 2.0.0
	 *
	 * @param string $location_id location ID
	 * @param \WC_Order $order
	 * @return Order
	 * @throws \Exception
	 */
	public function create_order( $location_id, \WC_Order $order ) {

		$request = new API\Requests\Orders( $this->client );

		$request->set_create_order_data( $location_id, $order );

		$this->set_response_handler( \WooCommerce\Square\API\Response::class );

		$response = $this->perform_request( $request );

		if ( $response->get_data() instanceof \Square\Models\CreateOrderResponse ) {
			return $response->get_data()->getOrder();
		}

		throw new \Exception( esc_html__( 'Failed to make request createOrder.', 'woocommerce-square' ) );
	}

	/**
	 * Retrieves a Square order.
	 *
	 * @param string $order_id The Square order ID.
	 *
	 * @return \Square\Models\Order
	 */
	public function retrieve_order( $order_id ) {
		$request = new API\Requests\Orders( $this->client );

		$request->set_retrieve_order_data( $order_id );

		$this->set_response_handler( \WooCommerce\Square\API\Response::class );

		$response = $this->perform_request( $request );

		return $response->get_data()->getOrder();
	}

	/**
	 * Calculates a Square order without creating one.
	 *
	 * @param \WC_Order            $order        Woo order object.
	 * @param \Square\Models\Order $square_order Square order object.
	 *
	 * @return \Square\Models\Order
	 */
	public function calculate_order( \WC_Order $order, \Square\Models\Order $square_order ) {
		$request = new API\Requests\Orders( $this->client );

		$request->set_calculate_order_data( $order, $square_order );

		$this->set_response_handler( \WooCommerce\Square\API\Response::class );

		$response = $this->perform_request( $request );

		return $response->get_data()->getOrder();
	}

	/**
	 * Updates a Square order.
	 *
	 * @param \WC_Order            $order        Woo order object.
	 * @param \Square\Models\Order $square_order Square order object.
	 */
	public function update_order( \WC_Order $order, \Square\Models\Order $square_order ) {
		$request = new API\Requests\Orders( $this->client );

		$request->set_update_order_data( $order, $square_order );

		$this->set_response_handler( \WooCommerce\Square\API\Response::class );

		$response = $this->perform_request( $request );

		return $response->get_data()->getOrder();
	}

	/**
	 * Updates the payment total of an existing payment.
	 *
	 * @param \WC_Order $order  The WooCommerce order object.
	 * @param float     $amount The new payment total.
	 *
	 * @return \Square\Models\Payment
	 */
	public function update_payment( \WC_Order $order, float $amount ) {
		$request = new API\Requests\Payments( $this->get_location_id(), $this->client );

		$request->set_update_payment_data( $order, $amount );

		$this->set_response_handler( \WooCommerce\Square\API\Response::class );

		$response = $this->perform_request( $request );

		return $response->get_data()->getPayment();
	}

	/**
	 * Adjusts an existing Square order by amount.
	 *
	 * @since 2.0.4
	 *
	 * @param string $location_id location ID
	 * @param \WC_Order $order
	 * @param int $version Current 'version' value of Square order
	 * @param int $amount Amount of adjustment in smallest unit
	 * @return Order
	 * @throws \Exception
	 */
	public function adjust_order( $location_id, \WC_Order $order, $version, $amount ) {

		$request = new API\Requests\Orders( $this->client );

		if ( $amount > 0 ) {
			$request->add_line_item_order_data( $location_id, $order, $version, $amount );
		} else {
			$request->add_discount_order_data( $location_id, $order, $version, -1 * $amount );
		}
		$this->set_response_handler( \WooCommerce\Square\API\Response::class );

		$response = $this->perform_request( $request );

		if ( $response->get_data() instanceof \Square\Models\UpdateOrderResponse ) {
			return $response->get_data()->getOrder();
		}

		throw new \Exception( esc_html__( 'Failed to make request updateOrder.', 'woocommerce-square' ) );
	}

	/**
	 * Performs payments when a transaction is done using multiple payment methods.
	 * For example: Gift Card + Square Credit Card.
	 *
	 * @param array  $payment_ids Array of payment IDs.
	 * @param string $order_id    Square order ID.
	 * @return \WooCommerce\Square\Gateway\API\Responses\Create_PayOrder
	 * @since 3.9.0
	 */
	public function pay_order( $payment_ids, $order_id ) {
		$request = new API\Requests\Orders( $this->client );

		$request->set_pay_order_data( $payment_ids, $order_id );

		$this->set_response_handler( \WooCommerce\Square\Gateway\API\Responses\Create_PayOrder::class );

		return $this->perform_request( $request );
	}


	/**
	 * Gets an existing transaction.
	 *
	 * @since 2.0.0
	 *
	 * @param string $transaction_id transaction ID
	 * @param string $location_id location ID
	 * @return API\Responses\Charge
	 * @throws \Exception
	 */
	public function get_transaction( $transaction_id, $location_id = '' ) {

		if ( ! $location_id ) {
			$location_id = $this->get_location_id();
		}

		$request = new API\Requests\Transactions( $location_id, $this->client );

		$request->set_get_transaction_data( $transaction_id );

		$this->set_response_handler( API\Responses\Charge::class );

		return $this->perform_request( $request );
	}


	/**
	 * Creates a Gift Card.
	 *
	 * @since 4.2.0
	 *
	 * @param $order_id Line item order ID.
	 * @return API\Responses\Get_Gift_Card
	 */
	public function create_gift_card( $order_id ) {
		$request = new API\Requests\Gift_Card( $this->location_id, $this->client );

		$request->set_create_gift_card_data( $order_id );

		$this->set_response_handler( API\Responses\Get_Gift_Card::class );

		return $this->perform_request( $request );
	}

	/**
	 * Activates a Gift Card which is in a pending state.
	 *
	 * @since 4.2.0
	 *
	 * @param string $gift_card_id The ID of the inactive Gift Card.
	 * @param string $order_id     Square Order ID associated with the Gift Card.
	 * @param string $line_item_id Line Item ID for the Gift Card.
	 */
	public function activate_gift_card( $gift_card_id, $order_id, $line_item_id ) {
		$request = new API\Requests\Gift_Card_Activities( $this->location_id, $this->client );

		$request->set_activate_gift_card_data( $gift_card_id, $order_id, $line_item_id );

		$this->set_response_handler( \WooCommerce\Square\API\Response::class );

		return $this->perform_request( $request );
	}

	/**
	 * Loads an existing gift card with an amount.
	 *
	 * @since 4.2.0
	 *
	 * @param string $gan          The gift card number.
	 * @param string $order_id     The Square order ID.
	 * @param string $line_item_id The line item order ID for the gift card.
	 */
	public function load_gift_card( $gan, $order ) {
		$request = new API\Requests\Gift_Card_Activities( $this->location_id, $this->client );

		$request->set_load_gift_card_data( $gan, $order );

		$this->set_response_handler( \WooCommerce\Square\API\Response::class );

		return $this->perform_request( $request );
	}

	/**
	 * Sets data to refund/adjust decrement funds in a gift card.
	 *
	 * @since 4.2.0
	 *
	 * @param string               $gan          Gift card number.
	 * @param \Square\Models\Money $amount_money The amount to be refunded.
	 * @param \WC_Order            $order        WooCommerce order.
	 */
	public function refund_gift_card( $gan, $amount_money, $order ) {
		$request = new API\Requests\Gift_Card_Activities( $this->location_id, $this->client );

		$request->set_gift_card_refund_data( $gan, $amount_money, $order );

		$this->set_response_handler( \WooCommerce\Square\API\Response::class );

		return $this->perform_request( $request );
	}

	/**
	 * Gets an existing payment.
	 *
	 * @since 2.2.0
	 *
	 * @param string $payment_id transaction ID
	 * @return API\Responses\Create_Payment
	 * @throws \Exception
	 */
	public function get_payment( $payment_id ) {

		$request = new API\Requests\Payments( $this->get_location_id(), $this->client );

		$request->set_get_payment_data( $payment_id );

		$this->set_response_handler( API\Responses\Create_Payment::class );

		return $this->perform_request( $request );
	}

	/**
	 * Cancel authorized payment.
	 *
	 * @since 4.6.0
	 *
	 * @param string $payment_id transaction ID
	 * @return API\Responses\Create_Payment
	 * @throws \Exception
	 */
	public function cancel_payment( $payment_id ) {

		$request = new API\Requests\Payments( $this->get_location_id(), $this->client );

		$request->set_cancel_payment_data( $payment_id );

		$this->set_response_handler( API\Responses\Create_Payment::class );

		return $this->perform_request( $request );
	}

	/**
	 * Retrieves a gift card using nonce.
	 *
	 * @since 3.7.0
	 *
	 * @param string $gan Gift card number.
	 *
	 * @return API\Responses\Get_Gift_Card
	 */
	public function retrieve_gift_card( $nonce = '' ) {

		$request = new API\Requests\Gift_Card( $this->get_location_id(), $this->client );

		$request->set_retrieve_gift_card_data( $nonce );

		$this->set_response_handler( API\Responses\Get_Gift_Card::class );

		return $this->perform_request( $request );
	}

	/**
	 * Retrieves a gift card using GAN.
	 *
	 * @since 4.2.0
	 *
	 * @param string $gan Gift card number.
	 *
	 * @return API\Responses\Get_Gift_Card
	 */
	public function retrieve_gift_card_by_gan( $gan = '' ) {

		$request = new API\Requests\Gift_Card( $this->get_location_id(), $this->client );

		$request->set_retrieve_gift_card_from_gan_data( $gan );

		$this->set_response_handler( API\Responses\Get_Gift_Card::class );

		return $this->perform_request( $request );
	}


	/**
	 * Validates the parsed response.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 * @throws \Exception
	 */
	protected function do_post_parse_response_validation() {

		// gateway responses need to get through to check API\Response::transaction_approved()
		if ( $this->get_response() instanceof API\Response ) {
			return true;
		}

		return parent::do_post_parse_response_validation();
	}


	/** Conditional methods *******************************************************************************************/


	/**
	 * Determines if this API supports getting a customer's tokenized payment methods.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function supports_get_tokenized_payment_methods() {

		return true;
	}


	/**
	 * Determines if this API supports updating tokenized payment methods.
	 *
	 * @see Payment_Gateway_API::update_tokenized_payment_method()
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function supports_update_tokenized_payment_method() {

		return false;
	}


	/**
	 * Determines if this API supports removing a tokenized payment method.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function supports_remove_tokenized_payment_method() {

		return true;
	}


	/** Getter methods ************************************************************************************************/


	/**
	 * Gets the location ID to be used for requests.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	protected function get_location_id() {

		return $this->location_id;
	}


	/**
	 * Gets the object associated with the request, if any.
	 *
	 * @since 2.0.0
	 *
	 * @return \WC_Order
	 */
	public function get_order() {

		return $this->order;
	}


	/**
	 * Gets the API ID.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	protected function get_api_id() {

		return $this->api_id ? $this->api_id : $this->get_plugin()->get_gateway()->get_id();
	}


	/** No-op methods *************************************************************************************************/


	/**
	 * The gateway API does not support check debits.
	 *
	 * @since 2.0.0
	 *
	 * @param \WC_Order $order order object
	 */
	public function check_debit( \WC_Order $order ) {}


	/**
	 * Updates a tokenized payment method.
	 *
	 * Square API does not allow updating a stored card's address, and instead recommends deleting and re-adding a new
	 * card. This isn't an option for us since subscriptions would break any time an address is updated.
	 *
	 * @since 2.0.0
	 *
	 * @param \WC_Order $order order object
	 */
	public function update_tokenized_payment_method( \WC_Order $order ) {}

	/**
	 * Set API ID to used for logging.
	 *
	 * @param string $api_id
	 * @return void
	 */
	public function set_api_id( $api_id ) {
		$this->api_id = $api_id;
	}
}
