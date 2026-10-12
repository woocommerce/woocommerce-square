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
 * Backing detail: Records::get_records() applies `max(50, $limit)` — that
 * is a floor, not a ceiling, so the service itself returns up to 50
 * records regardless of values below 50. The 50-record upper bound is
 * enforced by the input schema's `maximum: 50` on the `limit` property,
 * which clamps oversize requests before they reach the backing service.
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
			'description'         => __( 'Return entries from the Square sync log (per-product warnings, errors, hidden products) with optional filters by type, product, sort and limit. Limit is clamped to 50 by the input schema.', 'woocommerce-square' ),
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
						'description' => __( 'Maximum number of records to return. The schema enforces a hard upper bound of 50.', 'woocommerce-square' ),
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
		// Records is a static service — no plugin instance involved — so we
		// guard the class itself rather than reaching for
		// AbstractSquareAbility::get_settings_handler_or_error() /
		// get_sync_handler_or_error(), which gate on the plugin instance and
		// its handlers. The error code is intentionally identical so MCP
		// clients see a uniform "plugin not initialized" surface.
		if ( ! class_exists( Records::class ) ) {
			return new \WP_Error(
				'woocommerce_square_not_initialized',
				__( 'Square for WooCommerce is not initialized.', 'woocommerce-square' )
			);
		}

		$input = is_array( $input ) ? $input : array();

		// Runtime clamp duplicates the schema's `minimum: 1` / `maximum: 50` bound on `limit`.
		// The Abilities Loader applies the schema before execute() runs, so this branch only
		// kicks in for direct callers that reach execute() outside the loader (tests, other
		// PHP code). Keeping both copies guards against a future reader tightening one side
		// of the contract and leaving the other stale.
		$limit = isset( $input['limit'] ) ? max( 1, min( 50, (int) $input['limit'] ) ) : 50;

		$args = array(
			'orderby' => 'date',
			'sort'    => isset( $input['sort'] ) && 'ASC' === $input['sort'] ? 'ASC' : 'DESC',
			'limit'   => $limit,
		);

		if ( ! empty( $input['type'] ) ) {
			$args['type'] = (string) $input['type'];
		}
		if ( ! empty( $input['product_id'] ) ) {
			$args['product'] = (int) $input['product_id'];
		}

		// Records::get_records() applies max(50, $limit) as a floor, so a
		// limit < 50 still returns up to 50 records. Trim post-fetch so the
		// ability honors the schema's `minimum: 1` (no point advertising a
		// contract the backing service does not enforce).
		$records = Records::get_records( $args );
		if ( ! is_array( $records ) ) {
			return array();
		}
		$records = array_slice( $records, 0, $limit );

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
