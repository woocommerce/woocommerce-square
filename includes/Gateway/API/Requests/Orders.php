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
 */

namespace WooCommerce\Square\Gateway\API\Requests;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Square\API;
use WooCommerce\Square\Framework\Square_Helper;
use WooCommerce\Square\Handlers\Product;
use WooCommerce\Square\Utilities\Money_Utility;
use Square\Models\FulfillmentType;

/**
 * The Orders API request class.
 *
 * @since 2.0.0
 */
class Orders extends API\Request {

	/**
	 * Square order object for calculateOrder request.
	 * Used to store the order object when calculating discounts.
	 * Made public to allow access from API.php::do_square_request().
	 *
	 * @var \Square\Models\Order
	 */
	public $square_order;

	/**
	 * Proposed discount codes for calculateOrder request.
	 * Array of discount code IDs to propose for calculation.
	 * Made public to allow access from API.php::do_square_request().
	 *
	 * @var array
	 */
	public $proposed_discount_codes = array();

	/**
	 * Whether to return raw response data for calculateOrder.
	 * When true, the raw JSON response will be available in addition to the parsed order object.
	 * Made public to allow access from API.php::do_square_request().
	 *
	 * @var bool
	 */
	public $return_raw_response = false;

	/**
	 * WooCommerce order object.
	 * Stored for reference during calculateOrder request.
	 * Made public to allow access from API.php::do_square_request().
	 *
	 * @var \WC_Order|null
	 */
	public $wc_order;

	/**
	 * Raw response data from calculateOrder API call.
	 * This is populated by the API.php do_square_request method when handling calculateOrder.
	 * Used to provide access to the full JSON response including per-line-item discount details.
	 *
	 * @var array
	 */
	public $raw_calculate_order_response;

	/**
	 * Initializes a new Catalog request.
	 *
	 * @since 2.0.0
	 *
	 * @param \Square\SquareClient $api_client the API client
	 */
	public function __construct( $api_client ) {
		$this->square_api = $api_client->getOrdersApi();
	}


	/**
	 * Sets the data for creating an order.
	 *
	 * @since 2.0.0
	 *
	 * @param string $location_id location ID
	 * @param \WC_Order $order order object
	 */
	public function set_create_order_data( $location_id, \WC_Order $order ) {

		$this->square_api_method = 'createOrder';
		$this->square_request    = new \Square\Models\CreateOrderRequest();

		$order_model = new \Square\Models\Order( $location_id );
		if ( ! empty( $order->square_customer_id ) ) {
			$order_model->setCustomerId( $order->square_customer_id );
		}

		// Set the data.
		$this->set_order_data( $order, $order_model, 'create' );
	}

	/**
	 * Prepares data to retrieve a Square order.
	 *
	 * @param string $order_id The Square order ID.
	 */
	public function set_retrieve_order_data( $order_id ) {
		$this->square_api_method = 'retrieveOrder';
		$this->square_api_args   = array( $order_id );
	}

	/**
	 * Prepare data to update Square order.
	 *
	 * @param \WC_Order            $order        WooCommerce order object.
	 * @param \Square\Models\Order $square_order Square order object.
	 */
	public function set_update_order_data( \WC_Order $order, \Square\Models\Order $square_order ) {

		$this->square_api_method = 'updateOrder';
		$this->square_request    = new \Square\Models\UpdateOrderRequest();
		$this->square_request->setFieldsToClear(
			array(
				'discounts',
				'line_items',
				'service_charges',
				'taxes',
			)
		);

		$order_model = new \Square\Models\Order( $square_order->getLocationId() );
		$order_model->setCustomerId( $square_order->getCustomerId() );

		$order_model->setVersion( $square_order->getVersion() );

		// Set the data.
		$this->set_order_data( $order, $order_model );
		$this->square_api_args = array( $order->square_order_id, $this->square_request );
	}

