<?php
/**
 * Get pending background jobs ability definition.
 *
 * @package WooCommerce\Square
 */

// @phan-file-suppress PhanUndeclaredClassMethod, PhanUndeclaredFunction @phan-suppress-current-line UnusedSuppression -- Abilities API + AbilityDefinition added in WC 10.9; suppression covers older-WC compat runs where this class never loads.

namespace WooCommerce\Square\Internal\Abilities\Domain;

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Abilities\AbilityDefinition;
use WooCommerce\Square\Internal\Abilities\Abilities_Registrar;

/**
 * Registers the woocommerce-square/get-pending-jobs ability.
 *
 * Returns active background sync jobs (queued + currently processing) so
 * agents can answer "is sync stuck in a queue?" vs "still progressing?".
 * Distinct from get-sync-status, which is a composite health snapshot —
 * this ability surfaces the individual job records with per-job progress.
 *
 * Backing detail: Background_Job_Handler::get_jobs() reads job records
 * from wp_options with a LIKE-on-JSON status filter. No transient writes,
 * no API calls. Completed and failed jobs are excluded by default; pass
 * `include_terminal: true` to include them (still capped at 20 entries
 * total via the schema's `maximum` on `limit`).
 *
 * @internal Only loaded when WooCommerce 10.9+ is active.
 */
class GetPendingJobs extends AbstractSquareAbility implements AbilityDefinition {

	public static function get_name(): string {
		return 'woocommerce-square/get-pending-jobs';
	}

	public static function get_registration_args(): array {
		return array(
			'label'               => __( 'Get pending Square sync jobs', 'woocommerce-square' ),
			'description'         => __( 'Return active Square background sync jobs (queued + processing) with per-job progress (action, percentage, product counts, cursor). Use to diagnose whether a sync is stuck or still progressing.', 'woocommerce-square' ),
			'category'            => self::CATEGORY_SLUG,
			'input_schema'        => array(
				'type'                 => 'object',
				'default'              => (object) array(),
				'properties'           => array(
					'include_terminal' => array(
						'type'        => 'boolean',
						'default'     => false,
						'description' => __( 'When true, also include completed and failed jobs. Defaults to false (active jobs only).', 'woocommerce-square' ),
					),
					'limit'            => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'maximum'     => 20,
						'default'     => 20,
						'description' => __( 'Maximum number of jobs to return, newest first. The schema enforces a hard upper bound of 20.', 'woocommerce-square' ),
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'  => 'array',
				'items' => array(
					'type'       => 'object',
					'properties' => array(
						'id'                => array( 'type' => 'string' ),
						'status'            => array( 'type' => 'string' ),
						'action'            => array( 'type' => 'string' ),
						'percentage'        => array( 'type' => 'number' ),
						'manual'            => array( 'type' => 'boolean' ),
						'system_of_record'  => array( 'type' => 'string' ),
						'product_count'     => array( 'type' => 'integer' ),
						'processed_count'   => array( 'type' => 'integer' ),
						'updated_count'     => array( 'type' => 'integer' ),
						'skipped_count'     => array( 'type' => 'integer' ),
						'catalog_processed' => array( 'type' => 'boolean' ),
						'created_at'        => array( 'type' => array( 'string', 'null' ) ),
						'updated_at'        => array( 'type' => array( 'string', 'null' ) ),
					),
				),
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
	 * @param mixed $input Optional input args (include_terminal, limit).
	 * @return array|\WP_Error
	 */
	public static function execute( $input = null ) {
		$handler = self::get_background_job_handler_or_error();
		if ( is_wp_error( $handler ) ) {
			return $handler;
		}

		$input = is_array( $input ) ? $input : array();
		// Runtime clamp duplicates the schema's `minimum: 1` / `maximum: 20` bound on `limit`.
		// The Abilities Loader applies the schema before execute() runs, so this branch only
		// kicks in for direct callers that reach execute() outside the loader (tests, other
		// PHP code). Keeping both copies guards against a future reader tightening one side
		// of the contract and leaving the other stale.
		$limit    = isset( $input['limit'] ) ? max( 1, min( 20, (int) $input['limit'] ) ) : 20;
		$statuses = ! empty( $input['include_terminal'] )
			? array( 'queued', 'processing', 'completed', 'failed' )
			: array( 'queued', 'processing' );

		$jobs = $handler->get_jobs( array( 'status' => $statuses ) );
		if ( ! is_array( $jobs ) ) {
			return array();
		}

		$jobs = array_slice( $jobs, 0, $limit );

		$out = array();
		foreach ( $jobs as $job ) {
			if ( ! is_object( $job ) ) {
				continue;
			}
			$out[] = self::normalize_job( $job );
		}
		return $out;
	}

	/**
	 * Coerce a Background_Job stdClass into a stable associative-array shape.
	 *
	 * @param object $job Job object returned by Background_Job_Handler::get_jobs().
	 * @return array
	 */
	private static function normalize_job( $job ): array {
		return array(
			'id'                => isset( $job->id ) ? (string) $job->id : '',
			'status'            => isset( $job->status ) ? (string) $job->status : '',
			'action'            => isset( $job->action ) ? (string) $job->action : '',
			'percentage'        => isset( $job->percentage ) ? (float) $job->percentage : 0.0,
			'manual'            => ! empty( $job->manual ),
			'system_of_record'  => isset( $job->system_of_record ) ? (string) $job->system_of_record : '',
			'product_count'     => isset( $job->product_ids ) && is_array( $job->product_ids ) ? count( $job->product_ids ) : 0,
			'processed_count'   => isset( $job->processed_product_ids ) && is_array( $job->processed_product_ids ) ? count( $job->processed_product_ids ) : 0,
			'updated_count'     => isset( $job->updated_product_ids ) && is_array( $job->updated_product_ids ) ? count( $job->updated_product_ids ) : 0,
			'skipped_count'     => isset( $job->skipped_products ) && is_array( $job->skipped_products ) ? count( $job->skipped_products ) : 0,
			'catalog_processed' => ! empty( $job->catalog_processed ),
			'created_at'        => isset( $job->created_at ) ? (string) $job->created_at : null,
			'updated_at'        => isset( $job->updated_at ) ? (string) $job->updated_at : null,
		);
	}
}
