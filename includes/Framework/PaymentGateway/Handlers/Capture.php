<?php
/**
 * WooCommerce Payment Gateway Framework
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is bundled with this package in the file license.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0 or later
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@skyverge.com so we can send you a copy immediately.
 *
 * @since     3.0.0
 * @author    WooCommerce / SkyVerge
 * @copyright Copyright (c) 2013-2019, SkyVerge, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0 or later
 *
 * Modified by WooCommerce on 01 December 2021.
 */

namespace WooCommerce\Square\Framework\PaymentGateway\Handlers;

use WooCommerce\Square\Framework\PaymentGateway\Payment_Gateway;
use WooCommerce\Square\Framework\Plugin_Compatibility;
use WooCommerce\Square\Framework\Square_Helper;
use WooCommerce\Square\Framework\PaymentGateway\Api\Payment_Gateway_API_Response;
use WooCommerce\Square\Framework\Compatibility\Order_Compatibility;
use WooCommerce\Square\Utilities\Money_Utility;

defined( 'ABSPATH' ) || exit;

/**
 * The transaction capture handler.
 *
 * @since 3.0.0
 */
class Capture {


	/** @var Payment_Gateway payment gateway instance */
	private $gateway;


	/**
	 * Capture constructor.
	 *
	 * @since 3.0.0
	 *
	 * @param Payment_Gateway $gateway payment gateway instance
	 */
	public function __construct( Payment_Gateway $gateway ) {

		$this->gateway = $gateway;

		// auto-capture on order status change if enabled
		if ( $gateway->supports_capture() && $gateway->is_paid_capture_enabled() ) {
			add_action( 'woocommerce_order_status_changed', array( $this, 'maybe_capture_paid_order' ), 10, 3 );
		}
	}


