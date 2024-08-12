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

namespace WooCommerce\Square\Handlers;

use WooCommerce\Square\Framework\PaymentGateway\Payment_Gateway;
use WooCommerce\Square\Framework\Square_Helper;
use WooCommerce\Square\Plugin;
use WooCommerce\Square\Handlers\Product;

defined( 'ABSPATH' ) || exit;

/**
 * Order handler class.
 *
 * @since 2.0.0
 */
class Order {

	/**
	 * Array of previous stock values.
	 *
	 * @var array
	 */
	private $previous_stock = array();

	/**
	 * Array of product IDs that have been scheduled for sync in this request.
	 *
	 * @var array
	 */
	private $products_to_sync = array();

	/**
	 * Array of payment gateways that are Square payment gateways.
	 *
	 * @var array
	 */
	private $square_payment_gateways = array(
		Plugin::GATEWAY_ID,
		Plugin::CASH_APP_PAY_GATEWAY_ID,
	);

	/**
	 * Sets up Square order handler.
	 *
	 * @since 2.0.0
	 */
	public function __construct() {
		add_action( 'wp', array( $this, 'save_guest_details' ) );
		// remove Square variation IDs from order item meta
		add_action( 'woocommerce_hidden_order_itemmeta', array( $this, 'hide_square_order_item_meta' ) );

		if ( version_compare( WC()->version, '7.6', '>=' ) ) {
			// Add hooks for stock sync.
			add_action( 'woocommerce_reduce_order_item_stock', array( $this, 'maybe_stage_stock_updates_for_product' ), 10, 3 );
			add_action( 'woocommerce_reduce_order_stock', array( $this, 'maybe_sync_staged_inventory_updates' ) );
		} else {
			// @todo Remove this block when WooCommerce 7.6 is the minimum supported version.
			// ADD hooks for stock syncs based on changes from orders not from this gateway
			add_action( 'woocommerce_checkout_order_processed', array( $this, 'maybe_sync_stock_for_order_via_other_gateway' ), 10, 3 );
			add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'maybe_sync_stock_for_store_api_order_via_other_gateway' ), 10, 1 );

