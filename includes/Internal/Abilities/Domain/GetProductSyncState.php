<?php
/**
 * Get product sync state ability definition.
 *
 * @package WooCommerce\Square
 */

// @phan-file-suppress PhanUndeclaredClassMethod, PhanUndeclaredFunction @phan-suppress-current-line UnusedSuppression -- Abilities API + AbilityDefinition added in WC 10.9; suppression covers older-WC compat runs where this class never loads.

namespace WooCommerce\Square\Internal\Abilities\Domain;

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Abilities\AbilityDefinition;
use WooCommerce\Square\Handlers\Product;
use WooCommerce\Square\Internal\Abilities\Abilities_Registrar;

/**
 * Registers the woocommerce-square/get-product-sync-state ability.
 *
 * One-arg per-product read. Answers "is product X synced to Square,
 * and if so what's its Square item ID?". Sync state is held by a
 * taxonomy term (`wc_square_synced`, yes/no), NOT post-meta — the
 * Square item ID is post-meta (`_square_item_id`). The ability rolls
 * up the variation-parent lookup so callers don't need to know
 * variations check their parent product's term.
 *
 * Passes `$generate_if_not_found = false` to `Product::get_square_item_id()`
 * so the response distinguishes "synced, has a Square ID" from "synced,
 * not yet pushed". The default `true` arg returns a synthesized
 * `#item_<id>` placeholder which would mask the unpushed state.
 *
 * @internal Only loaded when WooCommerce 10.9+ is active.
 */
class GetProductSyncState extends AbstractSquareAbility implements AbilityDefinition {

	public static function get_name(): string {
		return 'woocommerce-square/get-product-sync-state';
	}

	public static function get_registration_args(): array {
		return array(
			'label'               => __( 'Get product sync state', 'woocommerce-square' ),
			'description'         => __( 'Return whether a specific WooCommerce product is set to sync with Square, plus the Square item ID (if one has been pushed) and the parent-product lookup for variations.', 'woocommerce-square' ),
			'category'            => self::CATEGORY_SLUG,
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'product_id' => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'description' => __( 'The WooCommerce product or variation ID.', 'woocommerce-square' ),
					),
				),
				'required'             => array( 'product_id' ),
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
	 * @param mixed $input Expected shape: array{product_id: int}.
	 * @return array|\WP_Error
	 */
	public static function execute( $input = null ) {
		if ( ! is_array( $input ) || ! array_key_exists( 'product_id', $input ) ) {
			return new \WP_Error(
				'woocommerce_square_missing_product_id',
				__( 'A product_id is required.', 'woocommerce-square' )
			);
		}

		$product_id = (int) $input['product_id'];
		if ( $product_id < 1 ) {
			return new \WP_Error(
				'woocommerce_square_invalid_product_id',
				__( 'The product_id must be a positive integer.', 'woocommerce-square' )
			);
		}

		if ( ! function_exists( 'wc_get_product' ) ) {
			return new \WP_Error(
				'woocommerce_square_not_initialized',
				__( 'WooCommerce is not initialized.', 'woocommerce-square' )
			);
		}

		$product = wc_get_product( $product_id );
		if ( ! $product instanceof \WC_Product ) {
			return new \WP_Error(
				'woocommerce_square_product_not_found',
				sprintf(
					/* translators: %d: requested product id. */
					__( 'No WooCommerce product was found with ID %d.', 'woocommerce-square' ),
					$product_id
				),
				array( 'status' => 404 )
			);
		}

		$is_variation      = $product->is_type( 'variation' );
		$parent_product_id = $is_variation ? (int) $product->get_parent_id() : null;

		// Critical: pass false for $generate_if_not_found so the response is
		// `null` (rather than a synthesized "#item_<id>" placeholder) when
		// the product has not yet been pushed to Square. Sync state is held
		// on the parent for variations; Product::get_square_item_id resolves
		// the post-meta directly via the WC_Product ID passed in.
		$id_lookup_target = $is_variation && $parent_product_id ? $parent_product_id : $product;
		$square_item_id   = Product::get_square_item_id( $id_lookup_target, false );
		$is_synced        = Product::is_synced_with_square( $product );

		return array(
			'product_id'        => $product_id,
			'is_synced'         => (bool) $is_synced,
			'square_item_id'    => $square_item_id ? (string) $square_item_id : null,
			'is_variation'      => $is_variation,
			'parent_product_id' => $parent_product_id,
		);
	}
}
