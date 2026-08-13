# Agent notes — Square for WooCommerce

This file collects guidance for AI agents working on this codebase.

## When changing the code path behind a registered ability

When you change the code path behind a registered ability (a Domain class
under `includes/Internal/Abilities/Domain/`, or one of the service / REST
controller methods it calls), audit the registration for required updates
to annotations, schema, description, and the harness output.

In particular:

- If the change adds a write operation to a previously read-only callback,
  flip `meta.annotations.readonly` to `false` and re-evaluate `destructive`
  and `idempotent`. `readonly: true` on a writing callback is the bug
  class agents cannot detect without the adversarial check.
- If the change adds or renames an input field, update the ability's
  `input_schema.properties`, `required`, and the field `description`.
- If the change widens or narrows the permission gate, update both the
  REST controller's `permission_callback` AND
  `Abilities_Registrar::can_manage_woocommerce_square()` — keep them
  identical, never duplicate the check inline.
- Re-run the integration harness against `wp-abilities-verify` and
  capture the updated artifact in your own working notes — these
  verification artifacts are not tracked in this repository.

## Backward Compatibility

Any change to a **public or externally exposed** class, interface, function, or method signature is **high-risk** and **must state its backward-compatibility impact in the PR description** - regardless of which namespace the symbol lives in. `WooCommerce\Square\Internal` is a code-organization choice, not a privacy boundary.

Treat a symbol as **externally exposed** when it is implemented or consumed outside this repository - by other plugins, themes, or site snippets - even if it looks internal. When in doubt, assume it is exposed and state the BC impact.

**As a producer of public API.** This plugin exposes a surface that third parties consume:
- The framework API interfaces - `Framework\Api\API_Request`, `Framework\Api\API_Response`, `Framework\PaymentGateway\Api\Payment_Gateway_API` and the `Payment_Gateway_API_*_Response` family. `includes/Framework/` is a vendored copy of the WooCommerce/SkyVerge plugin framework, so its consumers span a lineage of gateway plugins, not just this one.
- The gateway class hierarchy that extensions subclass and override - the framework's `Payment_Gateway` / `Payment_Gateway_Direct`, and this plugin's `Gateway` and `Cash_App_Pay_Gateway`.
- `do_action`/`apply_filters` hooks - the `wc_square_*` and `woocommerce_square_*` hooks this plugin fires, and the framework-wide `wc_payment_gateway_*` hooks.
- Gateway IDs and their settings keys - `square_credit_card`, `square_cash_app_pay`, `gift_cards_pay` - persisted in every order's `_payment_method` and in the `woocommerce_{gateway_id}_settings` option names (e.g. woocommerce_square_credit_card_settings, woocommerce_gift_cards_pay_settings), so changing one orphans merchant settings and detaches historical orders from the gateway that processed them.
- The `{$wpdb->prefix}woocommerce_square_customers` table. Schema changes ship as a new `Lifecycle::upgrade_to_*()` method that re-runs `create_table()` through `dbDelta`, which adds columns but never drops or rewrites them.

Adding a **required** method to an interface that external code can implement is backward-incompatible - existing implementers fatal on load. Removing one is breaking too: implementers carry a now-dead method that static analysis flags. Prefer a non-breaking alternative: add the method to a concrete class, introduce a separate new interface, or provide a default via an abstract base class.

**Deprecate, don't rename.** Never rename or remove an existing public symbol (class, interface, method, constant, hook, option key) in place. Mark the old one `@deprecated`, add the replacement alongside it, and keep both working through a deprecation window so consumers can migrate.

**As a consumer of upstream WooCommerce contracts.** This plugin extends `WC_Payment_Gateway` and implements the Blocks payment method integration (`AbstractPaymentMethodType`, `IntegrationInterface`). WooCommerce can change those contracts, including ones under `Internal`, and doing so is exactly the class of break this guardrail exists to prevent - WooCommerce 10.9.0 was reverted on WP Cloud after a required method added to a published interface fataled older versions of a gateway extension that implemented it. Keep implementations compatible across the supported WC range (L, L-1, L-2) and guard against contract changes rather than assuming the interface is frozen.

### The compatibility surface is wider than PHP signatures

Class and function signatures are not the only contracts. The following are equally binding: a change to any of them is **high-risk** and requires the same backward-compatibility impact statement in the PR description.

**Hooks and filters are public contracts.** Every `do_action` and `apply_filters` call - the `wc_square_*` and `woocommerce_square_*` hooks this plugin fires, and the framework's `wc_payment_gateway_*` hooks - is an interface third-party callbacks depend on. Removing a hook, renaming it, or removing/reordering its arguments breaks every attached callback. Changing *when* or *whether* a hook fires can break consumers that depend on its timing. Additive is the safe path: append new arguments at the end, never remove or reorder existing ones. To retire a hook, fire it through `do_action_deprecated()` / `apply_filters_deprecated()` for a deprecation window instead of deleting it.

**Do not assume global state.** This code runs in admin, REST, Store API checkout, CLI, Action Scheduler, and front-end contexts, and not all of them set the globals a front-end request does (`$post`, `$wp_query`, an initialized session or cart). Scheduled sync jobs in particular run with no cart and no logged-in customer. A newly introduced read of a global, or of `wc_square()->...` state, in a path reachable outside a standard request is a fatal or a silent misbehavior in the contexts that do not set it. Guard the exact dependency explicitly: use `function_exists`/`class_exists` for symbols, `isset` for variables, `did_action` for lifecycle state, and verify the plugin and the required component are initialized before dereferencing.

**Do not assume single-site.** Multisite changes where data lives: site-scoped vs network-scoped options (`get_option` vs `get_site_option`), per-site tables, user roles and capabilities, and upload paths all differ. The customer table and the Square connection settings are both per site, so one connection does not cover a network. A change that reads or writes site state must state in its PR whether it behaves correctly under multisite - and if it was not tested there, say so explicitly.

**Do not assume install layout.** WordPress could be configured to run in a subdirectory, with relocated `wp-content`, and behind reverse proxies. Never build paths or URLs by concatenation from the domain root; derive them (`plugins_url()`, `plugin_dir_path()`, `wp_upload_dir()`, and mind the `home_url()` vs `site_url()` distinction), as `WC_SQUARE_PLUGIN_URL` and `WC_SQUARE_PLUGIN_PATH` already do. A path that works on a root install and breaks elsewhere is a compatibility bug, not an edge case.

### Before changing any public or externally exposed surface (agent checklist)

1. Identify the contract you are touching: signature, hook, persisted identifier, global/scope expectation, site topology, or install layout.
2. Assume unseen consumers. You cannot enumerate third-party code; if the surface is reachable from outside this plugin, someone consumes it.
3. Prefer the additive path (new optional method, appended hook argument, new symbol + deprecation) over changing what exists.
4. State the impact in the PR description: what changed, who could consume it, and why it is safe or what the deprecation path is.
5. If you cannot establish the impact, stop and flag it to the user as needing review.

> Core's [AGENTS.md Backward Compatibility](https://github.com/woocommerce/woocommerce/blob/trunk/AGENTS.md#backward-compatibility) section carries the same guardrail.
