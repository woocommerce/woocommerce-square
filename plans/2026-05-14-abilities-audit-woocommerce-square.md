---
Last updated: 2026-05-14 00:55
---

# Square for WooCommerce — Abilities API Phase 1 Audit (RSM-108)

```yaml
plugin: woocommerce-square
repo: woocommerce/woocommerce-square
branch_audited: add/rsm-108-woocommerce-square-abilities
audited_at: 2026-05-14
auditor: Rafael Meneses (Pirate Goat / Woo Extensions)
baseline_abilities: 0
capability_gate: manage_woocommerce  # confirmed at includes/Admin/Rest/WC_Square_REST_Base_Controller.php line 25
plugin_family: woo-extension

proposed_abilities:

  # ---------------------------------------------------------------------------
  # SYNC SURFACE — service-backed (no REST endpoints; the highest-leverage reads
  # in the plugin are buried under wp_ajax_* handlers today)
  # ---------------------------------------------------------------------------

  - name: woocommerce-square/get-sync-status
    intent: "Return the current product/inventory sync state — is a sync in progress right now, what was the last sync timestamp, when is the next scheduled sync — so an agent can answer 'is Square sync healthy?' in one zero-arg call."
    backing:
      class: WooCommerce\Square\Sync
      file: includes/Sync.php
      method: GET
      route: null  # service-backed; no REST endpoint today
      route_registration_line: null
      callback: is_sync_in_progress|get_job_in_progress|get_last_synced_at|get_inventory_last_synced_at|get_next_sync_at|is_sync_enabled
      callback_line: 245
    permission:
      callback: ability_permission_callback
      resolves_to: "current_user_can('manage_woocommerce')"
      confirmed: true
    return_type: "Array with keys: is_in_progress (bool), current_job (string|null), last_synced_at (int|null timestamp), inventory_last_synced_at (int|null timestamp), next_sync_at (int|null timestamp), is_sync_enabled (bool)."
    effort: S
    annotations: { readonly: true, destructive: false, idempotent: true }
    notes:
      - "Zero-arg ability. The execute_callback composes a small associative array from six accessor methods on WooCommerce\\Square\\Sync (all read-only — no option writes, no scheduling side-effects)."
      - "Backing object is the singleton returned by `wc_square()->get_sync_handler()`. Access it via `wc_square()->get_sync_handler()` inside the execute_callback; do not instantiate Sync directly."
      - "Today this state is only exposed via the `wc_square_get_sync_with_square_status` wp_ajax_* handler — agents on the WP REST surface cannot reach it. This ability fills that gap without adding a new REST endpoint."
    risks:
      - "`get_job_in_progress()` returns a job object; coerce to a string job ID (or null) inside the execute_callback. Don't serialize the whole job object — that includes internal class state that's not part of the contract."
    reference_ability: true

  - name: woocommerce-square/get-sync-records
    intent: "Return entries from the sync failure/info log (per-product warnings, errors, hidden products) with filters by record type and product, so an agent can diagnose 'why didn't product X sync?' or 'what went wrong in yesterday's sync?'."
    backing:
      class: WooCommerce\Square\Sync\Records
      file: includes/Sync/Records.php
      method: GET
      route: null  # service-backed; static method on Records
      route_registration_line: null
      callback: get_records
      callback_line: 79
    permission:
      callback: ability_permission_callback
      resolves_to: "current_user_can('manage_woocommerce')"
      confirmed: true
    return_type: "Array of record summaries — each entry: id, type, message, product_id (int|null), timestamp (int), is_resolved (bool)."
    effort: M
    annotations: { readonly: true, destructive: false, idempotent: true }
    notes:
      - "Filter args accepted by `Records::get_records()`: `id`, `type`, `product`, `orderby` (date|type), `sort` (ASC|DESC), `limit` (default 50). Expose `type`, `product` (product_id), `limit`, and `sort` in input_schema; keep `orderby` internal at 'date' for predictability."
      - "Coerce each Record object to a plain array in the execute_callback (id, type, message, product_id, timestamp, is_resolved). Don't return Record instances directly — the Record class is internal."
      - "Records are stored in a single WP option `wc_square_sync_records`. No paging; the backing already caps at 50 entries."
    risks:
      - "The backing hard-caps at `max(50, $limit)` — passing `limit: 200` does NOT return 200; it returns up to 50. Document this in the input_schema description so agents don't get confused."

  # ---------------------------------------------------------------------------
  # CONNECTION SURFACE — leaner alternatives to a kitchen-sink settings blob
  # ---------------------------------------------------------------------------

  - name: woocommerce-square/get-connection-status
    intent: "Return the Square OAuth connection state — connected (bool), environment (sandbox|production), the configured location ID, and whether the plugin is fully configured — so an agent can answer 'is this store connected to Square, and against which environment?' without the full settings blob."
    backing:
      class: WooCommerce\Square\Settings
      file: includes/Settings.php
      method: GET
      route: null
      route_registration_line: null
      callback: is_connected|is_configured|is_sandbox|get_environment|get_location_id
      callback_line: 760
    permission:
      callback: ability_permission_callback
      resolves_to: "current_user_can('manage_woocommerce')"
      confirmed: true
    return_type: "Array with keys: is_connected (bool), is_configured (bool), environment (string — 'sandbox'|'production'), location_id (string|null), is_sandbox (bool)."
    effort: S
    annotations: { readonly: true, destructive: false, idempotent: true }
    notes:
      - "Zero-arg ability. Access via `wc_square()->get_settings_handler()`."
      - "Deliberately excludes `access_tokens`, connection/disconnection URLs, and the locations list. Use `woocommerce-square/get-locations` for that, or `woocommerce-square/get-settings` for the full kitchen sink (excluded from this MVP — see below)."
    risks: []

  - name: woocommerce-square/get-locations
    intent: "Return the list of Square locations the merchant is connected to (id, name, status, currency, country) so an agent can answer 'which Square locations are available, and which one is this store using?'."
    backing:
      class: WooCommerce\Square\Settings
      file: includes/Settings.php
      method: GET
      route: null
      route_registration_line: null
      callback: get_locations
      callback_line: 828
    permission:
      callback: ability_permission_callback
      resolves_to: "current_user_can('manage_woocommerce')"
      confirmed: true
    return_type: "Array of location summaries — each entry: id, name, status, currency, country (subject to what Square returns). Empty array when not connected."
    effort: S
    annotations: { readonly: true, destructive: false, idempotent: true }
    notes:
      - "Zero-arg ability. `Settings::get_locations()` accepts an optional `$force` bool to bypass a transient cache; ability keeps it `false` for normal calls."
      - "Returns `[]` when not connected — the merchant gate would still pass, but the response is empty. Don't surface that as an error; treat empty as 'not connected'."
    risks:
      - "`get_locations()` makes a Square API call when the cache is stale. Slow path. Agents calling this in a loop could rate-limit themselves; ability description should hint at point-in-time semantics."

  # ---------------------------------------------------------------------------
  # PRODUCT SURFACE — per-product sync state, the most agent-native read
  # ---------------------------------------------------------------------------

  - name: woocommerce-square/get-product-sync-state
    intent: "Return whether a specific product is set to sync with Square, plus the Square item ID (if assigned) and parent variation lookup, so an agent answering 'is product X synced to Square?' can act on a single product."
    backing:
      class: WooCommerce\Square\Handlers\Product
      file: includes/Handlers/Product.php
      method: GET
      route: null
      route_registration_line: null
      callback: is_synced_with_square|get_square_item_id
      callback_line: 609
    permission:
      callback: ability_permission_callback
      resolves_to: "current_user_can('manage_woocommerce')"
      confirmed: true
    return_type: "Array with keys: product_id (int), is_synced (bool), square_item_id (string|null), is_variation (bool), parent_product_id (int|null)."
    effort: S
    annotations: { readonly: true, destructive: false, idempotent: true }
    notes:
      - "One required input: `product_id` (positive integer). Resolve via `wc_get_product($product_id)`; return a WP_Error in the ability execute_callback if the product doesn't exist."
      - "Sync state is stored as a taxonomy term (`wc_square_synced` with term 'yes'/'no'), not as post-meta — see `WooCommerce\\Square\\Handlers\\Product::SYNCED_WITH_SQUARE_TAXONOMY`. The Square item ID is post-meta `_square_item_id` (`Product::SQUARE_ID_META_KEY`)."
      - "Call `Product::get_square_item_id($product_id, false)` — the second arg `$generate_if_not_found` MUST be `false`, otherwise reading state will mint a new ID as a side-effect and the ability won't be readonly."
    risks:
      - "`get_square_item_id($product_id, true)` is a write — it mints and persists a Square item ID. The ability MUST pass `false` for the second argument, otherwise `annotations.readonly` becomes a lie. Implementer: this is the load-bearing line in the execute_callback."

  # ---------------------------------------------------------------------------
  # GATEWAY CONFIG SURFACE — kept for 'how is checkout configured?' reads
  # (Settings-only abilities — included for the two gateways that actually
  # affect customer checkout flow; gift cards is deferred.)
  # ---------------------------------------------------------------------------

  - name: woocommerce-square/get-credit-card-payment-settings
    intent: "Return the Square credit-card gateway configuration (enabled, title, transaction type, capture mode, accepted card types, tokenization, digital-wallets toggle and styling) so an agent can answer 'how is the Square card gateway configured for checkout?'."
    backing:
      class: WooCommerce\Square\Admin\Rest\WC_REST_Square_Credit_Card_Payment_Settings_Controller
      file: includes/Admin/Rest/WC_REST_Square_Credit_Card_Payment_Settings_Controller.php
      method: GET
      route: /wc/v3/wc_square/payment_settings
      route_registration_line: 71
      callback: get_settings
      callback_line: 163
    permission:
      callback: check_permission
      resolves_to: "current_user_can('manage_woocommerce')"
      confirmed: true
    return_type: "Array (gateway/title/description, transaction_type, charge_virtual_orders, enable_paid_capture, card_types, tokenization, digital_wallets_* settings)."
    effort: S
    annotations: { readonly: true, destructive: false, idempotent: true }
    notes:
      - "Zero-arg REST callback. Construct a WP_REST_Request internally in the ability execute_callback (no input_schema fields required for MVP)."
      - "Backing option key: `woocommerce_square_credit_card_settings`."
    risks: []

  - name: woocommerce-square/get-cash-app-payment-settings
    intent: "Return the Square Cash App Pay gateway configuration (enabled, title, transaction type, capture, button theme/shape) so an agent can answer 'how is Cash App Pay configured for checkout?'."
    backing:
      class: WooCommerce\Square\Admin\Rest\WC_REST_Square_Cash_App_Settings_Controller
      file: includes/Admin/Rest/WC_REST_Square_Cash_App_Settings_Controller.php
      method: GET
      route: /wc/v3/wc_square/cash_app_settings
      route_registration_line: 64
      callback: get_settings
      callback_line: 131
    permission:
      callback: check_permission
      resolves_to: "current_user_can('manage_woocommerce')"
      confirmed: true
    return_type: "Array (gateway/title/description, transaction_type, charge_virtual_orders, enable_paid_capture, button_theme, button_shape)."
    effort: S
    annotations: { readonly: true, destructive: false, idempotent: true }
    notes:
      - "Zero-arg REST callback; same wrapping pattern as get-credit-card-payment-settings."
      - "Backing option key: `woocommerce_square_cash_app_pay_settings`."
    risks: []

excluded_from_mvp:
  - name: woocommerce-square/get-settings
    reason: "The general settings REST blob is replaced by leaner abilities (get-connection-status, get-locations, get-sync-status) which together cover the high-value reads. The kitchen-sink response includes short-lived nonce-stamped URLs and an `access_tokens` array — not worth exposing as a single ability when focused alternatives exist."
  - name: woocommerce-square/get-gift-cards-settings
    reason: "Lowest-leverage settings read in the plugin — answers 'is gift cards enabled and what's the placeholder image ID?'. Deferred until there's a real agent use case. Surfaced as a gap for Phase 2 if needed."
  - name: woocommerce-square/save-settings
    reason: "Write path that mutates the merchant connection state (sandbox toggle, sandbox token, system_of_record). Out of scope for the read-only first pass per RSM-108 hard rules."
  - name: woocommerce-square/save-credit-card-payment-settings
    reason: "Write path that mutates the live credit-card gateway used at checkout. Out of scope for the read-only first pass."
  - name: woocommerce-square/save-cash-app-payment-settings
    reason: "Write path that mutates the live Cash App Pay gateway used at checkout. Out of scope for the read-only first pass."
  - name: woocommerce-square/save-gift-cards-settings
    reason: "Write path that mutates the gift cards feature configuration. Out of scope for the read-only first pass."
  - name: woocommerce-square/import-products
    reason: "Kicks off an async product import job from Square — long-running, multi-stage, and not idempotent. Needs its own design pass (idempotency key, status polling endpoint) before exposing as an ability."
  - name: woocommerce-square/mark-connected-page-visited
    reason: "Writes a one-shot UX flag (`wc_square_connected_page_visited`) used purely to dismiss an onboarding hint. Low agent value; defer indefinitely unless a real need surfaces."
  - name: woocommerce-square/start-product-sync
    reason: "Write — kicks off `Sync::start_manual_sync()`. Same scope/idempotency concerns as import-products. Defer until paired with a stable polling ability."
  - name: woocommerce-square/get-synced-products
    reason: "List products synced to Square. Implementable via WC_Product_Query with a meta_query on the `wc_square_synced` taxonomy; a clean ability is straightforward but adds a non-trivial input_schema (status, search, limit, page) and is deferred from the first pass to keep MVP focused on operational reads."

surfaced_gaps:
  - name: woocommerce-square/get-synced-products
    one_line_rationale: "List view of all products marked for Square sync — answers 'which products in this catalog are synced?'. Implementable via the existing `wc_square_synced` taxonomy filter that `Products::filter_products_synced_with_square` already uses; deferred from MVP to keep scope tight."
  - name: woocommerce-square/get-webhook-events
    one_line_rationale: "The plugin handles Square webhooks but has no persisted event log surface; agents diagnosing a webhook-driven order issue cannot inspect recent events without grepping logs. Future work needs a backing read first."
  - name: woocommerce-square/get-gift-cards-settings
    one_line_rationale: "Settings read with low immediate leverage but trivially implementable; track here so it doesn't get lost between phases."
```

