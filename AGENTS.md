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

Any change to a **public or externally exposed** class, interface, function, or method signature is **high-risk** and **must state its backward-compatibility impact in the PR description** - regardless of which namespace the symbol lives in. `WooCommerce\Square\Internal\Abilities` is not a guarantee that a symbol is safe to change; `Internal` is a code-organization choice, not a privacy boundary. `includes/Framework/` is a vendored copy of the WooCommerce/SkyVerge plugin framework (`WooCommerce_Square_Loader::FRAMEWORK_VERSION`), so its classes carry consumers from a whole lineage of gateway plugins, not just this one.

Treat a symbol as **externally exposed** when it is implemented or consumed outside this repository - by other plugins, themes, or site snippets - even if it looks internal. When in doubt, assume it is exposed and state the BC impact.

**Adding a method to an interface that external code can implement must be flagged explicitly.** It is a backward-incompatible change: existing implementers fatal on load because they no longer satisfy the contract. This plugin publishes several such interfaces - `Framework\Api\API_Request`, `Framework\Api\API_Response`, `Framework\PaymentGateway\Api\Payment_Gateway_API`, and the `Payment_Gateway_API_*_Response` family. Likewise, **removing a required method from an interface is breaking** for existing implementers (they carry a now-dead method, which static analysis such as PHPStan will flag). Prefer a non-breaking alternative: add the method to the concrete class rather than the interface, introduce a separate new interface, or supply a default implementation via an abstract base class.

**Deprecate, don't rename.** For existing public symbols (classes, interfaces, methods, constants, hooks), never rename or remove them in place. Mark the old symbol `@deprecated`, introduce the replacement alongside it, and keep both working through a deprecation window so external consumers have time to migrate.

> This rule exists because WooCommerce 10.9.0 was reverted on WP Cloud: a required method added to a published interface fataled every older version of the gateway extension that implemented it. The same failure mode applies to any contract this plugin publishes.

### The compatibility surface is wider than PHP signatures

WordPress exposes more contracts than class and function signatures. The following are equally binding: a change to any of them is **high-risk** and requires the same backward-compatibility impact statement in the PR description.

**Hooks and filters are public contracts.** Every `do_action` and `apply_filters` call is an interface that third-party callbacks depend on - the `wc_square_*` hooks (`wc_square_sync_interval`, `wc_square_product_can_sync_with_square`, `wc_square_updated_product_from_square`, and roughly forty more), the `woocommerce_square_*` hooks (`woocommerce_square_create_product_data`, `woocommerce_square_abilities_enabled`), the `wc_payment_gateway_square_*` hooks, and the framework-wide `wc_payment_gateway_*` hooks. Removing a hook, renaming it, or removing/reordering its arguments breaks every attached callback. Changing *when* or *whether* a hook fires can break consumers that depend on its timing. Additive is the safe path: append new arguments at the end, never remove or reorder existing ones. To retire a hook, fire it through `do_action_deprecated()` / `apply_filters_deprecated()` for a deprecation window instead of deleting it.

**Never trust data that flows through hooks.** Keep hook callback parameters untyped and validate or coerce the value before passing it to strictly typed code, since any callback can receive a value another one produced. And when firing a filter, validate the final return value before using it, since any callback in the chain can return the wrong thing. This matters most on the money and catalog paths, where a filtered value ends up in a Square API request.

**Overridable classes are contracts too, including which internal methods get called.** Extensions subclass the framework's `Payment_Gateway` / `Payment_Gateway_Direct` and this plugin's `Gateway` and `Cash_App_Pay_Gateway`, and override individual methods. Adding a fast path or skip that avoids calling an overridable method silently disables those overrides even though no signature changed: the extension's code simply stops running. When optimizing such a class, ensure overridable methods are still invoked on every code path, or treat the change as breaking.

