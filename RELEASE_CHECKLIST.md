# Release Checklist

Copy this list into the release PR for each version. Every box needs evidence (CI link, command output or test ID). Items that could not be run are marked **NOT TESTED** with a reason — never ticked.

Version: `x.y.z`   Date: `YYYY-MM-DD`   Release owner: `____`

## Code quality
- [ ] `composer ci` passes (lint, version check, PHPCS, PHPStan, unit tests)
- [ ] Unit tests pass on PHP 7.4, 8.0, 8.1, 8.2, 8.3 (CI matrix)
- [ ] Integration tests pass on the minimum PHP 7.4 (`bin/test-integration-php.sh 7.4 single|multisite|woo`)
- [ ] Integration tests pass on WordPress 6.4 and latest
- [ ] `npm run lint:js`, `npm run test:js`, `npm run build` pass
- [ ] Plugin Check (`wordpress/plugin-check-action`) — no errors

## Security, privacy & licensing
- [ ] Security audit done for all changes (see SECURITY.md process)
- [ ] Role/capability tests pass (anonymous → super admin)
- [ ] No new outbound HTTP requests / telemetry (or documented and consented)
- [ ] License audit — every bundled third-party file listed in `docs/LICENSES.md`, GPL-compatible

## Release metadata
- [ ] Version bumped in plugin header, `DUMPSEO_VERSION`, readme `Stable tag`, `package.json`, `composer.json` (`composer check-versions`)
- [ ] `Tested up to` equals a WordPress version the integration tests actually ran on
- [ ] readme.txt validated with the WordPress.org readme validator
- [ ] CHANGELOG.md and readme.txt changelog updated
- [ ] Screenshots updated if UI changed

## Package
- [ ] Production ZIP built from `.distignore`; contains no `vendor/`, `node_modules/`, tests, dotfiles
- [ ] ZIP installed on a clean WordPress site: activates, no PHP notices/warnings, no JS console errors
- [ ] Upgrade from the previous release tested — settings and meta intact
- [ ] Rollback strategy documented (previous tag remains installable)
- [ ] Deactivate → reactivate works; uninstall behaves as documented
- [ ] Multisite: network activation + per-site activation tested

## Sign-off
- [ ] No open P0/P1 bugs
- [ ] Release approved by: `____`
