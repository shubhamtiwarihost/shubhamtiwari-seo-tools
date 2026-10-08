<?php
/**
 * Tests for the Plugin module registry.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Tests\Unit;

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use DumpSEO\Admin\SettingsPage;
use DumpSEO\Container;
use DumpSEO\Context;
use DumpSEO\Migrations\Migrator;
use DumpSEO\Module;
use DumpSEO\Plugin;

/**
 * Covers boot order, module filtering and extension hooks.
 *
 * @covers \DumpSEO\Plugin
 */
final class PluginTest extends TestCase {

	protected function set_up() {
		parent::set_up();
		OptionsStub::install( array( Migrator::VERSION_OPTION => DUMPSEO_VERSION ) );
		Functions\when( 'is_admin' )->justReturn( false );
		Functions\when( 'wp_doing_cron' )->justReturn( false );
	}

	protected function tear_down() {
		Plugin::reset();
		parent::tear_down();
	}

	public function test_boot_registers_only_modules_that_should_load(): void {
		$active   = $this->module( true );
		$inactive = $this->module( false );

		$active->expects( $this->once() )->method( 'register' );
		$inactive->expects( $this->never() )->method( 'register' );

		Filters\expectApplied( 'dumpseo_modules' )->once()->andReturn(
			array(
				'active'   => $active,
				'inactive' => $inactive,
				'bogus'    => new \stdClass(),
			)
		);
		Actions\expectDone( 'dumpseo_loaded' )->once();

		$plugin = Plugin::instance();
		$plugin->boot();

		$this->assertSame( $active, $plugin->module( 'active' ) );
		$this->assertNull( $plugin->module( 'inactive' ) );
		$this->assertNull( $plugin->module( 'bogus' ) );
	}

	public function test_boot_runs_only_once(): void {
		Filters\expectApplied( 'dumpseo_modules' )->once()->andReturn( array() );

		Plugin::instance()->boot();
		Plugin::instance()->boot();

		$this->addToAssertionCount( 1 );
	}

	public function test_boot_runs_migrations_before_modules(): void {
		$options = OptionsStub::install();
		Actions\expectDone( 'dumpseo_installed' )->once();
		Filters\expectApplied( 'dumpseo_modules' )->once()->andReturnUsing(
			function () use ( $options ) {
				$this->assertSame( DUMPSEO_VERSION, $options->options[ Migrator::VERSION_OPTION ] ?? null );
				return array();
			}
		);

		Plugin::instance()->boot();
	}

	public function test_extensions_can_replace_services_before_modules_build(): void {
		$custom = new Context();
		Actions\expectDone( 'dumpseo_container' )->once()->whenHappen(
			static function ( Container $container ) use ( $custom ) {
				$container->set(
					Context::class,
					static function () use ( $custom ) {
						return $custom;
					}
				);
			}
		);

		$plugin = Plugin::instance();
		$plugin->boot();

		$this->assertSame( $custom, $plugin->container()->get( Context::class ) );
	}

	public function test_core_services_are_declared(): void {
		$container = Plugin::build_container();

		$this->assertInstanceOf( Context::class, $container->get( Context::class ) );
		$this->assertInstanceOf( Migrator::class, $container->get( Migrator::class ) );
		$this->assertInstanceOf( SettingsPage::class, $container->get( SettingsPage::class ) );
	}

	public function test_settings_page_does_not_load_on_frontend(): void {
		$plugin = Plugin::instance();
		$plugin->boot();

		$this->assertNull( $plugin->module( 'settings_page' ) );
	}

	public function test_settings_page_loads_in_admin(): void {
		Functions\when( 'is_admin' )->justReturn( true );
		Functions\when( 'plugin_basename' )->justReturn( 'dumpseo/dumpseo.php' );

		$plugin = Plugin::instance();
		$plugin->boot();

		$this->assertInstanceOf( SettingsPage::class, $plugin->module( 'settings_page' ) );
	}

	/**
	 * Creates a mock module.
	 *
	 * @param bool $should_load Return value of should_load().
	 * @return Module&\PHPUnit\Framework\MockObject\MockObject
	 */
	private function module( bool $should_load ) {
		$module = $this->createMock( Module::class );
		$module->method( 'should_load' )->willReturn( $should_load );
		return $module;
	}
}
