<?php
/**
 * Versioned data migrations.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Migrations;

defined( 'ABSPATH' ) || exit;

/**
 * Brings stored data up to the running plugin version.
 *
 * Runs on activation AND on normal requests, because network activation and
 * upgrades by file replacement (FTP, deploy tools) never fire the activation
 * hook for every site. The per-request cost is one autoloaded option read and
 * a string comparison.
 *
 * Each migration step is recorded as soon as it completes, so an interrupted
 * run resumes where it stopped. A short-lived lock prevents two concurrent
 * requests from migrating at the same time.
 */
final class Migrator {

	public const VERSION_OPTION = 'dumpseo_db_version';
	public const LOCK_OPTION    = 'dumpseo_migration_lock';
	public const LOCK_TIMEOUT   = 600;

	/**
	 * Running plugin version.
	 *
	 * @var string
	 */
	private $code_version;

	/**
	 * Migration steps keyed by the version that introduced them.
	 *
	 * @var array<string, callable(): void>
	 */
	private $migrations;

	/**
	 * Constructor.
	 *
	 * @param string                          $code_version Running plugin version.
	 * @param array<string, callable(): void> $migrations   Steps keyed by version.
	 */
	public function __construct( string $code_version, array $migrations ) {
		$this->code_version = $code_version;
		$this->migrations   = $migrations;
	}

	/**
	 * Stored data version, or null on a fresh install.
	 */
	public function stored_version(): ?string {
		$version = get_option( self::VERSION_OPTION );
		return is_string( $version ) && '' !== $version ? $version : null;
	}

	/**
	 * Whether stored data is older than the code.
	 */
	public function needs_run(): bool {
		$stored = $this->stored_version();
		return null === $stored || version_compare( $stored, $this->code_version, '<' );
	}

	/**
	 * Runs pending steps if needed.
	 *
	 * @return string[] Versions of the steps that ran, in order.
	 */
	public function maybe_run(): array {
		if ( ! $this->needs_run() || ! $this->acquire_lock() ) {
			return array();
		}

		try {
			$stored = $this->stored_version();

			if ( null === $stored ) {
				// Fresh install: nothing to migrate.
				$this->record( $this->code_version );

				/**
				 * Fires once when DumpSEO is installed on a site for the first time.
				 *
				 * @param string $version Installed plugin version.
				 */
				do_action( 'dumpseo_installed', $this->code_version );
				return array();
			}

			$ran = array();
			foreach ( $this->pending( $stored ) as $version => $step ) {
				$step();
				$this->record( $version );
				$ran[] = $version;
			}
			$this->record( $this->code_version );

			/**
			 * Fires after DumpSEO data has been upgraded.
			 *
			 * @param string   $from Previous data version.
			 * @param string   $to   New data version.
			 * @param string[] $ran  Migration steps that ran.
			 */
			do_action( 'dumpseo_upgraded', $stored, $this->code_version, $ran );
			return $ran;
		} finally {
			delete_option( self::LOCK_OPTION );
		}
	}

	/**
	 * Steps newer than $stored and not newer than the code, oldest first.
	 *
	 * @param string $stored Stored data version.
	 * @return array<string, callable(): void>
	 */
	public function pending( string $stored ): array {
		$pending = array();
		foreach ( $this->migrations as $version => $step ) {
			$version = (string) $version;
			if ( version_compare( $version, $stored, '>' ) && version_compare( $version, $this->code_version, '<=' ) ) {
				$pending[ $version ] = $step;
			}
		}
		uksort( $pending, 'version_compare' );
		return $pending;
	}

	/**
	 * Stores the data version (autoloaded: it is read on every request).
	 *
	 * @param string $version Version to store.
	 */
	private function record( string $version ): void {
		update_option( self::VERSION_OPTION, $version, true );
	}

	/**
	 * Takes the migration lock. add_option() is atomic on the unique option_name key.
	 */
	private function acquire_lock(): bool {
		if ( add_option( self::LOCK_OPTION, time(), '', false ) ) {
			return true;
		}

		$locked_at = (int) get_option( self::LOCK_OPTION );
		if ( $locked_at > 0 && time() - $locked_at < self::LOCK_TIMEOUT ) {
			return false;
		}

		// Stale lock from a crashed run: replace it.
		delete_option( self::LOCK_OPTION );
		return add_option( self::LOCK_OPTION, time(), '', false );
	}
}
