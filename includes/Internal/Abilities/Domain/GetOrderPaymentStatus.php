<?php
/**
 * Get order payment status ability definition.
 *
 * @package WooCommerce\Square
 */

// @phan-file-suppress PhanUndeclaredClassMethod, PhanUndeclaredFunction @phan-suppress-current-line UnusedSuppression -- Abilities API + AbilityDefinition added in WC 10.9; suppression covers older-WC compat runs where this class never loads.

namespace WooCommerce\Square\Internal\Abilities\Domain;

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Abilities\AbilityDefinition;
use WooCommerce\Square\Internal\Abilities\Abilities_Registrar;
use WooCommerce\Square\Plugin;

/**
 * Registers the woocommerce-square/get-order-payment-status ability.
 *
 * Returns Square-specific payment state for a single WooCommerce order:
 * transaction id, capture state, gift-card split-payment metadata, refund
 * totals, and the underlying order status/total. Lets agents answer "why
 * did order X's payment behave this way?" without round-tripping through
 * the WC admin UI.
 *
 * Backing detail: reads post-meta only (no Square API calls, no transient
 * writes). Both Square gateways are checked — square_credit_card and
 * square_cash_app_pay. When the order's payment method is something else,
 * the ability still returns the order's status/total + an
 * `is_square_payment: false` signal so agents can route accordingly
 * rather than receiving an error.
 *
 * @internal Only loaded when WooCommerce 10.9+ is active.
 */
class GetOrderPaymentStatus extends AbstractSquareAbility implements AbilityDefinition {

	public static function get_name(): string {
		return 'woocommerce-square/get-order-payment-status';
	}

