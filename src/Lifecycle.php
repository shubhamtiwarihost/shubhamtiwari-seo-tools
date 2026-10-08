<?php
/**
 * Activation and deactivation handlers.
 *
 * @package DumpSEO
 */

namespace DumpSEO;

use DumpSEO\Migrations\Migrator;

defined( 'ABSPATH' ) || exit;

/**
 * Activation/deactivation. Uninstall lives in uninstall.php.
 */
final class Lifecycle {

	/**
	 * Runs on activation. Idempotent.
	 *
	 * On network activation only the current site is initialised here. Every
	 * other site initialises itself on its first request (Plugin::boot() runs
	 * the migrator), which avoids looping over thousands of sites in one
	 * request.
	 *
	 * @param bool $network_wide Whether the plugin is being network-activated.
	 */
	public static function activate( $network_wide = false ): void {
		unset( $network_wide );

		$migrator = Plugin::instance()->container()->get( Migrator::class );
		if ( $migrator instanceof Migrator ) {
			$migrator->maybe_run();
		}
	}

	/**
	 * Runs on deactivation. Never deletes user data.
	 */
	public static function deactivate(): void {
		// Nothing to clean up yet. Rewrite rules are flushed here once the sitemap module exists.
	}
}