## Controller Inventory

| Class | File | REST Base | Routes |
|---|---|---|---|
| WC_REST_Square_Settings_Controller | includes/Admin/Rest/WC_REST_Square_Settings_Controller.php | wc/v3/wc_square/settings | GET /wc/v3/wc_square/settings, POST /wc/v3/wc_square/settings |
| WC_REST_Square_Credit_Card_Payment_Settings_Controller | includes/Admin/Rest/WC_REST_Square_Credit_Card_Payment_Settings_Controller.php | wc/v3/wc_square/payment_settings | GET /wc/v3/wc_square/payment_settings, POST /wc/v3/wc_square/payment_settings |
| WC_REST_Square_Cash_App_Settings_Controller | includes/Admin/Rest/WC_REST_Square_Cash_App_Settings_Controller.php | wc/v3/wc_square/cash_app_settings | GET /wc/v3/wc_square/cash_app_settings, POST /wc/v3/wc_square/cash_app_settings |
| WC_REST_Square_Gift_Cards_Settings_Controller | includes/Admin/Rest/WC_REST_Square_Gift_Cards_Settings_Controller.php | wc/v3/wc_square/gift_cards_settings | GET /wc/v3/wc_square/gift_cards_settings, POST /wc/v3/wc_square/gift_cards_settings |
| WooCommerce\Square\AJAX (REST endpoints embedded in the AJAX handler class) | includes/AJAX.php | wc/v3 (no shared base) | POST /wc/v3/wc_square/import-products, POST /wc/v3/wc_square/connected_page_visited |

