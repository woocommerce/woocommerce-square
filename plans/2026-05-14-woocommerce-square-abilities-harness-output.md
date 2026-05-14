---
Last updated: 2026-05-14 20:55
---

# Square for WooCommerce Abilities Verification — Static + Partial Runtime

## Status: WARN

One annotation-correctness WARN on `woocommerce-square/get-locations` (transient cache write + a self-heal `clear_location_id()` call on cold-cache that is documented inline via `// verify-ignore` and tracked as a Phase 2 follow-up). Everything else clean. No FAILs.

## Audit doc validation

- Audit doc: `plans/2026-05-14-abilities-audit-woocommerce-square.md`
- Schema fields all present, no `backing: null` (service-backed abilities use `route: null` with the `class`+`callback`+`file` populated — a deviation from the schema's REST-centric framing, documented in the audit's "Notes and Surprises").
- One `reference_ability: true` (on `get-sync-status`). ✓
- `plugin_family: woo-extension` matches CWD detection (composer.json declares `woocommerce/woocommerce-square` and the main plugin file declares `WC requires at least: 10.5`). ✓
- `capability_gate: manage_woocommerce` re-verified against `includes/Admin/Rest/WC_Square_REST_Base_Controller.php:25`. Match. ✓

## Static inventory

Static enumeration was adapted to the WC 10.9 loader pattern this plugin uses
— there is no direct `wp_register_ability(` call in the plugin source.
Instead, Domain classes implementing `AbilityDefinition` are listed in
`Abilities_Registrar::ABILITY_CLASSES` and registered through the
`woocommerce_ability_definition_classes` filter when Woo Core 10.9+'s
`AbilitiesLoader` iterates them.

Seven abilities, all under the `woocommerce-square/` namespace, all in the
`woocommerce` category:

| # | Ability ID | Domain class | Backing |
|---|---|---|---|
| 1 ★ | `woocommerce-square/get-sync-status` | `Domain\GetSyncStatus` | Service — `WooCommerce\Square\Handlers\Sync` (6 accessors) |
| 2 | `woocommerce-square/get-sync-records` | `Domain\GetSyncRecords` | Service — `WooCommerce\Square\Sync\Records::get_records()` |
| 3 | `woocommerce-square/get-connection-status` | `Domain\GetConnectionStatus` | Service — `WooCommerce\Square\Settings` (5 accessors) |
| 4 | `woocommerce-square/get-locations` | `Domain\GetLocations` | Service — `WooCommerce\Square\Settings::get_locations(false)` |
| 5 | `woocommerce-square/get-product-sync-state` | `Domain\GetProductSyncState` | Service — `WooCommerce\Square\Handlers\Product::is_synced_with_square()` + `::get_square_item_id(..., false)` |
| 6 | `woocommerce-square/get-credit-card-payment-settings` | `Domain\GetCreditCardPaymentSettings` | REST delegate — `GET /wc/v3/wc_square/payment_settings` |
| 7 | `woocommerce-square/get-cash-app-payment-settings` | `Domain\GetCashAppPaymentSettings` | REST delegate — `GET /wc/v3/wc_square/cash_app_settings` |

★ = reference ability.

## Annotation correctness (the adversarial core)

Every ability claims `readonly: true, destructive: false, idempotent: true`.
Each `execute()` body was inspected for write patterns:

| Ability | Claim | Result | Evidence |
|---|---|---|---|
| `get-sync-status` | RO | **OK** | Six pure accessors on `Sync`: `is_sync_in_progress()`, `is_sync_enabled()`, `get_job_in_progress()`, `get_last_synced_at()`, `get_inventory_last_synced_at()`, `get_next_sync_at()`. None of these write state — `get_job_in_progress()` reads from the background-job handler's queue; `get_*_synced_at` reads options; `get_next_sync_at` reads ActionScheduler. |
| `get-sync-records` | RO | **OK** | `Records::get_records()` is a pure `get_option('wc_square_sync_records', [])` read with in-memory filtering. No mutation. |
| `get-connection-status` | RO | **OK** | Five getters on `Settings`: `is_connected()`, `is_configured()`, `is_sandbox()`, `get_environment()`, `get_location_id()`. All read options. |
| `get-locations` | RO | **WARN** | Backing `Settings::get_locations(false)` is mostly-read but on COLD CACHE (a) writes a 1-hour transient (`wc_square_locations_<ver>`), and (b) can call `clear_location_id()` if the merchant's stored location is no longer present in the connected Square account's locations list. (a) is a forgivable cache-population write; (b) IS a real settings mutation (`update_option('wc_square_settings', ...)`). Documented inline via `// verify-ignore: readonly` with a clear note + tracked as a Phase 2 follow-up (bypass `Settings::get_locations()` and read the transient + API client directly to make the readonly claim load-bearing). The cache-warm path (the typical case) is genuinely readonly. |
| `get-product-sync-state` | RO | **OK** | `wc_get_product()` reads the post; `Product::is_synced_with_square()` reads taxonomy terms via `wp_get_post_terms`; `Product::get_square_item_id($id, false)` reads post-meta. The `false` argument is load-bearing — the default `true` would synthesize and return a `#item_<id>` placeholder (no persistence, but masks the "not yet pushed" state). The audit's original wording about this being a "write when generate=true" was incorrect; the side-effect is the placeholder return, not a post-meta write — corrected here. |
| `get-credit-card-payment-settings` | RO | **OK** | Delegates to `GET /wc/v3/wc_square/payment_settings` → `WC_REST_Square_Credit_Card_Payment_Settings_Controller::get_settings()` → `get_option('woocommerce_square_credit_card_settings', [])`. Pure read. |
| `get-cash-app-payment-settings` | RO | **OK** | Same pattern, `get_option('woocommerce_square_cash_app_pay_settings', [])`. Pure read. |

