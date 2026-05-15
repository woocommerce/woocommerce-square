<?php
/**
 * Get sync status ability definition.
 *
 * @package WooCommerce\Square
 */

// @phan-file-suppress PhanUndeclaredClassMethod, PhanUndeclaredFunction @phan-suppress-current-line UnusedSuppression -- Abilities API + AbilityDefinition added in WC 10.9; suppression covers older-WC compat runs where this class never loads.

namespace WooCommerce\Square\Internal\Abilities\Domain;

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Abilities\AbilityDefinition;
use WooCommerce\Square\Internal\Abilities\Abilities_Registrar;

/**
 * Registers the woocommerce-square/get-sync-status ability.
 *
 * Reference ability for the Square for WooCommerce Abilities API rollout —
 * zero-arg overview answering "is Square product/inventory sync healthy?".
 * Returns a small associative array composed from six accessor methods on
 * the Sync handler: whether a sync is currently in progress, the id of the
 * running job (if any), the last product- and inventory-sync timestamps,
 * the next scheduled sync timestamp, and whether product sync is enabled.
 *
 * Today this state is only exposed via the `wc_square_get_sync_with_square_status`
 * wp_ajax_* handler; this ability fills the agent-facing gap without adding
 * a new REST endpoint.
 *
 * @internal Only loaded when WooCommerce 10.9+ is active. The
 *           Abilities_Registrar short-circuits before referencing this
 *           class on earlier WC versions; PHP's lazy autoload means the
 *           unresolved AbilityDefinition interface FQN never reaches the
 *           parser there.
 */
class GetSyncStatus extends AbstractSquareAbility implements AbilityDefinition {

	public static function get_name(): string {
		return 'woocommerce-square/get-sync-status';
	}

	public static function get_registration_args(): array {
		return array(
			'label'               => __( 'Get Square sync status', 'woocommerce-square' ),
			'description'         => __( 'Return the current Square sync state — whether a sync is in progress, the last product- and inventory-sync timestamps, the next scheduled sync, and whether product sync is enabled.', 'woocommerce-square' ),
			'category'            => self::CATEGORY_SLUG,
			'input_schema'        => array(
				'type'                 => 'object',
				'default'              => (object) array(),
				'properties'           => array(),
				'additionalProperties' => false,
			),
			'execute_callback'    => array( self::class, 'execute' ),
			'permission_callback' => array( Abilities_Registrar::class, 'can_manage_woocommerce_square' ),
			// output_schema deliberately omitted — see first-ability-template §"output_schema omission rule".
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
	 * Composes a small associative array from the Sync handler's read methods.
	 * The job object returned by `get_job_in_progress()` is coerced to a
	 * stable string id (or null) — the internal class shape is not part of
	 * the ability contract.
	 *
	 * @param mixed $input Ignored; the ability accepts no input.
	 * @return array|\WP_Error Associative array of sync state, or WP_Error
	 *                         when the plugin has not finished initializing.
	 */
	public static function execute( $input = null ) {
		unset( $input );

		$sync_handler = self::get_sync_handler_or_error();
		if ( is_wp_error( $sync_handler ) ) {
			return $sync_handler;
		}

		$job            = $sync_handler->get_job_in_progress();
		$current_job_id = ( is_object( $job ) && isset( $job->id ) ) ? (string) $job->id : null;

		return array(
			'is_in_progress'           => (bool) $sync_handler->is_sync_in_progress(),
			'is_sync_enabled'          => (bool) $sync_handler->is_sync_enabled(),
			'current_job_id'           => $current_job_id,
			'last_synced_at'           => $sync_handler->get_last_synced_at(),
			'inventory_last_synced_at' => $sync_handler->get_inventory_last_synced_at(),
			'next_sync_at'             => $sync_handler->get_next_sync_at(),
		);
	}
}
