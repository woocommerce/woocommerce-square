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

defined( 'ABSPATH' ) || exit;

use Exception;
use WC_Product;
use WooCommerce\Square\Handlers\Product;
use WooCommerce\Square\Sync\Records;
use WooCommerce\Square;
use WooCommerce\Square\Gateway\Gift_Card;
use Automattic\WooCommerce\Utilities\FeaturesUtil;

/**
 * Products admin handler.
 *
 * @since 2.0.0
 */
class Products {


	/** @var array associative array of product error codes and messages */
	private $product_errors;

	/** @var array associative array of memoized errors being output for a product at one time */
	private $output_errors = array();

	/** @var int[] array of product IDs that have been scheduled for sync in this request */
	private $products_to_sync = array();

	/** @var int[] array of product IDs that have been scheduled for deletion in this request */
	private $products_to_delete = array();

	/** @var bool whether gift card features are enabled */
	private $gift_card_enabled = 'no';

	/** @var int[] array of product IDs that have been scheduled for inventory sync in this request */
	private $products_to_inventory_sync = array();

	/** @var Square\Plugin plugin instance */
	private $plugin;

	/**
	 * Sets up the products admin handler.
	 *
	 * @since 2.0.0
	 */
	public function __construct( Square\Plugin $plugin ) {

		$this->plugin = $plugin;

		// add common errors
		$this->product_errors = array(
			/* translators: Placeholder: %s - product name */
			'missing_sku'           => __( "Please add an SKU to sync %s with Square. The SKU must match the item's SKU in your Square account.", 'woocommerce-square' ),
			/* translators: Placeholder: %s - product name */
			'missing_variation_sku' => __( "Please add an SKU to every variation of %s for syncing with Square. Each SKU must be unique and match the corresponding item's SKU in your Square account.", 'woocommerce-square' ),
		);

		// Get gift card features status.
		$gift_card_settings      = get_option( Gift_Card::SQUARE_PAYMENT_SETTINGS_OPTION_NAME, array() );
		$this->gift_card_enabled = $gift_card_settings['enabled'] ?? 'no';

		add_action( 'current_screen', array( $this, 'add_tabs' ), 99 );

		// add hooks
		$this->add_products_edit_screen_hooks();
		$this->add_product_edit_screen_hooks();
		$this->add_product_sync_hooks();
	}

