# AGENTS.md

## Project Knowledge

- WooCommerce Square plugin: WooCommerce payments + catalog/inventory sync with Square.
- Main PHP runtime code is in `includes/`.
- JS/CSS sources are in `src/`; built artifacts are in `build/`.
- Plugin bootstrap and version constants are in `woocommerce-square.php`.

## CRITICAL

- Keep `CLAUDE.md` as `@AGENTS.md` so this file is the single source of truth.
- Edit `src/`, not `build/`; do not edit `vendor/` or WordPress/WooCommerce core.
- Never commit secrets (Square credentials stay in local env files/secrets).

## Commands

- Setup: `nvm use && npm ci && composer install`
- Build package: `npm run build`
- JS lint: `npm run lint:js`
- PHP lint: `./vendor/bin/phpcs`
- Start local env: `npm run env:start`
- Stop local env: `npm run env:stop`
- Env health check: `curl -fsS -I http://localhost:8888 | head -n 1`
- Square plugin status (CLI): `wp-env run cli wp plugin is-active woocommerce-square && echo ACTIVE || echo INACTIVE`
- E2E tests: `npm run test:e2e` (requires Square credentials in `tests/e2e/config/.env`)

## Conventions

- Main development branch is `trunk`; release branches use `release/{version}`.
- Changelog entries in `changelog.txt` use prefixes: `Add -`, `Fix -`, `Dev -`.
- Playwright E2E test titles MUST include one tag: `@general`, `@giftcard`, `@cashapp`, or `@sync`.

## Architectural Notes

- Bootstrap flow is defined by `WooCommerce_Square_Loader` in `woocommerce-square.php`.
- Plugin namespace `WooCommerce\\Square\\` maps to `includes/`.
- `build/` is generated output from `src/`; avoid hand edits.

## Common Pitfalls

- `npm run env:start` can exit non-zero when optional test plugins are unavailable; confirm env status using the health check command.
- Browser-level Square status checks should use `/wp-admin/plugins.php` and `tr[data-slug="woocommerce-square"]`.