All four `WC_REST_Square_*` controllers extend `WooCommerce\Square\Admin\Rest\WC_Square_REST_Base_Controller`, defined at `includes/Admin/Rest/WC_Square_REST_Base_Controller.php`. The base controller registers no routes directly; it only defines the shared `check_permission()` method.

## Service classes used as backings (non-REST)

For abilities backed by service methods rather than REST callbacks, the file/line references below are the load-bearing entry points the registrar wraps:

| Class | File | Methods invoked |
|---|---|---|
| WooCommerce\Square\Sync | includes/Sync.php | `is_sync_in_progress()` (l.245), `get_job_in_progress()` (l.334), `get_last_synced_at()` (l.374), `get_inventory_last_synced_at()` (l.407), `get_next_sync_at()` (l.354), `is_sync_enabled()` (l.259) |
| WooCommerce\Square\Sync\Records | includes/Sync/Records.php | `get_records( $args )` static (l.79) |
| WooCommerce\Square\Settings | includes/Settings.php | `is_connected()` (l.760), `is_configured()` (l.747), `is_sandbox()` (l.773), `get_environment()` (l.1080), `get_location_id()` (l.803), `get_locations( $force )` (l.828) |
| WooCommerce\Square\Handlers\Product | includes/Handlers/Product.php | `is_synced_with_square( $product )` static (l.609), `get_square_item_id( $product_id, $generate_if_not_found = true )` static (l.1086) |