	public static function get_registration_args(): array {
		return array(
			'label'               => __( 'Get Square payment status for an order', 'woocommerce-square' ),
			'description'         => __( 'Return Square-specific payment state for a single WooCommerce order: transaction id, capture state, gift-card split-payment metadata, refund totals, plus the underlying order status and total. Useful for diagnosing per-order payment issues.', 'woocommerce-square' ),
			'category'            => self::CATEGORY_SLUG,
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'order_id' => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'description' => __( 'WooCommerce order ID.', 'woocommerce-square' ),
					),
				),
				'required'             => array( 'order_id' ),
				'additionalProperties' => false,
			),
			'execute_callback'    => array( self::class, 'execute' ),
			'permission_callback' => array( Abilities_Registrar::class, 'can_manage_woocommerce_square' ),
			'meta'                => array(
				'annotations'  => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
				'show_in_rest' => true,
				'mcp'          => array(
					'public' => true,
				),
			),
		);
	}

	/**
	 * Execute callback.
	 *
	 * @param mixed $input Input args; `order_id` is required.
	 * @return array|\WP_Error
	 */
	public static function execute( $input = null ) {
		$input    = is_array( $input ) ? $input : array();
		$order_id = isset( $input['order_id'] ) ? (int) $input['order_id'] : 0;

		if ( $order_id <= 0 ) {
			return new \WP_Error(
				'woocommerce_square_invalid_order_id',
				__( 'A positive order_id is required.', 'woocommerce-square' ),
				array( 'status' => 400 )
			);
		}

		if ( ! function_exists( 'wc_get_order' ) ) {
			return self::not_initialized_error();
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return new \WP_Error(
				'woocommerce_square_order_not_found',
				/* translators: %d: requested order ID */
				sprintf( __( 'Order %d was not found.', 'woocommerce-square' ), $order_id ),
				array( 'status' => 404 )
			);
		}

		$payment_method = (string) $order->get_payment_method();

		// Treat as Square only when the live plugin actually owns this
		// gateway id AND it is one of the two Square-branded gateways. The
		// gift-cards gateway is intentionally excluded — its data attaches
		// to the parent Square charge via gift_card_* meta read below.
		$plugin    = self::get_plugin_or_error();
		$is_square = false;
		$prefix    = '';
		if ( ! is_wp_error( $plugin )
			&& $plugin->has_gateway( $payment_method )
			&& in_array( $payment_method, array( Plugin::GATEWAY_ID, Plugin::CASH_APP_PAY_GATEWAY_ID ), true )
		) {
			// Defer to the gateway for the meta-key prefix so the storage
			// path (Payment_Gateway::update_order_meta()) and this reader
			// share a single source of truth. If the gateway ever changes
			// how it derives its prefix, this reader follows automatically.
			$gateway = $plugin->get_gateway( $payment_method );
			if ( $gateway && method_exists( $gateway, 'get_order_meta_prefix' ) ) {
				$prefix    = (string) $gateway->get_order_meta_prefix();
				$is_square = '' !== $prefix;
			}
		}

		$result = array(
			'order_id'          => $order_id,
			'order_status'      => (string) $order->get_status(),
			'order_total'       => (float) $order->get_total(),
			'order_currency'    => (string) $order->get_currency(),
			'payment_method'    => $payment_method,
			'is_square_payment' => $is_square,
			'refunds'           => self::collect_refunds( $order ),
		);

		if ( ! $is_square ) {
			return $result;
		}

		$read = static function ( $key ) use ( $order, $prefix ) {
			$value = $order->get_meta( $prefix . $key, true );
			return '' === $value ? null : $value;
		};

		// Pre-fetch every meta key once. get_meta() does a linear scan of the
		// order's meta_data on each call — read each here, reference the local
		// from the result array below.
		$trans_id            = $read( 'trans_id' );
		$trans_date          = $read( 'trans_date' );
		$square_order_id     = $read( 'square_order_id' );
		$square_location_id  = $read( 'square_location_id' );
		$square_version      = $read( 'square_version' );
		$charge_type         = $read( 'charge_type' );
		$charge_captured     = $read( 'charge_captured' );
		$capture_total       = $read( 'capture_total' );
		$authorization_amt   = $read( 'authorization_amount' );
		$auth_can_capture    = $read( 'auth_can_be_captured' );
		$retry_count         = $read( 'retry_count' );
		$is_tender_gift_card = $read( 'is_tender_type_gift_card' );

		$result['square'] = array(
			'transaction_id'        => null === $trans_id ? null : (string) $trans_id,
			'transaction_date'      => null === $trans_date ? null : (string) $trans_date,
			'square_order_id'       => null === $square_order_id ? null : (string) $square_order_id,
			'square_location_id'    => null === $square_location_id ? null : (string) $square_location_id,
			'square_version'        => null === $square_version ? null : (string) $square_version,
			'charge_type'           => null === $charge_type ? null : (string) $charge_type,
			'charge_captured'       => null === $charge_captured ? null : (string) $charge_captured,
			'capture_total'         => null === $capture_total ? null : (float) $capture_total,
			'authorization_amount'  => null === $authorization_amt ? null : (float) $authorization_amt,
			'auth_can_be_captured'  => null === $auth_can_capture ? null : (string) $auth_can_capture,
			'retry_count'           => null === $retry_count ? null : (int) $retry_count,
			'has_square_charge'     => null !== $trans_id,
			'is_fully_captured'     => 'yes' === $charge_captured,
			'is_partially_captured' => 'partial' === $charge_captured,
		);

		// Match the canonical reader in Handlers\Order::is_tender_type_gift_card(),
		// which compares against the string '1' (WC stores meta-bool `true` as '1').
		// Previous version compared against 'yes' here, which never matched and
		// made the gift-card-only branch dead.
		$gift_card_split = 'PARTIAL' === $charge_type || '1' === (string) $is_tender_gift_card;

		if ( $gift_card_split ) {
			$gift_trans_id  = $read( 'gift_card_trans_id' );
			$gift_partial   = $read( 'gift_card_partial_total' );
			$gift_refunded  = $read( 'gift_card_recorded_refund_total' );
			$gift_line_item = $read( 'gift_card_line_item_id' );

			$result['square']['gift_card'] = array(
				'is_split_payment' => true,
				'transaction_id'   => null === $gift_trans_id ? null : (string) $gift_trans_id,
				'partial_total'    => null === $gift_partial ? null : (float) $gift_partial,
				'refunded_total'   => null === $gift_refunded ? 0.0 : (float) $gift_refunded,
				'line_item_id'     => null === $gift_line_item ? null : (string) $gift_line_item,
			);
		} else {
			$result['square']['gift_card'] = array( 'is_split_payment' => false );
		}

		return $result;
	}

	/**
	 * Collect a compact summary of WC refunds attached to the order.
	 *
	 * @param \WC_Order $order Order being inspected.
	 * @return array
	 */
	private static function collect_refunds( \WC_Order $order ): array {
		$out = array();
		foreach ( $order->get_refunds() as $refund ) {
			if ( ! is_object( $refund ) || ! method_exists( $refund, 'get_id' ) ) {
				continue;
			}
			$out[] = array(
				'id'     => (int) $refund->get_id(),
				'amount' => (float) $refund->get_amount(),
				'reason' => method_exists( $refund, 'get_reason' ) ? (string) $refund->get_reason() : '',
				'date'   => method_exists( $refund, 'get_date_created' ) && $refund->get_date_created()
					? $refund->get_date_created()->date( 'c' )
					: null,
			);
		}
		return $out;
	}
}
