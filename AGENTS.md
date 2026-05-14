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
- Re-run `wp-abilities-verify` and update the artifact under
  `plans/<YYYY-MM-DD>-woocommerce-square-abilities-harness-output.md`.

See `plans/2026-05-14-abilities-audit-woocommerce-square.md` for the
initial audit and `plans/2026-05-14-woocommerce-square-abilities-harness-output.md`
for the baseline verification artifact.
