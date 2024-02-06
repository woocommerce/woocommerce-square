<?php

namespace WooCommerce\Square\Admin;

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use Automattic\WooCommerce\Internal\Admin\Features\ProductBlockEditor\ProductTemplates\ProductBlock;
use Automattic\WooCommerce\Internal\Admin\Features\ProductBlockEditor\ProductTemplates\Section;
use WooCommerce\Square\Handlers\Product;

class Product_Editor_Compatibility {
	public function __construct() {
		if ( ! FeaturesUtil::feature_is_enabled( 'product_block_editor' ) ) {
			return;
		}

		add_filter(
			'woocommerce_rest_prepare_product_object',
			array( $this, 'add_data_to_product_response' ),
			10,
			2
		);

		add_action(
			'woocommerce_rest_insert_product_object',
			array( $this, 'process_data_before_save' ),
			10,
			2
		);

		add_action(
			'woocommerce_block_template_area_product-form_after_add_block_basic-details',
			array( $this, 'add_sync_with_square_control' )
		);

		add_action(
			'woocommerce_block_template_area_product-form_after_add_block_product-sku-field',
			array( $this, 'add_inventory_control' )
		);

		add_action(
			'woocommerce_block_template_after_add_block',
			array( $this, 'remove_core_blocks' )
		);
	}

	/**
	 * Adds pre-orders meta to the product response.
	 *
	 * @param \WP_REST_Response $response The response object.
	 * @param \WC_Product       $product  The product object.
	 *
	 * @return WP_REST_Response
	 */
	public function add_data_to_product_response( $response, $product ) {
		$data                     = $response->get_data();
		$data['is_sync_enabled']  = wc_square()->get_settings_handler()->is_product_sync_enabled();
		$data['is_square_synced'] = Product::is_synced_with_square( $product );
		$data['edit_link']        = Product::get_product_edit_link( $product );
		$data['sor']              = wc_square()->get_settings_handler()->get_system_of_record();
		$response->set_data( $data );

		return $response;
	}

	/**
	 * Saves custom data to product metadata.
	 *
	 * @param WC_Product      $product The product object.
	 * @param WP_REST_Request $request The request object.
	 */
	public function process_data_before_save( $product, $request ) {
		$is_square_synced = $request->get_param( 'is_square_synced' );

		if ( ! is_null( $is_square_synced ) ) {
			if ( $is_square_synced ) {
				Product::set_synced_with_square( $product, 'yes' );
			} else {
				Product::set_synced_with_square( $product, 'no' );
			}
		}
	}

	/**
	 * Adds the sync with Square control to the product editor.
	 *
	 * @param Section $basic_details_field The basic details block.
	 */
	public function add_sync_with_square_control( Section $basic_details_field ) {
		$basic_details_field->add_block(
			array(
				'id'             => '_wc_square_synced',
				'blockName'      => 'woocommerce-square/sync-with-square-field',
				'attributes'     => array(
					'title' => __( 'Sync with Square', 'woocommerce-square' ),
				),
				'hideConditions' => array(
					array(
						'expression' => '!editedProduct.is_sync_enabled',
					),
				),
			)
		);
	}

	/**
	 * Adds the inventory control to the product editor.
	 *
	 * @param Section $sku_field The SKU field block.
	 */
	public function add_inventory_control( $sku_field ) {
		/**
		 * Inventory section block.
		 *
		 * @var Section $parent
		 */
		$parent = $sku_field->get_parent();

		$parent->add_block(
			array(
				'id'        => '_wc_square_stock_management_field',
				'blockName' => 'woocommerce-square/stock-management-field',
			)
		);

		$parent->add_block(
			array(
				'id'        => '_wc_square_stock_quantity_field',
				'blockName' => 'woocommerce-square/stock-quantity-field',
			)
		);
	}

	/**
	 * Removes the manage stock and stock quantity blocks from the product editor.
	 *
	 * @param ProductBlock $block Core product blocks.
	 */
	public function remove_core_blocks( $block ) {
		$blocks_to_remove = array(
			'product-inventory-quantity',
			'product-track-stock',
		);

		if ( in_array( $block->get_id(), $blocks_to_remove, true ) ) {
			$block->remove();
		}
	}
}
