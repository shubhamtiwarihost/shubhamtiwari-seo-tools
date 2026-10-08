<?php
/**
 * List of migration steps.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Migrations;

defined( 'ABSPATH' ) || exit;

/**
 * Migration steps keyed by the plugin version that introduces them.
 *
 * Rules for adding a step:
 * - Key it with the release version that ships it (e.g. '1.1.0').
 * - It must be idempotent: running it twice leaves the same result.
 * - Never delete user content; migrate it.
 * - Large data sets must be processed in batches (schedule a cron event).
 */
final class Registry {

	/**
	 * Returns all migration steps.
	 *
	 * @return array<string, callable(): void>
	 */
	public static function all(): array {
		return array();
	}
}