## Notes and Surprises

### Pivot from settings-only to operational reads
An earlier draft of this audit proposed four settings-only reads (general, credit card, Cash App, gift cards). On review, the highest-leverage reads in the plugin are NOT settings — they're sync state, connection state, and per-product sync state, all of which live behind service classes with no REST surface. This revision keeps two gateway-config reads (the two that affect customer checkout: credit card and Cash App) and replaces the rest with service-backed abilities. The reference ability is `get-sync-status` — zero-arg, answers the highest-frequency operational question on the plugin.

### Capability gate is uniform
Every `WC_REST_Square_*` controller inherits `WC_Square_REST_Base_Controller::check_permission()` and uses it verbatim as the `permission_callback`. No per-route overrides. The two REST routes registered inside `includes/AJAX.php` use a structurally identical `check_permission()` method defined locally on `AJAX` (line 99). All resolve to `current_user_can('manage_woocommerce')`. Safe to treat `manage_woocommerce` as the single gate for the whole MVP set, including the service-backed abilities — they map to admin-only merchant configuration tasks.

### Two REST endpoints live inside the AJAX class
The grep for `register_rest_route(` surfaces `includes/AJAX.php` alongside the four canonical controllers. `WooCommerce\Square\AJAX` is named after `wp_ajax_*` actions but also registers two REST routes (`wc_square/import-products` and `wc_square/connected_page_visited`) during `rest_api_init`. Both are POST writes and are excluded from the MVP; flagging here so a future audit doesn't mistake them for AJAX-only handlers and miss them.