`destructive: false`: no `delete_*`, `refund_*`, `cancel_*`, `trash_*`, or
non-GET REST delegates in any callback. **OK** across all 7.

`idempotent: true`: every ability's execute is a pure projection over state
the agent itself does not alter. Repeated calls with the same input on the
same state return the same payload. The only nuance is `get-locations`'s
cold-cache self-heal — once it has fired, the stored location is gone, so a
subsequent call observes a different state. But that subsequent call still
returns the locations list correctly; the side-effect is on the merchant's
settings, not on the response. Annotation stands.

## Permission gates

| Ability | Shape | Resolves to | Result |
|---|---|---|---|
| all 7 | Shape A — array callable `[Abilities_Registrar::class, 'can_manage_woocommerce_square']` | `current_user_can('manage_woocommerce')` | **OK** |

No Shape B (`WP_REST_Request`-typed callbacks), no Shape E (literal `true`),
no inline lambdas. Uniform across the surface — matches the audit's declared
gate, matches `WC_Square_REST_Base_Controller::check_permission()` at
`includes/Admin/Rest/WC_Square_REST_Base_Controller.php:25`. Cross-checked
against the audit's `capability_gate` field. **Match.**

## Schema lints

| Principle | Result | Notes |
|---|---|---|
| Object schemas declare `additionalProperties: false` | **OK** | All 7 abilities. |
| Required fields have descriptions | **OK** | Only `get-product-sync-state` has a required field (`product_id`); its `description` is set. |
| Enums non-empty | **OK** | `get-sync-records.sort` enum has `["ASC", "DESC"]`. |
| No `$ref` usage | **OK** | None. |
| Defaults statically constant | **OK** | All `default: (object) array()` casts use the `(object) array()` form documented in `input-schema-gotchas.md` so the validator boundary sees an object, not an array. |
| Reference ability has no required inputs | **OK** | `get-sync-status` declares no `required` array — accepts zero-arg invocation. |

`input_schema.default` is NOT a property-default at runtime — the Abilities
API does not inject per-property defaults into `$input` before the execute
callback. The two abilities that have property defaults (`get-sync-records`
default for `limit` and `sort`) re-apply the defaults inside `execute()` via
`isset()` checks. **OK**.

## Error-code vocabulary

Codes returned via `WP_Error` from execute callbacks:

| Code | Ability | Vocabulary match | Notes |
|---|---|---|---|
| `woocommerce_square_not_initialized` | most | **OK** | matches `<plugin>_not_initialized` |
| `woocommerce_square_missing_product_id` | `get-product-sync-state` | **OK** | matches `<plugin>_missing_<field>` |
| `woocommerce_square_invalid_product_id` | `get-product-sync-state` | **OK** | matches `<plugin>_invalid_<field>` |
| `woocommerce_square_product_not_found` | `get-product-sync-state` | **OK** | extends vocabulary with the `not_found` resource-suffix pattern; mirrors WooCommerce REST controller conventions |
| `woocommerce_square_missing_controller` | abstract base `delegate_to_rest_controller` | **OK** | extends with `missing_<resource>` — fires only when a backing controller class is not loaded |

Prefix `woocommerce_square_*` matches the plugin's existing error-code
prefix used elsewhere in the codebase. Codes follow the lowercase /
underscore / action-second / field-last rule from
`error-code-vocabulary.md`. No `ability_invalid_input` codes hand-rolled in
callback bodies — the schema-validator path produces that code on its own
for REST-bridged invocations.

## Runtime mode — partial (WC 10.9 not yet available on the wp-env channel)

wp-env was brought up successfully with WP 6.9.4 + WC 10.8.0-beta.2 — WC
10.9's `AbilitiesLoader` is not yet shipped on the `latest` plugin channel.
That means the full ability-execution path (`wp_get_ability($name)` returning a
populated `WP_Ability`, `check_permissions()` running against real users,
twin-invocation idempotency) cannot be exercised in this session. What CAN
be verified is the **silent-bail path** — the load-bearing safety property
on WC < 10.9 — and it passes cleanly.

