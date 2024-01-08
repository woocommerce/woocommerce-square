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

use Square\Models\RefundPaymentRequest;
use WooCommerce\Square\Utilities\Money_Utility;

/**
 * The Refunds API request class.
 *
 * @since 2.2.0
 */
class Refunds extends \WooCommerce\Square\API\Request {

	/**
	 * Initializes a new refund request.
	 *
	 * @since 2.2.0
	 * @param \Square\SquareClient $api_client the API client
	 */
	public function __construct( $api_client ) {
		$this->square_api = $api_client->getRefundsApi();
	}


	/**
	 * Sets the data for refund a payment.
	 *
	 * @since 2.2.0
	 *
	 * @param \WC_Order $order       order object
	 * @param array     $refund_data array of data required for a refund.
	 */
	public function set_refund_data( \WC_Order $order, $refund_data = array() ) {

		$this->square_api_method = 'refundPayment';

		// The refund objects are sorted by date DESC, so the last one created will be at the start of the array
		$refunds    = $order->get_refunds();
		$refund_obj = $refunds[0];

		$refund_amount = empty( $refund_data['amount'] ) ? $order->refund->amount : $refund_data['amount'];
		$tender_id     = empty( $refund_data['tender_id'] ) ? $order->refund->tender_id : $refund_data['tender_id'];
		$payment_type  = empty( $refund_data['payment_type'] ) ? '' : "-{$refund_data['payment_type']}";

		$this->square_request = new RefundPaymentRequest(
			wc_square()->get_idempotency_key( $order->get_id() . ':' . $refund_obj->get_id() . $payment_type ),
			Money_Utility::amount_to_money( $refund_amount, $order->get_currency() )
		);

		$this->square_request->setPaymentId( $tender_id );

		$this->square_request->setReason( $order->refund->reason );

		$this->square_api_args = array( $this->square_request );
	}
}