### `save_settings()` returns via `wp_send_json_success()` rather than `WP_REST_Response`
The four controllers' write paths terminate with `wp_send_json_success()` — that emits a response and calls `wp_die()`, which is unusual for a REST callback and would short-circuit the REST stack. Not in scope for this Phase 1 read-only registration, but worth flagging for the write follow-up because abilities wrapping these endpoints would need to either reimplement the save in the ability execute_callback or fix the controller to return `WP_REST_Response` first.

### Sync state is exposed via `wp_ajax_*` only, not REST
`WooCommerce\Square\Sync` exposes seven public read methods covering job state, schedule, and timestamps. None have a REST endpoint — the only consumer is the `wc_square_get_sync_with_square_status` wp_ajax_* handler used by the admin UI. The `get-sync-status` ability fills this gap without adding a REST route; the Abilities API can wrap the service method directly.

### Sync records read API is filter-friendly but capped
`Sync\Records::get_records()` accepts `id`, `type`, `product`, `orderby`, `sort`, `limit` filter args (lines 79-149). The `limit` argument is enforced as `max(50, absint($limit))` — passing larger values does NOT increase the cap. The ability's input_schema must document this; agents will otherwise pass `limit: 1000` and get 50 back with no indication of truncation.

### `get_square_item_id()` is a write when `$generate_if_not_found = true` (default)
`Product::get_square_item_id($product_id)` defaults to `$generate_if_not_found = true` — calling it without the second argument MINTS a new Square item ID and persists it as post-meta on the product. The `get-product-sync-state` ability MUST pass `false` for the second argument, otherwise the readonly annotation becomes a lie. This is flagged as a risk on the ability and is the single most important implementation detail in the audit.

### Product sync state lives in a taxonomy, not post-meta
Counter-intuitively, the "is this product synced with Square?" flag is stored as a `wc_square_synced` taxonomy term (yes/no), not as post-meta. The Square item ID itself IS post-meta (`_square_item_id`). The `Products::filter_products_synced_with_square` filter uses a `tax_query` on the taxonomy. Any future `get-synced-products` ability (currently in `surfaced_gaps`) should mirror that tax_query approach, not a meta_query.

### `get_locations()` is a slow path under the hood
`Settings::get_locations(false)` reads from a transient cache; `Settings::get_locations(true)` forces a Square API round-trip. The ability keeps `$force = false` and warns in description that the response is point-in-time. Agents that need fresh data after a manual disconnect/reconnect must accept a small staleness window.

### REST controllers are constructor-instantiated, not loaded via service container
Each controller's `__construct()` hooks `register_routes` onto `rest_api_init` directly. There is no central registry. The implementer wiring `Abilities_Registrar::init()` into the bootstrap should follow the same pattern (hook on `abilities_api_init` per the wp-abilities-api conventions) rather than instantiating from a service container the plugin does not have.
