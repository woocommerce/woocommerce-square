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

use WooCommerce\Square\Handlers\Product;
use WooCommerce\Square\Sync\Records;

/**
 * AJAX handler.
 *
 * @since 2.0.0
 */
class AJAX {


	/**
	 * Adds AJAX action callbacks.
	 *
	 * @since 2.0.0
	 */
	public function __construct() {

		// check an individual product sync status
		add_action( 'wp_ajax_wc_square_get_quick_edit_product_details', array( $this, 'get_quick_edit_product_details' ) );

		// fetch product stock from Square
		add_action( 'wp_ajax_wc_square_fetch_product_stock_with_square', array( $this, 'fetch_product_stock_with_square' ) );

		add_action( 'wp_ajax_wc_square_import_products_from_square', array( $this, 'import_products_from_square' ) );

		// sync all products with Square
		add_action( 'wp_ajax_wc_square_sync_products_with_square', array( $this, 'sync_products_with_square' ) );

		// handle sync records
		add_action( 'wp_ajax_wc_square_handle_sync_records', array( $this, 'handle_sync_records' ) );

		// get the status of a sync job
		add_action( 'wp_ajax_wc_square_get_sync_with_square_status', array( $this, 'get_sync_with_square_job_status' ) );
	}

	/**
	 * Fetches product stock data from Square.
	 *
	 * @internal
	 *
	 * @since 2.0.0
	 */
	public function fetch_product_stock_with_square() {
		check_ajax_referer( 'fetch-product-stock-with-square', 'security' );

		if ( ! current_user_can( 'edit_products' ) ) {
			wp_send_json_error( __( 'Invalid permissions.', 'woocommerce-square' ) );
		}

		$fix_error = __( 'Please mark product as un-synced and save, then synced again.', 'woocommerce-square' );
		$product   = isset( $_REQUEST['product_id'] ) ? wc_get_product( (int) $_REQUEST['product_id'] ) : false;

		if ( $product ) {

			try {
				$product  = Product::update_stock_from_square( $product );
				$response = array(
					'quantity'     => $product->get_stock_quantity(),
					'manage_stock' => $product->get_manage_stock(),
				);

				wp_send_json_success( $response );

			} catch ( \Exception $exception ) {

				/* translators: Placeholders: %1$s = error message, %2$s = help text */
				wp_send_json_error( sprintf( __( 'Unable to fetch inventory: %1$s. %2$s', 'woocommerce-square' ), $exception->getMessage(), $fix_error ) );
			}
		}

		/* translators: Placeholders: %s = help text */
		wp_send_json_error( sprintf( __( 'Error finding item in Square. %s', 'woocommerce-square' ), $fix_error ) );
	}


	/**
	 * Starts importing products from Square.
	 *
	 * @internal
	 *
	 * @since 2.0.0
	 */
	public function import_products_from_square() {

		check_ajax_referer( 'import-products-from-square', 'security' );

		// The edit_others_shop_orders capability is used to determine if the user can access these settings.
		if ( ! current_user_can( 'edit_others_shop_orders' ) ) {
			wp_send_json_error( __( 'Could not start import. Invalid permissions.', 'woocommerce-square' ) );
		}

		$started = wc_square()->get_sync_handler()->start_product_import( ( ! empty( $_POST['update_during_import'] ) && 'true' === $_POST['update_during_import'] ) );

		if ( ! $started ) {
			wp_send_json_error( __( 'Could not start import. Please try again.', 'woocommerce-square' ) );
		}

		wp_send_json_success( __( 'Your products are being imported in the background! This may take some time to complete.', 'woocommerce-square' ) );
	}


	/**
	 * Starts syncing products with Square.
	 *
	 * @internal
	 *
	 * @since 2.0.0
	 */
	public function sync_products_with_square() {

		check_ajax_referer( 'sync-products-with-square', 'security' );

		// The edit_others_shop_orders capability is used to determine if the user can access these settings.
		if ( ! current_user_can( 'edit_others_shop_orders' ) ) {
			wp_send_json_error();
		}

		$started = wc_square()->get_sync_handler()->start_manual_sync();

		if ( ! $started ) {
			wp_send_json_error();
		}

		wp_send_json_success();
	}


