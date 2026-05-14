<?php
/**
 * Get sync records ability definition.
 *
 * @package WooCommerce\Square
 */

// @phan-file-suppress PhanUndeclaredClassMethod, PhanUndeclaredFunction @phan-suppress-current-line UnusedSuppression -- Abilities API + AbilityDefinition added in WC 10.9; suppression covers older-WC compat runs where this class never loads.

namespace WooCommerce\Square\Internal\Abilities\Domain;

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Abilities\AbilityDefinition;
use WooCommerce\Square\Internal\Abilities\Abilities_Registrar;
use WooCommerce\Square\Sync\Records;

/**
 * Registers the woocommerce-square/get-sync-records ability.
 *
 * Returns entries from the Square sync log (per-product warnings, errors,
 * hidden products) so agents can diagnose "why didn't product X sync?" or
 * "what went wrong in yesterday's sync?". Filter parameters mirror the
 * subset of arguments accepted by Records::get_records() that make sense
 * to expose: type, product_id, limit, sort. orderby stays internal at
 * 'date' for predictability.
 *
 * Backing detail: Records::get_records() caps results at max(50, $limit)
 * — passing limit > 50 does NOT return more than 50; the cap is hard.
 * Each Record is coerced to a plain associative array so the internal
 * Record class shape stays out of the ability contract.
 *
 * @internal Only loaded when WooCommerce 10.9+ is active.
 */
class GetSyncRecords extends AbstractSquareAbility implements AbilityDefinition {

	public static function get_name(): string {
		return 'woocommerce-square/get-sync-records';
	}

	public static function get_registration_args(): array {
		return array(
			'label'               => __( 'Get Square sync records', 'woocommerce-square' ),
			'description'         => __( 'Return entries from the Square sync log (per-product warnings, errors, hidden products) with optional filters by type, product, sort and limit. Limit is capped at 50 by the backing service.', 'woocommerce-square' ),
			'category'            => self::CATEGORY_SLUG,
			'input_schema'        => array(
				'type'                 => 'object',
				'default'              => (object) array(),
				'properties'           => array(
					'type'       => array(
						'type'        => 'string',
						'description' => __( 'Filter records by type. Optional.', 'woocommerce-square' ),
					),
					'product_id' => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'description' => __( 'Filter records to those attached to a specific WooCommerce product ID. Optional.', 'woocommerce-square' ),
					),
					'sort'       => array(
						'type'        => 'string',
						'enum'        => array( 'ASC', 'DESC' ),
						'default'     => 'DESC',
						'description' => __( 'Sort direction (by date). Defaults to DESC (newest first).', 'woocommerce-square' ),
					),
					'limit'      => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'maximum'     => 50,
						'default'     => 50,
						'description' => __( 'Maximum number of records to return. The backing service caps at 50; values above 50 are clamped.', 'woocommerce-square' ),
					),
				),
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
	 * @param mixed $input Filter args (type, product_id, sort, limit).
	 * @return array|\WP_Error Array of record summaries.
	 */
	public static function execute( $input = null ) {
		if ( ! class_exists( Records::class ) ) {
			return new \WP_Error(
				'woocommerce_square_not_initialized',
				__( 'Square for WooCommerce is not initialized.', 'woocommerce-square' )
			);
		}

		$input = is_array( $input ) ? $input : array();

		$args = array(
			'orderby' => 'date',
			'sort'    => isset( $input['sort'] ) && 'ASC' === $input['sort'] ? 'ASC' : 'DESC',
			'limit'   => isset( $input['limit'] ) ? (int) $input['limit'] : 50,
		);

		if ( ! empty( $input['type'] ) ) {
			$args['type'] = (string) $input['type'];
		}
		if ( ! empty( $input['product_id'] ) ) {
			$args['product'] = (int) $input['product_id'];
		}

		$records = Records::get_records( $args );
		if ( ! is_array( $records ) ) {
			return array();
		}

		$out = array();
		foreach ( $records as $record ) {
			if ( ! is_object( $record ) ) {
				continue;
			}
			$out[] = array(
				'id'          => method_exists( $record, 'get_id' ) ? (string) $record->get_id() : '',
				'type'        => method_exists( $record, 'get_type' ) ? (string) $record->get_type() : '',
				'message'     => method_exists( $record, 'get_message' ) ? (string) $record->get_message() : '',
				'product_id'  => method_exists( $record, 'get_product_id' ) ? ( $record->get_product_id() ? (int) $record->get_product_id() : null ) : null,
				'timestamp'   => method_exists( $record, 'get_timestamp' ) ? (int) $record->get_timestamp() : 0,
				'is_resolved' => method_exists( $record, 'is_resolved' ) ? (bool) $record->is_resolved() : false,
			);
		}

		return $out;
	}
}