	/**
	 * Sets the data for calculating a Square order.
	 *
	 * Note: The SDK's CalculateOrderRequest only supports order and proposed_rewards (loyalty).
	 * We need to send proposed_discount_codes (discount code IDs), so the actual API call is
	 * made in API.php::do_square_request() via direct HTTP, following the same request/response pattern.
	 *
	 * @since 5.3.0
	 *
	 * @param \WC_Order|null       $order                   Optional. WooCommerce order object. Can be null when called from cart context.
	 * @param \Square\Models\Order $square_order            Square order object to calculate.
	 * @param array                $proposed_discount_codes Optional. Array of discount code IDs to propose for calculation.
	 * @param bool                 $return_raw_response     Optional. If true, the raw JSON response will be available.
	 */
	public function set_calculate_order_data( $order, \Square\Models\Order $square_order, array $proposed_discount_codes = array(), $return_raw_response = false ) {
		// Set the API method name - this will be intercepted in API.php::do_square_request()
		// so we can send proposed_discount_codes (the SDK's CalculateOrderRequest does not support them).
		$this->square_api_method = 'calculateOrder';

		// Store the order objects and parameters for use in the custom HTTP request handler
		$this->square_order            = $square_order;
		$this->proposed_discount_codes = $proposed_discount_codes;
		$this->return_raw_response     = $return_raw_response;
		$this->wc_order                = $order;

		// Note: square_api_args is not used for calculateOrder since we handle it via direct HTTP
		$this->square_api_args = array();
	}

	/**
	 * Sets the data for an order.
	 *
	 * @since 3.7.0
	 *
	 * @param \WC_Order            $order              WooCommerce order object.
	 * @param \Square\Models\Order $square_order       Square order object.
	 * @param string               $order_request_type The type of order request.
	 */
	public function set_order_data( \WC_Order $order, \Square\Models\Order $order_model, $order_request_type = 'update' ) {
		$order_model->setReferenceId( $order->get_order_number() );

		// Set fulfillment data for create order request if order sync is enabled.
		if ( $this->is_order_fulfillment_sync_enabled() && 'create' === $order_request_type ) {
			$order_model = $this->set_fulfillment_data( $order, $order_model );
		}

		$taxes = $this->get_order_taxes( $order );
		// When Square coupon/redemption is used, send shipping as service charge (not line item) to avoid discount applying to shipping. Otherwise keep original behavior (shipping as line items).
		$square_coupon_in_use = ! empty( \WooCommerce\Square\Coupons::get_order_square_discount_code_ids( $order ) );
		$line_item_sources    = array_merge( $this->get_product_line_items( $order ), $this->get_fee_line_items( $order ) );
		if ( ! $square_coupon_in_use ) {
			$line_item_sources = array_merge( $line_item_sources, $this->get_shipping_line_items( $order ) );
		}
		$all_line_items = $this->get_api_line_items( $order, $line_item_sources, $taxes );

		$square_order_line_items = array_values(
			array_filter(
				$all_line_items,
				function( $line_item ) {
					return $line_item instanceof \Square\Models\OrderLineItem;
				}
			)
		);

		$square_discount_line_items = array_values(
			array_filter(
				$all_line_items,
				function( $line_item ) {
					return $line_item instanceof \Square\Models\OrderLineItemDiscount;
				}
			)
		);

		$square_updated_taxes_line_items = array_values(
			array_filter(
				$all_line_items,
				function( $line_item ) {
					return $line_item instanceof \Square\Models\OrderLineItemTax;
				}
			)
		);

		// Merge existing and new taxes.
		$taxes = array_merge( $taxes, $square_updated_taxes_line_items );

		$order_model->setLineItems( $square_order_line_items );

		if ( ! empty( $square_discount_line_items ) ) {
			$order_model->setDiscounts( $square_discount_line_items );
		}

		$order_model->setTaxes( array_values( $taxes ) );

		// Only send shipping as service charges when Square coupon is used; otherwise shipping is already in line items.
		if ( $square_coupon_in_use ) {
			$service_charges = $this->get_order_service_charges_for_shipping( $order, $taxes );
			if ( ! empty( $service_charges ) ) {
				$order_model->setServiceCharges( $service_charges );
			}
		}

		$this->square_request->setIdempotencyKey( wc_square()->get_idempotency_key( $order->unique_transaction_ref ) );
		$this->square_request->setOrder( $order_model );

		$this->square_api_args = array( $this->square_request );
	}