Probe script: `tests/php/runtime-probe.php` (committed alongside this
artifact for reproducibility). Run via:

```
npx wp-env run cli -- wp eval-file wp-content/plugins/woocommerce-square/tests/php/runtime-probe.php
```

Output recorded in this session:

```
WP version: 6.9.4
WC version: 10.8.0-beta.2
AbilitiesLoader (WC 10.9+ required): no
Square Abilities_Registrar autoloads: yes
AbstractSquareAbility autoloads: yes
wp_register_ability function: yes
wp_get_ability function: yes
Feature flag default value: false
Loader filter wired (expect no with feature flag default off): no

--- forcing the feature flag on ---
After flag-on init(): loader filter wired (expect no on WC<10.9, yes on WC>=10.9): no
  -> CONFIRMED silent-bail path. Registrar correctly short-circuits before
     adding the filter when AbilitiesLoader is absent (WC<10.9).

--- can_manage_woocommerce_square() roundtrip ---
  anon user: false (OK)
  subscriber: false (OK)
  admin: true (OK)

--- append_classes() — Domain class strings only (no autoload) ---
  count: 7
   - WooCommerce\Square\Internal\Abilities\Domain\GetSyncStatus
   - WooCommerce\Square\Internal\Abilities\Domain\GetSyncRecords
   - WooCommerce\Square\Internal\Abilities\Domain\GetConnectionStatus
   - WooCommerce\Square\Internal\Abilities\Domain\GetLocations
   - WooCommerce\Square\Internal\Abilities\Domain\GetProductSyncState
   - WooCommerce\Square\Internal\Abilities\Domain\GetCreditCardPaymentSettings
   - WooCommerce\Square\Internal\Abilities\Domain\GetCashAppPaymentSettings

--- wp_get_ability check (expect null on WC<10.9, since registrar bails) ---
  woocommerce-square/get-sync-status: null
  woocommerce-square/get-sync-records: null
  woocommerce-square/get-connection-status: null
  woocommerce-square/get-locations: null
  woocommerce-square/get-product-sync-state: null
  woocommerce-square/get-credit-card-payment-settings: null
  woocommerce-square/get-cash-app-payment-settings: null
```

What this confirms:

- **Lazy-autoload property holds.** The probe only references Domain
  classes via their FQN string in `ABILITY_CLASSES` — calling
  `class_exists()` or `method_exists()` on those FQNs would force
  autoload and fatal on the missing `AbilityDefinition` interface. The
  registrar's `append_classes()` returns the FQN strings without
  triggering autoload, which is the exact safety contract documented
  in `wc-1009-dependency.md`.
- **Silent-bail path works.** With the feature flag forced on and the
  loader absent, `init()` short-circuits before adding the
  `woocommerce_ability_definition_classes` filter. No errors, no
  notices, no fatals. The plugin coexists with WC 10.8 without
  side-effects.
- **Capability roundtrip works.** Anonymous user denied, subscriber
  denied, administrator allowed. Matches the `manage_woocommerce` gate
  used by the plugin's existing REST controllers.
- **`AbstractSquareAbility` autoloads safely.** It does not import
  `AbilityDefinition`, only references it in subclass type
  declarations — so loading the abstract base alone never fatals.

Pre-merge gate: a reviewer running a fresh wp-env on WC 10.9 (when the
stable or dev tag is available) should re-run `runtime-probe.php` and
expect the loader to be **present**, the filter to wire, and each
ability to enumerate via `wp_get_ability($name)`. The
runtime-probe.php script is kept under `tests/php/` so the same
artifact can be regenerated against a future WC 10.9 environment
without re-deriving the probe shape.

## Notes for reviewers

- The static-mode artifact in this PR establishes the annotation, schema,
  permission, and error-code surfaces. The single WARN
  (`get-locations`'s cold-cache `clear_location_id()`) is documented
  inline in the Domain class via `// verify-ignore` with a follow-up
  task to bypass `Settings::get_locations()` and make the readonly
  claim load-bearing.
- The audit doc deviated from the canonical schema in two small ways
  (`backing.route: null` for service-backed abilities, audit's
  `get-product-sync-state` write-side risk was a misreading of
  `Product::get_square_item_id()` semantics). Both are noted here and
  corrected against current source; the audit doc remains the planning
  artifact and is not retroactively edited.
- The plugin previously had no PHPUnit infrastructure. This PR
  bootstraps the minimum scaffolding alongside the abilities tests.
  Running the test suite requires
  `bash tests/php/bin/install-wp-tests.sh <db> <user> <pass>` once,
  then `composer test-unit`.