**Gateway IDs and settings keys are persisted contracts.** `Plugin::GATEWAY_ID` (`square_credit_card`), `Plugin::CASH_APP_PAY_GATEWAY_ID` (`square_cash_app_pay`) and `Plugin::GIFT_CARD_PAY_GATEWAY_ID` (`gift_cards_pay`) are written into every order's `_payment_method`, into `woocommerce_gateway_order`, and into the settings option names (`woocommerce_square_credit_card_settings`). Changing an ID orphans merchant settings and detaches historical orders from the gateway that processed them - refunds and voids on those orders stop working. The same applies to the registered ability IDs (`woocommerce-square/get-sync-status` and its siblings), which are addressed by name over the Abilities REST route.

**The database schema is a contract too.** The plugin owns one table, `{$wpdb->prefix}woocommerce_square_customers` (`square_id`, `email_address`, `user_id`), created by `Gateway\Customer_Helper::create_table()`. There is no separate DB version constant: schema changes ship as a new `Lifecycle::upgrade_to_*()` method registered in the upgrade version list, which re-runs `create_table()` through `dbDelta`. `dbDelta` will add columns and indexes but will not drop or rewrite them, so a narrowing change needs an explicit migration and must leave existing rows readable.

**Registered script and style handles are public contracts.** Third-party code enqueues handles such as `wc-square-cart-checkout-block`, `wc-square-digital-wallet`, `wc-square-apple-pay`, and the framework's `payment-gateway-admin-order` and `payment-gateway-token-editor`, and lists them as dependencies. Renaming a handle breaks those consumers. To rename with a compatibility window, register the legacy handle as an alias that depends on the new handle; do not register the same file under both handles, or pages with mixed consumers will load it twice.

**Do not assume global state.** This code runs in the admin, in the `wc/v3` REST controllers (`wc_square/settings` and friends), in the Store API checkout through `Blocks_Handler` and `Cash_App_Pay_Blocks_Handler`, in Action Scheduler jobs (`wc_square_sync_orders`, `wc_square_job_runner`, `wc_square_refresh_connection`), and under WP-CLI - and not all of those set the globals a front-end request does (`$post`, `$wp_query`, an initialized session or cart). Order sync from Square arrives through scheduled polling, not an inbound webhook, so it runs with no request context at all. A newly introduced read of a global, or of `wc_square()->...` state, in a path reachable outside a standard request is a fatal or a silent misbehavior in the contexts that do not set it. Guard the exact dependency explicitly: use `function_exists`/`class_exists` for symbols, `isset` for variables, `did_action` for lifecycle state, and verify the plugin and the required handler are initialized before dereferencing.

**Do not assume single-site.** Multisite changes where data lives: site-scoped vs network-scoped options (`get_option` vs `get_site_option`), per-site tables, user roles and capabilities, and upload paths all differ. The customer index table is built from `$wpdb->prefix`, which is site-scoped, so a network-activated install needs its schema created and upgraded per site - a site added after activation has no table until `Lifecycle` runs for it. Square credentials and location settings are likewise per site, so one connection does not cover a network. A change that reads or writes site state must state in its PR whether it behaves correctly under multisite - and if it was not tested there, say so explicitly.

**Do not assume install layout.** WordPress could be configured to run in a subdirectory, with relocated `wp-content`, and behind reverse proxies. Never build paths or URLs by concatenation from the domain root; derive them (`plugins_url()`, `plugin_dir_path()`, `wp_upload_dir()`, and mind the `home_url()` vs `site_url()` distinction) as `WC_SQUARE_PLUGIN_URL` and `WC_SQUARE_PLUGIN_PATH` already do. The Apple Pay domain verification file is the known exception: it is written to `$_SERVER['DOCUMENT_ROOT'] . '/.well-known/'` because Apple requires it at the domain root, which is precisely the assumption that breaks on subdirectory installs. Do not copy that pattern anywhere else.

### Before changing any public or externally exposed surface (agent checklist)

1. Identify the contract you are touching: signature, hook, persisted identifier, database schema, script handle, global/scope expectation, site topology, or install layout.
2. Assume unseen consumers. You cannot enumerate third-party code; if the surface is reachable from outside this plugin, someone consumes it.
3. Prefer the additive path (new optional method, appended hook argument, new symbol + deprecation) over changing what exists.
4. State the impact in the PR description: what changed, who could consume it, and why it is safe or what the deprecation path is.
5. If you cannot establish the impact, stop and flag it to the user as needing review.