	/**
	 * Handles sync records actions.
	 *
	 * @internal
	 *
	 * @since 2.0.0
	 */
	public function handle_sync_records() {

		check_ajax_referer( 'handle-sync-with-square-records', 'security' );

		if ( ! current_user_can( 'edit_others_shop_orders' ) ) {
			wp_send_json_error( __( 'An error occurred. Invalid permissions.', 'woocommerce-square' ) );
		}

		$error = '';

		if ( isset( $_POST['id'], $_POST['handle'] ) ) {

			$id     = sanitize_key( $_POST['id'] );
			$action = sanitize_key( $_POST['handle'] );

			if ( 'all' === $id && 'delete' === $action ) {

				$outcome = Records::clean_records();
				$error   = esc_html__( 'Could not delete records.', 'woocommerce-square' );

			} elseif ( is_string( $id ) && '' !== $id ) {

				switch ( $action ) {

					case 'delete':
						$outcome = Records::delete_record( $id );
						$error   = esc_html__( 'Could not delete record.', 'woocommerce-square' );

						break;

					case 'resolve':
						$record = Records::get_record( $id );
						if ( $record ) {
							$record->resolve();
							$outcome = $record->save();
						}

						$error = esc_html__( 'Could not resolve record.', 'woocommerce-square' );

						break;

					case 'unsync':
						$record  = Records::get_record( $id );
						$product = $record ? $record->get_product() : null;
						if ( $product ) {
							$record->resolve();
							$outcome = Product::unset_synced_with_square( $product ) && $record->save();
						}

						$error = esc_html__( 'Could not unsync product.', 'woocommerce-square' );

						break;
				}
			}

			if ( ! empty( $outcome ) ) {
				wp_send_json_success( $outcome );
			}
		}

		/* translators: Placeholder: %s - error message */
		wp_send_json_error( sprintf( __( 'An error occurred. %s', 'woocommerce-square' ), $error ) );
	}


	/**
	 * Gets a sync job status.
	 *
	 * Also bumps the job progression (useful for when background processing isn't available).
	 *
	 * @internal
	 *
	 * @since 2.0.0
	 */
	public function get_sync_with_square_job_status() {
		check_ajax_referer( 'get-sync-with-square-status', 'security' );

		if ( ! current_user_can( 'edit_others_shop_orders' ) ) {
			wp_send_json_error( __( 'An error occurred. Invalid permissions.', 'woocommerce-square' ) );
		}

		$job_id = isset( $_POST['job_id'] ) ? sanitize_key( $_POST['job_id'] ) : null;

		if ( $job_id ) {
			try {
				$handler         = wc_square()->get_background_job_handler();
				$job_in_progress = $handler ? $handler->get_job( $job_id ) : false;
				if ( $job_in_progress ) {

					$result = array(
						'action'                  => $job_in_progress->action,
						'id'                      => $job_in_progress->id,
						'job_products_count'      => count( $job_in_progress->product_ids ),
						'percentage'              => ( (float) count( $job_in_progress->processed_product_ids ) / max( 1, count( $job_in_progress->product_ids ) ) ) * 100,
						'imported_products_count' => count( $job_in_progress->processed_product_ids ),
						'updated_products_count'  => count( $job_in_progress->updated_product_ids ),
						'skipped_products_count'  => count( $job_in_progress->skipped_products ),
						'status'                  => $job_in_progress->status,
					);

					wp_send_json_success( $result );
				}
			} catch ( \Exception $e ) {

				wp_send_json_error( $e->getMessage() );
			}
		}

		/* translators: Placeholder: %s - sync job ID */
		wp_send_json_error( sprintf( esc_html__( 'No sync job in progress found %s', 'woocommerce-square' ), is_string( $job_id ) ? $job_id : null ) );
	}

	/**
	 * Get sync status, variable status, and edit url for product
	 *
	 * Used to manipulate quick edit menu for product
	 *
	 * @since 2.1.6
	 */
	public function get_quick_edit_product_details() {

		check_ajax_referer( 'get-quick-edit-product-details', 'security' );

		if ( ! current_user_can( 'edit_products' ) ) {
			wp_send_json_error( 'invalid_permission' );
		}

		$product = isset( $_POST['product_id'] ) ? wc_get_product( (int) $_POST['product_id'] ) : false;

		if ( $product ) {
			$is_variable = $product->is_type( 'variable' );

			if ( ! Product::has_sku( $product ) ) {
				if ( $is_variable ) {
					wp_send_json_error( 'missing_variation_sku' );
				} else {
					wp_send_json_error( 'missing_sku' );
				}
			}

			$is_synced_with_square = Product::is_synced_with_square( $product ) ? 'yes' : 'no';
			$is_woocommerce_sor    = wc_square()->get_settings_handler()->is_system_of_record_woocommerce();

			wp_send_json_success(
				array(
					'edit_url'              => $is_woocommerce_sor ? get_edit_post_link( (int) $_POST['product_id'] ) : null,
					'i18n'                  => $is_woocommerce_sor ? __( 'Stock must be fetched from Square before editing stock quantity', 'woocommerce-square' ) : null,
					'is_synced_with_square' => $is_synced_with_square,
					'is_variable'           => $is_variable,
				)
			);
		}
		wp_send_json_error( 'invalid_product' );
	}

}
