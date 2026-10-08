# Development

## Prerequisites
- PHP with `mbstring`, `xml`, `zip`. The plugin supports PHP 7.4, 8.0, 8.1, 8.2 and 8.3; code must stay 7.4-compatible (PHPCS and PHPStan enforce it). A newer local PHP is fine for running the tools.
- Composer 2 (`brew install composer`)
- Node.js 20+ and npm
- Docker Desktop (running) — for the wp-env WordPress + MySQL environment

## Setup
```bash
composer install
npm install
npm run env:start      # WordPress at http://localhost:8888 (admin / password)
```

## Everyday commands
| Command | What it does |
|---|---|
| `composer lint` | `php -l` on every PHP file |
| `composer check-versions` | Fails if plugin header, `DUMPSEO_VERSION`, readme Stable tag and package.json disagree |
| `composer phpcs` / `composer phpcbf` | WordPress Coding Standards + PHP 7.4 compatibility / auto-fix |
| `composer phpstan` | Static analysis, level 6, with WordPress stubs |
| `composer test` | Unit tests (Brain Monkey, no WordPress) |
| `composer ci` | All of the above |
| `npm run test:php:integration` | Integration tests inside wp-env against real WordPress + MySQL |
| `npm run test:php:multisite` | Same integration suite, as a multisite network |
| `bin/test-integration-php.sh <7.4\|8.0\|8.1\|8.2\|8.3> [single\|multisite\|woo]` | Integration suite on a specific PHP version, using the running wp-env environment (Docker required) |
| `npm run test:php:woo` | Same integration suite with WooCommerce active (wp-env installs it); includes the `woocommerce` test group, which is skipped otherwise. Needs a WordPress version current WooCommerce supports |
| `npm run lint:js` / `npm run test:js` | ESLint (WordPress config) / Jest |
| `npm run build` | Build the editor sidebar (`build/editor/`) and block scripts (`build/blocks/*/`) |
| `npm run env:stop` | Stop the wp-env containers |

## Tests
- `tests/Unit/` — pure logic, WordPress functions mocked with Brain Monkey. Fast; run on every change.
- `tests/Integration/` — real WordPress via the core test suite that wp-env provides at `/wordpress-phpunit`.
- Test WordPress 6.4 locally: `echo '{"core":"WordPress/WordPress#6.4"}' > .wp-env.override.json && npx wp-env start --update`.

## Coding rules
- WordPress Coding Standards; PSR-4 class files in `src/`.
- Every `phpcs:ignore` or config exclusion must carry a reason comment.
- No `@phpstan-ignore` without a reason.
- Sanitize on input, escape on output (late), nonce + `current_user_can()` for every state change.
- All user-facing strings use text domain `dumpseo`.

## Git
- `main` is always releasable. Work on branches; merge via PR once CI is green.
