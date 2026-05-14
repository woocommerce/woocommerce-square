<?php
/**
 * Manual token-cost estimator for the abilities surface.
 *
 * Synthesizes the per-ability JSON shape that an MCP `tools/list`
 * response would contain (name, description, inputSchema, annotations),
 * serializes it, and prints both char + estimated-token counts.
 *
 * Approximation: ~4 chars per token in English text. JSON brackets,
 * braces and quotes weight a bit lower; descriptions weight a bit
 * higher. For provisional budgeting we use the 4-char-per-token rule.
 *
 * Run via:
 *   npx wp-env run cli -- wp eval-file wp-content/plugins/woocommerce-square/tests/php/measure-token-cost.php
 */

// Hand-built mirror of each Domain class's get_registration_args() shape,
// stripped to what tools/list serializes for an MCP client. This avoids
// touching the actual Domain classes (lazy-autoload safety) and keeps the
// measurement self-contained.

$abilities = array(
	'woocommerce-square/get-sync-status'                  => array(
		'description' => 'Return the current Square sync state — whether a sync is in progress, the last product- and inventory-sync timestamps, the next scheduled sync, and whether product sync is enabled.',
		'inputSchema' => array(
			'type'                 => 'object',
			'properties'           => new stdClass(),
			'additionalProperties' => false,
		),
		'annotations' => array(
			'readonly'    => true,
			'destructive' => false,
			'idempotent'  => true,
			'title'       => 'Get Square sync status',
		),
	),
	'woocommerce-square/get-sync-records'                 => array(
		'description' => 'Return entries from the Square sync log (per-product warnings, errors, hidden products) with optional filters by type, product, sort and limit. Limit is capped at 50 by the backing service.',
		'inputSchema' => array(
			'type'                 => 'object',
			'properties'           => array(
				'type'       => array(
					'type'        => 'string',
					'description' => 'Filter records by type. Optional.',
				),
				'product_id' => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => 'Filter records to those attached to a specific WooCommerce product ID. Optional.',
				),
				'sort'       => array(
					'type'        => 'string',
					'enum'        => array( 'ASC', 'DESC' ),
					'default'     => 'DESC',
					'description' => 'Sort direction (by date). Defaults to DESC (newest first).',
				),
				'limit'      => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'maximum'     => 50,
					'default'     => 50,
					'description' => 'Maximum number of records to return. The backing service caps at 50; values above 50 are clamped.',
				),
			),
			'additionalProperties' => false,
		),
		'annotations' => array(
			'readonly'    => true,
			'destructive' => false,
			'idempotent'  => true,
			'title'       => 'Get Square sync records',
		),
	),
	'woocommerce-square/get-connection-status'            => array(
		'description' => 'Return the Square OAuth connection state: connected (bool), configured (bool), environment (sandbox or production), the configured location id, and whether the sandbox toggle is on. Deliberately excludes tokens, connection URLs, and the locations list.',
		'inputSchema' => array(
			'type'                 => 'object',
			'properties'           => new stdClass(),
			'additionalProperties' => false,
		),
		'annotations' => array(
			'readonly'    => true,
			'destructive' => false,
			'idempotent'  => true,
			'title'       => 'Get Square connection status',
		),
	),
	'woocommerce-square/get-locations'                    => array(
		'description' => 'Return the Square locations the merchant\'s connected account exposes (id, name, status, currency, country). Empty array when not connected. Response is cached for ~1 hour; treat as point-in-time.',
		'inputSchema' => array(
			'type'                 => 'object',
			'properties'           => new stdClass(),
			'additionalProperties' => false,
		),
		'annotations' => array(
			'readonly'    => true,
			'destructive' => false,
			'idempotent'  => true,
			'title'       => 'Get Square locations',
		),
	),
	'woocommerce-square/get-product-sync-state'           => array(
		'description' => 'Return whether a specific WooCommerce product is set to sync with Square, plus the Square item ID (if one has been pushed) and the parent-product lookup for variations.',
		'inputSchema' => array(
			'type'                 => 'object',
			'properties'           => array(
				'product_id' => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => 'The WooCommerce product or variation ID.',
				),
			),
			'required'             => array( 'product_id' ),
			'additionalProperties' => false,
		),
		'annotations' => array(
			'readonly'    => true,
			'destructive' => false,
			'idempotent'  => true,
			'title'       => 'Get product sync state',
		),
	),
	'woocommerce-square/get-credit-card-payment-settings' => array(
		'description' => 'Return the Square credit-card gateway configuration (enabled, title, description, transaction type, capture, card types, tokenization, digital-wallets toggle and styling).',
		'inputSchema' => array(
			'type'                 => 'object',
			'properties'           => new stdClass(),
			'additionalProperties' => false,
		),
		'annotations' => array(
			'readonly'    => true,
			'destructive' => false,
			'idempotent'  => true,
			'title'       => 'Get Square credit-card gateway settings',
		),
	),
	'woocommerce-square/get-cash-app-payment-settings'    => array(
		'description' => 'Return the Square Cash App Pay gateway configuration (enabled, title, description, transaction type, capture, button theme and shape).',
		'inputSchema' => array(
			'type'                 => 'object',
			'properties'           => new stdClass(),
			'additionalProperties' => false,
		),
		'annotations' => array(
			'readonly'    => true,
			'destructive' => false,
			'idempotent'  => true,
			'title'       => 'Get Square Cash App Pay gateway settings',
		),
	),
);

$tools = array();
foreach ( $abilities as $name => $shape ) {
	$tools[] = array_merge( array( 'name' => $name ), $shape );
}

$payload = wp_json_encode( $tools, JSON_PRETTY_PRINT );
$chars   = strlen( $payload );
$tokens  = (int) ceil( $chars / 4 );

echo PHP_EOL . '=== Square for WooCommerce — abilities tools/list token cost estimate ===' . PHP_EOL;
echo 'Abilities measured: ' . count( $abilities ) . PHP_EOL;
echo 'Pretty-printed JSON payload size: ' . $chars . ' chars' . PHP_EOL;
echo 'Estimated tokens (chars / 4 heuristic): ~' . $tokens . PHP_EOL;
echo PHP_EOL;
echo 'Per-ability breakdown:' . PHP_EOL;
$compact_total = 0;
foreach ( $tools as $tool ) {
	$compact      = wp_json_encode( $tool );
	$tool_chars   = strlen( $compact );
	$tool_tokens  = (int) ceil( $tool_chars / 4 );
	$compact_total += $tool_chars;
	echo str_pad( $tool['name'], 56 ) . ' ' . str_pad( $tool_chars . 'c', 8 ) . ' ~' . $tool_tokens . 't' . PHP_EOL;
}
echo PHP_EOL;
$compact_tokens = (int) ceil( $compact_total / 4 );
echo 'Compact (non-pretty-printed) JSON payload size: ' . $compact_total . ' chars' . PHP_EOL;
echo 'Compact estimated tokens: ~' . $compact_tokens . PHP_EOL;
echo PHP_EOL;
echo 'Budget: <= 2,000 tokens per plugin (provisional).' . PHP_EOL;
echo 'Status: ' . ( $compact_tokens <= 2000 ? 'WITHIN BUDGET' : 'OVER BUDGET' ) . PHP_EOL;