	/**
	 * Adds hooks to the admin products edit screen.
	 *
	 * Products filtering, bulk actions, etc.
	 *
	 * @since 2.0.0
	 */
	private function add_products_edit_screen_hooks() {

		// adds an option to the "Filter by product type" dropdown
		add_action( 'restrict_manage_posts', array( $this, 'add_filter_products_synced_with_square_option' ) );
		// allow filtering products by sync status by altering results
		add_filter( 'request', array( $this, 'filter_products_synced_with_square' ) );

		// prevent copying Square data when duplicating a product automatically
		add_action( 'woocommerce_product_duplicate', array( $this, 'handle_product_duplication' ), 20, 2 );

		// handle quick/bulk edit actions in the products edit screen for setting sync status
		add_action( 'woocommerce_product_quick_edit_end', array( $this, 'add_quick_edit_inputs' ) );
		add_action( 'woocommerce_product_bulk_edit_end', array( $this, 'add_bulk_edit_inputs' ) );
		add_action( 'woocommerce_product_quick_edit_save', array( $this, 'set_synced_with_square' ) );
		add_action( 'woocommerce_product_bulk_edit_save', array( $this, 'set_synced_with_square' ) );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'process_post_data' ) );

		// export product sync status.
		add_filter( 'woocommerce_product_export_column_names', array( $this, 'add_sync_status_to_column' ) );
		add_filter( 'woocommerce_product_export_product_default_columns', array( $this, 'add_sync_status_to_column' ) );
		add_filter( 'woocommerce_product_export_product_column_wc_square_synced', array( $this, 'export_sync_status_taxonomy' ), 10, 2 );
		add_filter( 'woocommerce_csv_product_import_mapping_options', array( $this, 'register_sync_status_for_importer' ) );
		add_filter( 'woocommerce_csv_product_import_mapping_default_columns', array( $this, 'map_sync_status_column' ) );
		add_filter( 'woocommerce_product_import_pre_insert_product_object', array( $this, 'import_sync_status' ), 10, 2 );

		if ( 'yes' === $this->gift_card_enabled ) {
			add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'add_input_fields_to_gift_card_product' ) );
			add_filter( 'woocommerce_product_is_taxable', array( $this, 'disable_taxes_for_gift_card_product' ), 10, 2 );
			add_filter( 'woocommerce_product_needs_shipping', array( $this, 'disable_shipping_for_gift_card_product' ), 10, 2 );
			add_filter( 'woocommerce_coupon_is_valid_for_product', array( $this, 'disable_coupons_for_gift_card_product' ), 10, 4 );
			add_filter( 'woocommerce_coupon_is_valid', array( $this, 'coupon_is_valid' ), 10, 2 );
			add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 4 );
			add_filter( 'woocommerce_get_item_data', array( $this, 'add_sent_to_email_to_cart_item' ), 10, 2 );
			add_filter( 'woocommerce_add_to_cart_sold_individually_found_in_cart', array( $this, 'limit_gift_card_quantity_in_cart' ), 10, 2 );
			add_filter( 'woocommerce_order_item_needs_processing', array( $this, 'filter_needs_processing' ), 10, 2 );
			add_filter( 'woocommerce_loop_add_to_cart_link', array( $this, 'filter_shop_page_add_to_cart_button' ), 10, 3 );
			add_filter( 'woocommerce_single_product_image_thumbnail_html', array( $this, 'filter_single_product_featured_image_placeholder' ) );
			add_filter( 'woocommerce_product_get_image', array( $this, 'filter_gift_card_product_featured_image_placeholder' ), 10, 3 );

			// Product blocks support.
			add_filter( 'woocommerce_product_add_to_cart_text', array( $this, 'gift_card_add_to_cart_text' ), 10, 2 );
			add_filter( 'woocommerce_product_add_to_cart_url', array( $this, 'gift_card_add_to_cart_url' ), 10, 2 );
			add_filter( 'woocommerce_product_has_options', array( $this, 'gift_card_product_has_options' ), 10, 2 );
			add_filter( 'woocommerce_product_supports', array( $this, 'gift_card_product_supports' ), 10, 3 );
			add_filter( 'woocommerce_product_get_image_id', array( $this, 'gift_card_product_image_id' ), 10, 2 );
		}
	}

	/**
	 * Adds hooks to individual products edit screens.
	 *
	 * Product data input fields, variations, etc.
	 *
	 * @since 2.0.0
	 */
	private function add_product_edit_screen_hooks() {

		// handle individual products input fields for setting sync status
		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'add_product_data_fields' ) );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'process_product_data' ), 20 );
		add_action( 'woocommerce_before_product_object_save', array( $this, 'maybe_adjust_square_stock' ) );

		add_action( 'admin_notices', array( $this, 'add_notice_product_hidden_from_catalog' ) );

		if ( 'yes' === $this->gift_card_enabled ) {
			add_filter( 'product_type_options', array( __CLASS__, 'add_gift_card_checkbox' ) );
			add_filter( 'woocommerce_product_data_tabs', array( $this, 'filter_product_tabs' ), 50 );
		}
	}

	/**
	 * Adds hooks to sync products that have been updated.
	 *
	 * @since 2.0.0
	 */
	private function add_product_sync_hooks() {

		add_action( 'woocommerce_update_product', array( $this, 'validate_product_update_and_sync' ) );
		add_action( 'trashed_post', array( $this, 'maybe_stage_products_for_deletion' ) );
		add_action( 'shutdown', array( $this, 'maybe_sync_staged_products' ) );
		add_action( 'shutdown', array( $this, 'maybe_delete_staged_products' ) );

		// Sync product inventory when a product is added to the cart.
		add_action( 'woocommerce_add_to_cart', array( $this, 'maybe_stage_products_for_sync_inventory' ), 10, 4 );
		add_action( 'shutdown', array( $this, 'maybe_sync_product_inventory' ) );
	}

	/**
	 * Add help tabs.
	 *
	 * @since 4.7.0
	 */
	public function add_tabs() {
		if ( ! function_exists( 'wc_get_screen_ids' ) ) {
			return;
		}

		$screen = get_current_screen();

		if ( ! $screen || ! in_array( $screen->id, wc_get_screen_ids(), true ) ) {
			return;
		}

		$help_tabs = $screen->get_help_tabs();
		if ( ! isset( $help_tabs['woocommerce_onboard_tab'] ) ) {
			return;
		}

		$updated_help_tab = $help_tabs['woocommerce_onboard_tab'];

		$square_text  = '<h2>' . esc_html__( 'Square Onboarding Setup Wizard', 'woocommerce-square' ) . '</h2>';
		$square_text .= '<p>' . esc_html__( 'If you need to access the Square onboarding setup wizard again, please click on the button below.', 'woocommerce-square' ) . '</p>' .
			'<p><a href="' . esc_url( admin_url( 'admin.php?page=woocommerce-square-onboarding' ) ) . '" class="button button-primary">' . esc_html__( 'Setup wizard', 'woocommerce-square' ) . '</a></p>';

		$updated_help_tab['content'] .= $square_text;

		// Remove the old help tab and add the new one.
		$screen->remove_help_tab( 'woocommerce_onboard_tab' );
		$screen->add_help_tab( $updated_help_tab );
	}

	/**
	 * Adds an option to filter products by sync status.
	 *
	 * @internal
	 *
	 * @since 2.0.0
	 *
	 * @param string $post_type the post type context
	 */
	public function add_filter_products_synced_with_square_option( $post_type ) {

		if ( 'product' !== $post_type ) {
			return;
		}

		$label = esc_html__( 'Synced with Square', 'woocommerce-square' );

		// Nonce check not required, checked against known string, read-only action.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$selected = isset( $_GET['product_type'] ) && 'synced-with-square' === $_GET['product_type'] ? 'selected=\"selected\"' : '';

		wc_enqueue_js(
			"
			jQuery( document ).ready( function( $ ) {
				$( 'select#dropdown_product_type' ) . append( '<option value=\"synced-with-square\" ' + '" . $selected . "' + '>' + '" . $label . "' + '</option>' );
			} );
			"
		);
	}


	/**
	 * Filters products in admin edit screen by sync status with Square.
	 *
	 * @internal
	 *
	 * @since 2.0.0
	 *
	 * @param array $query_vars query variables
	 * @return array
	 */
	public function filter_products_synced_with_square( $query_vars ) {
		global $typenow;

		// Nonce check not required, just filtering products, read-only action.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'product' === $typenow && isset( $_GET['product_type'] ) && 'synced-with-square' === $_GET['product_type'] ) {

			// not really a product type, otherwise WooCommerce will handle it as such
			unset( $query_vars['product_type'] );

			if ( ! isset( $query_vars['tax_query'] ) ) {
				$query_vars['tax_query'] = array(); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			} else {
				$query_vars['tax_query']['relation'] = 'AND';
			}

			$query_vars['tax_query'][] = array(
				'taxonomy' => Product::SYNCED_WITH_SQUARE_TAXONOMY,
				'field'    => 'slug',
				'terms'    => array( 'yes' ),
			);
		}

		return $query_vars;
	}


	/**
	 * Adds general product data options to a product metabox.
	 *
	 * @internal
	 *
	 * @since 2.0.0
	 */
	public function add_product_data_fields() {
		global $product_object;

		if ( ! $product_object instanceof \WC_Product ) {
			return;
		}

		// don't show fields if product sync is disabled
		if ( ! wc_square()->get_settings_handler()->is_product_sync_enabled() ) {
			return;
		}

		?>
		<div class="wc-square-sync-with-square options_group show_if_simple show_if_variable">
			<?php

			$selector = '_' . Product::SYNCED_WITH_SQUARE_TAXONOMY;
			$value    = Product::is_synced_with_square( $product_object ) ? 'yes' : 'no';
			$errors   = $this->check_product_sync_errors( $product_object );

			$setting_label = wc_square()->get_settings_handler()->is_system_of_record_square() ? __( 'Update product data with Square data', 'woocommerce-square' ) : __( 'Send product data to Square', 'woocommerce-square' );

			woocommerce_wp_checkbox(
				array(
					'id'                => $selector,
					'label'             => __( 'Sync with Square', 'woocommerce-square' ),
					'value'             => $value,
					'cbvalue'           => 'yes',
					'default'           => 'no',
					'description'       => $setting_label,
					'custom_attributes' => ! empty( $errors ) ? array( 'disabled' => 'disabled' ) : array(),
				)
			);

			?>
			<p class="form-field wc-square-sync-with-square-errors">
				<?php foreach ( $this->product_errors as $error_code => $error_message ) : ?>
					<?php $styles = ! in_array( $error_code, array_keys( $errors ), true ) ? 'display:none; color:#A00;' : 'display:block; color:#A00;'; ?>
					<span class="wc-square-sync-with-square-error <?php echo sanitize_html_class( $error_code ); ?>" style="<?php echo esc_attr( $styles ); ?>"><?php echo wp_kses_post( $this->format_product_error( $error_code, $product_object ) ); ?></span>
				<?php endforeach; ?>
			</p>

			<input type="hidden" id="<?php echo esc_attr( Product::SQUARE_VARIATION_ID_META_KEY ); ?>" value="<?php echo esc_attr( $product_object->get_meta( Product::SQUARE_VARIATION_ID_META_KEY ) ); ?>" />

		</div>
		<?php
	}


	/**
	 * Outputs HTML with a dropdown field to mark a product to be synced with Square.
	 *
	 * @since 2.0.0
	 *
	 * @param bool $bulk whether the field is meant for bulk edit
	 */
	private function output_synced_with_square_edit_field( $bulk = false ) {

		?>
		<div class="inline-edit-group wc-square-sync-with-square">
			<label>
				<span class="title"><?php esc_html_e( 'Sync with Square?', 'woocommerce-square' ); ?></span>
				<span class="input-text-wrap">
					<select class="square-synced" name="<?php echo esc_attr( Product::SYNCED_WITH_SQUARE_TAXONOMY ); ?>">
						<?php if ( true === $bulk ) : // in bulk actions there's the option to leave the value unchanged (or unset) ?>
							<option value="">&mdash; <?php esc_html_e( 'No change', 'woocommerce-square' ); ?> &mdash;</option>
						<?php endif; ?>
						<option value="no"><?php esc_html_e( 'No', 'woocommerce-square' ); ?></option>
						<option value="yes"><?php esc_html_e( 'Yes', 'woocommerce-square' ); ?></option>
					</select>
				</span>
			</label>
			<p class="form-field wc-square-sync-with-square-errors">
				<?php foreach ( $this->product_errors as $error_code => $error_message ) : ?>
					<?php $product_name_placeholder = __( 'This product', 'woocommerce-square' ); ?>
					<?php $product_name = strtolower( $product_name_placeholder ); ?>
					<span class="wc-square-sync-with-square-error <?php echo esc_attr( $error_code ); ?>" style="display:none; color:#A00;"><?php echo esc_html( sprintf( $error_message, $product_name ) ); ?></span>
				<?php endforeach; ?>
			</p>
		</div>
		<?php
	}


	/**
	 * Adds quick edit fields to the products screen.
	 *
	 * @internal
	 *
	 * @since 2.0.0
	 */
	public function add_quick_edit_inputs() {

		$this->output_synced_with_square_edit_field();
	}


	/**
	 * Adds bulk edit fields to the products screen.
	 *
	 * @internal
	 *
	 * @since 2.0.0
	 */
	public function add_bulk_edit_inputs() {

		$this->output_synced_with_square_edit_field( true );
	}


	/**
	 * In case Woo is the SOR, validates whether a product can be synced with Square and disable sync if not
	 *
	 * @since 2.0.8
	 *
	 * @param int $product_id the product ID
	 */
	public function validate_product_update_and_sync( $product_id ) {
		if ( ! wc_square()->get_settings_handler()->is_system_of_record_woocommerce() ) {
			return;
		}

		$product = wc_get_product( $product_id );

		if ( ! Product::is_synced_with_square( $product ) ) {
			return;
		}

		$errors = $this->check_product_sync_errors( $product );

		if ( ! empty( $errors ) ) {
			// if there are errors, remove the link and display them
			Product::unset_synced_with_square( $product );

			foreach ( $errors as $error ) {
				wc_square()->get_message_handler()->add_error( $error );
				Records::set_record(
					array(
						'type'       => 'alert',
						'product_id' => $product_id,
						'message'    => $error,
					)
				);
			}
		} else {
			$this->maybe_stage_product_for_sync( $product );
		}
	}

	/**
	 * Stages a product for sync with Square on product save if Woo is the SOR and the product is set to 'synced with square'.
	 *
	 * @internal
	 *
	 * @since 2.0.0
	 *
	 * @param WC_Product $product the product object
	 */
	public function maybe_stage_product_for_sync( $product ) {

		if ( ! $product || ! Product::is_synced_with_square( $product ) || in_array( $product->get_id(), $this->products_to_sync, true ) ) {
			return;
		}

		$in_progress = wc_square()->get_sync_handler()->get_job_in_progress();

		if ( $in_progress ) {
			// return early if an import that is updating existing products is in progress.
			if ( isset( $in_progress->update_products_during_import ) && $in_progress->update_products_during_import ) {
				return;
			}

			if ( in_array( $product->get_id(), $in_progress->product_ids, true ) ) {
				return;
			}
		}

		// the triggering action for this method can be called multiple times in a single request - keep track
		// of product IDs that have been scheduled for sync here to avoid multiple syncs on the same request
		$this->products_to_sync[] = $product->get_id();
	}


	/**
	 * Initializes a synchronization event for any staged products in this request.
	 *
	 * @internal
	 *
	 * @since 2.0.0
	 */
	public function maybe_sync_staged_products() {

		if ( ! defined( 'DOING_SQUARE_SYNC' ) && ! empty( $this->products_to_sync ) && wc_square()->get_settings_handler()->is_system_of_record_woocommerce() ) {

			wc_square()->get_sync_handler()->start_manual_sync( $this->products_to_sync );
		}
	}


	/**
	 * Removes a product from Square if it is deleted locally and Woo is the SOR.
	 *
	 * @since 2.0.0
	 *
	 * @param int $product_id the product ID
	 */
	public function maybe_stage_products_for_deletion( $product_id ) {

		if ( wc_square()->get_settings_handler()->is_system_of_record_woocommerce() ) {

			$product = wc_get_product( $product_id );

			if ( $product && Product::is_synced_with_square( $product ) ) {

				// the triggering action for this method can be called multiple times in a single request - keep track
				// of product IDs that have been scheduled for sync here to avoid multiple syncs on the same request
				$this->products_to_delete[] = $product_id;
			}
		}
	}


	/**
	 * Deletes any products staged for remote deletion.
	 *
	 * @since 2.0.0
	 */
	public function maybe_delete_staged_products() {

		if ( ! empty( $this->products_to_delete ) && wc_square()->get_settings_handler()->is_system_of_record_woocommerce() ) {

			wc_square()->get_sync_handler()->start_manual_deletion( $this->products_to_delete );
		}
	}


	/**
	 * Sets a product's synced with Square status for quick/bulk edit action.
	 *
	 * @internal
	 *
	 * @since 2.0.0
	 *
	 * @param \WC_Product $product a product object
	 */
	public function set_synced_with_square( $product ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended
		$posted_data_key = Product::SYNCED_WITH_SQUARE_TAXONOMY;

		if ( 'woocommerce_product_bulk_edit_save' === current_action() ) {
			$default_value = null; // in bulk actions this will preserve the existing setting if nothing is specified
		} else {
			$default_value = 'no'; // in individual products context, the value should be always an explicit yes or no
		}

		$square_synced = isset( $_REQUEST[ $posted_data_key ] ) && in_array( $_REQUEST[ $posted_data_key ], array( 'yes', 'no' ), true ) ? sanitize_key( $_REQUEST[ $posted_data_key ] ) : $default_value; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( is_string( $square_synced ) ) {
			$errors = $this->check_product_sync_errors( $product );
			if ( 'no' === $square_synced || empty( $errors ) ) {
				Product::set_synced_with_square( $product, $square_synced );
			} elseif ( ! empty( $errors ) ) {
				foreach ( $errors as $error ) {
					wc_square()->get_message_handler()->add_error( $error );
				}
			}
		}

		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}


	/**
	 * Updates Square sync status for a product upon saving.
	 *
	 * @internal
	 *
	 * @since 2.0.0
	 *
	 * @param \WC_Product $product product object
	 */
	public function process_product_data( $product ) {

		// don't process fields if product sync is disabled
		if ( ! wc_square()->get_settings_handler()->is_product_sync_enabled() ) {
			return;
		}

		// bail if no valid product found, if it's a variation, errors have already been output
		if ( ! $product || ( $product instanceof \WC_Product_Variation || $product->is_type( 'product_variation' ) ) || ! empty( $this->output_errors[ $product->get_id() ] ) ) {
			return;
		}

		$errors     = array();
		$posted_key = '_' . Product::SYNCED_WITH_SQUARE_TAXONOMY;
		$set_synced = isset( $_POST[ $posted_key ] ) && 'yes' === sanitize_key( $_POST[ $posted_key ] ); // phpcs:ignore
		$was_synced = Product::is_synced_with_square( $product );

		// condition has unchanged
		if ( ! $set_synced && ! $was_synced ) {
			return;
		}

		if ( $set_synced || $was_synced ) {
			if ( $set_synced && $product->is_type( 'variable' ) && wc_square()->get_settings_handler()->is_inventory_sync_enabled() ) {
				// if syncing inventory with Square, parent variable products don't manage stock
				$product->set_manage_stock( false );
			}

			// finally, set the product sync with Square flag
			Product::set_synced_with_square( $product, $set_synced ? 'yes' : 'no' );
		}
	}


	/**
	 * Adjusts a product's Square stock.
	 *
	 * @since 2.0.0
	 *
	 * @param \WC_Product $product product object
	 */
	public function maybe_adjust_square_stock( $product ) {
		$is_new_product_editor_enabled = FeaturesUtil::feature_is_enabled( 'product_block_editor' );

		// this is hooked in to general product object save, so scope to specifically saving products via the admin
		if ( $is_new_product_editor_enabled && ! wc_rest_is_from_product_editor() ) {
			return;
		} elseif ( ! $is_new_product_editor_enabled && ! doing_action( 'wp_ajax_woocommerce_save_variations' ) && ! doing_action( 'woocommerce_admin_process_product_object' ) ) {
			return;
		}

		// only send stock updates for Woo SOR
		if ( ! wc_square()->get_settings_handler()->is_system_of_record_woocommerce() || ! wc_square()->get_settings_handler()->is_inventory_sync_enabled() ) {
			return;
		}

		if ( ! $product instanceof \WC_Product || ! Product::is_synced_with_square( $product ) ) {
			return;
		}

		$square_id = $product->get_meta( Product::SQUARE_VARIATION_ID_META_KEY );

		// only send when the product has an associated Square ID
		if ( ! $square_id ) {
			return;
		}

		$data    = $product->get_data();
		$changes = $product->get_changes();
		$change  = 0;

		if ( isset( $data['stock_quantity'], $changes['stock_quantity'] ) ) {
			$change = (int) $changes['stock_quantity'] - $data['stock_quantity'];
		}

		if ( 0 !== $change ) {

			try {

				if ( $change > 0 ) {
					wc_square()->get_api()->add_inventory( $square_id, $change );
				} else {
					wc_square()->get_api()->remove_inventory( $square_id, $change );
				}
			} catch ( \Exception $exception ) {

				wc_square()->log( 'Could not adjust Square inventory for ' . $product->get_formatted_name() . '. ' . $exception->getMessage() );

				$quantity = (float) $data['stock_quantity'];

				// if the API request fails, set the product quantity back from whence it came
				$product->set_stock_quantity( $quantity );
			}
		}
	}


	/**
	 * Prevents copying Square data when duplicating a product in admin.
	 *
	 * @internal
	 *
	 * @since 2.0.0
	 *
	 * @param \WC_Product $duplicated_product product duplicate
	 * @param \WC_Product $original_product product duplicated
	 */
	public function handle_product_duplication( $duplicated_product, $original_product ) {

		if ( Product::is_synced_with_square( $original_product ) ) {
			Product::unset_synced_with_square( $duplicated_product );
		}

		$duplicated_product->delete_meta_data( Product::SQUARE_ID_META_KEY );
		$duplicated_product->delete_meta_data( Product::SQUARE_VARIATION_ID_META_KEY );

		if ( $duplicated_product->is_type( 'variable' ) ) {

			foreach ( $duplicated_product->get_children() as $duplicated_variation_id ) {
				$duplicated_product_variation = wc_get_product( $duplicated_variation_id );
				if ( $duplicated_product_variation ) {

					$duplicated_product_variation->delete_meta_data( Product::SQUARE_VARIATION_ID_META_KEY );
					$duplicated_product_variation->save_meta_data();
				}
			}
		}

		$duplicated_product->save_meta_data();
	}


	/**
	 * Outputs an admin notice when a product was hidden from catalog upon a sync error.
	 *
	 * @internal
	 *
	 * @since 2.0.0
	 */
	public function add_notice_product_hidden_from_catalog() {
		global $current_screen, $post;

		if ( $post && $current_screen && 'product' === $current_screen->id ) {

			$product = wc_get_product( $post );

			if ( $product && 'hidden' === $product->get_catalog_visibility() ) {

				$product_id = $product->get_id();
				$records    = Records::get_records( array( 'product' => $product_id ) );

				foreach ( $records as $record ) {

					if ( $record->was_product_hidden() && $product_id === $record->get_product_id() ) {

						wc_square()->get_message_handler()->add_warning(
							sprintf(
								/* translators: Placeholder: %1$s - date (localized), %2$s - time (localized), %3$s - opening <a> HTML link tag, %4$s closing </a> HTML link tag */
								esc_html__( 'The product catalog visibility has been set to "hidden", as a matching product could not be found in Square on %1$s at %2$s. %3$sCheck sync records%4$s.', 'woocommerce-square' ),
								date_i18n( wc_date_format(), $record->get_timestamp() ),
								date_i18n( wc_time_format(), $record->get_timestamp() ),
								'<a href="' . esc_url( add_query_arg( array( 'section' => 'update' ), wc_square()->get_settings_url() ) ) . '">',
								'</a>'
							)
						);

						break;
					}
				}
			}
		}
	}


	/**
	 * Check whether this product can be synced with Square
	 *
	 * @param \WC_Product $product product object
	 * @return array errors
	 */
	private function check_product_sync_errors( \WC_Product $product ) {

		$errors = array();
		if ( ! Product::has_sku( $product ) ) {
			if ( $product->is_type( 'variable' ) ) {
				$errors['missing_variation_sku'] = $this->format_product_error( 'missing_variation_sku', $product );
			} else {
				$errors['missing_sku'] = $this->format_product_error( 'missing_sku', $product );
			}
		}

		return $errors;
	}


	/**
	 * Formats product error message with product information
	 *
	 * @param string $error error identifier (e.g. 'missing_variation_sku' or 'missing_sku')
	 * @param \WC_Product $product product object
	 * @return string formatted error message
	 */
	private function format_product_error( string $error, \WC_Product $product ) {
		return sprintf(
			$this->product_errors[ $error ],
			Product::get_product_edit_link( $product )
		);
	}


	/**
	 * Gets the plugin instance.
	 *
	 * @since 2.0.8
	 *
	 * @return Plugin
	 */
	protected function get_plugin() {
		return $this->plugin;
	}

	/**
	 * Adds the sync status column to the export columns.
	 *
	 * @param array $columns Array of columns
	 * @return array
	 */
	public function add_sync_status_to_column( $columns ) {
		$columns['wc_square_synced'] = __( 'Sync with Square', 'woocommerce-square' );
		return $columns;
	}

	/**
	 * Sets column data.
	 *
	 * @param mixed       $value   Value of a column
	 * @param \WC_Product $product WooCommerce product object.
	 */
	public function export_sync_status_taxonomy( $value, $product ) {
		$terms = get_terms(
			array(
				'object_ids' => $product->get_ID(),
				'taxonomy'   => 'wc_square_synced',
			)
		);

		if ( ! is_wp_error( $terms ) && is_array( $terms ) && count( $terms ) > 0 ) {
			$term = $terms[0];

			return $term->name;
		}

		return 'no';
	}

	/**
	 * Registers the sync status to the importer.
	 *
	 * @param array $columns Array of columns
	 * @return array
	 */
	public function register_sync_status_for_importer( $columns ) {
		$columns['wc_square_synced'] = __( 'Sync with Square', 'woocommerce-square' );
		return $columns;
	}

	/**
	 * Add automatic mapping support for wc_square_synced column.
	 *
	 * @param array $columns Array of columns
	 * @return array
	 */
	public function map_sync_status_column( $columns ) {
		$columns[ __( 'Sync with Square', 'woocommerce-square' ) ] = 'wc_square_synced';
		return $columns;
	}

	/**
	 * Imports square sync status.
	 *
	 * @param \WC_Product $product WooCommerce product.
	 * @param array       Import data.
	 *
	 * @return array
	 */
	public function import_sync_status( $product, $data ) {
		if ( is_a( $product, 'WC_Product' ) ) {

			if ( ! empty( $data['wc_square_synced'] ) ) {
				switch ( $data['wc_square_synced'] ) {
					case 'yes':
						wp_set_object_terms( $product->get_id(), array( 'yes' ), 'wc_square_synced' );
						break;

					case 'no':
						wp_set_object_terms( $product->get_id(), array( 'no' ), 'wc_square_synced' );
						break;
				}
			}
		}

		return $product;
	}

	/**
	 * Returns array of product types that support a Square Gift Card.
	 *
	 * @since 4.2.0
	 * @return array
	 */
	public static function get_gift_card_compatible_product_types() {
		return array(
			'simple',
			'variable',
		);
	}

	/**
	 * Add checkbox in product type options.
	 *
	 * @since 4.2.0
	 *
	 * @param  array $actions Array of actions.
	 * @return array
	 */
	public static function add_gift_card_checkbox( $actions ) {
		global $product_object;

		$wrapper_classes = array();

		foreach ( self::get_gift_card_compatible_product_types() as $type ) {
			$wrapper_classes[] = 'show_if_' . $type;
		}

		$wrapper_classes[] = 'hide_if_bundle';
		$wrapper_classes[] = 'hide_if_composite';

		$actions['square_gift_card'] = array(
			'id'            => Product::SQUARE_GIFT_CARD_KEY,
			'wrapper_class' => implode( ' ', $wrapper_classes ),
			'label'         => __( 'Square Gift Card', 'woocommerce-square' ),
			'description'   => __( 'Square Gift cards are virtual products that can be purchased by customers and gifted to one or more recipients. Gift card code holders can redeem and use them as store credit.', 'woocommerce-square' ),
			'default'       => Product::is_gift_card( $product_object ) ? 'yes' : 'no',
		);

		return $actions;
	}

	/**
	 * Removes product settings tabs that are irrelevant to the Gift Card product type.
	 *
	 * @since 4.2.0
	 *
	 * @param array $tabs Array of tabs
	 * @return array
	 */
	public static function filter_product_tabs( $tabs ) {
		$tabs['shipping']['class'][] = 'hide_if_square_gift_card';

		return $tabs;
	}

	/**
	 * Handles a gift card product on publish/update.
	 *
	 * @since 4.2.0
	 *
	 * @param \WC_Product $product WooCommerce product.
	 * @return void
	 */
	public static function process_post_data( $product ) {
		if ( ! $product->is_type( self::get_gift_card_compatible_product_types() ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing

		// Is gift card.
		if ( isset( $_POST[ Product::SQUARE_GIFT_CARD_KEY ] ) ) {
			$product->update_meta_data( Product::SQUARE_GIFT_CARD_KEY, 'yes' );
			$product->set_virtual( true );
			$product->set_sold_individually( 'yes' );
			$product->set_tax_status( 'none' );
		} elseif ( 'yes' === $product->get_meta( Product::SQUARE_GIFT_CARD_KEY ) ) {
			$product->delete_meta_data( Product::SQUARE_GIFT_CARD_KEY );
			$product->set_virtual( false );
			$product->set_sold_individually( 'no' );
			$product->set_tax_status( 'taxable' );
		}

		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	/**
	 * Adds email address input fields to sent gift cards.
	 *
	 * @since 4.2.0
	 */
	public static function add_input_fields_to_gift_card_product() {
		global $product;

		if ( ! Product::is_gift_card( $product ) ) {
			return;
		}

		$buying_option   = isset( $_POST['square-gift-card-buying-option'] ) ? sanitize_text_field( wp_unslash( $_POST['square-gift-card-buying-option'] ) ) : false; // phpcs:ignore
		$gan             = isset( $_POST['square-gift-card-gan'] ) ? sanitize_text_field( wp_unslash( $_POST['square-gift-card-gan'] ) ) : false; // phpcs:ignore
		$is_load_checked = 'load' === $buying_option && ! empty( $gan );

		?>

		<div id="square-gift-card-buying-options">
			<label for="square-gift-card-buying-option__new">
				<input type="radio" name="square-gift-card-buying-option" id="square-gift-card-buying-option__new" <?php echo ! $is_load_checked ? 'checked' : ''; ?> value="new" />
				<?php esc_html_e( 'Buy a new gift card', 'woocommerce-square' ); ?>
			</label>

			<label for="square-gift-card-buying-option__reload">
				<input type="radio" name="square-gift-card-buying-option" id="square-gift-card-buying-option__reload" <?php echo $is_load_checked ? 'checked' : ''; ?> value="load" />
				<?php esc_html_e( 'Add value to an existing gift card', 'woocommerce-square' ); ?>
			</label>

			<div id="square-gift-card-email-to-wrapper" data-square-gift-card-activity="new" <?php echo $is_load_checked ? 'style="display: none;"' : ''; ?>>
				<div class="square-gift-card-field-wrapper">
					<label><?php esc_html_e( "Sender's name", 'woocommerce-square' ); ?><span class="wc-square-required-indicator">&nbsp;*</span></label>
					<input type="text" name="square-gift-card-sender-name" <?php echo $is_load_checked ? '' : 'required'; ?>/>
				</div>

				<div class="square-gift-card-field-wrapper">
					<label><?php esc_html_e( "Recipient's email", 'woocommerce-square' ); ?><span class="wc-square-required-indicator">&nbsp;*</span></label>
					<input type="email" name="square-gift-card-send-to-email" <?php echo $is_load_checked ? '' : 'required'; ?>/>
				</div>

				<div class="square-gift-card-field-wrapper">
					<label><?php esc_html_e( "Recipient's name", 'woocommerce-square' ); ?><span class="wc-square-required-indicator">&nbsp;*</span></label>
					<input type="text" name="square-gift-card-sent-to-first-name" id="square-gift-card-sent-to-first-name" <?php echo $is_load_checked ? '' : 'required'; ?>/>
				</div>

				<div class="square-gift-card-field-wrapper">
					<label><?php esc_html_e( 'Message', 'woocommerce-square' ); ?></label>
					<textarea name="square-gift-card-sent-to-message" id="square-gift-card-sent-to-message" cols="30" rows="5"></textarea>
				</div>
			</div>

			<div id="square-gift-card-gan-wrapper" data-square-gift-card-activity="load" <?php echo ! $is_load_checked ? 'style="display: none;"' : ''; ?>>
				<label><?php esc_html_e( 'Enter the Gift Card number', 'woocommerce-square' ); ?><span class="wc-square-required-indicator">&nbsp;*</span></label>
				<input type="text" pattern="[0-9]+" name="square-gift-card-gan" <?php echo $is_load_checked ? 'required' : 'disabled'; ?> placeholder="<?php esc_attr_e( 'Gift Card number', 'woocommerce-square' ); ?>" value="<?php echo $is_load_checked ? esc_attr( $gan ) : ''; ?>" />
			</div>
		</div>
		<?php
	}

	/**
	 * Disables taxes for a Square Gift Card.
	 *
	 * @since 4.2.0
	 *
	 * @param boolean     $tax_status Indicates whether taxes should be applied to a WooCommerce product.
	 * @param \WC_Product $product    The WooCommerce product object.
	 *
	 * @return boolean
	 */
	public function disable_taxes_for_gift_card_product( $tax_status, $product ) {
		if ( Product::is_gift_card( $product ) ) {
			return false;
		}

		return $tax_status;
	}

	/**
	 * Disables shipping for a Square Gift Card.
	 *
	 * @since 4.2.0
	 *
	 * @param boolean     $needs_shipping Indicates whether shipping is required for a WooCommerce product.
	 * @param \WC_Product $product        The WooCommerce product object.
	 *
	 * @return boolean
	 */
	public function disable_shipping_for_gift_card_product( $needs_shipping, $product ) {
		if ( $product->is_type( 'variation' ) ) {
			$parent_id = $product->get_parent_id();
			$product   = wc_get_product( $parent_id );
		}

		if ( Product::is_gift_card( $product ) ) {
			return false;
		}

		return $needs_shipping;
	}

	/**
	 * Disables coupons for a Square Gift Card.
	 *
	 * @since 4.2.0
	 *
	 * @param boolean     $is_valid Indicates whether coupons are applicable to a WooCommerce product.
	 * @param \WC_Product $product  The WooCommerce product object.
	 * @param \WC_Coupon  $coupon   The WooCommerce coupon object.
	 * @param array       $values   Cart item values.
	 *
	 * @return boolean
	 */
	public function disable_coupons_for_gift_card_product( $is_valid, $product, $coupon, $values ) {
		if ( Product::is_gift_card( $product ) ) {
			return false;
		}

		return $is_valid;
	}

	/**
	 * Invalidate coupons when used with gift card products.
	 *
	 * @since 4.2.0
	 *
	 * @param  bool       $is_valid Whether a coupon is valid.
	 * @param  WC_Coupon  $coupon   The coupon being applied.
	 * @return bool
	 */
	public function coupon_is_valid( $is_valid, $coupon ) {
		if ( $is_valid ) {
			switch ( $coupon->get_discount_type() ) {
				case 'fixed_cart':
					if ( Gift_Card::cart_contains_gift_card() ) {
						throw new Exception( esc_html__( 'Sorry, this coupon is not applicable to gift card products.', 'woocommerce-square' ) );
					}
					break;
			}
		}

		return $is_valid;
	}

	/**
	 * Adds Gift Card send-to email address to cart item.
	 *
	 * @since 4.2.0
	 *
	 * @param array $cart_item_data Array of cart items.
	 * @param int   $product_id     Woo product ID.
	 * @param int   $variation_id   Woo product variation ID.
	 * @param int   $quantity       Quantity of a product added to cart.
	 */
	public function add_cart_item_data( $cart_item_data, $product_id, $variation_id, $quantity ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$product = wc_get_product( $product_id );

		// Return if product is not a gift card product.
		if ( ! Product::is_gift_card( $product ) ) {
			return $cart_item_data;
		}

		// Add email data.
		if ( Gift_Card::is_new() && isset( $_POST['square-gift-card-send-to-email'] ) && ! empty( $_POST['square-gift-card-send-to-email'] ) ) {
			$sender_name = isset( $_POST['square-gift-card-sender-name'] ) ? wc_clean( wp_unslash( $_POST['square-gift-card-sender-name'] ) ) : '';
			$email       = isset( $_POST['square-gift-card-send-to-email'] ) ? is_email( wp_unslash( $_POST['square-gift-card-send-to-email'] ) ) : '';
			$first_name  = isset( $_POST['square-gift-card-sent-to-first-name'] ) ? wc_clean( wp_unslash( $_POST['square-gift-card-sent-to-first-name'] ) ) : '';
			$message     = isset( $_POST['square-gift-card-sent-to-message'] ) ? wc_clean( wp_unslash( $_POST['square-gift-card-sent-to-message'] ) ) : '';

			if ( $sender_name ) {
				$cart_item_data['square-gift-card-sender-name'] = $sender_name;
			}

			if ( $email ) {
				$cart_item_data['square-gift-card-send-to-email'] = $email;
			}

			if ( $first_name ) {
				$cart_item_data['square-gift-card-sent-to-first-name'] = $first_name;
			}

			if ( $message ) {
				$cart_item_data['square-gift-card-sent-to-message'] = $message;
			}
		}

		// Add gift card number.
		if ( Gift_Card::is_load() && isset( $_POST['square-gift-card-gan'] ) ) {
			if ( empty( $_POST['square-gift-card-gan'] ) ) {
				throw new Exception( esc_html__( 'The gift card number field is empty.', 'woocommerce-square' ) );
			}

			$cart_item_data['square-gift-card-gan'] = wc_clean( wp_unslash( $_POST['square-gift-card-gan'] ) );

			$response = $this->get_plugin()->get_gateway()->get_api()->retrieve_gift_card_by_gan( $cart_item_data['square-gift-card-gan'] );

			if ( ! $response->get_data() instanceof \Square\Models\RetrieveGiftCardFromGANResponse ) {
				throw new Exception( esc_html__( 'The gift card number is either invalid or does not exist.', 'woocommerce-square' ) );
			}
		}

		// phpcs:enable WordPress.Security.NonceVerification.Missing
		return $cart_item_data;
	}

	/**
	 * Adds gift card meta to cart item.
	 *
	 * @since 4.2.0
	 *
	 * @param array $item_data Cart item data. Empty by default.
	 * @param array $cart_item Cart item array.
	 */
	public function add_sent_to_email_to_cart_item( $item_data, $cart_item ) {
		if ( ! empty( $cart_item['square-gift-card-sender-name'] ) ) {
			$item_data[] = array(
				'key'   => esc_html__( "Sender's name", 'woocommerce-square' ),
				'value' => wc_clean( $cart_item['square-gift-card-sender-name'] ),
			);
		}

		if ( ! empty( $cart_item['square-gift-card-send-to-email'] ) ) {
			$item_data[] = array(
				'key'   => esc_html__( "Recipient's email", 'woocommerce-square' ),
				'value' => wc_clean( $cart_item['square-gift-card-send-to-email'] ),
			);
		}

		if ( ! empty( $cart_item['square-gift-card-gan'] ) ) {
			$item_data[] = array(
				'key'   => esc_html__( 'Gift card number', 'woocommerce-square' ),
				'value' => wc_clean( $cart_item['square-gift-card-gan'] ),
			);
		}

		if ( ! empty( $cart_item['square-gift-card-sent-to-first-name'] ) ) {
			$item_data[] = array(
				'key'   => esc_html__( "Recipient's name", 'woocommerce-square' ),
				'value' => wc_clean( $cart_item['square-gift-card-sent-to-first-name'] ),
			);
		}

		if ( ! empty( $cart_item['square-gift-card-sent-to-message'] ) ) {
			$item_data[] = array(
				'key'   => esc_html__( 'Message', 'woocommerce-square' ),
				'value' => wc_clean( $cart_item['square-gift-card-sent-to-message'] ),
			);
		}

		return $item_data;
	}

	/**
	 * Replaces the `Add to cart` button on the shop page
	 * to `Buy Gift Card` for a gift card product.
	 *
	 * @param string      $html    Add to Cart button HTML.
	 * @param \WC_Product $product WooCommerce product.
	 * @param array       $args    Attributes for the button.
	 */
	public function filter_shop_page_add_to_cart_button( $html, $product, $args ) {
		if ( ! is_shop() ) {
			return $html;
		}

		/** @var \WC_Product $product */
		if ( ! Product::is_gift_card( $product ) ) {
			return $html;
		}

		return sprintf(
			'<a href="%s" class="%s" %s>%s</a>',
			esc_url( $product->get_permalink() ),
			'button wp-element-button',
			isset( $args['attributes'] ) ? wc_implode_html_attributes( $args['attributes'] ) : '',
			esc_html__( 'Buy Gift Card', 'woocommerce-square' )
		);
	}

	/**
	 * Adds a custom gift card placeholder image to products that are marked
	 * as a gift card.
	 *
	 * @since 4.2.0
	 *
	 * @param string      $image   HTML string for image.
	 * @param \WC_Product $product WooCommerce product.
	 * @param string      $size (default: 'woocommerce_thumbnail').
	 *
	 * @return string
	 */
	public function filter_gift_card_product_featured_image_placeholder( $image, $product, $size ) {
		if ( ! self::should_use_default_gift_card_placeholder_image() ) {
			return $image;
		}

		if ( has_post_thumbnail( $product->get_id() ) ) {
			return $image;
		}

		if ( ! Product::is_gift_card( $product ) ) {
			return $image;
		}

		$placeholder_image_id = self::get_gift_card_default_placeholder_id();

		$default_attr = array(
			'class' => 'woocommerce-placeholder wp-post-image',
			'alt'   => __( 'Placeholder', 'woocommerce-square' ),
		);

		if ( wp_attachment_is_image( $placeholder_image_id ) ) {
			$image = wp_get_attachment_image(
				$placeholder_image_id,
				$size,
				false,
				$default_attr
			);
		}

		return $image;
	}

	/**
	 * Adds a custom gift card placeholder image to product that are marked
	 * as a gift card on the single product page.
	 *
	 * @since 4.2.0
	 *
	 * @param string $html HTML string for image.
	 *
	 * @return string
	 */
	public function filter_single_product_featured_image_placeholder( $html ) {
		if ( ! self::should_use_default_gift_card_placeholder_image() ) {
			return $html;
		}

		$product_id = get_the_ID();

		if ( ! $product_id ) {
			return $html;
		}

		$product = wc_get_product( $product_id );

		if ( ! $product instanceof \WC_Product ) {
			return $html;
		}

		if ( is_product() && has_post_thumbnail( $product->get_id() ) ) {
			return $html;
		}

		if ( ! Product::is_gift_card( $product ) ) {
			return $html;
		}

		$placeholder_image_id = self::get_gift_card_default_placeholder_id();

		if ( wp_attachment_is_image( $placeholder_image_id ) ) {
			$html = wc_get_gallery_image_html( $placeholder_image_id, true );
		}

		return $html;
	}

	/**
	 * Limits adding a single gift card product to cart per order.
	 *
	 * @since 4.2.0
	 *
	 * @param boolean $found_in_cart Indicates if the product is found in cart.
	 * @param int     $product_id    The ID of the product being added to the cart.
	 *
	 * @return boolean
	 */
	public function limit_gift_card_quantity_in_cart( $found_in_cart, $product_id ) {
		$product = wc_get_product( $product_id );

		if ( Product::is_gift_card( $product ) && Gift_Card::cart_contains_gift_card() ) {
			$message         = esc_html__( 'You can only add 1 gift card product to your cart per order.', 'woocommerce-square' );
			$wp_button_class = wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '';
			throw new Exception( sprintf( '<a href="%s" class="button wc-forward%s">%s</a> %s', esc_url( wc_get_cart_url() ), esc_attr( $wp_button_class ), esc_html__( 'View cart', 'woocommerce-square' ), esc_html( $message ) ) );
		}

		return $found_in_cart;
	}

	/**
	 * Disables processing for a gift card product so that the order goes to
	 * the `completed` state.
	 *
	 * @since 4.2.0
	 *
	 * @param boolean     $virtual_downloadable_item Is a product virtual & downloadable item.
	 * @param \WC_Product $product                   Current product being processed.
	 */
	public function filter_needs_processing( $virtual_downloadable_item, $product ) {
		if ( Product::is_gift_card( $product ) ) {
			return false;
		}

		return $virtual_downloadable_item;
	}

	/**
	 * Stage products for inventory sync when product is added to cart.
	 *
	 * @param string $cart_item_key  Cart item key.
	 * @param int    $product_id     Product ID.
	 * @param int    $quantity       Quantity.
	 * @param int    $variation_id   Variation ID.
	 *
	 * @since 4.1.0
	 */
	public function maybe_stage_products_for_sync_inventory( $cart_item_key, $product_id, $quantity, $variation_id ) {
		if ( ! $product_id && ! $variation_id ) {
			return;
		}

		// Bail if inventory sync is not enabled.
		if ( ! wc_square()->get_settings_handler()->is_inventory_sync_enabled() ) {
			return;
		}

		if ( $variation_id ) {
			$product_id = $variation_id;
		}

		// Bail if the product is not synced with Square.
		$product = wc_get_product( $product_id );
		if ( ! $product instanceof \WC_Product || ! Product::is_synced_with_square( $product ) ) {
			return;
		}

		// Stage product for inventory sync.
		$this->products_to_inventory_sync[] = $product_id;
	}

	/**
	 * Sync product inventory of staged products.
	 *
	 * @since 4.1.0
	 */
	public function maybe_sync_product_inventory() {
		if ( ! empty( $this->products_to_inventory_sync ) && wc_square()->get_settings_handler()->is_inventory_sync_enabled() ) {
			// Sync product inventory asynchronously.
			$async_request = $this->plugin->get_async_request_handler();
			if ( $async_request instanceof Async_Request ) {
				$async_request->data( array( 'product_ids' => $this->products_to_inventory_sync ) )->dispatch();
			}
		}
	}

	/**
	 * Add to cart text.
	 *
	 * @param string      $text    Add to cart text.
	 * @param \WC_Product $product Product object.
	 * @return string
	 */
	public function gift_card_add_to_cart_text( $text, $product ) {
		if ( ! is_a( $product, 'WC_Product' ) ) {
			return $text;
		}

		if ( is_single( $product->get_id() ) ) {
			return $text;
		}

		if ( Product::is_gift_card( $product ) ) {
			return esc_html__( 'Buy Gift Card', 'woocommerce-square' );
		}

		return $text;
	}

	/**
	 * Add to cart URL.
	 *
	 * @param string      $url     Add to cart URL.
	 * @param \WC_Product $product Product object.
	 * @return string
	 */
	public function gift_card_add_to_cart_url( $url, $product ) {
		if ( ! is_a( $product, 'WC_Product' ) ) {
			return $url;
		}

		if ( is_single( $product->get_id() ) ) {
			return $url;
		}

		if ( Product::is_gift_card( $product ) ) {
			return get_permalink( $product->get_id() );
		}

		return $url;
	}

	/**
	 * Determine if the Product has options.
	 *
	 * This will change the add to card button link to product page.
	 *
	 * @param boolean     $has_options Whether the product has options.
	 * @param \WC_Product $product     Product object.
	 */
	public function gift_card_product_has_options( $has_options, $product ) {
		if ( ! is_a( $product, 'WC_Product' ) ) {
			return $has_options;
		}

		if ( Product::is_gift_card( $product ) ) {
			return true;
		}

		return $has_options;
	}

	/**
	 * Determine if the Product supports a feature.
	 *
	 * Disable AJAX add to cart for gift card products.
	 *
	 * @param boolean     $supports Whether the product supports a feature.
	 * @param string      $feature  Feature.
	 * @param \WC_Product $product  Product object.
	 * @return boolean
	 */
	public function gift_card_product_supports( $supports, $feature, $product ) {
		if ( ! is_a( $product, 'WC_Product' ) ) {
			return $supports;
		}

		if ( 'ajax_add_to_cart' === $feature && Product::is_gift_card( $product ) ) {
			return false;
		}

		return $supports;
	}

	/**
	 * Adds a custom gift card placeholder image to products that are marked
	 * as a gift card.
	 *
	 * @param string      $image_id Image ID.
	 * @param \WC_Product $product  WooCommerce product.
	 *
	 * @return string
	 */
	public function gift_card_product_image_id( $image_id, $product ) {
		if ( ! self::should_use_default_gift_card_placeholder_image() ) {
			return $image_id;
		}

		if ( ! Product::is_gift_card( $product ) ) {
			return $image_id;
		}

		if ( has_post_thumbnail( $product->get_id() ) ) {
			return $image_id;
		}

		if ( empty( $image_id ) ) {
			$placeholder_image_id = self::get_gift_card_default_placeholder_id();

			if ( $placeholder_image_id ) {
				$image_id = $placeholder_image_id;
			}
		}

		return $image_id;
	}

	/**
	 * Returns true if a gift card product should use the provided
	 * default placeholder image.
	 *
	 * @since 4.8.1
	 *
	 * @return bool
	 */
	public static function should_use_default_gift_card_placeholder_image() {
		$settings   = get_option( Gift_Card::SQUARE_PAYMENT_SETTINGS_OPTION_NAME, array() );
		$is_enabled = isset( $settings['enabled'] ) && 'yes' === $settings['enabled'];

		if ( ! $is_enabled ) {
			return false;
		}

		$should_use_placeholder = isset( $settings['is_default_placeholder'] ) && 'yes' === $settings['is_default_placeholder'];

		if ( ! $should_use_placeholder ) {
			return false;
		}

		return true;
	}

	/**
	 * Returns the default placeholder image ID for gift card products.
	 *
	 * @since 4.8.1
	 *
	 * @return int
	 */
	public static function get_gift_card_default_placeholder_id() {
		$settings = get_option( Gift_Card::SQUARE_PAYMENT_SETTINGS_OPTION_NAME, array() );

		return (int) ( $settings['placeholder_id'] ?? 0 );
	}

	/**
	 * Returns the default placeholder image URL for gift card products.
	 *
	 * @since 4.8.1
	 *
	 * @return string|bool
	 */
	public static function get_gift_card_default_placeholder_url() {
		$placeholder_id = self::get_gift_card_default_placeholder_id();

		if ( ! $placeholder_id ) {
			return '';
		}

		$attachment = get_post( $placeholder_id );

		if ( ! $attachment ) {
			return '';
		}

		return wp_get_attachment_url( $attachment->ID );
	}
}