	/**
	 * Captures an order on status change to a "paid" status.
	 *
	 * @internal
	 *
	 * @since 3.0.0
	 *
	 * @param int $order_id order ID
	 * @param string $old_status status being changed
	 * @param string $new_status new order status
	 */
	public function maybe_capture_paid_order( $order_id, $old_status, $new_status ) {

		$paid_statuses = wc_get_is_paid_statuses();

		// bail if changing to a non-paid status or from a paid status
		if ( ! in_array( $new_status, $paid_statuses, true ) || in_array( $old_status, $paid_statuses, true ) ) {
			return;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		$payment_method = Order_Compatibility::get_prop( $order, 'payment_method' );

		if ( $payment_method !== $this->get_gateway()->get_id() ) {
			return;
		}

		$this->maybe_perform_capture( $order );
	}


	/**
	 * Perform a capture on an order if it can be captured.
	 *
	 * This acts as a wrapper for when the process should just bail without logging any errors or order notes, like when
	 * performing capture via bulk action.
	 *
	 * @since 3.0.0
	 *
	 * @param \WC_Order $order order object
	 * @param float|null $amount amount to capture
	 * @return bool
	 */
	public function maybe_perform_capture( \WC_Order $order, $amount = null ) {

		// don't log any errors for for orders that can't be captured
		if ( ! $this->order_can_be_captured( $order ) ) {
			return false;
		}

		$result = $this->perform_capture( $order, $amount );

		return ! empty( $result['success'] );
	}


	/**
	 * Performs a credit card capture for an order.
	 *
	 * @since 3.0.0
	 *
	 * @param \WC_Order $order WooCommerce order object
	 * @param float|null $amount amount to capture
	 * @return array {
	 *     Capture transaction results
	 *
	 *     @type bool   $success whether the capture was successful
	 *     @type int    $code    result code
	 *     @type string $message result message
	 * }
	 */
	public function perform_capture( \WC_Order $order, $amount = null ) {

		$order = $this->get_gateway()->get_order_for_capture( $order, $amount );

		try {

			// notify if the gateway doesn't support captures when this is called directly
			if ( ! $this->get_gateway()->supports_capture() ) {

				$message = "{$this->get_gateway()->get_method_title()} does not support payment captures";

				Plugin_Compatibility::wc_doing_it_wrong( __METHOD__, $message, '3.0.0' );

				throw new \Exception( $message, 500 );
			}

			// don't try to capture failed/cancelled/fully refunded transactions
			if ( ! $this->is_order_ready_for_capture( $order ) ) {
				throw new \Exception( __( 'Order cannot be captured', 'woocommerce-square' ), 400 );
			}

			// don't re-capture fully captured orders
			if ( $this->has_order_authorization_expired( $order ) ) {
				throw new \Exception( __( 'Transaction authorization has expired', 'woocommerce-square' ), 400 );
			}

			// don't re-capture fully captured orders
			if ( $this->is_order_fully_captured( $order ) ) {
				throw new \Exception( __( 'Transaction has already been fully captured', 'woocommerce-square' ), 400 );
			}

			// generally unavailable
			if ( ! $this->order_can_be_captured( $order ) ) {
				throw new \Exception( __( 'Transaction cannot be captured', 'woocommerce-square' ), 400 );
			}

			// Handle capture for orders where the payment was split between Square Gift and Credit cards.
			if ( $this->get_gateway()->get_order_meta( $order, 'charge_type' ) === $this->get_gateway()::CHARGE_TYPE_PARTIAL ) {
				$transaction_id           = $this->get_gateway()->get_order_meta( $order, 'trans_id' );
				$gift_card_transaction_id = $this->get_gateway()->get_order_meta( $order, 'gift_card_trans_id' );
				$square_order_id          = $this->get_gateway()->get_order_meta( $order, 'square_order_id' );

				$response = $this->get_gateway()->get_api()->pay_order(
					array(
						$transaction_id,
						$gift_card_transaction_id,
					),
					$square_order_id
				);
			} else {
				// attempt the capture
				$response = $this->get_gateway()->get_api()->capture_payment( $order );
			}

			$gift_card_purchase_type = \WooCommerce\Square\Handlers\Order::get_gift_card_purchase_type( $order );

			if ( 'new' === $gift_card_purchase_type ) {
				$this->get_gateway()->create_gift_card( $order );
			} elseif ( 'load' === $gift_card_purchase_type ) {
				$gan = \WooCommerce\Square\Handlers\Order::get_gift_card_gan( $order );
				$this->get_gateway()->load_gift_card( $gan, $order );
			}

			// bail early if the capture wasn't approved
			if ( ! $response->transaction_approved() ) {

				$this->do_capture_failed( $order, $response );

				throw new \Exception( $response->get_status_code() . ' - ' . $response->get_status_message() );
			}

			$message = sprintf(
				/* translators: Placeholders: %1$s - payment gateway title (such as Authorize.net, Braintree, etc), %2$s - transaction amount. Definitions: Capture, as in capture funds from a credit card. */
				esc_html__( '%1$s Capture total of %2$s Approved', 'woocommerce-square' ),
				$this->get_gateway()->get_method_title(),
				wc_price( $order->capture->amount, array( 'currency' => Order_Compatibility::get_prop( $order, 'currency', 'view' ) ) )
			);

			// adds the transaction id (if any) to the order note
			if ( $response->get_data() instanceof \Square\Models\PayOrderResponse && ! empty( $response->get_transaction_ids() ) ) {
				/** @var \Square\Models\Order $order_response */
				$square_order = $response->get_order();
				$message     .= ' ' . $this->get_gateway()->build_split_payment_order_note( $order, $square_order );
				/* translators: %s list of transaction IDs for split payments. */
				$message .= ' ' . sprintf( esc_html__( '(Transaction IDs %s)', 'woocommerce-square' ), implode( ', ', $response->get_transaction_ids() ) );
			} elseif ( $response->get_transaction_id() ) {
				/* translators: %s transaction ID */
				$message .= ' ' . sprintf( esc_html__( '(Transaction ID %s)', 'woocommerce-square' ), $response->get_transaction_id() );
			}

			$order->add_order_note( $message );

			// add the standard capture data to the order
			$this->do_capture_success( $order, $response );

			// if the original auth amount has been captured, complete payment
			if ( $this->get_gateway()->get_order_meta( $order, 'capture_total' ) >= $order->get_total() ) {

				// prevent stock from being reduced when payment is completed as this is done when the charge was authorized
				add_filter( 'woocommerce_payment_complete_reduce_order_stock', '__return_false', 100 );

				// complete the order
				$order->payment_complete();
			}

			return array(
				'success' => true,
				'code'    => 200,
				'message' => $message,
			);

		} catch ( \Exception $exception ) {

			// add an order note if this isn't a general error
			if ( 500 !== $exception->getCode() ) {

				$note_message = sprintf(
				/* translators: Placeholders: %1$s - payment gateway title (such as Authorize.net, Braintree, etc), %2$s - failure message. Definitions: "capture" as in capturing funds from a credit card. */
					esc_html__( '%1$s Capture Failed: %2$s', 'woocommerce-square' ),
					$this->get_gateway()->get_method_title(),
					$exception->getMessage()
				);

				$order->add_order_note( $note_message );
			}

			return array(
				'success' => false,
				'code'    => $exception->getCode(),
				'message' => $exception->getMessage(),
			);
		}
	}


	/**
	 * Adds the standard capture data to an order.
	 *
	 * @since 3.0.0
	 *
	 * @param \WC_Order $order the order object
	 * @param Payment_Gateway_API_Response $response transaction response
	 */
	public function do_capture_success( \WC_Order $order, Payment_Gateway_API_Response $response ) {

		$total_captured = (float) $this->get_gateway()->get_order_meta( $order, 'capture_total' ) + (float) $order->capture->amount;

		$this->get_gateway()->update_order_meta( $order, 'capture_total', Square_Helper::number_format( $total_captured ) );
		$this->get_gateway()->update_order_meta( $order, 'charge_captured', $this->get_gateway()->supports_partial_capture() && $this->get_gateway()->is_partial_capture_enabled() && $total_captured < (float) $this->get_order_capture_maximum( $order ) ? 'partial' : 'yes' );

		// add capture transaction ID
		if ( $response && $response->get_transaction_id() ) {
			$this->get_gateway()->update_order_meta( $order, 'capture_trans_id', $response->get_transaction_id() );
		}
	}


	/**
	 * Lets gateways handle any specific capture failure results for the order.
	 *
	 * @since 3.0.0
	 *
	 * @param \WC_Order $order WooCommerce order object
	 * @param Payment_Gateway_API_Response $response API response object
	 */
	public function do_capture_failed( \WC_Order $order, Payment_Gateway_API_Response $response ) { }


	/** Conditional Methods *******************************************************************************************/


	/**
	 * Determines if an order is eligible for capture.
	 *
	 * @since 3.0.0
	 *
	 * @param \WC_Order $order order object
	 * @return bool
	 */
	public function order_can_be_captured( \WC_Order $order ) {

		// check whether the charge has already been captured by this gateway
		if ( ! $this->is_order_ready_for_capture( $order ) || $this->is_order_fully_captured( $order ) ) {
			return false;
		}

		// if for any reason the authorization can not be captured
		if ( 'no' === $this->get_gateway()->get_order_meta( $order, 'auth_can_be_captured' ) ) {
			return false;
		}

		// authorization hasn't already been captured, but has it expired?
		return ! $this->has_order_authorization_expired( $order );
	}


	/**
	 * Determines if an order is ready for capture.
	 *
	 * The base implementation of this method checks for a valid order status and that a transaction ID is set.
	 *
	 * @since 3.0.0
	 *
	 * @param \WC_Order $order order object
	 * @return bool
	 */
	public function is_order_ready_for_capture( \WC_Order $order ) {

		return ! in_array( $order->get_status(), array( 'cancelled', 'refunded', 'failed' ), true ) && $this->get_gateway()->get_order_meta( $order, 'trans_id' );
	}


	/**
	 * Determines if an order has been fully captured
	 *
	 * @since 3.0.0
	 *
	 * @param \WC_Order $order
	 * @return bool
	 */
	public function is_order_fully_captured( \WC_Order $order ) {

		$captured = 'yes' === $this->get_gateway()->get_order_meta( $order, 'charge_captured' );

		if ( ! $captured && $this->get_gateway()->supports_partial_capture() && $this->get_gateway()->is_partial_capture_enabled() ) {
			$captured = (float) $this->get_gateway()->get_order_meta( $order, 'capture_total' ) >= (float) $this->get_order_capture_maximum( $order );
		}

		return $captured;
	}


	/**
	 * Determines if an order's authorization has expired.
	 *
	 * @since 3.0.0
	 *
	 * @param \WC_Order $order
	 * @return bool
	 */
	public function has_order_authorization_expired( \WC_Order $order ) {

		$transaction_date = $this->get_gateway()->get_order_meta( Order_Compatibility::get_prop( $order, 'id' ), 'trans_date' );

		$transaction_time = strtotime( $transaction_date );

		return $transaction_date && floor( ( time() - $transaction_time ) / 3600 ) > $this->get_gateway()->get_authorization_time_window();
	}


	/**
	 * Determines if an order's authorization has been captured, even partially.
	 *
	 * @since 3.0.0
	 *
	 * @param \WC_Order $order order object
	 * @return bool
	 */
	public function is_order_captured( \WC_Order $order ) {

		return in_array( $this->get_gateway()->get_order_meta( $order, 'charge_captured' ), array( 'yes', 'partial' ), true );
	}


	/** Getter Methods ************************************************************************************************/


	/**
	 * Gets the maximum amount that can be captured from an order.
	 *
	 * Gateways can override this for an value above or below the order total.
	 * For instance, some processors allow capturing an amount a certain
	 * percentage higher than the payment total.
	 *
	 * @since 3.0.0
	 *
	 * @param \WC_Order $order WooCommerce order object
	 * @return float
	 */
	public function get_order_capture_maximum( \WC_Order $order ) {

		return $this->get_order_authorization_amount( $order );
	}


	/**
	 * Gets the amount originally authorized for an order.
	 *
	 * @since 3.0.0
	 *
	 * @param \WC_Order $order order object
	 * @return float
	 */
	public function get_order_authorization_amount( \WC_Order $order ) {

		// if a specific auth amount was stored, use it
		// otherwise, use the order total
		$amount = $this->get_gateway()->get_order_meta( $order, 'authorization_amount' );
		$amount = $amount ? $amount : $order->get_total();

		return (float) $amount;
	}


	/**
	 * Gets the payment gateway instance.
	 *
	 * @since 3.0.0
	 *
	 * @return Payment_Gateway
	 */
	protected function get_gateway() {
		return $this->gateway;
	}
}
