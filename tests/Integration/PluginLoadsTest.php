<?php
/**
 * Smoke tests against a real WordPress install.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Tests\Integration;

use DumpSEO\Lifecycle;
use DumpSEO\Migrations\Migrator;
use DumpSEO\Plugin;
use WP_UnitTestCase;

/**
 * Verifies the plugin boots and stores its bookkeeping correctly inside WordPress.
 */
final class PluginLoadsTest extends WP_UnitTestCase {

	public function test_plugin_constants_and_boot(): void {
		$this->assertTrue( defined( 'DUMPSEO_VERSION' ) );
		$this->assertSame( 1, did_action( 'dumpseo_loaded' ) );
		$this->assertInstanceOf( Plugin::class, Plugin::instance() );
	}

	public function test_activation_is_idempotent_and_version_is_autoloaded(): void {
		delete_option( Migrator::VERSION_OPTION );

		Lifecycle::activate();
		Lifecycle::activate();

		$this->assertSame( DUMPSEO_VERSION, get_option( Migrator::VERSION_OPTION ) );
		$this->assertFalse( get_option( Migrator::LOCK_OPTION ), 'Lock must be released.' );

		// Read on every request, so it must be autoloaded ('yes'/'on' depending on WP version).
		$this->assertContains( $this->autoload_flag( Migrator::VERSION_OPTION ), array( 'yes', 'on' ) );
	}

	public function test_upgrade_path_with_real_options_table(): void {
		update_option( Migrator::VERSION_OPTION, '0.0.1' );
		$ran = array();

		$migrator = new Migrator(
			'0.0.3',
			array(
				'0.0.3' => static function () use ( &$ran ) {
					$ran[] = '0.0.3';
				},
				'0.0.2' => static function () use ( &$ran ) {
					$ran[] = '0.0.2';
				},
			)
		);

		$this->assertSame( array( '0.0.2', '0.0.3' ), $migrator->maybe_run() );
		$this->assertSame( array( '0.0.2', '0.0.3' ), $ran );
		$this->assertSame( '0.0.3', get_option( Migrator::VERSION_OPTION ) );
		$this->assertSame( array(), $migrator->maybe_run(), 'Second run is a no-op.' );
	}

	public function test_migration_lock_is_not_autoloaded(): void {
		add_option( Migrator::LOCK_OPTION, time(), '', false );
		$this->assertContains( $this->autoload_flag( Migrator::LOCK_OPTION ), array( 'no', 'off' ) );
	}

	/**
	 * Reads an option's raw autoload column.
	 *
	 * @param string $name Option name.
	 */
	private function autoload_flag( string $name ): ?string {
		global $wpdb;
		return $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", $name ) );
	}
}
