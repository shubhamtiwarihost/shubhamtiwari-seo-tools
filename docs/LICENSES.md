# Dependency & License Inventory

DumpSEO is GPL-2.0-or-later. This file lists every third-party component **shipped in the distributed plugin ZIP**, plus development-only tooling for transparency.

## Shipped in the plugin ZIP

| Library | Version | License | Source | GPL-compatible | Attribution required |
|---|---|---|---|---|---|
| _(none)_ | | | | | |

`build/editor/index.js` and `build/blocks/breadcrumbs/index.js` are compiled from our own `assets-src/` (plus webpack's generated module loader, which is build output, not a library); WordPress packages (`@wordpress/*`) are referenced as external `wp.*` globals provided by WordPress core and are **not** bundled (see the `index.asset.php` next to each bundle). Re-verify this whenever a dependency is added.

## Development-only (NOT shipped)

| Tool | License | Purpose |
|---|---|---|
| PHPUnit, Brain Monkey, Yoast PHPUnit Polyfills | BSD-3 / MIT / BSD-3 | Tests |
| PHP_CodeSniffer, WPCS, PHPCompatibilityWP | BSD-3 / MIT / LGPL-3 | Coding standards |
| PHPStan, phpstan-wordpress, wordpress-stubs | MIT | Static analysis |
| @wordpress/scripts, @wordpress/env, TypeScript | GPL-2.0+ / Apache-2.0 | Build, lint, test environment |

The original source of both bundles (`assets-src/`) and the build definition (`package.json`, `babel.config.js`) are shipped in the ZIP, as WordPress.org guideline 4 requires for compiled code.

Last audited: 2026-09-30 (Phase 16, release readiness): both bundles checked for license banners and third-party code — none; `assets/js/*.js` and `assets/css/*.css` are our own.
