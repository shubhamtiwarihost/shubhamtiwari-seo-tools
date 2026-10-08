#!/usr/bin/env bash
#
# Runs the integration suite on a specific PHP version.
#
# wp-env can only switch PHP by rebuilding its images, and its PHP 7.4 image
# no longer builds (the base image's package repositories are gone). This
# script reuses the running wp-env test environment (WordPress files, test
# library, MySQL) and runs PHPUnit in the official php:<version>-cli image
# with the mysqli extension added.
#
# Usage: bin/test-integration-php.sh <php-version> [single|multisite|woo]
# Example: bin/test-integration-php.sh 7.4 multisite
# Requires: `npm run env:start` first. Tests whatever WordPress version wp-env runs.

set -euo pipefail

php_version="${1:?PHP version required, e.g. 7.4}"
mode="${2:-single}"

plugin_dir="$(cd "$(dirname "$0")/.." && pwd)"
install_path="$(cd "$plugin_dir" && npx wp-env install-path 2>/dev/null | tail -1)"
env_id="$(basename "$install_path")"
network="${env_id}_default"
image="dumpseo-test-php:${php_version}"

printf 'FROM php:%s-cli\nRUN docker-php-ext-install mysqli >/dev/null\n' "$php_version" | docker build -q -t "$image" - >/dev/null

extra_env=()
case "$mode" in
	single) ;;
	multisite) extra_env+=( -e WP_MULTISITE=1 ) ;;
	woo) extra_env+=( -e DUMPSEO_TEST_WOO=1 ) ;;
	*) echo "Unknown mode: $mode (single, multisite or woo)" >&2; exit 2 ;;
esac

woo_mount=()
for dir in "$install_path"/woocommerce*; do
	[ -d "$dir" ] && woo_mount+=( -v "$dir:/var/www/html/wp-content/plugins/$(basename "$dir")" )
done

docker run --rm --network "$network" \
	-e WP_TESTS_DIR=/wordpress-phpunit \
	-e WORDPRESS_DB_USER=root -e WORDPRESS_DB_PASSWORD=password \
	-e WORDPRESS_DB_NAME=tests-wordpress -e WORDPRESS_DB_HOST=tests-mysql \
	${extra_env[@]+"${extra_env[@]}"} \
	-v "$install_path/tests-WordPress:/var/www/html" \
	-v "$install_path/tests-WordPress-PHPUnit/tests/phpunit:/wordpress-phpunit" \
	${woo_mount[@]+"${woo_mount[@]}"} \
	-v "$plugin_dir:/var/www/html/wp-content/plugins/dumpseo" \
	-w /var/www/html/wp-content/plugins/dumpseo \
	"$image" sh -c 'echo "PHP $(php -r "echo PHP_VERSION;")"; vendor/bin/phpunit -c phpunit-integration.xml.dist'