	/**
	 * Sets the fulfillment data for an order.
	 *
	 * @since 5.0.0
	 *
	 * @param \WC_Order            $order        WooCommerce order object.
	 * @param \Square\Models\Order $order_model Square order object.
	 * @return \Square\Models\Order
	 */
	protected function set_fulfillment_data( \WC_Order $order, \Square\Models\Order $order_model ) {
		$order_model->setState( 'OPEN' );

		// Create comprehensive fulfillment object.
		$fulfillment = new \Square\Models\OrderFulfillment();
		$fulfillment->setState( 'PROPOSED' );
		$fulfillment->setUid( wc_square()->get_idempotency_key( '', false ) );

		// Determine fulfillment type based on shipping method.
		$shipping_methods = $order->get_shipping_methods();
		$fulfillment_type = empty( $shipping_methods ) ? FulfillmentType::PICKUP : FulfillmentType::SHIPMENT;
		$fulfillment->setType( $fulfillment_type );

		// Add fulfillment details based on type.
		if ( FulfillmentType::SHIPMENT === $fulfillment_type ) {
			$shipment_details = new \Square\Models\OrderFulfillmentShipmentDetails();

			// Add recipient information.
			$recipient = new \Square\Models\OrderFulfillmentRecipient();
			$recipient->setDisplayName( trim( $order->get_formatted_shipping_full_name() ) );
			$recipient->setPhoneNumber( $order->get_billing_phone() );

			// Add shipping address.
			$shipping_address = new \Square\Models\Address();
			$shipping_address->setAddressLine1( $order->get_shipping_address_1() );
			$shipping_address->setAddressLine2( $order->get_shipping_address_2() );
			$shipping_address->setLocality( $order->get_shipping_city() );
			$shipping_address->setAdministrativeDistrictLevel1( $order->get_shipping_state() );
			$shipping_address->setPostalCode( $order->get_shipping_postcode() );
			$shipping_address->setCountry( $order->get_shipping_country() );
			$recipient->setAddress( $shipping_address );

			$shipment_details->setRecipient( $recipient );

			// Add shipping method as carrier if available.
			foreach ( $shipping_methods as $shipping_method ) {
				$shipment_details->setCarrier( $shipping_method->get_method_title() );
				break; // Use first shipping method.
			}

			$fulfillment->setShipmentDetails( $shipment_details );

		} elseif ( FulfillmentType::PICKUP === $fulfillment_type ) {
			$pickup_details = new \Square\Models\OrderFulfillmentPickupDetails();
			$pickup_details->setScheduleType( 'ASAP' );

			// Add recipient information for pickup.
			$recipient = new \Square\Models\OrderFulfillmentRecipient();
			$recipient->setDisplayName( trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ) );
			$recipient->setPhoneNumber( $order->get_billing_phone() );

			// Use billing address for pickup.
			$pickup_address = new \Square\Models\Address();
			$pickup_address->setAddressLine1( $order->get_billing_address_1() );
			$pickup_address->setAddressLine2( $order->get_billing_address_2() );
			$pickup_address->setLocality( $order->get_billing_city() );
			$pickup_address->setAdministrativeDistrictLevel1( $order->get_billing_state() );
			$pickup_address->setPostalCode( $order->get_billing_postcode() );
			$pickup_address->setCountry( $order->get_billing_country() );
			$recipient->setAddress( $pickup_address );

			$pickup_details->setRecipient( $recipient );

			// Add customer note if available.
			if ( $order->get_customer_note() ) {
				$pickup_details->setNote( Square_Helper::str_truncate( $order->get_customer_note(), 500 ) );
			}

			$fulfillment->setPickupDetails( $pickup_details );
		}

		// Set the fulfillment on the order.
		$order_model->setFulfillments( array( $fulfillment ) );