			// Add specific hook for paypal IPN callback
			add_action( 'valid-paypal-standard-ipn-request', array( $this, 'maybe_sync_stock_for_order_via_paypal' ), 10, 1 );
		}

		// ADD hooks to restore stock for pending and cancelled order status.
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'maybe_sync_inventory_for_stock_increase' ), 1 );
		add_action( 'woocommerce_order_status_pending', array( $this, 'maybe_sync_inventory_for_stock_increase' ), 1 );

		// ADD hooks to listen to refunds on orders from other gateways.
		add_action( 'woocommerce_order_refunded', array( $this, 'maybe_sync_stock_for_refund_from_other_gateway' ), 10, 2 );

		// Add gift card order item to the order edit screen.
		add_action( 'woocommerce_admin_order_items_after_fees', array( $this, 'add_admin_order_items' ) );

		// Include gift card information in payment method info.
		add_filter( 'woocommerce_order_get_payment_method_title', array( $this, 'filter_payment_method_title' ), 10, 2 );
		add_filter( 'woocommerce_gateway_title', array( $this, 'filter_gateway_title' ), 10, 2 );

		// Add Gift Card "send-to" email address to the order meta.
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_gift_card_add_to_cart_details_to_order' ), 10, 4 );
		add_action( 'woocommerce_add_order_again_cart_item', array( $this, 'reorder_gift_card' ) );
		add_filter( 'woocommerce_order_item_display_meta_key', array( $this, 'modify_gift_card_line_item_key' ), 10, 3 );
		add_action( 'wc_square_gift_card_activated', array( $this, 'trigger_email_for_gift_card_sent' ) );
		add_filter( 'woocommerce_hidden_order_itemmeta', array( $this, 'hide_gift_card_line_item_meta' ) );
		add_filter( 'woocommerce_get_order_item_totals', array( $this, 'filter_order_item_totals' ), 10, 2 );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'render_admin_missing_billing_details_notice' ) );
		add_action( 'before_woocommerce_pay_form', array( $this, 'render_missing_billing_details_notice' ), 10, 1 );
		add_filter( 'woocommerce_order_email_verification_required', array( $this, 'no_verification_on_guest_save' ) );
	}

	/**
	 * Ensures the Square order item meta is hidden.
	 *
	 * @since 2.0.0
	 *
	 * @param string[] $hidden the hidden order item meta
	 * @return string[] updated meta
	 */
	public function hide_square_order_item_meta( $hidden ) {

		$hidden[] = '_square_item_variation_id';

		return $hidden;
	}

	/**
	 * Add hooks to ensure PayPal IPN callbacks are added caches and considered for inventory changes
	 * when the sync happens. This also adds the shutdown hook to ensure sync happens if needed at
	 * a later stage.
	 *
	 * @since 2.1.1
	 *
	 * @param array $posted values returned from PayPal Standard IPN callback.
	 */
	public function maybe_sync_stock_for_order_via_paypal( $posted ) {
		if ( empty( $posted['custom'] ) ) {
			return;
		}

		$raw_order = json_decode( $posted['custom'] );
		if ( empty( $raw_order->order_id ) ) {
			return;
		}

		$order = wc_get_order( $raw_order->order_id );

		if ( ! $order || ! $order instanceof \WC_Order ) {
			return;
		}

		$this->sync_stock_for_order( $order );
	}

	/**
	 * Checks if we should sync stock for this order.
	 * We only sync for other gateways that Square will not be aware of.
	 *
	 * This functions sets a process in motion that gathers products that will be processed on shutdown.
	 *
	 * @since 2.0.8
	 *
	 * @param int      $order_id    Order ID number.
	 * @param array    $posted_data Submitted order data.
	 * @param WC_Order $order       Order object.
	 */
	public function maybe_sync_stock_for_order_via_other_gateway( $order_id, $posted_data, $order ) {

		// Confirm we are not processing the order through the Square gateway.
		if ( ! $order instanceof \WC_Order || in_array( $order->get_payment_method(), $this->square_payment_gateways, true ) ) {
			return;
		}

		$this->sync_stock_for_order( $order );
	}

	/**
	 * Checks if we should sync stock for this order.
	 * We only sync for other gateways that Square will not be aware of.
	 *
	 * This functions sets a process in motion that gathers products that will be processed on shutdown.
	 *
	 * @since 4.0.0
	 *
	 * @param \WC_Order $order Order object.
	 */
	public function maybe_sync_stock_for_store_api_order_via_other_gateway( $order ) {

		// Confirm we are not processing the order through the Square gateway.
		if ( ! $order instanceof \WC_Order || in_array( $order->get_payment_method(), $this->square_payment_gateways, true ) ) {
			return;
		}

		$this->sync_stock_for_order( $order );
	}

	/**
	 * For a given order sync stock if inventory sync is enabled.
	 *
	 * @since 2.1.1
	 *
	 * @param \WC_Order $order the order for which the stock must be synced.
	 */
	protected function sync_stock_for_order( $order ) {

		if ( ! wc_square()->get_settings_handler()->is_inventory_sync_enabled() ) {
			return;
		}

		$this->cache_previous_stock( $order );

		add_action( 'woocommerce_product_set_stock', array( $this, 'maybe_stage_inventory_updates_for_product' ) );
		add_action( 'woocommerce_variation_set_stock', array( $this, 'maybe_stage_inventory_updates_for_product' ) );

		add_action( 'shutdown', array( $this, 'maybe_sync_staged_inventory_updates' ) );
	}

	/**
	 * Loop through order and cached previous stock values before they are reduced.
	 *
	 * @since 2.0.8
	 *
	 * @param WC_Order $order Order object.
	 */
	private function cache_previous_stock( $order ) {

		// Loop over all items.
		foreach ( $order->get_items() as $item ) {
			if ( ! $item->is_type( 'line_item' ) ) {
				continue;
			}

			// Check to make sure it hasn't already been reduced.
			$product            = $item->get_product();
			$item_stock_reduced = $item->get_meta( '_reduced_stock', true );

			if ( $item_stock_reduced || ! $product || ! $product->managing_stock() ) {
				continue;
			}

			$this->previous_stock[ $product->get_id() ] = $product->get_stock_quantity();
		}
	}

	/**
	 * Stages a product inventory update for sync with Square when a product stock is updated.
	 *
	 * @internal The staged values will be stored in product_to_sync
	 *
	 * @since 2.0.8
	 *
	 * @param WC_Product $product the updated product with inventory updates.
	 */
	public function maybe_stage_inventory_updates_for_product( $product ) {

		// Do not add inventory changes if we are already doing a sync, or we are not syncing this product.
		if ( defined( 'DOING_SQUARE_SYNC' ) || ! $product || ! Product::is_synced_with_square( $product ) ) {
			return;
		}

		// Compare stock to get difference.
		$product_id = $product->get_id();
		$previous   = isset( $this->previous_stock[ $product_id ] ) ? $this->previous_stock[ $product_id ] : false;
		$current    = $product->get_stock_quantity();
		$adjustment = (int) $current - $previous;

		if ( false === $previous || 0 === $adjustment ) {
			return;
		}

		// Record what type of inventory action occurred.
		$this->products_to_sync[ $product_id ] = $adjustment;
	}

	/**
	 * Stage inventory updates for products in the order.
	 * This function only used for WooCommerce 7.6 and above.
	 *
	 * @param \WC_Order_Item_Product $item   Order item data.
	 * @param array                  $change Change Details.
	 * @param \WC_Order              $order  Order data.
	 * @return void
	 *
	 * @since 4.1.0
	 */
	public function maybe_stage_stock_updates_for_product( $item, $change, $order ) {
		$product = $change['product'] ?? false;

		/**
		 * Bail If
		 * 1. Order is processed using Square payment gateway OR
		 * 2. Inventory sync is not enabled OR
		 * 3. Square sync is in progress OR
		 * 4. Square sync is not enabled for the product
		 */
		if (
			! $order instanceof \WC_Order ||
			in_array( $order->get_payment_method(), $this->square_payment_gateways, true ) ||
			! wc_square()->get_settings_handler()->is_inventory_sync_enabled() ||
			defined( 'DOING_SQUARE_SYNC' ) ||
			! $product ||
			! Product::is_synced_with_square( $product )
		) {
			return;
		}

		// Get stock adjustment for the product.
		$product_id = $product->get_id();
		$previous   = $change['from'] ?? false;
		$current    = $change['to'] ?? false;
		$adjustment = (int) $current - $previous;

		if ( false === $previous || false === $current || 0 === $adjustment ) {
			return;
		}

		// Stage the inventory update.
		$this->products_to_sync[ $product_id ] = $adjustment;
	}

	/**
	 * Maybe restore stock for an order that was cancelled or moved to pending.
	 * This is only for orders that were not processed through the Square gateway.
	 *
	 * @param int $order_id Order ID.
	 *
	 * @since 4.1.0
	 */
	public function maybe_sync_inventory_for_stock_increase( $order_id ) {
		if ( ! wc_square()->get_settings_handler()->is_inventory_sync_enabled() ) {
			return;
		}

		// Confirm we are not processing the order through the Square gateway.
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order || in_array( $order->get_payment_method(), $this->square_payment_gateways, true ) ) {
			return;
		}

		$trigger_increase = $order->get_order_stock_reduced();

		// Only continue if we're increasing stock.
		if ( ! $trigger_increase ) {
			return;
		}

		// Cache the product's previous stock value.
		add_action( 'woocommerce_variation_before_set_stock', array( $this, 'cache_product_previous_stock' ) );
		add_action( 'woocommerce_product_before_set_stock', array( $this, 'cache_product_previous_stock' ) );

		// Stage the inventory update.
		add_action( 'woocommerce_variation_set_stock', array( $this, 'maybe_stage_inventory_updates_for_product' ) );
		add_action( 'woocommerce_product_set_stock', array( $this, 'maybe_stage_inventory_updates_for_product' ) );

		// Sync the staged inventory updates.
		add_action( 'woocommerce_restore_order_stock', array( $this, 'maybe_sync_staged_inventory_updates' ) );
	}

	/**
	 * Cache the product's previous stock value.
	 *
	 * @param WC_Product $product Product object.
	 *
	 * @since 4.1.0
	 */
	public function cache_product_previous_stock( $product ) {
		$this->previous_stock[ $product->get_id() ] = $product->get_stock_quantity();
	}

	/**
	 * Initializes a synchronization event for any staged inventory updates in this request.
	 *
	 * @internal
	 *
	 * @since 2.0.8
	 */
	public function maybe_sync_staged_inventory_updates() {

		$inventory_adjustments = array();

		foreach ( $this->products_to_sync as $product_id => $adjustment ) {

			$product = wc_get_product( $product_id );
			if ( ! $product instanceof \WC_Product ) {
				continue;
			}

			$inventory_adjustment = Product::get_inventory_change_adjustment_type( $product, $adjustment );

			if ( empty( $inventory_adjustment ) ) {
				continue;
			}

			$inventory_adjustments[] = $inventory_adjustment;
		}

		if ( empty( $inventory_adjustments ) ) {
			return;
		}

		wc_square()->log( 'New order from other gateway inventory syncing..' );
		$idempotency_key = wc_square()->get_idempotency_key( md5( serialize( $inventory_adjustments ) ) . '_change_inventory' );
		wc_square()->get_api()->batch_change_inventory( $idempotency_key, $inventory_adjustments );

		// Reset the staged inventory updates.
		$this->products_to_sync = array();
	}

	/**
	 * Handle order refunds inventory/stock changes sync.
	 *
	 * @since 2.0.8
	 *
	 * @param in $order_id
	 * @param int $refund_id
	 */
	public function maybe_sync_stock_for_refund_from_other_gateway( $order_id, $refund_id ) {

		if ( ! wc_square()->get_settings_handler()->is_inventory_sync_enabled() ) {
			return;
		}

		// Confirm we are not processing the order through the Square gateway.
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order || in_array( $order->get_payment_method(), $this->square_payment_gateways, true ) ) {
			return;
		}

		// don't refund items if the "Restock refunded items" option is unchecked - maintains backwards compatibility if this function is called outside of the `woocommerce_order_refunded` do_action
		if ( isset( $_POST['restock_refunded_items'] ) ) {
			// Validate the user has permissions to process this request.
			if ( ! check_ajax_referer( 'order-item', 'security', false ) || ! current_user_can( 'edit_shop_orders' ) ) {
				return;
			}

			if ( 'false' === $_POST['restock_refunded_items'] ) {
				return;
			}
		}

		$refund                = new \WC_Order_Refund( $refund_id );
		$inventory_adjustments = array();
		foreach ( $refund->get_items() as $item ) {

			if ( 'line_item' !== $item->get_type() ) {
				continue;
			}

			$product = $item->get_product();
			if ( ! $product instanceof \WC_Product ) {
				continue;
			}

			$adjustment           = -1 * ( $item->get_quantity() ); // we want a positive value to increase the stock and a negative number to decrease it.
			$inventory_adjustment = Product::get_inventory_change_adjustment_type( $product, $adjustment );

			if ( empty( $inventory_adjustment ) ) {
				continue;
			}

			$inventory_adjustments[] = $inventory_adjustment;
		}

		if ( empty( $inventory_adjustments ) ) {
			return;
		}

		wc_square()->log( 'Order from other gateway Refund inventory updates syncing..' );
		$idempotency_key = wc_square()->get_idempotency_key( md5( serialize( $inventory_adjustments ) ) . '_change_inventory' );
		wc_square()->get_api()->batch_change_inventory( $idempotency_key, $inventory_adjustments );
	}

	/**
	 * Add gift card recipient data to order line item meta.
	 *
	 * @since 4.2.0
	 *
	 * @param \WC_Order_Item_Product $item          The order item product.
	 * @param string                 $cart_item_key The cart item key.
	 * @param array                  $values        Values associated with a cart item key.
	 * @param \WC_Order              $order         Woo Order.
	 */
	public function add_gift_card_add_to_cart_details_to_order( $item, $cart_item_key, $values, $order ) {
		$product = wc_get_product( $values['product_id'] );

		if ( ! $product instanceof \WC_Product ) {
			return;
		}

		// Return if the product is not a gift card product.
		if ( ! Product::is_gift_card( $product ) ) {
			return;
		}

		// Add meta if a new gift card is purchased.
		if ( ! empty( $values['square-gift-card-send-to-email'] ) ) {
			if ( ! empty( $values['square-gift-card-sender-name'] ) ) {
				$item->add_meta_data( 'square-gift-card-sender-name', $values['square-gift-card-sender-name'] );
			}

			$item->add_meta_data( 'square-gift-card-send-to-email', $values['square-gift-card-send-to-email'] );
			$item->add_meta_data( '_square-gift-card-purchase-type', 'new' );

			if ( ! empty( $values['square-gift-card-sent-to-first-name'] ) ) {
				$item->add_meta_data( 'square-gift-card-sent-to-first-name', $values['square-gift-card-sent-to-first-name'] );
			}

			if ( ! empty( $values['square-gift-card-sent-to-message'] ) ) {
				$item->add_meta_data( 'square-gift-card-sent-to-message', $values['square-gift-card-sent-to-message'] );
			}
		}

		// Add meta if an existing gift card is loaded with amount.
		if ( ! empty( $values['square-gift-card-gan'] ) ) {
			$item->add_meta_data( 'square-gift-card-gan', $values['square-gift-card-gan'] );
			$item->add_meta_data( '_square-gift-card-purchase-type', 'load' );
		}

		// Fallback when gift card product is added to card directly from the /shop page which
		// bypasses adding recipient email address, so we default to purchasing a new gift card
		// without a recipient email address.
		if ( empty( $values['square-gift-card-send-to-email'] ) && empty( $values['square-gift-card-gan'] ) ) {
			$item->add_meta_data( '_square-gift-card-purchase-type', 'new' );
		}
	}

	/**
	 * Adds gift card related order item meta to the order again cart item.
	 *
	 * @param array $cart_item_data Cart item data.
	 *
	 * @return array
	 */
	public function reorder_gift_card( $cart_item_data ) {
		// Disabling PHPCS check as nonce verification is done in the parent method.
		$order_id = absint( wp_unslash( $_GET['order_again'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! $order_id ) {
			return $cart_item_data;
		}

		$order       = wc_get_order( $order_id );
		$order_items = $order->get_items();

		if ( empty( $order_items ) ) {
			return $cart_item_data;
		}

		$product = wc_get_product( $cart_item_data['product_id'] );

		if ( ! Product::is_gift_card( $product ) ) {
			return $cart_item_data;
		}

		/** @var \WC_Order_Item $order_item */
		foreach ( $order_items as $order_item ) {
			$order_item_data = $order_item->get_data();
			$product         = wc_get_product( $order_item_data['product_id'] );

			if ( ! Product::is_gift_card( $product ) ) {
				continue;
			}

			$cart_item_data['square-gift-card-send-to-email']      = $order_item->get_meta( 'square-gift-card-send-to-email' );
			$cart_item_data['square-gift-card-sender-name']        = $order_item->get_meta( 'square-gift-card-sender-name' );
			$cart_item_data['square-gift-card-sent-to-first-name'] = $order_item->get_meta( 'square-gift-card-sent-to-first-name' );
			$cart_item_data['square-gift-card-sent-to-message']    = $order_item->get_meta( 'square-gift-card-sent-to-message' );
		}

		return $cart_item_data;
	}

	/**
	 * Filters the meta key into a human-readable format.
	 *
	 * @since 4.2.0
	 *
	 * @param string                 $display_key The value that will be displayed in the order line item.
	 * @param \WC_Meta_Data          $meta        The order line item meta data object.
	 * @param \WC_Order_Item_Product $order_item  Instance of the order item product.
	 *
	 * @return string
	 */
	public function modify_gift_card_line_item_key( $display_key, $meta, $order_item ) {
		if ( 'square-gift-card-sender-name' === $meta->key ) {
			return __( "Sender's name", 'woocommerce-square' );
		}

		if ( 'square-gift-card-send-to-email' === $meta->key ) {
			return __( "Recipient's email", 'woocommerce-square' );
		}

		if ( 'square-gift-card-sent-to-first-name' === $meta->key ) {
			return __( "Recipient's name", 'woocommerce-square' );
		}

		if ( 'square-gift-card-sent-to-message' === $meta->key ) {
			return __( 'Message', 'woocommerce-square' );
		}

		if ( 'square-gift-card-gan' === $meta->key ) {
			return __( 'Gift card number', 'woocommerce-square' );
		}

		return $display_key;
	}

	/**
	 * Triggers an email to the recipient of the gift card.
	 *
	 * @since 4.2.0
	 *
	 * @param \WC_Order $order WooCommerce order.
	 */
	public function trigger_email_for_gift_card_sent( $order ) {
		wc_square()->get_email_handler()->get_gift_card_sent()->trigger( $order );
	}

	/**
	 * Hides the meta that indicates the type of gift card purchase,
	 * new or reload.
	 *
	 * @since 4.2.0
	 *
	 * @param array $meta_array Array of line order item meta.
	 * @return array
	 */
	public function hide_gift_card_line_item_meta( $meta_array ) {
		$meta_array[] = '_square-gift-card-purchase-type';

		return $meta_array;
	}

	/**
	 * Returns the type of gift card purchase type - `new` or `load`.
	 *
	 * - `new` indicates a new gift card is purchased.
	 * - `load` indicates an existing gift card is loaded with additional funds.
	 *
	 * @since 4.2.0
	 *
	 * @param \WC_Order $order WooCommerce order.
	 *
	 * @return string|boolean Types of gift card purchase, false otherwise.
	 */
	public static function get_gift_card_purchase_type( $order ) {
		$order_items = $order->get_items();

		/** @var \WC_Order_Item_Product $order_item */
		foreach ( $order_items as $order_item ) {
			$purchase_type = $order_item->get_meta( '_square-gift-card-purchase-type' );

			if ( ! empty( $purchase_type ) ) {
				return $purchase_type;
			}
		}

		return false;
	}

	/**
	 * Returns the gift card number from the order line item.
	 *
	 * @since 4.2.0
	 *
	 * @param \WC_Order $order WooCommerce order.
	 *
	 * @return string|boolean The gift card number, false otherwise.
	 */
	public static function get_gift_card_gan( $order ) {
		$order_items = $order->get_items();

		/** @var \WC_Order_Item_Product $order_item */
		foreach ( $order_items as $order_item ) {
			$gan = $order_item->get_meta( 'square-gift-card-gan' );

			if ( ! empty( $gan ) ) {
				return $gan;
			}
		}

		return false;
	}

	/**
	 * Returns if the order was placed using a Square credit card.
	 *
	 * @since 3.7.0
	 *
	 * @param \WC_Order $order WooCommerce order.
	 *
	 * @return boolean
	 */
	public static function is_tender_type_card( $order ) {
		return '1' === wc_square()->get_gateway( $order->get_payment_method() )->get_order_meta( $order, 'is_tender_type_card' );
	}

	/**
	 * @since 3.7.0
	 *
	 * @param \WC_Order $order WooCommerce order.
	 *
	 * @return boolean
	 */
	public static function is_tender_type_gift_card( $order ) {
		return '1' === wc_square()->get_gateway( $order->get_payment_method() )->get_order_meta( $order, 'is_tender_type_gift_card' );
	}

	/**
	 * Returns if the order was placed using a Square cash app pay.
	 *
	 * @since 4.6.0
	 *
	 * @param \WC_Order $order WooCommerce order.
	 *
	 * @return boolean
	 */
	public static function is_tender_type_cash_app_pay( $order ) {
		return '1' === wc_square()->get_gateway( $order->get_payment_method() )->get_order_meta( $order, 'is_tender_type_cash_app_wallet' );
	}

	/**
	 * Sets the amount charged on the gift card for the given order.
	 *
	 * @since 3.7.0
	 *
	 * @param \WC_Order $order  WooCommerce order.
	 * @param float     $amount The total amount charged on the gift card for the order.
	 */
	public static function set_gift_card_total_charged_amount( $order, $amount ) {
		wc_square()->get_gateway( $order->get_payment_method() )->update_order_meta( $order, 'gift_card_charged_amount', $amount );
	}

	/**
	 * Returns the amount charged on the gift card for the given order.
	 *
	 * @since 3.7.0
	 *
	 * @param \WC_Order $order WooCommerce order.
	 *
	 * @return float
	 */
	public static function get_gift_card_total_charged_amount( $order ) {
		return (float) wc_square()->get_gateway( $order->get_payment_method() )->get_order_meta( $order, 'gift_card_charged_amount' );
	}

	/**
	 * Returns the last 4 digits of the gift card.
	 *
	 * @since 3.7.0
	 *
	 * @param \WC_Order $order WooCommerce order.
	 *
	 * @return string
	 */
	public static function get_gift_card_last4( $order ) {
		return wc_square()->get_gateway( $order->get_payment_method() )->get_order_meta( $order, 'gift_card_last4' );
	}

	/**
	 * Sets the last 4 digits of the gift card.
	 *
	 * @since 3.7.0
	 *
	 * @param \WC_Order $order WooCommerce order.
	 *
	 * @return string
	 */
	public static function set_gift_card_last4( $order, $number ) {
		return wc_square()->get_gateway( $order->get_payment_method() )->update_order_meta( $order, 'gift_card_last4', $number );
	}

	/**
	 * Returns the total amount that is refunded to the gift card.
	 *
	 * @since 3.7.0
	 *
	 * @param \WC_Order $order WooCommerce order.
	 *
	 * @return float
	 */
	public static function get_gift_card_total_refunded_amount( $order ) {
		$amount = (float) wc_square()->get_gateway( $order->get_payment_method() )->get_order_meta( $order, 'gift_card_refunded_amount' );
		$amount = empty( $amount ) ? 0 : $amount;

		return $amount;
	}

	/**
	 * Sets the total amount that is refunded to the gift card.
	 *
	 * @since 3.7.0
	 *
	 * @param \WC_Order $order  WooCommerce order.
	 * @param float     $amount The total amount refunded to the gift card for the order.
	 */
	public static function set_gift_card_total_refunded_amount( $order, $amount ) {
		wc_square()->get_gateway( $order->get_payment_method() )->update_order_meta( $order, 'gift_card_refunded_amount', $amount );
	}

	/**
	 * Sets the total order amount before applying the gift card.
	 *
	 * @since 3.7.0
	 *
	 * @param \WC_Order $order  WooCommerce order.
	 * @param float     $amount
	 */
	public static function set_order_total_before_gift_card( $order, $amount ) {
		wc_square()->get_gateway( $order->get_payment_method() )->update_order_meta( $order, 'order_total_before_gift_card', $amount );
	}

	/**
	 * Gets the total order amount before applying the gift card.
	 *
	 * @since 3.7.0
	 *
	 * @return float
	 */
	public static function get_order_total_before_gift_card( $order ) {
		return (float) wc_square()->get_gateway( $order->get_payment_method() )->get_order_meta( $order, 'order_total_before_gift_card' );
	}

	/**
	 * Displays the gift card details in the order item.
	 *
	 * @param int $order_id WooCommerce order ID.
	 */
	public function add_admin_order_items( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! self::is_tender_type_gift_card( $order ) ) {
			return;
		}

		?>
		<tr class="square_gift_card item">
			<td class="thumb">
				<div style="width: 38px;">
					<img src="<?php echo esc_url( WC_SQUARE_PLUGIN_URL . '/build/images/gift-card.png' ); ?>" />
				</div>
			</td>
			<td class="name">
				<div class="view">
				</div>
				<div class="view">
					<table cellspacing="0" class="display_meta">
						<tbody>
							<tr>
								<th>
									<?php esc_html_e( 'Square Gift Card:', 'woocommerce-square' ); ?>
								</th>
								<td>
									<?php
									printf(
										/* Translators: %s - last 4 digits of the gift card. */
										esc_html__( 'ending in %s', 'woocommerce-square' ),
										esc_html( self::get_gift_card_last4( $order ) )
									);
									?>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
			</td>
			<td class="item_cost" width="1%">&nbsp;</td>
			<td class="quantity" width="1%">&nbsp;</td>
			<td class="line_cost" width="1%">
				<div class="view">-
				<?php
					echo wp_kses_post( wc_price( self::get_gift_card_total_charged_amount( $order ), array( 'currency' => $order->get_currency() ) ) );
					$refunded_amount = self::get_gift_card_total_refunded_amount( $order );
				?>
				</div>
			</td>
			<td class="wc-order-edit-line-item" width="1%">
		</tr>
		<?php
	}

	/**
	 * Includes info regarding gift card in the payment method title.
	 *
	 * @since 3.7.0
	 *
	 * @param string    $value Payment method title.
	 * @param \WC_Order $order WooCommerce order.
	 *
	 * @return string
	 */
	public function filter_payment_method_title( $value, $order ) {
		$is_tender_gift_card = self::is_tender_type_gift_card( $order );
		$is_tender_card      = self::is_tender_type_card( $order );
		$is_tender_cash_app  = self::is_tender_type_cash_app_pay( $order );

		if ( $is_tender_gift_card && ( $is_tender_card || $is_tender_cash_app ) ) {
			$gateway               = wc_square()->get_gateway( $order->get_payment_method() );
			$gift_card_charged     = $gateway->get_order_meta( $order, 'gift_card_partial_total' );
			$other_gateway_charged = $gateway->get_order_meta( $order, 'other_gateway_partial_total' );
			if ( empty( $other_gateway_charged ) ) {
				// Backward compatibility.
				$other_gateway_charged = $gateway->get_order_meta( $order, 'credit_card_partial_total' );
			}

			$payment_method = '';

			if ( $is_tender_card ) {
				$payment_method = esc_html__( 'Credit Card', 'woocommerce-square' );
			} elseif ( $is_tender_cash_app ) {
				$payment_method = esc_html__( 'Cash App Pay', 'woocommerce-square' );
			}

			return sprintf(
				/* translators: %1$s - Amount charged on gift card, %2$s -  Amount charged on credit card. */
				__( 'Square Gift Card (%1$s) and %2$s (%3$s)', 'woocommerce-square' ),
				get_woocommerce_currency_symbol( $order->get_currency() ) . Square_Helper::number_format( $gift_card_charged ),
				$payment_method ?? '',
				get_woocommerce_currency_symbol( $order->get_currency() ) . Square_Helper::number_format( $other_gateway_charged )
			);
		}

		if ( $is_tender_gift_card ) {
			return sprintf(
				/* translators: %1$s - Amount charged on gift card. */
				__( 'Square Gift Card (%1$s)', 'woocommerce-square' ),
				get_woocommerce_currency_symbol( $order->get_currency() ) . $order->get_total()
			);
		}

		return $value;
	}

	/**
	 * Includes info regarding gift card in the payment gateway title.
	 *
	 * @since 3.7.0
	 *
	 * @param string $value Gateway title.
	 * @param string $id    Plugin id.
	 *
	 * @return string
	 */
	public function filter_gateway_title( $value, $id ) {
		$plugin_gateways = wc_square()->get_gateway_ids() ?? array();
		if ( ! in_array( $id, $plugin_gateways, true ) || ! is_admin() || ! function_exists( 'get_current_screen' ) ) {
			return $value;
		}

		$screen = get_current_screen();

		if ( ! ( $screen && 'shop_order' === $screen->id ) ) {
			return $value;
		}

		if ( ! isset( $_GET['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return $value;
		}

		$post_id = wc_clean( absint( $_GET['post'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order   = wc_get_order( $post_id );

		if ( ! $order instanceof \WC_Order ) {
			return $value;
		}

		return self::filter_payment_method_title( $value, $order );
	}

	/**
	 * Includes split payment details on the order details screen.
	 *
	 * @param array     $total_rows Array of order details.
	 * @param \WC_Order $order      WooCommerce Order.
	 *
	 * @return array
	 */
	public function filter_order_item_totals( $total_rows, $order ) {
		$charge_type = wc_square()->get_gateway( $order->get_payment_method() )->get_order_meta( $order, 'charge_type' );

		if ( Payment_Gateway::CHARGE_TYPE_PARTIAL !== $charge_type ) {
			return $total_rows;
		}

		$gift_card_partial_total     = wc_square()->get_gateway( $order->get_payment_method() )->get_order_meta( $order, 'gift_card_partial_total' );
		$other_gateway_partial_total = wc_square()->get_gateway( $order->get_payment_method() )->get_order_meta( $order, 'other_gateway_partial_total' );
		if ( empty( $other_gateway_partial_total ) ) {
			// Backward compatibility.
			$other_gateway_partial_total = wc_square()->get_gateway( $order->get_payment_method() )->get_order_meta( $order, 'credit_card_partial_total' );
		}

		$total_rows['order_total']['value'] = sprintf(
			/* translators: %1$s - Order total, %2$s - Amount charged on gift card, %3$s - Other payment method %4$s -  Amount charged on credit card/cash app pay. */
			esc_html__( '%1$s — Total split between gift card (%2$s) and %3$s (%4$s)', 'woocommerce-square' ),
			wp_kses_post( $total_rows['order_total']['value'] ),
			wp_kses_post( wc_price( $gift_card_partial_total, array( 'currency' => $order->get_currency() ) ) ),
			'square_cash_app_pay' === $order->get_payment_method() ? esc_html__( 'cash app pay', 'woocommerce-square' ) : esc_html__( 'credit card', 'woocommerce-square' ),
			wp_kses_post( wc_price( $other_gateway_partial_total, array( 'currency' => $order->get_currency() ) ) )
		);

		return $total_rows;
	}

	/**
	 * Renders a notice if the billing country is not set in manual order.
	 *
	 * @since 4.2.0
	 * @param \WC_Order $order
	 */
	public function render_admin_missing_billing_details_notice( $order ) {
		$created_via = $order->get_created_via();

		if ( $order->is_paid() ) {
			return;
		}

		if ( ! ( '' === $created_via || 'admin' === $created_via ) ) {
			return;
		}

		$billing_country = $order->get_billing_country();

		if ( ! empty( $billing_country ) ) {
			return;
		}

		?>
		<p style="color: #b32d2e; display: none;" class="form-field form-field-wide square-billing-details-info"><?php esc_html_e( 'Billing country is a mandatory field for Square payment gateway.', 'woocommerce-square' ); ?></p>
		<?php
	}

	/**
	 * Renders a notice if the billing country is not set in manual order.
	 *
	 * @since 4.2.0
	 *
	 * @param \WC_Order $order
	 */
	public function render_missing_billing_details_notice( $order ) {
		if ( is_user_logged_in() ) {
			return;
		}

		if ( ! empty( $order->get_billing_country() ) ) {
			return;
		}

		require_once WC()->plugin_path() . '/includes/admin/wc-meta-box-functions.php';

		?>
		<div style="display: none;" id="square-pay-for-order-billing-details-wrapper">
		<?php
			wc_print_notice( __( 'Billing country is not set which is required for payment using Square. Please update the billing country before continuing.', 'woocommerce-square' ), 'notice', array( 'error-scope' => 'square-billing-not-set' ) );
		?>

		<form action="" method="POST">
			<h3><?php esc_html_e( 'Update billing details:', 'woocommerce-square' ); ?></h2>
			<div id="order_data">
			<?php
			foreach ( WC()->countries->get_address_fields( '', 'billing_' ) as $key => $field ) {
				if ( 'billing_email' === $key ) {
					continue;
				}

				if ( is_callable( array( $order, 'get_' . $key ) ) ) {
					$current_value = $order->{"get_$key"}( 'edit' );
				} else {
					$current_value = $order->get_meta( '_' . $key );
				}

				woocommerce_form_field( $key, $field, $current_value );
			}

			wp_nonce_field( 'wc_verify_email', 'check_submission' );
			?>

					<input type="submit" name="wc-square-save-guest-details" value="<?php esc_html_e( 'Save details', 'woocommerce-square' ); ?>">
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Saves billing details of a guest user on the Pay for order page.
	 *
	 * @since 4.2.0
	 */
	public function save_guest_details() {
		if ( ! isset( $_POST['wc-square-save-guest-details'] ) ) {
			return;
		}

		$nonce = isset( $_POST['check_submission'] ) ? wc_clean( wp_unslash( $_POST['check_submission'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'wc_verify_email' ) ) {
			return;
		}

		$order_id = absint( get_query_var( 'order-pay' ) );
		$order    = wc_get_order( $order_id );
		$props    = array();

		foreach ( WC()->countries->get_address_fields( '', 'billing_' ) as $key => $field ) {
			if ( 'billing_email' === $key ) {
				continue;
			}

			if ( is_callable( array( $order, 'set_' . $key ) ) ) {
				$props[ $key ] = isset( $_POST[ $key ] ) ? wc_clean( wp_unslash( $_POST[ $key ] ) ) : '';
			} else {
				$order->update_meta_data( $key, wc_clean( wp_unslash( $_POST[ $key ] ) ) );
			}
		}

		$order->set_props( $props );
		$order->add_order_note(
			esc_html__( 'Customer has updated their billing details from the Pay for order page.', 'woocommerce-square' ),
			0,
			true
		);
		$order->save();
	}

	/**
	 * Disables email verification when billing details are updated by guest on the
	 * pay for order page.
	 *
	 * @since 4.2.0
	 *
	 * @param bool $email_verification_required If email verification is required.
	 *
	 * @return boolean
	 */
	public function no_verification_on_guest_save( $email_verification_required ) {
		// Nonce already verified before filter `woocommerce_order_email_verification_required`.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['wc-square-save-guest-details'] ) ) {
			return false;
		}

		return $email_verification_required;
	}
}
