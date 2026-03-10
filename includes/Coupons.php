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

namespace WooCommerce\Square;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Square\Utilities\Coupon_Utility;
use WooCommerce\Square\API;
use WooCommerce\Square\Handlers\Product;
use WooCommerce\Square\Utilities\Money_Utility;
use WooCommerce\Square\Framework\Square_Helper;

/**
 * Class Coupons
 *
 * Handles coupon (WooCommerce term) and discount code (Square term)
 * use during the checkout process.
 *
 * @package WooCommerce\Square
 */
class Coupons {

	/**
	 * The singleton instance.
	 *
	 * @var Coupons|null
	 */
	private static $instance = null;

	/**
	 * Stores the last Square discount validation error message for woocommerce_coupon_error filter.
	 * Used when we reject a Square coupon in woocommerce_coupon_is_valid (e.g. no discount applies).
	 *
	 * @var array|null Array with 'code' and 'message' keys, or null.
	 */
	private static $last_square_validation_error = null;

	/**
	 * Gets the singleton instance.
	 *
	 * @since 5.3.0
	 *
	 * @return Coupons The singleton instance.
	 */
	public static function instance() {

		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance::init();
		}

		return self::$instance;
	}

	/**
	 * Initialize Coupons class.
	 *
	 * @since 5.3.0
	 */
	public static function init() {
		if ( ! Coupon_Utility::is_square_discount_codes_enabled() ) {
			return;
		}

		// Detect Square coupon > calculate the discount from API > Store in the cart session for later use.
		add_action( 'woocommerce_applied_coupon', array( self::$instance, 'handle_coupon_applied' ), 10, 1 );

		// Clear the discount data from the cart session when the coupon is removed.
		add_action( 'woocommerce_removed_coupon', array( self::$instance, 'handle_coupon_removed' ), 10, 1 );

		// Store the discount code data in the order meta data.
		add_action( 'woocommerce_checkout_create_order', array( self::$instance, 'populate_order_square_discount_meta' ), 10, 1 );

		// Ensure Square discount meta is always set (backup in case create_order didn't run or metadata was missed).
		// This assurance is needed to avoid simple discounts instead of a redemption based dynamic discount.
		add_action( 'woocommerce_checkout_update_order_meta', array( self::$instance, 'ensure_order_square_discount_meta' ), 10, 1 );

		// Fetch Square discount full data (pricing rule, version, code) > Convert to a WooCommerce Coupon array.
		// Keeping discount amount and product_ids empty as we will override it with the Square's CalculateOrder response.
		add_filter( 'woocommerce_get_shop_coupon_data', array( self::$instance, 'filter_woocommerce_get_shop_coupon_data' ), 10, 3 );

		// Override the discount amount (which was zeroed out by default) with the Square calculated discount amount.
		add_filter( 'woocommerce_coupon_get_discount_amount', array( self::$instance, 'override_discount_amount_with_square' ), 10, 5 );

		// Validate Square coupon provides a discount BEFORE WooCommerce adds it (avoids success + error double message).
		add_filter( 'woocommerce_coupon_is_valid', array( self::$instance, 'validate_square_coupon_has_discount' ), 5, 2 );

		// Prevent non-Square coupons from being used with Square coupons.
		add_filter( 'woocommerce_coupon_is_valid', array( self::$instance, 'prevent_non_square_coupons_with_square_coupons' ), 10, 2 );

		// Show our custom error message when we reject a Square coupon in validate_square_coupon_has_discount.
		add_filter( 'woocommerce_coupon_error', array( self::$instance, 'filter_square_coupon_validation_error_message' ), 10, 3 );

		// Trigger recalculation before WooCommerce calculates totals (covers add, remove, quantity update).
		add_action( 'woocommerce_before_calculate_totals', array( self::$instance, 'handle_cart_contents_changed' ), 5 );
	}

	/**
	 * Handle coupon application - trigger Square discount calculation if conditions are met.
	 *
	 * @since 5.3.0
	 *
	 * @param string $coupon_code The coupon code that was applied.
	 */
	public static function handle_coupon_applied( $coupon_code ) {
		// Check if this is a Square discount code.
		$square_discount_code_id = Coupon_Utility::get_square_discount_code_id_by_code( $coupon_code );

		if ( empty( $square_discount_code_id ) ) {
			// Not a Square discount code, skip.
			return;
		}

		// Store discount code ID in cart session for later use.
		WC()->session->set( '_square_discount_code_id_' . $coupon_code, $square_discount_code_id );

		$cart = WC()->cart;

		if ( ! $cart || $cart->is_empty() ) {
			return;
		}

		// Skip API call if we already calculated in woocommerce_coupon_is_valid (avoids duplicate CalculateOrder).
		$existing_amount = WC()->session->get( '_square_discount_amount_' . $coupon_code );
		if ( null !== $existing_amount && (float) $existing_amount > 0 ) {
			return;
		}

		// Calculate now to validate the discount code and store the discount amount in the cart session.
		try {
			self::calculate_square_discount_from_cart( $coupon_code, $square_discount_code_id );
		} catch ( \Exception $e ) {
			Coupon_Utility::remove_coupon_from_cart( $coupon_code );
			// Clear our Square session data for this coupon in case removal didn't fire woocommerce_removed_coupon in this context.
			self::handle_coupon_removed( $coupon_code );
			/* translators: %1$s: coupon code, %2$s: error message */
			wc_add_notice( sprintf( __( 'Unable to apply coupon "%1$s": %2$s', 'woocommerce-square' ), esc_html( $coupon_code ), esc_html( $e->getMessage() ) ), 'error' );
		}
	}

	/**
	 * Prevent non-Square coupons from being used with Square coupons.
	 *
	 * @since 5.3.0
	 *
	 * @param bool       $is_valid Whether the coupon is valid.
	 * @param \WC_Coupon $coupon   Coupon object.
	 *
	 * @return bool|WP_Error True if valid, false or WP_Error if invalid.
	 */
	public static function prevent_non_square_coupons_with_square_coupons( $is_valid, $coupon ) {
		// If coupon is already invalid, don't override.
		if ( ! $is_valid ) {
			return $is_valid;
		}

		$cart = WC()->cart;
		if ( ! $cart ) {
			return $is_valid;
		}

		$coupon_code     = $coupon->get_code();
		$applied_coupons = $cart->get_applied_coupons();

		// Check if this is a Square discount code.
		$is_square_coupon = ! empty( Coupon_Utility::get_square_discount_code_id_by_code( $coupon_code ) );

		// Check if there are any applied coupons.
		if ( ! empty( $applied_coupons ) ) {
			$has_square_coupon = ! empty( self::get_applied_square_coupon_codes() );

			// Cannot mix Square and WooCommerce coupons: reject when adding one type while the other is already applied.
			// Multiple Square coupons (or multiple WC coupons) are allowed; only cross-type mixing is blocked.
			if ( ( $is_square_coupon && ! $has_square_coupon ) || ( ! $is_square_coupon && $has_square_coupon ) ) {
				$coupon->set_error_message(
					sprintf(
						/* translators: %s: coupon code */
						__( 'Sorry, the discount code "%s" cannot be used with another coupon. Please remove the other coupon and try again.', 'woocommerce-square' ),
						esc_html( $coupon_code )
					)
				);
				return false;
			}
		}

		return $is_valid;
	}

	/**
	 * Validate Square coupon provides a discount before WooCommerce applies it.
	 * Runs at priority 5 so we reject before the success message is shown.
	 *
	 * @since 5.3.0
	 *
	 * @param bool       $is_valid Whether the coupon is valid.
	 * @param \WC_Coupon $coupon   Coupon object.
	 *
	 * @return bool True if valid, false if Square coupon provides no discount.
	 */
	public static function validate_square_coupon_has_discount( $is_valid, $coupon ) {
		if ( ! $is_valid ) {
			return $is_valid;
		}

		$coupon_code             = $coupon->get_code();
		$square_discount_code_id = Coupon_Utility::get_square_discount_code_id_by_code( $coupon_code );
		if ( empty( $square_discount_code_id ) ) {
			return $is_valid;
		}

		$cart = WC()->cart;
		if ( ! $cart || $cart->is_empty() ) {
			return $is_valid;
		}

		try {
			self::calculate_square_discount_from_cart( $coupon_code, $square_discount_code_id );
		} catch ( \Exception $e ) {
			self::$last_square_validation_error = array(
				'code'    => $coupon_code,
				'message' => $e->getMessage(),
			);
			return false;
		}

		return $is_valid;
	}

	/**
	 * Replace the generic coupon error message with our Square-specific messages when we rejected.
	 * Handles: (1) Square discount validation (no discount, zero total), (2) mixing Square and WooCommerce coupons.
	 *
	 * @since 5.3.0
	 *
	 * @param string $error_message The error message.
	 * @param int    $error_code    The error code.
	 * @param \WC_Coupon|null $coupon The coupon object.
	 *
	 * @return string The error message.
	 */
	public static function filter_square_coupon_validation_error_message( $error_message, $error_code, $coupon ) {
		if ( ! $coupon ) {
			return $error_message;
		}

		// Use custom message from Square validation (e.g. no discount, zero total).
		if ( null !== self::$last_square_validation_error ) {
			$coupon_code = $coupon->get_code();
			if ( wc_is_same_coupon( $coupon_code, self::$last_square_validation_error['code'] ) ) {
				$message = self::$last_square_validation_error['message'];

				self::$last_square_validation_error = null;
				return $message;
			}
		}

		// Use custom message from prevent_non_square_coupons (e.g. cannot mix Square and WooCommerce coupons).
		if ( \WC_Coupon::E_WC_COUPON_INVALID_FILTERED === $error_code ) {
			$custom_message = $coupon->get_error_message();
			if ( ! empty( $custom_message ) ) {
				return $custom_message;
			}
		}

		return $error_message;
	}

	/**
	 * Preflight the construction of a WC_Coupon object.
	 *
	 * @since 5.3.0
	 *
	 * @param false|array $coupon      Coupon data. False indicates that the coupon has not yet
	 *                                 been found/replaced via the preflight process.
	 * @param mixed       $coupon_data Coupon data, object, ID or code as passed to the \WC_Coupon constructor.
	 * @param \WC_Coupon  $wc_coupon   The WC_Coupon instance being constructed
	 * @return false|array Modified coupon data.
	 */
	public static function filter_woocommerce_get_shop_coupon_data( $coupon, $coupon_data, \WC_Coupon $wc_coupon ) {
		// Coupon has already been found via preflight.
		if ( false !== $coupon ) {
			return $coupon;
		}

		// Only handle requests for string coupon codes.
		if ( ! is_string( $coupon_data ) ) {
			return $coupon;
		}

		// Use cached discount code lookup.
		$code_data = Coupon_Utility::get_discount_code( $coupon_data );
		if ( null === $code_data ) {
			return $coupon;
		}

		$wc_coupon = Coupon_Utility::map_square_discount_code_to_woocommerce_coupon( $code_data );
		if ( false === $wc_coupon ) {
			return $coupon;
		}

		return $wc_coupon;
	}

	/**
	 * Populate order with Square discount meta from cart session.
	 *
	 * @since 5.3.0
	 *
	 * @param \WC_Order $order The order object.
	 * @return bool True if meta was set, false otherwise.
	 */
	public static function populate_order_square_discount_meta( $order ) {
		if ( ! $order instanceof \WC_Order || ! function_exists( 'wc_square' ) ) {
			return false;
		}

		$square_discount_code_ids = array();

		// Prefer cart/session so we have amounts; works for shortcode checkout.
		$cart = WC()->cart;
		if ( $cart && WC()->session ) {
			$applied_coupons = $cart->get_applied_coupons();
			if ( ! empty( $applied_coupons ) ) {
				foreach ( $applied_coupons as $coupon_code ) {
					$square_discount_code_id = WC()->session->get( '_square_discount_code_id_' . $coupon_code );
					if ( empty( $square_discount_code_id ) ) {
						$square_discount_code_id = Coupon_Utility::get_square_discount_code_id_by_code( $coupon_code );
					}
					if ( empty( $square_discount_code_id ) ) {
						continue;
					}
					$square_discount_code_ids[] = $square_discount_code_id;
				}
			}
		}

		// Fallback: get Square discount code IDs from order coupon items (e.g. block/Store API checkout where session may be empty).
		if ( empty( $square_discount_code_ids ) ) {
			$coupon_items = $order->get_coupons();
			if ( ! empty( $coupon_items ) ) {
				foreach ( $coupon_items as $coupon_item ) {
					$coupon_code = $coupon_item->get_code();
					if ( empty( $coupon_code ) ) {
						continue;
					}
					$square_discount_code_id = Coupon_Utility::get_square_discount_code_id_by_code( $coupon_code );
					if ( empty( $square_discount_code_id ) ) {
						continue;
					}
					$square_discount_code_ids[] = $square_discount_code_id;
				}
			}
		}

		if ( empty( $square_discount_code_ids ) ) {
			return false;
		}

		$order->update_meta_data( '_square_discount_code_ids', $square_discount_code_ids );

		return true;
	}

	/**
	 * Ensure order has Square discount code meta (backup for edge cases).
	 * Runs on woocommerce_checkout_update_order_meta so metadata is always available.
	 *
	 * @since 5.3.0
	 *
	 * @param int $order_id The order ID.
	 */
	public static function ensure_order_square_discount_meta( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$existing_ids = self::get_order_square_discount_code_ids( $order );
		if ( ! empty( $existing_ids ) ) {
			return;
		}

		if ( self::populate_order_square_discount_meta( $order ) ) {
			$order->save_meta_data();
		}
	}

	/**
	 * Last-chance: ensure order has Square discount code IDs from order coupon items before payment.
	 * Call this from the payment gateway when meta may be missing (e.g. block checkout). If the order
	 * has coupon items and they are Square codes, populates _square_discount_code_ids and saves.
	 *
	 * @since 5.3.0
	 *
	 * @param \WC_Order $order The order object.
	 */
	public static function ensure_order_square_discount_code_ids_before_payment( $order ) {
		if ( ! $order instanceof \WC_Order || ! function_exists( 'wc_square' ) ) {
			return;
		}
		if ( ! empty( self::get_order_square_discount_code_ids( $order ) ) ) {
			return;
		}
		$coupon_items = $order->get_coupons();
		if ( empty( $coupon_items ) ) {
			return;
		}
		$square_discount_code_ids = array();
		foreach ( $coupon_items as $coupon_item ) {
			$coupon_code = $coupon_item->get_code();
			if ( empty( $coupon_code ) ) {
				continue;
			}
			$square_discount_code_id = Coupon_Utility::get_square_discount_code_id_by_code( $coupon_code );
			if ( empty( $square_discount_code_id ) ) {
				continue;
			}
			$square_discount_code_ids[] = $square_discount_code_id;
		}
		if ( empty( $square_discount_code_ids ) ) {
			return;
		}
		$order->update_meta_data( '_square_discount_code_ids', $square_discount_code_ids );
		$order->save_meta_data();
	}

	/**
	 * Get all Square discount code IDs stored on an order (for creating redemptions).
	 *
	 * @since 5.3.0
	 *
	 * @param \WC_Order $order The order object.
	 * @return string[] Array of Square discount code IDs (empty if none).
	 */
	public static function get_order_square_discount_code_ids( $order ) {
		if ( ! $order instanceof \WC_Order ) {
			return array();
		}
		$ids = $order->get_meta( '_square_discount_code_ids' );
		if ( is_array( $ids ) && ! empty( $ids ) ) {
			return array_values( array_filter( $ids, 'is_string' ) );
		}
		return array();
	}

	/**
	 * Handle coupon removal - clear Square discount session data.
	 *
	 * @since 5.3.0
	 *
	 * @param string $coupon_code The coupon code that was removed.
	 */
	public static function handle_coupon_removed( $coupon_code ) {
		// Clear all Square-related session data for this coupon.
		WC()->session->__unset( '_square_discount_code_id_' . $coupon_code );
		WC()->session->__unset( '_square_discount_amount_' . $coupon_code );
		WC()->session->__unset( '_square_discount_per_item_' . $coupon_code );

		// Clear cached discount code data.
		Coupon_Utility::clear_cache_discount_code( $coupon_code );
	}

	/**
	 * Get applied coupon codes that are Square discount codes.
	 *
	 * @since 5.3.0
	 *
	 * @return string[] Array of applied Square coupon codes.
	 */
	private static function get_applied_square_coupon_codes() {
		$cart = WC()->cart;
		if ( ! $cart || $cart->is_empty() ) {
			return array();
		}

		$square_codes = array();
		foreach ( $cart->get_applied_coupons() as $applied_code ) {
			if ( ! empty( Coupon_Utility::get_square_discount_code_id_by_code( $applied_code ) ) ) {
				$square_codes[] = $applied_code;
			}
		}
		return $square_codes;
	}

	/**
	 * Check if the cart has any Square discount code applied.
	 *
	 * Square discount codes can only be redeemed when paying with Square.
	 * Used to restrict available payment gateways to Square when a Square coupon is applied.
	 *
	 * @since 5.3.0
	 *
	 * @return bool True if cart has an applied Square discount code.
	 */
	public static function cart_has_square_coupon() {
		if ( ! Coupon_Utility::is_square_discount_codes_enabled() ) {
			return false;
		}

		return ! empty( self::get_applied_square_coupon_codes() );
	}

	/**
	 * Calculate Square discount from cart using CalculateOrder API.
	 *
	 * @since 5.3.0
	 *
	 * @param string $coupon_code The coupon code to calculate discount for.
	 * @param string $square_discount_code_id The Square discount code ID to calculate discount for.
	 * @return float The calculated discount amount.
	 * @throws \Exception If calculation fails.
	 */
	private static function calculate_square_discount_from_cart( $coupon_code, $square_discount_code_id ) {
		$cart = WC()->cart;

		if ( ! $cart || $cart->is_empty() ) {
			throw new \Exception( 'Cart is empty.' );
		}

		// Build array of ALL Square discount codes that should apply (existing applied + current coupon if not yet applied).
		// When validating, the new coupon isn't in applied_coupons yet, so we add it explicitly.
		$proposed_discount_codes = array();
		$applied_coupons         = $cart->get_applied_coupons();
		foreach ( $applied_coupons as $applied_code ) {
			$id = Coupon_Utility::get_square_discount_code_id_by_code( $applied_code );
			if ( ! empty( $id ) ) {
				$proposed_discount_codes[] = $id;
			}
		}

		// Add current coupon's ID if not already in list (e.g. validation before it's added to cart).
		$current_in_proposed = in_array( $square_discount_code_id, $proposed_discount_codes, true );
		if ( ! $current_in_proposed ) {
			$proposed_discount_codes[] = $square_discount_code_id;
		}

		// Get location ID.
		$settings_handler = wc_square()->get_settings_handler();
		if ( ! $settings_handler ) {
			throw new \Exception( 'Square settings handler is not available.' );
		}

		$location_id = $settings_handler->get_location_id();
		if ( empty( $location_id ) ) {
			throw new \Exception( 'Square location ID is not configured.' );
		}

		// Build Square Order from cart.
		$square_order = self::build_square_order_from_cart( $location_id );
		if ( ! $square_order ) {
			throw new \Exception( 'Failed to build Square order from cart.' );
		}

		// Get API instance.
		$api = wc_square()->get_gateway()->get_api();
		if ( ! $api ) {
			throw new \Exception( 'Square API is not available.' );
		}

		// Call CalculateOrder with ALL discount codes (existing + new) so we get the combined total.
		// Note: We pass null for WC_Order since we're calculating from cart, not an existing order.
		$calculate_result      = $api->calculate_order( null, $square_order, $proposed_discount_codes, true );
		$calculated_order      = $calculate_result['order'];
		$calculated_order_data = $calculate_result['raw_response'];

		// Extract per-line-item discounts from Square's response.
		$line_item_discounts   = array();
		$total_discount_amount = 0;

		if ( isset( $calculated_order_data['line_items'] ) && is_array( $calculated_order_data['line_items'] ) ) {
			$cart_items_by_key = Coupon_Utility::get_cart_items_by_key( $cart );

			// Sum combined discount from each line and map to cart item keys (for fallback and totals).
			foreach ( $calculated_order_data['line_items'] as $square_line_item ) {
				$line_item_discount_cents = isset( $square_line_item['total_discount_money']['amount'] ) ? (int) $square_line_item['total_discount_money']['amount'] : 0;
				if ( $line_item_discount_cents <= 0 ) {
					continue;
				}

				$line_item_discount     = Money_Utility::cents_to_float( $line_item_discount_cents );
				$total_discount_amount += $line_item_discount;

				// Map this Square line item to a WooCommerce cart key so we can store per-item discount
				// for display and for override_discount_amount_with_square (cart key => amount).
				$matched_key = Coupon_Utility::match_square_line_to_cart_key( $square_line_item, $cart_items_by_key );
				if ( $matched_key ) {
					$line_item_discounts[ $matched_key ] = ( $line_item_discounts[ $matched_key ] ?? 0 ) + $line_item_discount;
				}
			}
		}

		// If we couldn't extract per-item discounts, fall back to total discount calculation.
		if ( empty( $line_item_discounts ) ) {
			// Order sent to Square has no shipping (merchandise + fees only). Compare like-for-like.
			$cart_subtotal  = $cart->get_subtotal();
			$cart_fee_total = 0;
			foreach ( $cart->get_fees() as $fee ) {
				$cart_fee_total += (float) $fee->amount;
			}
			$cart_tax_total = $cart->get_total_tax();

			// Total before discount (exclude shipping so it matches the order we sent to Square).
			$total_before_discount = $cart_subtotal + $cart_fee_total + $cart_tax_total;

			// Get calculated total from Square (in cents).
			$total_after_discount_cents = $calculated_order->getTotalMoney() ? $calculated_order->getTotalMoney()->getAmount() : 0;
			$total_after_discount       = Money_Utility::cents_to_float( $total_after_discount_cents );

			// Discount amount is the difference.
			$total_discount_amount = $total_before_discount - $total_after_discount;

			// Ensure discount is not negative.
			if ( $total_discount_amount < 0 ) {
				$total_discount_amount = 0;
			}
		}

		// Reject coupon when it provides no discount (e.g. product-specific with no matching items, free delivery only).
		if ( $total_discount_amount <= 0 ) {
			/* translators: error message when discount code applies but gives no discount */
			throw new \Exception( esc_html__( 'This discount code does not apply to your current cart. It may be for specific products or free delivery only—add qualifying items or check the code requirements.', 'woocommerce-square' ) );
		}

		// Reject coupon if it would make the order total zero (WooCommerce-Square cannot process zero-amount transactions).
		$order_total_cents = $calculated_order->getTotalMoney() ? $calculated_order->getTotalMoney()->getAmount() : 0;
		if ( $order_total_cents <= 0 ) {
			/* translators: error message when discount would make order total zero */
			throw new \Exception( esc_html__( 'This discount code cannot be used because it would make your order total zero. Square cannot process zero-amount transactions.', 'woocommerce-square' ) );
		}

		// Build list of coupon codes in this calculation (to avoid double-counting when multiple coupons applied).
		$coupon_codes_in_calc = array();
		foreach ( $applied_coupons as $applied_code ) {
			$id = Coupon_Utility::get_square_discount_code_id_by_code( $applied_code );
			if ( ! empty( $id ) && in_array( $id, $proposed_discount_codes, true ) ) {
				$coupon_codes_in_calc[] = $applied_code;
			}
		}
		if ( ! $current_in_proposed ) {
			$coupon_codes_in_calc[] = $coupon_code;
		}

		self::store_square_discount_amounts_per_coupon( $calculated_order_data, $total_discount_amount, $line_item_discounts, $coupon_codes_in_calc, $cart );

		// Remove coupons that received no discount (e.g. product-specific with no qualifying item in cart).
		$applied_coupons = $cart->get_applied_coupons();
		foreach ( $coupon_codes_in_calc as $code ) {
			$stored_amount = WC()->session->get( '_square_discount_amount_' . $code );
			if ( null === $stored_amount || (float) $stored_amount <= 0 ) {
				if ( wc_is_same_coupon( $code, $coupon_code ) ) {
					self::handle_coupon_removed( $code );
					/* translators: error when discount code applies to nothing in cart */
					throw new \Exception( esc_html__( 'This discount code does not apply to your current cart. It may be for specific products or free delivery only—add qualifying items or check the code requirements.', 'woocommerce-square' ) );
				}
				if ( in_array( $code, $applied_coupons, true ) ) {
					Coupon_Utility::remove_coupon_from_cart( $code );
					/* translators: notice when a coupon was removed because it no longer applies */
					wc_add_notice( sprintf( esc_html__( 'The coupon "%s" was removed because it does not apply to your current cart.', 'woocommerce-square' ), $code ), 'notice' );
					self::handle_coupon_removed( $code );
				}
			}
		}

		return $total_discount_amount;
	}

	/**
	 * Stores each coupon's discount amount and per-item breakdown in session for cart/checkout display.
	 *
	 * Uses Square's order.discounts[] when available so each coupon shows its real amount (e.g. $5 off
	 * vs 10% off). Coupons with no matching order.discount (e.g. product-specific with no qualifying item) stay at 0.
	 * When Square returns no order.discounts and there is exactly one coupon, that coupon gets the full total.
	 *
	 * @since 5.3.0
	 *
	 * @param array    $calculated_order_data  Raw CalculateOrder response (order.discounts, line_items).
	 * @param float    $total_discount_amount  Combined discount total (from line items or order total).
	 * @param array    $line_item_discounts    Cart item key => combined discount amount (for fallback).
	 * @param string[] $coupon_codes_in_calc   WooCommerce coupon codes included in this calculation.
	 * @param \WC_Cart $cart                   WooCommerce cart (for mapping Square lines to cart keys).
	 */
	private static function store_square_discount_amounts_per_coupon( $calculated_order_data, $total_discount_amount, $line_item_discounts, $coupon_codes_in_calc, $cart ) {
		$coupon_count    = count( $coupon_codes_in_calc );
		$order_discounts = isset( $calculated_order_data['discounts'] ) && is_array( $calculated_order_data['discounts'] ) ? $calculated_order_data['discounts'] : array();

		// Map Square's catalog_object_id (from order.discounts) to our coupon code so we don't rely on array order.
		$catalog_id_to_coupon = array();
		foreach ( $coupon_codes_in_calc as $code ) {
			$code_data = Coupon_Utility::get_discount_code( $code );
			if ( ! empty( $code_data['pricing_rule_id'] ) ) {
				$pricing_rule_version = isset( $code_data['pricing_rule_version'] ) ? (int) $code_data['pricing_rule_version'] : ( isset( $code_data['version'] ) ? (int) $code_data['version'] : 0 );
				$discount_catalog_id  = Coupon_Utility::get_discount_catalog_id_for_pricing_rule( $code_data['pricing_rule_id'], $pricing_rule_version );
				if ( ! empty( $discount_catalog_id ) ) {
					$catalog_id_to_coupon[ $discount_catalog_id ] = $code;
				}
			}
		}

		$cart_items_by_key   = Coupon_Utility::get_cart_items_by_key( $cart );
		$per_coupon_amount   = array();
		$per_coupon_per_item = array();
		foreach ( $coupon_codes_in_calc as $code ) {
			$per_coupon_amount[ $code ]   = 0;
			$per_coupon_per_item[ $code ] = array();
		}

		if ( empty( $order_discounts ) && 1 === $coupon_count ) {
			$only_code                         = $coupon_codes_in_calc[0];
			$per_coupon_amount[ $only_code ]   = $total_discount_amount;
			$per_coupon_per_item[ $only_code ] = $line_item_discounts;
		}

		foreach ( $order_discounts as $idx => $order_discount ) {
			$discount_uid = isset( $order_discount['uid'] ) ? $order_discount['uid'] : '';
			$catalog_id   = isset( $order_discount['catalog_object_id'] ) ? $order_discount['catalog_object_id'] : '';
			$code         = null;
			if ( ! empty( $catalog_id ) && isset( $catalog_id_to_coupon[ $catalog_id ] ) ) {
				$code = $catalog_id_to_coupon[ $catalog_id ];
			}
			if ( ! $code && $idx < $coupon_count ) {
				$code = $coupon_codes_in_calc[ $idx ];
			}
			if ( ! $code ) {
				continue;
			}

			if ( isset( $order_discount['applied_money']['amount'] ) ) {
				$per_coupon_amount[ $code ] = Money_Utility::cents_to_float( (int) $order_discount['applied_money']['amount'] );
			}

			if ( $discount_uid && isset( $calculated_order_data['line_items'] ) && is_array( $calculated_order_data['line_items'] ) ) {
				foreach ( $calculated_order_data['line_items'] as $square_line_item ) {
					$applied_list = isset( $square_line_item['applied_discounts'] ) && is_array( $square_line_item['applied_discounts'] ) ? $square_line_item['applied_discounts'] : array();
					foreach ( $applied_list as $applied_discount ) {
						if ( ( $applied_discount['discount_uid'] ?? '' ) !== $discount_uid || ! isset( $applied_discount['applied_money']['amount'] ) ) {
							continue;
						}
						$line_amount = Money_Utility::cents_to_float( (int) $applied_discount['applied_money']['amount'] );
						$cart_key    = Coupon_Utility::match_square_line_to_cart_key( $square_line_item, $cart_items_by_key );
						if ( $cart_key ) {
							$per_coupon_per_item[ $code ][ $cart_key ] = ( $per_coupon_per_item[ $code ][ $cart_key ] ?? 0 ) + $line_amount;
						}
						break;
					}
				}
			}
		}

		// When prices are inclusive of tax, WooCommerce expects discount amounts to be inclusive.
		// Square returns fixed discounts without tax; scale using the cart's own subtotal/subtotal_tax (core-calculated, compound-aware).
		$inclusive_multiplier = 1.0;
		if ( wc_prices_include_tax() && wc_tax_enabled() ) {
			$cart_subtotal     = (float) $cart->get_subtotal();
			$cart_subtotal_tax = (float) $cart->get_subtotal_tax();
			if ( $cart_subtotal > 0 ) {
				$inclusive_multiplier = ( $cart_subtotal + $cart_subtotal_tax ) / $cart_subtotal;
			}
		}

		foreach ( $coupon_codes_in_calc as $code ) {
			WC()->session->set( '_square_discount_code_id_' . $code, Coupon_Utility::get_square_discount_code_id_by_code( $code ) );

			$amount   = $per_coupon_amount[ $code ] ?? 0;
			$per_item = $per_coupon_per_item[ $code ] ?? array();

			// Square returns discounts in ex-tax terms (we send ex-tax base). When the store uses "prices include tax",
			// WooCommerce expects coupon amounts to be inclusive. Scale all coupon types by the cart's incl/ex ratio.
			if ( 1.0 !== $inclusive_multiplier ) {
				$amount = (float) $amount * $inclusive_multiplier;
				foreach ( $per_item as $key => $val ) {
					$per_item[ $key ] = (float) $val * $inclusive_multiplier;
				}
			}

			WC()->session->set( '_square_discount_amount_' . $code, $amount );
			WC()->session->set( '_square_discount_per_item_' . $code, $per_item );
		}
	}

	/**
	 * Build Square line-item tax rate objects from the cart (for use in Square Order).
	 * Uses cart items and WC_Tax so tax definitions exist even when cart totals are not yet calculated.
	 *
	 * @since 5.3.0
	 *
	 * @param \WC_Cart $cart     WooCommerce cart.
	 * @param string   $tax_type One of API::TAX_TYPE_INCLUSIVE or API::TAX_TYPE_ADDITIVE.
	 * @return array<int, \Square\Models\OrderLineItemTax> Map of WC rate_id => OrderLineItemTax.
	 */
	private static function build_square_tax_rates_from_cart( $cart, $tax_type ) {
		$tax_rates     = array();
		$customer      = $cart->get_customer();
		$seen_rate_ids = array();
		foreach ( $cart->get_cart() as $cart_item ) {
			$product = $cart_item['data'];
			if ( ! $product || ! $product->is_taxable() ) {
				continue;
			}
			$item_rates = \WC_Tax::get_rates( $product->get_tax_class(), $customer );
			foreach ( $item_rates as $rate_id => $rate_data ) {
				if ( isset( $seen_rate_ids[ $rate_id ] ) ) {
					continue;
				}
				$seen_rate_ids[ $rate_id ] = true;
				$tax_rate_row              = \WC_Tax::_get_tax_rate( $rate_id );
				$rate_percentage           = ( $tax_rate_row && isset( $tax_rate_row['tax_rate'] ) ) ? (float) $tax_rate_row['tax_rate'] : 0;
				$label                     = ( $tax_rate_row && isset( $tax_rate_row['tax_rate_name'] ) ) ? $tax_rate_row['tax_rate_name'] : __( 'Tax', 'woocommerce-square' );
				$tax_item                  = new \Square\Models\OrderLineItemTax();
				$tax_item->setUid( uniqid() );
				$tax_item->setName( $label );
				$tax_item->setType( $tax_type );
				$tax_item->setScope( 'LINE_ITEM' );
				$tax_item->setPercentage( Square_Helper::number_format( $rate_percentage ) );
				$tax_rates[ $rate_id ] = $tax_item;
			}
		}
		return $tax_rates;
	}

	/**
	 * Build Square Order object from cart data (for calculation only, no order creation).
	 *
	 * @since 5.3.0
	 *
	 * @param string $location_id Square location ID.
	 * @return \Square\Models\Order Square Order object ready for CalculateOrder.
	 */
	private static function build_square_order_from_cart( $location_id ) {
		$cart = WC()->cart;
		if ( ! $cart || $cart->is_empty() ) {
			return null;
		}

		$currency    = get_woocommerce_currency();
		$order_model = new \Square\Models\Order( $location_id );

		// Build line items from cart. Always use ADDITIVE tax and ex-tax base so Square applies discount to ex-tax amounts; we scale for WC display via inclusive_multiplier.
		$line_items = array();
		$tax_type   = API::TAX_TYPE_ADDITIVE;

		// Build tax rates for the order (from cart items so we have definitions even when cart totals aren't ready).
		$tax_rates = wc_tax_enabled() ? self::build_square_tax_rates_from_cart( $cart, $tax_type ) : array();

		// Convert cart items to Square line items (base price = ex-tax only).
		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
			$product  = $cart_item['data'];
			$quantity = (float) $cart_item['quantity'];

			$line_item = new \Square\Models\OrderLineItem( (string) $quantity );

			// Check if gift card.
			if ( Product::is_gift_card( $product ) ) {
				$line_item->setItemType( 'GIFT_CARD' );
			}

			// Use cart line subtotal (ex tax) when available (core-calculated); else compute ex-tax from product price.
			$line_subtotal       = null;
			$line_tax_data_total = array();
			if ( isset( $cart_item['line_subtotal'] ) && is_numeric( $cart_item['line_subtotal'] ) ) {
				$line_subtotal = (float) $cart_item['line_subtotal'];
				if ( isset( $cart_item['line_tax_data']['subtotal'] ) && is_array( $cart_item['line_tax_data']['subtotal'] ) ) {
					foreach ( $cart_item['line_tax_data']['subtotal'] as $rate_id => $tax_amount ) {
						$line_tax_data_total[ $rate_id ] = (float) $tax_amount;
					}
				}
			}

			if ( null === $line_subtotal ) {
				$price_total   = (float) $product->get_price() * $quantity;
				$line_subtotal = $price_total;
				if ( wc_tax_enabled() && $product->is_taxable() ) {
					$customer           = $cart->get_customer();
					$is_vat_exempt      = $customer && $customer->get_is_vat_exempt();
					$item_tax_rates     = \WC_Tax::get_rates( $product->get_tax_class(), $customer );
					$price_includes_tax = wc_prices_include_tax();
					if ( ! $is_vat_exempt && ! empty( $item_tax_rates ) ) {
						$subtotal_taxes = \WC_Tax::calc_tax( $price_total, $item_tax_rates, $price_includes_tax );
						foreach ( $subtotal_taxes as $rate_id => $tax_amount ) {
							$line_tax_data_total[ $rate_id ] = wc_round_tax_total( $tax_amount );
						}
						if ( $price_includes_tax ) {
							$line_subtotal = $price_total - array_sum( $line_tax_data_total );
						}
					}
				}
			}

			// Base price per unit: always ex-tax (ADDITIVE).
			$subtotal_per_unit = $quantity > 0 ? $line_subtotal / $quantity : 0;

			$line_item->setQuantity( (string) $quantity );
			$line_item->setBasePriceMoney( Money_Utility::amount_to_money( $subtotal_per_unit, $currency ) );

			// Set catalog object ID if available.
			$square_variation_id = $product->get_meta( Product::SQUARE_VARIATION_ID_META_KEY );
			if ( ! empty( $square_variation_id ) ) {
				$line_item->setCatalogObjectId( $square_variation_id );
			} else {
				$line_item->setName( $product->get_name() );
			}

			// Apply taxes (using computed rates for this product).
			$applied_taxes = array();
			if ( ! empty( $line_tax_data_total ) ) {
				foreach ( $line_tax_data_total as $rate_id => $tax_amount ) {
					if ( (float) $tax_amount > 0 && isset( $tax_rates[ $rate_id ] ) ) {
						$applied_taxes[] = new \Square\Models\OrderLineItemAppliedTax( $tax_rates[ $rate_id ]->getUid() );
					}
				}
			}

			if ( ! empty( $applied_taxes ) ) {
				$line_item->setAppliedTaxes( $applied_taxes );
			}

			// Note: We do NOT add WooCommerce discounts here - Square will calculate via discount codes.
			$line_items[] = $line_item;
		}

		// Add fees as line items.
		foreach ( $cart->get_fees() as $fee_key => $fee ) {
			$fee_amount = (float) $fee->amount;
			if ( 0 !== $fee_amount ) {
				$fee_line_item = new \Square\Models\OrderLineItem( '1' );
				$fee_line_item->setName( $fee->name );
				$fee_line_item->setBasePriceMoney( Money_Utility::amount_to_money( $fee_amount, $currency ) );
				$line_items[] = $fee_line_item;
			}
		}

		$order_model->setLineItems( $line_items );

		// Set taxes.
		if ( ! empty( $tax_rates ) ) {
			$order_model->setTaxes( array_values( $tax_rates ) );
		}

		// Add shipping as service charges (same as CreateOrder when Square coupon is used).
		$service_charges = self::get_cart_service_charges_for_shipping( $cart, $tax_rates, $currency );
		if ( ! empty( $service_charges ) ) {
			$order_model->setServiceCharges( $service_charges );
		}

		return $order_model;
	}

	/**
	 * Build Square order service charges for cart shipping (mirrors Orders::get_order_service_charges_for_shipping).
	 * Shipping is sent as service charges, not line items, so CalculateOrder matches CreateOrder.
	 *
	 * @since 5.3.0
	 *
	 * @param \WC_Cart                        $cart       Cart object.
	 * @param \Square\Models\OrderLineItemTax[] $tax_rates Tax objects keyed by rate_id.
	 * @param string                          $currency   Currency code.
	 * @return \Square\Models\OrderServiceCharge[]
	 */
	private static function get_cart_service_charges_for_shipping( $cart, array $tax_rates, $currency ) {
		$service_charges = array();

		if ( ! $cart->needs_shipping() ) {
			return $service_charges;
		}

		$chosen_shipping_methods = WC()->session ? WC()->session->get( 'chosen_shipping_methods', array() ) : array();
		$packages                = WC()->shipping() ? WC()->shipping()->get_packages() : array();

		if ( ! empty( $packages ) && ! empty( $chosen_shipping_methods ) ) {
			foreach ( $packages as $package_key => $package ) {
				$chosen_id = isset( $chosen_shipping_methods[ $package_key ] ) ? $chosen_shipping_methods[ $package_key ] : null;
				if ( null === $chosen_id || empty( $package['rates'][ $chosen_id ] ) ) {
					continue;
				}

				$rate = $package['rates'][ $chosen_id ];
				if ( ! $rate instanceof \WC_Shipping_Rate ) {
					continue;
				}

				$total = (float) $rate->get_cost();
				if ( $total <= 0 ) {
					continue;
				}

				$charge = new \Square\Models\OrderServiceCharge();
				$charge->setUid( wc_square()->get_idempotency_key( 'shipping-cart-' . $package_key . '-' . $rate->get_id(), false ) );
				$charge->setName( $rate->get_label() ? $rate->get_label() : __( 'Shipping', 'woocommerce-square' ) );
				$charge->setAmountMoney( Money_Utility::amount_to_money( $total, $currency ) );
				$charge->setCalculationPhase( 'SUBTOTAL_PHASE' );

				$rate_taxes = $rate->get_taxes();
				$total_tax  = is_array( $rate_taxes ) ? array_sum( array_map( 'floatval', $rate_taxes ) ) : 0;
				$charge->setTaxable( $total_tax > 0 );

				if ( $total_tax > 0 && ! empty( $tax_rates ) ) {
					$applied_taxes = array();
					if ( is_array( $rate_taxes ) ) {
						foreach ( array_keys( $rate_taxes ) as $tax_id ) {
							if ( empty( $tax_id ) ) {
								continue;
							}
							$tax_obj = isset( $tax_rates[ $tax_id ] ) ? $tax_rates[ $tax_id ] : ( isset( $tax_rates[ (string) $tax_id ] ) ? $tax_rates[ (string) $tax_id ] : ( isset( $tax_rates[ (int) $tax_id ] ) ? $tax_rates[ (int) $tax_id ] : null ) );
							if ( $tax_obj instanceof \Square\Models\OrderLineItemTax ) {
								$applied_taxes[] = new \Square\Models\OrderLineItemAppliedTax( $tax_obj->getUid() );
							}
						}
					}
					if ( empty( $applied_taxes ) && $total_tax > 0 ) {
						$first_tax = reset( $tax_rates );
						if ( $first_tax instanceof \Square\Models\OrderLineItemTax ) {
							$applied_taxes[] = new \Square\Models\OrderLineItemAppliedTax( $first_tax->getUid() );
						}
					}
					if ( ! empty( $applied_taxes ) ) {
						$charge->setAppliedTaxes( $applied_taxes );
					}
				}

				$service_charges[] = $charge;
			}
		} else {
			// Fallback: use cart shipping total when packages/chosen methods not available (e.g. before shipping selected).
			$shipping_total = (float) $cart->get_shipping_total();
			$shipping_tax   = (float) $cart->get_shipping_tax();
			if ( $shipping_total > 0 ) {
				$charge = new \Square\Models\OrderServiceCharge();
				$charge->setUid( wc_square()->get_idempotency_key( 'shipping-cart-total', false ) );
				$charge->setName( __( 'Shipping', 'woocommerce-square' ) );
				$charge->setAmountMoney( Money_Utility::amount_to_money( $shipping_total, $currency ) );
				$charge->setCalculationPhase( 'SUBTOTAL_PHASE' );
				$charge->setTaxable( $shipping_tax > 0 );
				if ( $shipping_tax > 0 && ! empty( $tax_rates ) ) {
					$first_tax = reset( $tax_rates );
					if ( $first_tax instanceof \Square\Models\OrderLineItemTax ) {
						$charge->setAppliedTaxes( array( new \Square\Models\OrderLineItemAppliedTax( $first_tax->getUid() ) ) );
					}
				}
				$service_charges[] = $charge;
			}
		}

		return $service_charges;
	}

	/**
	 * Override WooCommerce discount calculation with Square's calculated amount.
	 *
	 * @since 5.3.0
	 *
	 * @param float      $discount      Discount amount.
	 * @param float      $discounting_amount Amount the coupon is being applied to.
	 * @param array|null $cart_item     Cart item being discounted.
	 * @param bool       $single        True if discounting a single qty item.
	 * @param \WC_Coupon $coupon        Coupon object.
	 * @return float Discount amount.
	 */
	public static function override_discount_amount_with_square( $discount, $discounting_amount, $cart_item, $single, $coupon ) {
		$coupon_code = $coupon->get_code();

		// Verify the coupon is actually applied to the cart before using session data.
		if ( ! Coupon_Utility::is_coupon_in_applied_cart( $coupon_code ) ) {
			return $discount;
		}

		// Check if we have Square discount data stored for this coupon.
		$square_discount_per_item = WC()->session->get( '_square_discount_per_item_' . $coupon_code );

		if ( null !== $square_discount_per_item && is_array( $square_discount_per_item ) ) {
			// This is a Square discount code - use Square's calculated per-item amounts.
			if ( $cart_item && is_array( $cart_item ) && isset( $cart_item['key'] ) ) {
				$cart_item_key = $cart_item['key'];
				if ( isset( $square_discount_per_item[ $cart_item_key ] ) && $square_discount_per_item[ $cart_item_key ] > 0 ) {
					$item_line_discount = (float) $square_discount_per_item[ $cart_item_key ];
					$quantity           = isset( $cart_item['quantity'] ) ? (float) $cart_item['quantity'] : 1;
					return $single ? ( $quantity > 0 ? $item_line_discount / $quantity : 0 ) : $item_line_discount;
				}
			}

			return 0;
		}

		// Fallback: if per-item discounts not available, check for total discount and distribute proportionally.
		$square_discount_amount = WC()->session->get( '_square_discount_amount_' . $coupon_code );
		if ( null !== $square_discount_amount && $square_discount_amount > 0 ) {
			$cart = WC()->cart;
			if ( $cart && $cart_item && is_array( $cart_item ) ) {
				$cart_subtotal = $cart->get_subtotal();
				if ( $cart_subtotal > 0 ) {
					$item_subtotal      = isset( $cart_item['line_subtotal'] ) ? (float) $cart_item['line_subtotal'] : 0;
					$proportion         = $item_subtotal / $cart_subtotal;
					$item_line_discount = $square_discount_amount * $proportion;
					$quantity           = isset( $cart_item['quantity'] ) ? (float) $cart_item['quantity'] : 1;
					return $single ? ( $quantity > 0 ? $item_line_discount / $quantity : 0 ) : $item_line_discount;
				}
			}
		}

		return $discount;
	}

	/**
	 * Handle cart contents changed - recalculate Square discounts if applied.
	 * Uses request coalescing: in a single request, we only call the Square API once per distinct cart state
	 * (e.g. when woocommerce_before_calculate_totals fires multiple times with the same cart, we skip redundant calls).
	 *
	 * @since 5.3.0
	 */
	public static function handle_cart_contents_changed() {
		// Prevent infinite loops - check if we're already recalculating.
		static $recalculating = false;
		if ( $recalculating ) {
			return;
		}

		$cart = WC()->cart;
		if ( ! $cart || $cart->is_empty() ) {
			return;
		}

		$applied_coupons = $cart->get_applied_coupons();
		if ( empty( $applied_coupons ) ) {
			return;
		}

		// Coalescing: skip recalc if we already ran it this request for the same cart state.
		static $last_recalc_cart_hash = null;
		$coupons_sorted               = $applied_coupons;
		sort( $coupons_sorted );
		$cart_state_parts = array( implode( ',', $coupons_sorted ) );
		foreach ( $cart->get_cart() as $key => $item ) {
			$cart_state_parts[] = $key . ':' . ( isset( $item['quantity'] ) ? $item['quantity'] : 0 );
		}
		$cart_hash = md5( implode( '|', $cart_state_parts ) );
		if ( $last_recalc_cart_hash === $cart_hash ) {
			return;
		}

		// Check if any Square coupons are still applied and recalculate.
		$square_coupons_to_remove = array();
		foreach ( $applied_coupons as $coupon_code ) {
			$square_discount_code_id = Coupon_Utility::get_square_discount_code_id_by_code( $coupon_code );

			if ( ! empty( $square_discount_code_id ) ) {
				// This is a Square coupon - recalculate the discount for the new cart contents.
				$recalculating = true;

				try {
					self::calculate_square_discount_from_cart( $coupon_code, $square_discount_code_id );
					$last_recalc_cart_hash = $cart_hash;
				} catch ( \Exception $e ) {
					// Recalculation failed (e.g. combined total would be zero). Remove ALL Square coupons
					// to clear the invalid state - we cannot determine which single coupon to remove.
					$square_coupons_to_remove = self::get_applied_square_coupon_codes();
					if ( function_exists( 'wc_square' ) ) {
						wc_square()->log( sprintf( 'Error recalculating discount after cart change for coupon %s: %s', $coupon_code, $e->getMessage() ), 'square-coupons' );
					}
					break;
				} finally {
					$recalculating = false;
				}
			}
		}

		// Remove invalid Square coupons and show error.
		if ( ! empty( $square_coupons_to_remove ) ) {
			foreach ( $square_coupons_to_remove as $code ) {
				Coupon_Utility::remove_coupon_from_cart( $code );
				self::handle_coupon_removed( $code );
			}
			wc_add_notice( __( 'One or more discount codes could not be applied because they would make your order total zero. Square cannot process zero-amount transactions.', 'woocommerce-square' ), 'error' );
		}
	}
}