		return $order_model;
	}

	/**
	 * Sets request data when a payment is to be made using multiple payment methods.
	 * For example: Gift Card + Square Credit Card.
	 *
	 * @param array  $payment_ids Array of payment IDs.
	 * @param string $order_id    Square order ID.
	 * @since 3.9.0
	 */
	public function set_pay_order_data( $payment_ids, $order_id ) {
		$this->square_api_method = 'payOrder';
		$this->square_request    = new \Square\Models\PayOrderRequest( uniqid() );

		$this->square_request->setPaymentIds( $payment_ids );
		$this->square_api_args = array( $order_id, $this->square_request );
	}

	/**
	 * Gets Square line item objects for an order's product items.
	 *
	 * @since 2.0.0
	 *
	 * @param \WC_Order $order order object
	 * @return \WC_Order_Item_Product[]
	 */
	protected function get_product_line_items( \WC_Order $order ) {

		$line_items = array();

		foreach ( $order->get_items() as $item ) {

			if ( ! $item instanceof \WC_Order_Item_Product ) {
				continue;
			}

			$line_items[] = $item;
		}

		return $line_items;
	}


	/**
	 * Gets Square line item objects for an order's fee items.
	 *
	 * @since 2.0.0
	 *
	 * @param \WC_Order $order order object
	 * @return \WC_Order_Item_Fee[]
	 */
	protected function get_fee_line_items( \WC_Order $order ) {

		$line_items = array();

		foreach ( $order->get_fees() as $item ) {

			if ( ! $item instanceof \WC_Order_Item_Fee ) {
				continue;
			}

			$line_items[] = $item;
		}

		return $line_items;
	}


	/**
	 * Gets Square line item objects for an order's shipping items.
	 *
	 * @since 2.0.0
	 *
	 * @param \WC_Order $order order object
	 * @return \WC_Order_Item_Shipping[]
	 */
	protected function get_shipping_line_items( \WC_Order $order ) {

		$line_items = array();

		foreach ( $order->get_shipping_methods() as $item ) {

			if ( ! $item instanceof \WC_Order_Item_Shipping ) {
				continue;
			}

			$line_items[] = $item;
		}

		return $line_items;
	}


	/**
	 * Gets Square order service charge objects for the order's shipping methods.
	 * Sends shipping as order-level service charges per Square's recommendation (not as line items).
	 *
	 * @since 5.3.0
	 *
	 * @param \WC_Order                        $order WooCommerce order object.
	 * @param \Square\Models\OrderLineItemTax[] $taxes Order taxes keyed by rate_id (for taxable service charges).
	 * @return \Square\Models\OrderServiceCharge[]
	 */
	protected function get_order_service_charges_for_shipping( \WC_Order $order, array $taxes ) {
		$service_charges = array();

		foreach ( $order->get_shipping_methods() as $item ) {
			if ( ! $item instanceof \WC_Order_Item_Shipping ) {
				continue;
			}

			$total = (float) $item->get_total();
			if ( $total <= 0 ) {
				continue;
			}

			$charge = new \Square\Models\OrderServiceCharge();
			$charge->setUid( wc_square()->get_idempotency_key( 'shipping-' . $item->get_id(), false ) );
			$charge->setName( $item->get_name() ? $item->get_name() : __( 'Shipping', 'woocommerce-square' ) );
			$charge->setAmountMoney( Money_Utility::amount_to_money( $total, $order->get_currency() ) );
			$charge->setCalculationPhase( 'SUBTOTAL_PHASE' );

			$total_tax = (float) $item->get_total_tax();
			$charge->setTaxable( $total_tax > 0 );

			if ( $total_tax > 0 && ! empty( $taxes ) ) {
				$applied_taxes = array();
				$get_taxes     = $item->get_taxes();
				if ( isset( $get_taxes['total'] ) && is_array( $get_taxes['total'] ) ) {
					foreach ( array_keys( $get_taxes['total'] ) as $tax_id ) {
						if ( empty( $tax_id ) ) {
							continue;
						}
						// Match rate_id as int or string (Woo may store either).
						$tax_obj = isset( $taxes[ $tax_id ] ) ? $taxes[ $tax_id ] : ( isset( $taxes[ (string) $tax_id ] ) ? $taxes[ (string) $tax_id ] : ( isset( $taxes[ (int) $tax_id ] ) ? $taxes[ (int) $tax_id ] : null ) );
						if ( $tax_obj instanceof \Square\Models\OrderLineItemTax ) {
							$applied_taxes[] = new \Square\Models\OrderLineItemAppliedTax( $tax_obj->getUid() );
						}
					}
				}
				// If shipping has tax but no per-rate breakdown, apply first order tax so Square applies tax.
				if ( empty( $applied_taxes ) && $total_tax > 0 ) {
					$first_tax = reset( $taxes );
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

		return $service_charges;
	}


	/**
	 * Gets Square API line item objects.
	 *
	 * @since 2.2.6
	 *
	 * @param \WC_Order $order
	 * @param \WC_Order_Item[] $line_items
	 * @param \Square\Models\OrderLineItemTax[] $taxes
	 * @return \Square\Models\OrderLineItem[]
	 */
	protected function get_api_line_items( \WC_Order $order, $line_items, $taxes ) {
		$api_line_items = array();
		$tax_type       = wc_prices_include_tax() ? API::TAX_TYPE_INCLUSIVE : API::TAX_TYPE_ADDITIVE;

		$square_discount_code_ids = \WooCommerce\Square\Coupons::get_order_square_discount_code_ids( $order );
		$has_square_discount      = ! empty( $square_discount_code_ids );

		/**
		 * When any Square discount coupon is in use, always force the
		 * ADDITIVE tax type, regardless of WooCommerce's tax display settings.
		 *
		 * This is necessary because we observe calculation mismatches when
		 * prices are inclusive of tax (i.e. displayed as tax-included) and
		 * Square coupons are used. Forcing prices as ADDITIVE tax type
		 * ensures Square's calculation aligns perfectly with WooCommerce's
		 * total, preventing rounding and total discrepancies.
		 */
		if ( $has_square_discount ) {
			$tax_type = API::TAX_TYPE_ADDITIVE;
		}

		/** @var \WC_Order_Item_Product $item */
		foreach ( $line_items as $item ) {
			$is_product = $item instanceof \WC_Order_Item_Product;
			// Plugins can make the quantity a float, eg https://wordpress.org/plugins/decimal-product-quantity-for-woocommerce/
			$quantity  = $is_product ? (float) $item->get_quantity() : 1;
			$line_item = new \Square\Models\OrderLineItem( (string) $quantity );

			if ( $is_product && Product::is_gift_card( $item->get_product() ) ) {
				$line_item->setItemType( 'GIFT_CARD' );
			}

			$total_tax       = (float) $item->get_total_tax();
			$total_amount    = (float) $item->get_total();
			$subtotal_amount = $is_product ? (float) $item->get_subtotal() : $total_amount;

			// Include the tax in subtotal when prices are inclusive of taxes.
			if ( API::TAX_TYPE_INCLUSIVE === $tax_type ) {
				$subtotal_amount += $total_tax;
			}

			// Subtotal per quantity.
			if ( $quantity > 0 ) {
				$subtotal_amount = $subtotal_amount / $quantity;
			} else {
				$subtotal_amount = 0;
			}

			$line_item->setQuantity( (string) $quantity );
			$line_item->setBasePriceMoney( Money_Utility::amount_to_money( $subtotal_amount, $order->get_currency() ) );

			if ( $is_product && $item->get_meta( Product::SQUARE_VARIATION_ID_META_KEY ) ) {
				$line_item->setCatalogObjectId( $item->get_meta( Product::SQUARE_VARIATION_ID_META_KEY ) );
			} else {
				$line_item->setName( $item->get_name() );
			}

			// CALCULATE DISCOUNT.
			// Skip adding discount line items if Square discount code is present (discount will be applied via CreateRedemption).
			if ( $item instanceof \WC_Order_Item_Product ) {
				$discount = (float) $item->get_subtotal() - (float) $item->get_total();

				// Only add discount line items if no Square discount code is present.
				// If Square discount code(s) are present, the discount will be applied via CreateRedemption.
				if ( $discount > 0 && ! $has_square_discount ) {
					$discount_uid = wc_square()->get_idempotency_key( '', false );

					$line_item->setAppliedDiscounts(
						array( new \Square\Models\OrderLineItemAppliedDiscount( $discount_uid ) )
					);

					$order_line_item_discount = new \Square\Models\OrderLineItemDiscount();
					$order_line_item_discount->setUid( $discount_uid );
					$order_line_item_discount->setName( __( 'Discount', 'woocommerce-square' ) );
					$order_line_item_discount->setType( 'FIXED_AMOUNT' );
					$order_line_item_discount->setScope( 'LINE_ITEM' );
					$order_line_item_discount->setAmountMoney(
						Money_Utility::amount_to_money(
							$discount,
							$order->get_currency()
						)
					);

					$api_line_items[] = $order_line_item_discount;
				}
			}

			// CALCULATE TAXES.
			$applied_taxes = array();
			$get_taxes     = $item->get_taxes();
			if ( isset( $get_taxes['total'] ) ) {
				foreach ( $get_taxes['total'] as $key => $tax_amount ) {
					// CALCULATE TAX.

					if ( empty( $tax_amount ) ) {
						continue;
					}

					$item_uid            = $item->get_id();
					$tax_uid             = $taxes[ $key ]->getUid();
					$prev_percentage     = $taxes[ $key ]->getPercentage();
					$adjusted_percentage = $prev_percentage;
					/*
					 * $total_amount could be 0 for some cases, eg: Retail Delivery Fee via Avalara AvaTax for Minnesota (MN) and Colorado (CO).
					 * @see https://linear.app/a8c/issue/SQUARE-232/divisionbyzeroerror-in-square-gateway-with-zero-amount-fee-tax
					 */
					if ( ! empty( $total_amount ) ) {
						$adjusted_percentage = (float) $tax_amount * 100 / $total_amount;
						$adjusted_percentage = number_format( (float) $adjusted_percentage, 2, '.', '' );
					}

					if ( $prev_percentage !== $adjusted_percentage ) {
						// Create a new tax.
						$uniqid = uniqid();

						$tax_item          = new \Square\Models\OrderLineItemTax();
						$adjusted_tax_name = $taxes[ $key ]->getName() . __( ' - (Adjusted Tax for) - ', 'woocommerce-square' ) . $item_uid;
						$tax_item->setUid( $uniqid );
						$tax_item->setName( $adjusted_tax_name );
						$tax_item->setType( $tax_type );
						$tax_item->setScope( 'LINE_ITEM' );
						$tax_item->setPercentage( $adjusted_percentage );

						$api_line_items[] = $tax_item;
					} else {
						$uniqid = $tax_uid;
					}

					$applied_taxes[] = new \Square\Models\OrderLineItemAppliedTax( $uniqid );
				}
			}

			$line_item->setAppliedTaxes( $applied_taxes );

			$api_line_items[] = $line_item;
		}

		return $api_line_items;
	}


	/**
	 * Gets the tax line items for an order.
	 *
	 * @since 2.0.0
	 *
	 * @param \WC_Order $order
	 * @return \Square\Models\OrderLineItemTax[]
	 */
	protected function get_order_taxes( \WC_Order $order ) {
		$taxes    = array();
		$tax_type = wc_prices_include_tax() ? API::TAX_TYPE_INCLUSIVE : API::TAX_TYPE_ADDITIVE;

		$has_square_discount = ! empty( \WooCommerce\Square\Coupons::get_order_square_discount_code_ids( $order ) );

		/**
		 * When any Square discount coupon is in use, always force the
		 * ADDITIVE tax type, regardless of WooCommerce's tax display settings.
		 *
		 * This is necessary because we observe calculation mismatches when
		 * prices are inclusive of tax (i.e. displayed as tax-included) and
		 * Square coupons are used. Forcing prices as ADDITIVE tax type
		 * ensures Square's calculation aligns perfectly with WooCommerce's
		 * total, preventing rounding and total discrepancies.
		 */
		if ( $has_square_discount ) {
			$tax_type = API::TAX_TYPE_ADDITIVE;
		}

		foreach ( $order->get_taxes() as $tax ) {
			$tax_item = new \Square\Models\OrderLineItemTax();
			$tax_item->setUid( uniqid() );
			$tax_item->setName( $tax->get_name() );
			$tax_item->setType( $tax_type );
			$tax_item->setScope( 'LINE_ITEM' );
			$tax_item->setPercentage( Square_Helper::number_format( (float) $tax->get_rate_percent() ) );
			$taxes[ $tax->get_rate_id() ] = $tax_item;
		}

		return $taxes;
	}

	/**
	 * Creates applied taxes array for each Square line item.
	 *
	 * @since 2.0.4
	 *
	 * @param \Square\Models\OrderLineItemTax[] $taxes
	 * @param WC_Order_Item $line_item
	 * @return \Square\Models\OrderLineItemAppliedTax[] $taxes
	 */
	protected function apply_taxes( $taxes, $line_item ) {

		$tax_ids = array();

		$get_taxes = $line_item->get_taxes();
		if ( isset( $get_taxes['total'] ) ) {
			foreach ( $get_taxes['total'] as $key => $value ) {
				$tax_ids[] = $key;
			}
		}

		$applied_taxes = array();

		foreach ( $tax_ids as $tax_id ) {
			if ( empty( $tax_id ) ) {
				continue;
			}

			$applied_taxes[] = new \Square\Models\OrderLineItemAppliedTax( $taxes[ $tax_id ]->getUid() );
		};

		return empty( $applied_taxes ) ? null : $applied_taxes;
	}


	/**
	 * Sets the data for updating an order with a line item adjustment.
	 *
	 * @since 2.0.4
	 *
	 * @param string $location_id location ID
	 * @param \WC_Order $order order object
	 * @param int $version Current 'version' value of Square order
	 * @param int $amount Amount of line item in smallest unit
	 */
	public function add_line_item_order_data( $location_id, \WC_Order $order, $version, $amount ) {

		$this->square_api_method = 'updateOrder';
		$this->square_request    = new \Square\Models\UpdateOrderRequest();

		$order_model = new \Square\Models\Order( $location_id );
		$order_model->setVersion( $version );

		$line_item = new \Square\Models\OrderLineItem( (string) 1 );
		$line_item->setName( __( 'Adjustment', 'woocommerce-square' ) );
		$line_item->setQuantity( (string) 1 );

		$money_object = new \Square\Models\Money();
		$money_object->setAmount( $amount );
		$money_object->setCurrency( $order->get_currency() );

		$line_item->setBasePriceMoney( $money_object );
		$order_model->setLineItems( array( $line_item ) );

		$this->square_request->setIdempotencyKey( wc_square()->get_idempotency_key( $order->unique_transaction_ref ) . $version );
		$this->square_request->setOrder( $order_model );

		$this->square_api_args = array(
			$order->square_order_id,
			$this->square_request,
		);
	}


	/**
	 * Sets the data for updating an order with a discount adjustment.
	 *
	 * @since 2.0.4
	 *
	 * @param string $location_id location ID
	 * @param \WC_Order $order order object
	 * @param int $version Current 'version' value of Square order
	 * @param int $amount Amount of discount in smallest unit
	 */
	public function add_discount_order_data( $location_id, \WC_Order $order, $version, $amount ) {

		$this->square_api_method = 'updateOrder';
		$this->square_request    = new \Square\Models\UpdateOrderRequest();

		$order_model = new \Square\Models\Order( $location_id );
		$order_model->setVersion( $version );

		$order_line_item_discount = new \Square\Models\OrderLineItemDiscount();
		$order_line_item_discount->setName( __( 'Adjustment', 'woocommerce-square' ) );
		$order_line_item_discount->setType( 'FIXED_AMOUNT' );

		$money_object = new \Square\Models\Money();
		$money_object->setAmount( $amount );
		$money_object->setCurrency( $order->get_currency() );

		$order_line_item_discount->setAmountMoney( $money_object );
		$order_line_item_discount->setScope( 'ORDER' );

		$order_model->setDiscounts( array( $order_line_item_discount ) );

		$this->square_request->setIdempotencyKey( wc_square()->get_idempotency_key( $order->unique_transaction_ref ) . $version );
		$this->square_request->setOrder( $order_model );

		$this->square_api_args = array(
			$order->square_order_id,
			$this->square_request,
		);
	}

	/**
	 * Sets the data for updating an order with a service charge adjustment.
	 * Used for positive adjustments so the amount is not eligible for coupon discount (unlike a line item).
	 *
	 * @since 5.0.0
	 *
	 * @param string                    $location_id           Square location ID.
	 * @param \WC_Order                 $order                 WooCommerce order.
	 * @param int                       $version               Current version of the Square order.
	 * @param int                       $amount                Adjustment amount in smallest currency unit (cents).
	 * @param \Square\Models\Order|null $existing_square_order Current Square order (to preserve existing service charges e.g. shipping).
	 */
	public function add_service_charge_order_data( $location_id, \WC_Order $order, $version, $amount, $existing_square_order = null ) {
		$this->square_api_method = 'updateOrder';
		$this->square_request    = new \Square\Models\UpdateOrderRequest();

		$order_model = new \Square\Models\Order( $location_id );
		$order_model->setVersion( $version );

		$existing_charges = array();
		if ( $existing_square_order && method_exists( $existing_square_order, 'getServiceCharges' ) ) {
			$existing = $existing_square_order->getServiceCharges();
			if ( is_array( $existing ) ) {
				$existing_charges = $existing;
			}
		}

		$money = new \Square\Models\Money();
		$money->setAmount( (int) $amount );
		$money->setCurrency( $order->get_currency() );

		$adjustment_charge = new \Square\Models\OrderServiceCharge();
		$adjustment_charge->setUid( wc_square()->get_idempotency_key( 'adjustment-' . $version, false ) );
		$adjustment_charge->setName( __( 'Adjustment', 'woocommerce-square' ) );
		$adjustment_charge->setAmountMoney( $money );
		$adjustment_charge->setCalculationPhase( 'TOTAL_PHASE' );
		$adjustment_charge->setTaxable( false );

		$order_model->setServiceCharges( array_merge( $existing_charges, array( $adjustment_charge ) ) );

		$this->square_request->setIdempotencyKey( wc_square()->get_idempotency_key( $order->unique_transaction_ref ) . $version );
		$this->square_request->setOrder( $order_model );

		$this->square_api_args = array(
			$order->square_order_id,
			$this->square_request,
		);
	}

	/**
	 * Sets the data for searching orders.
	 *
	 * @since 5.0.0
	 *
	 * @param array  $location_ids Array of location IDs to search in.
	 * @param string $start_time   Start time for the search (ISO 8601 format).
	 * @param int    $limit        Maximum number of orders to return.
	 * @param string $cursor       Cursor for pagination.
	 * @param string $end_time     Optional end time for the search (ISO 8601 format).
	 */
	public function set_search_orders_data( $location_ids, $start_time, $limit = 100, $cursor = '', $end_time = '' ) {

		$this->square_api_method = 'searchOrders';
		$this->square_request    = new \Square\Models\SearchOrdersRequest();

		$this->square_request->setLocationIds( $location_ids );
		$this->square_request->setLimit( $limit );

		// Set cursor for pagination if provided.
		if ( ! empty( $cursor ) ) {
			$this->square_request->setCursor( $cursor );
		}

		// Create the query object.
		$query = new \Square\Models\SearchOrdersQuery();

		// Create the filter.
		$filter = new \Square\Models\SearchOrdersFilter();

		// Set date filter with bounded time window.
		$date_filter = new \Square\Models\SearchOrdersDateTimeFilter();
		$time_range  = new \Square\Models\TimeRange();
		$time_range->setStartAt( $start_time );

		// If end time is provided, set it to create a bounded time window.
		if ( ! empty( $end_time ) ) {
			$time_range->setEndAt( $end_time );
		}

		$date_filter->setUpdatedAt( $time_range );
		$filter->setDateTimeFilter( $date_filter );

		// Set states filter - only get OPEN and COMPLETED orders.
		$state_filter = new \Square\Models\SearchOrdersStateFilter( array( 'OPEN', 'COMPLETED', 'CANCELED' ) );
		$filter->setStateFilter( $state_filter );

		$query->setFilter( $filter );

		// Set sort.
		$sort = new \Square\Models\SearchOrdersSort( 'UPDATED_AT' );
		$sort->setSortOrder( 'ASC' );
		$query->setSort( $sort );

		$this->square_request->setQuery( $query );

		$this->square_api_args = array( $this->square_request );
	}

	/**
	 * Check if order fulfillment sync is enabled.
	 *
	 * @since 5.0.0
	 * @return bool True if enabled, false otherwise.
	 */
	public function is_order_fulfillment_sync_enabled() {
		return wc_square()->get_settings_handler()->is_order_fulfillment_sync_enabled();
	}

}
