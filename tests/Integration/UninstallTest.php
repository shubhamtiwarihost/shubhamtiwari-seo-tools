<?php
/**
 * Uninstall behaviour.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Tests\Integration;

use DumpSEO\Migrations\Migrator;
use DumpSEO\Settings\Settings;
use WP_UnitTestCase;

/**
 * Runs the real uninstall.php and checks what it removes (and what it keeps).
 */
final class UninstallTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		// Store raw values; the sanitize callback is irrelevant to uninstall.
		remove_all_filters( 'sanitize_option_' . Settings::OPTION );
	}

	public function test_without_opt_in_settings_and_version_are_kept(): void {
		update_option( Settings::OPTION, array( 'separator' => 'pipe' ) );
		update_option( Migrator::VERSION_OPTION, '0.1.0' );
		update_option( Migrator::LOCK_OPTION, time() );

		$this->run_uninstall();

		$this->assertSame( array( 'separator' => 'pipe' ), get_option( Settings::OPTION ) );
		$this->assertSame( '0.1.0', get_option( Migrator::VERSION_OPTION ), 'Kept so a reinstall upgrades instead of treating data as fresh.' );
		$this->assertFalse( get_option( Migrator::LOCK_OPTION ), 'Temporary lock is always removed.' );
	}

	public function test_opt_in_removes_data_and_keeps_content(): void {
		$post_id  = self::factory()->post->create( array( 'post_title' => 'Keep me' ) );
		$redirect = self::factory()->post->create(
			array(
				'post_type'  => 'dumpseo_redirect',
				'post_title' => '/old',
			)
		);
		update_option( 'dumpseo_redirect_index', array( '/old' => array() ) );
		update_option( Settings::OPTION, array( 'remove_data_on_uninstall' => true ) );
		update_option( Migrator::VERSION_OPTION, '0.1.0' );

		$this->run_uninstall();

		$this->assertFalse( get_option( Settings::OPTION ) );
		$this->assertFalse( get_option( Migrator::VERSION_OPTION ) );
		$this->assertNull( get_post( $redirect ), 'Redirects are DumpSEO data.' );
		$this->assertFalse( get_option( 'dumpseo_redirect_index' ) );
		$this->assertSame( 'Keep me', get_the_title( $post_id ), 'Uninstall must never delete content.' );
	}

	public function test_truthy_non_boolean_does_not_count_as_opt_in(): void {
		update_option( Settings::OPTION, array( 'remove_data_on_uninstall' => '1' ) );

		$this->run_uninstall();

		$this->assertNotFalse( get_option( Settings::OPTION ), 'Only a real boolean true (saved via the settings page) deletes data.' );
	}

	public function test_multisite_respects_each_sites_own_choice(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite only. Run: npm run test:php:multisite' );
		}

		$keep_site   = self::factory()->blog->create();
		$remove_site = self::factory()->blog->create();

		switch_to_blog( $keep_site );
		remove_all_filters( 'sanitize_option_' . Settings::OPTION );
		update_option( Settings::OPTION, array( 'separator' => 'pipe' ) );
		restore_current_blog();

		switch_to_blog( $remove_site );
		update_option( Settings::OPTION, array( 'remove_data_on_uninstall' => true ) );
		restore_current_blog();

		$this->run_uninstall();

		switch_to_blog( $keep_site );
		$this->assertSame( array( 'separator' => 'pipe' ), get_option( Settings::OPTION ) );
		restore_current_blog();

		switch_to_blog( $remove_site );
		$this->assertFalse( get_option( Settings::OPTION ) );
		restore_current_blog();
	}

	/**
	 * Includes uninstall.php the way WordPress does.
	 */
	private function run_uninstall(): void {
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'dumpseo/dumpseo.php' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Core constant uninstall.php checks for.
		}
		require dirname( __DIR__, 2 ) . '/uninstall.php';
	}
}
