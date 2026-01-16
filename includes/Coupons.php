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
	 * Gets the singleton instance.
	 *
	 * @since x.x.x
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
	 */
	public static function init() {
		// Hooks for handling coupon application and removal.
		add_action( 'woocommerce_applied_coupon', array( self::$instance, 'handle_coupon_applied' ), 10, 1 );
		add_action( 'woocommerce_removed_coupon', array( self::$instance, 'handle_coupon_removed' ), 10, 1 );

		// Hooks for handling coupon data and discount amount.
		add_filter( 'woocommerce_get_shop_coupon_data', array( self::$instance, 'filter_woocommerce_get_shop_coupon_data' ), 10, 3 );
	}

	/**
	 * Preflight the construction of a WC_Coupon object.
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

		$bearer_token = wc_square()->get_settings_handler()->get_access_token();
		$is_sandbox   = wc_square()->get_settings_handler()->is_sandbox();
		$api_url      = 'https://connect.squareup' . ( $is_sandbox ? 'sandbox' : '' ) . '.com/v2/discount-codes/search';

		$query = array(
			'query' => array(
				'filter' => array(
					'code' => $coupon_data,
				),
			),
		);

		$response = wp_remote_post(
			$api_url,
			array(
				'headers' => array(
					'Authorization'  => 'Bearer ' . $bearer_token,
					'Content-Type'   => 'application/json',
					'Square-Version' => '2025-01-23',
				),
				'body'    => wp_json_encode( $query ),
			)
		);

		// Do not pre-flight the coupon if there was an error or non-200 response.
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return $coupon;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( empty( $data['discount_codes'] ) ) {
			// No codes found.
			return $coupon;
		}

		foreach ( $data['discount_codes'] as $code ) {
			if ( $code['code'] === $coupon_data ) {
				$code_data = $code;
				break;
			}
		}

		$wc_coupon = Coupon_Utility::map_square_discount_code_to_woocommerce_coupon( $code_data );

		return $wc_coupon;
	}

	/**
	 * Retrieve discount code from the Square API.
	 *
	 * @param string $discount_code The discount code to retrieve.
	 * @return array|null Discount code details, or null if not found.
	 */
	public static function get_discount_code( $discount_code ) {}

	/**
	 * Cache discount code details.
	 *
	 * @param string     $discount_code The discount code.
	 * @param array|null $code_details  The discount code details to cache. Null if not found.
	 */
	public static function set_cache_discount_code( $discount_code, $code_details ) {}

	/**
	 * Retrieve cached discount code details.
	 *
	 * @param string $discount_code The discount code.
	 * @return array|null Cached discount code details, or null if not found (known unknown).
	 */
	public static function get_cache_discount_code( $discount_code ) {}

	/**
	 * Clear cached discount code details.
	 *
	 * @param string $discount_code The discount code.
	 */
	public static function clear_cache_discount_code( $discount_code ) {}

	/**
	 * Handle coupon application - trigger Square discount calculation if conditions are met.
	 *
	 * @since x.x.x
	 *
	 * @param string $coupon_code The coupon code that was applied.
	 */
	public static function handle_coupon_applied( $coupon_code ) {
		// Check if this is a Square discount code.
		$square_discount_code_id = self::get_square_discount_code_id_by_code( $coupon_code );
		
		if ( empty( $square_discount_code_id ) ) {
			// Not a Square discount code, skip.
			return;
		}

		// Store discount code ID in cart session for later use.
		WC()->session->set( '_square_discount_code_id_' . $coupon_code, $square_discount_code_id );

		// Check if we can calculate now (shipping must be selected if required).
		$cart = WC()->cart;

		if ( ! $cart ) {
			return;
		}

		// Check if shipping is required and selected.
		if ( $cart->needs_shipping() ) {
			$chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );
			if ( empty( $chosen_shipping_methods ) ) {
				// Shipping not selected yet, mark for later calculation.
				WC()->session->set( '_square_discount_pending_recalc_' . $coupon_code, true );
				return;
			}
		}

		// Shipping is selected (or not required), calculate now.
		try {
			self::calculate_square_discount_from_cart( $coupon_code );
		} catch ( \Exception $e ) {
			// Remove coupon and show error.
			$cart->remove_coupon( $coupon_code );
			wc_add_notice( sprintf( __( 'Unable to apply coupon "%s": %s', 'woocommerce-square' ), $coupon_code, $e->getMessage() ), 'error' );
		}
	}

	/**
	 * Get Square discount code ID by coupon code.
	 *
	 * @since x.x.x
	 *
	 * @param string $coupon_code The coupon code to search for.
	 * @return string|null The discount code ID or null if not found.
	 */
	public static function get_square_discount_code_id_by_code( $coupon_code ) {
		// Only proceed if WooCommerce Square is active.
		if ( ! function_exists( 'wc_square' ) ) {
			return null;
		}

		$settings_handler = wc_square()->get_settings_handler();
		if ( ! $settings_handler ) {
			return null;
		}

		// Return from transient if available.
		$transient_key  = 'square_discount_code_id_' . $coupon_code;
		$transient_data = get_transient( $transient_key );
		if ( $transient_data ) {
			return $transient_data;
		}

		$bearer_token = $settings_handler->get_access_token();
		$is_sandbox   = $settings_handler->is_sandbox();

		if ( empty( $bearer_token ) ) {
			return null;
		}

		$api_url = 'https://connect.squareup' . ( $is_sandbox ? 'sandbox' : '' ) . '.com/v2/discount-codes/search';

		$query = array(
			'query' => array(
				'filter' => array(
					'code' => $coupon_code,
				),
			),
		);

		$response = wp_remote_post(
			$api_url,
			array(
				'headers' => array(
					'Authorization'  => 'Bearer ' . $bearer_token,
					'Content-Type'   => 'application/json',
					'Square-Version' => '2025-01-23',
				),
				'body'    => wp_json_encode( $query ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				$error_message = is_wp_error( $response ) ? $response->get_error_message() : wp_remote_retrieve_response_message( $response );
				error_log( sprintf( 'Square: Error searching for discount code %s: %s', $coupon_code, $error_message ) );
			}
			return null;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( empty( $data['discount_codes'] ) ) {
			return null;
		}

		// Find matching code.
		foreach ( $data['discount_codes'] as $code ) {
			if ( isset( $code['code'] ) && strtoupper( $code['code'] ) === strtoupper( $coupon_code ) ) {
				$discount_code_id = isset( $code['id'] ) ? $code['id'] : null;

				// Cache the discount code ID.
				set_transient( $transient_key, $discount_code_id, HOUR_IN_SECONDS * 1 );

				// Return the discount code ID.
				return $discount_code_id;
			}
		}

		return null;
	}

	/**
	 * Handle coupon removal - clear Square discount session data.
	 *
	 * @since x.x.x
	 *
	 * @param string $coupon_code The coupon code that was removed.
	 */
	public static function handle_coupon_removed( $coupon_code ) {
		// Clear all Square-related session data for this coupon.
		WC()->session->__unset( '_square_discount_code_id_' . $coupon_code );
		WC()->session->__unset( '_square_discount_amount_' . $coupon_code );
		WC()->session->__unset( '_square_discount_per_item_' . $coupon_code );
		WC()->session->__unset( '_square_discount_pending_recalc_' . $coupon_code );
	}

	/**
	 * Calculate Square discount from cart using CalculateOrder API.
	 *
	 * @since x.x.x
	 *
	 * @param string $coupon_code The coupon code to calculate discount for.
	 * @return float The calculated discount amount.
	 * @throws \Exception If calculation fails.
	 */
	private static function calculate_square_discount_from_cart( $coupon_code ) {
		// Only proceed if WooCommerce Square is active
		if ( ! function_exists( 'wc_square' ) ) {
			throw new \Exception( 'WooCommerce Square plugin is not available.' );
		}

		$cart = WC()->cart;
		
		if ( ! $cart || $cart->is_empty() ) {
			throw new \Exception( 'Cart is empty.' );
		}

		// Get Square discount code ID.
		$square_discount_code_id = self::get_square_discount_code_id_by_code( $coupon_code );
		if ( empty( $square_discount_code_id ) ) {
			throw new \Exception( 'Square discount code not found.' );
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

		// Call CalculateOrder with discount code and get raw response for per-line-item discounts.
		$calculate_result      = $api->calculate_order( $square_order, array( $square_discount_code_id ), true );
		$calculated_order      = $calculate_result['order'];
		$calculated_order_data = $calculate_result['raw_response'];

		// Extract per-line-item discounts from Square's response.
		$line_item_discounts   = array();
		$total_discount_amount = 0;

		if ( isset( $calculated_order_data['line_items'] ) && is_array( $calculated_order_data['line_items'] ) ) {
			// Map Square line items to cart items by matching catalog object ID or name.
			$cart_items_by_key = array();
			foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
				$product             = $cart_item['data'];
				$square_variation_id = $product->get_meta( Product::SQUARE_VARIATION_ID_META_KEY );
				$product_name        = $product->get_name();

				// Skip if square variation id is not set or the product is not set for square sync.
				if ( empty( $square_variation_id ) || ! $product ) {
					continue;
				}

				// Create mapping keys: catalog object ID (preferred) or product name.
				$cart_items_by_key[ $cart_item_key ] = array(
					'catalog_object_id' => $square_variation_id,
					'name' => $product_name,
					'quantity' => (float) $cart_item['quantity'],
				);
			}

			// Extract discounts from Square's calculated line items.
			foreach ( $calculated_order_data['line_items'] as $square_line_item ) {
				// Get discount amount for this line item (in cents).
				$line_item_discount_cents = 0;
				if ( isset( $square_line_item['total_discount_money'] ) && isset( $square_line_item['total_discount_money']['amount'] ) ) {
					$line_item_discount_cents = (int) $square_line_item['total_discount_money']['amount'];
				}

				if ( $line_item_discount_cents > 0 ) {
					$line_item_discount = Money_Utility::cents_to_float( $line_item_discount_cents );
					$total_discount_amount += $line_item_discount;

					// Try to match this Square line item to a cart item.
					$matched_cart_item_key = null;
					
					// First try to match by catalog object ID.
					if ( isset( $square_line_item['catalog_object_id'] ) && ! empty( $square_line_item['catalog_object_id'] ) ) {
						foreach ( $cart_items_by_key as $cart_key => $cart_item_info ) {
							if ( $cart_item_info['catalog_object_id'] === $square_line_item['catalog_object_id'] ) {
								$matched_cart_item_key = $cart_key;
								break;
							}
						}
					}
					
					// If no match by catalog ID, try to match by name.
					if ( ! $matched_cart_item_key && isset( $square_line_item['name'] ) ) {
						foreach ( $cart_items_by_key as $cart_key => $cart_item_info ) {
							if ( $cart_item_info['name'] === $square_line_item['name'] ) {
								$matched_cart_item_key = $cart_key;
								break;
							}
						}
					}

					// Store discount for this cart item.
					if ( $matched_cart_item_key ) {
						if ( ! isset( $line_item_discounts[ $matched_cart_item_key ] ) ) {
							$line_item_discounts[ $matched_cart_item_key ] = 0;
						}
						$line_item_discounts[ $matched_cart_item_key ] += $line_item_discount;
					}
				}
			}
		}

		// If we couldn't extract per-item discounts, fall back to total discount calculation.
		if ( empty( $line_item_discounts ) ) {
			// Calculate order total without discount for comparison.
			$cart_subtotal = $cart->get_subtotal();
			$cart_shipping_total = $cart->get_shipping_total();
			$cart_fee_total = 0;
			foreach ( $cart->get_fees() as $fee ) {
				$cart_fee_total += (float) $fee->amount;
			}
			$cart_tax_total = $cart->get_total_tax();
			
			// Total before discount (in dollars).
			$total_before_discount = $cart_subtotal + $cart_shipping_total + $cart_fee_total + $cart_tax_total;

			// Get calculated total from Square (in cents).
			$total_after_discount_cents = $calculated_order->getTotalMoney() ? $calculated_order->getTotalMoney()->getAmount() : 0;
			$total_after_discount = Money_Utility::cents_to_float( $total_after_discount_cents );
			
			// Discount amount is the difference.
			$total_discount_amount = $total_before_discount - $total_after_discount;
			
			// Ensure discount is not negative.
			if ( $total_discount_amount < 0 ) {
				$total_discount_amount = 0;
			}
		}

		// Store per-item discounts and total in cart session.
		WC()->session->set( '_square_discount_amount_' . $coupon_code, $total_discount_amount );
		WC()->session->set( '_square_discount_per_item_' . $coupon_code, $line_item_discounts );
		WC()->session->set( '_square_discount_code_id_' . $coupon_code, $square_discount_code_id );

		return $total_discount_amount;
	}

	/**
	 * Build Square Order object from cart data (for calculation only, no order creation).
	 *
	 * @since x.x.x
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

		// Build line items from cart.
		$line_items = array();
		$tax_type   = wc_prices_include_tax() ? API::TAX_TYPE_INCLUSIVE : API::TAX_TYPE_ADDITIVE;

		// Get tax rates for cart.
		$tax_rates = array();
		if ( wc_tax_enabled() ) {
			$tax_totals = $cart->get_tax_totals();
			foreach ( $tax_totals as $rate_id => $tax ) {
				$tax_item = new \Square\Models\OrderLineItemTax();
				$tax_item->setUid( uniqid() );
				$tax_item->setName( $tax->label );
				$tax_item->setType( $tax_type );
				$tax_item->setScope( 'LINE_ITEM' );
				
				// Get tax rate percentage.
				$rate_percentage = 0;
				$tax_rate        = \WC_Tax::_get_tax_rate( $rate_id );
				if ( $tax_rate && isset( $tax_rate['tax_rate'] ) ) {
					$rate_percentage = (float) $tax_rate['tax_rate'];
				}
				$tax_item->setPercentage( Square_Helper::number_format( $rate_percentage ) );
				$tax_rates[ $rate_id ] = $tax_item;
			}
		}

		// Convert cart items to Square line items.
		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
			$product  = $cart_item['data'];
			$quantity = (float) $cart_item['quantity'];
			
			$line_item = new \Square\Models\OrderLineItem( (string) $quantity );

			// Check if gift card.
			if ( Product::is_gift_card( $product ) ) {
				$line_item->setItemType( 'GIFT_CARD' );
			}

			// Calculate amounts.
			$line_subtotal     = (float) $cart_item['line_subtotal'];
			$line_total        = (float) $cart_item['line_total'];
			$line_subtotal_tax = (float) $cart_item['line_subtotal_tax'];
			$line_tax          = (float) $cart_item['line_tax'];

			// Base price per unit (before any discounts).
			$subtotal_per_unit = $line_subtotal;
			if ( API::TAX_TYPE_INCLUSIVE === $tax_type ) {
				$subtotal_per_unit += $line_subtotal_tax;
			}
			
			if ( $quantity > 0 ) {
				$subtotal_per_unit = $subtotal_per_unit / $quantity;
			} else {
				$subtotal_per_unit = 0;
			}

			$line_item->setQuantity( (string) $quantity );
			$line_item->setBasePriceMoney( Money_Utility::amount_to_money( $subtotal_per_unit, $currency ) );

			// Set catalog object ID if available.
			$square_variation_id = $product->get_meta( Product::SQUARE_VARIATION_ID_META_KEY );
			if ( ! empty( $square_variation_id ) ) {
				$line_item->setCatalogObjectId( $square_variation_id );
			} else {
				$line_item->setName( $product->get_name() );
			}

			// Apply taxes.
			$applied_taxes = array();
			if ( ! empty( $cart_item['line_tax_data'] ) && is_array( $cart_item['line_tax_data'] ) ) {
				$tax_data = $cart_item['line_tax_data'];
				if ( isset( $tax_data['total'] ) && is_array( $tax_data['total'] ) ) {
					foreach ( $tax_data['total'] as $rate_id => $tax_amount ) {
						if ( ! empty( $tax_amount ) && isset( $tax_rates[ $rate_id ] ) ) {
							$applied_taxes[] = new \Square\Models\OrderLineItemAppliedTax( $tax_rates[ $rate_id ]->getUid() );
						}
					}
				}
			}
			
			if ( ! empty( $applied_taxes ) ) {
				$line_item->setAppliedTaxes( $applied_taxes );
			}

			// Note: We do NOT add WooCommerce discounts here - Square will calculate via discount codes.
			$line_items[] = $line_item;
		}

		// Add shipping as line item (only if shipping method is selected).
		$chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );
		if ( ! empty( $chosen_shipping_methods ) && $cart->needs_shipping() ) {
			$packages = WC()->shipping()->get_packages();
			foreach ( $packages as $package_index => $package ) {
				if ( isset( $chosen_shipping_methods[ $package_index ] ) ) {
					$shipping_method = $chosen_shipping_methods[ $package_index ];
					$shipping_cost = 0;
					
					// Get shipping cost from package.
					if ( isset( $package['rates'][ $shipping_method ] ) ) {
						$shipping_rate = $package['rates'][ $shipping_method ];
						$shipping_cost = (float) $shipping_rate->get_cost();
						
						if ( $shipping_cost > 0 ) {
							$shipping_line_item = new \Square\Models\OrderLineItem( '1' );
							$shipping_line_item->setName( $shipping_rate->get_label() );
							$shipping_line_item->setBasePriceMoney( Money_Utility::amount_to_money( $shipping_cost, $currency ) );
							$line_items[] = $shipping_line_item;
						}
					}
				}
			}
		}

		// Add fees as line items.
		foreach ( $cart->get_fees() as $fee_key => $fee ) {
			$fee_amount = (float) $fee->amount;
			if ( $fee_amount != 0 ) {
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

		return $order_model;
	}
}
