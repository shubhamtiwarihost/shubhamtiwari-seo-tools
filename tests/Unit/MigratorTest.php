<?php
/**
 * Tests for Migrator.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Tests\Unit;

use Brain\Monkey\Actions;
use DumpSEO\Migrations\Migrator;

/**
 * Covers install, upgrade, ordering, locking and failure recovery.
 *
 * @covers \DumpSEO\Migrations\Migrator
 */
final class MigratorTest extends TestCase {

	/**
	 * Steps that ran, in order.
	 *
	 * @var string[]
	 */
	private $ran = array();

	/**
	 * Builds steps that record themselves in $this->ran. Deliberately unsorted.
	 *
	 * @param string[] $versions Versions.
	 * @return array<string, callable(): void>
	 */
	private function steps( array $versions ): array {
		$steps = array();
		foreach ( $versions as $version ) {
			$steps[ $version ] = function () use ( $version ) {
				$this->ran[] = $version;
			};
		}
		return $steps;
	}

	public function test_fresh_install_records_version_without_running_steps(): void {
		$options = OptionsStub::install();
		Actions\expectDone( 'dumpseo_installed' )->once()->with( '1.1.0' );
		Actions\expectDone( 'dumpseo_upgraded' )->never();

		$result = ( new Migrator( '1.1.0', $this->steps( array( '1.0.1', '1.1.0' ) ) ) )->maybe_run();

		$this->assertSame( array(), $result );
		$this->assertSame( array(), $this->ran );
		$this->assertSame( '1.1.0', $options->options[ Migrator::VERSION_OPTION ] );
		$this->assertTrue( $options->autoload[ Migrator::VERSION_OPTION ], 'Version option is read every request, so it must autoload.' );
		$this->assertArrayNotHasKey( Migrator::LOCK_OPTION, $options->options, 'Lock must be released.' );
	}

	public function test_upgrade_runs_only_pending_steps_in_version_order(): void {
		$options = OptionsStub::install( array( Migrator::VERSION_OPTION => '1.0.0' ) );
		Actions\expectDone( 'dumpseo_upgraded' )->once()->with( '1.0.0', '1.1.0', array( '1.0.1', '1.0.10', '1.1.0' ) );

		$steps = $this->steps( array( '1.2.0', '1.0.10', '0.9.0', '1.1.0', '1.0.0', '1.0.1' ) );
		$ran   = ( new Migrator( '1.1.0', $steps ) )->maybe_run();

		// 1.0.10 > 1.0.1 under version_compare (a plain string sort would get this wrong).
		$this->assertSame( array( '1.0.1', '1.0.10', '1.1.0' ), $this->ran );
		$this->assertSame( $this->ran, $ran );
		$this->assertSame( '1.1.0', $options->options[ Migrator::VERSION_OPTION ] );
	}

	public function test_up_to_date_site_does_nothing(): void {
		OptionsStub::install( array( Migrator::VERSION_OPTION => '1.1.0' ) );
		Actions\expectDone( 'dumpseo_upgraded' )->never();

		$migrator = new Migrator( '1.1.0', $this->steps( array( '1.1.0' ) ) );

		$this->assertFalse( $migrator->needs_run() );
		$this->assertSame( array(), $migrator->maybe_run() );
		$this->assertSame( array(), $this->ran );
	}

	public function test_downgraded_code_never_runs_steps_or_lowers_version(): void {
		$options = OptionsStub::install( array( Migrator::VERSION_OPTION => '2.0.0' ) );

		( new Migrator( '1.1.0', $this->steps( array( '1.1.0' ) ) ) )->maybe_run();

		$this->assertSame( array(), $this->ran );
		$this->assertSame( '2.0.0', $options->options[ Migrator::VERSION_OPTION ] );
	}

	public function test_active_lock_blocks_concurrent_run(): void {
		$options = OptionsStub::install(
			array(
				Migrator::VERSION_OPTION => '1.0.0',
				Migrator::LOCK_OPTION    => time() - 5,
			)
		);

		( new Migrator( '1.1.0', $this->steps( array( '1.1.0' ) ) ) )->maybe_run();

		$this->assertSame( array(), $this->ran );
		$this->assertSame( '1.0.0', $options->options[ Migrator::VERSION_OPTION ] );
		$this->assertArrayHasKey( Migrator::LOCK_OPTION, $options->options, 'Must not release a lock it does not own.' );
	}

	public function test_stale_lock_is_taken_over(): void {
		OptionsStub::install(
			array(
				Migrator::VERSION_OPTION => '1.0.0',
				Migrator::LOCK_OPTION    => time() - Migrator::LOCK_TIMEOUT - 1,
			)
		);

		( new Migrator( '1.1.0', $this->steps( array( '1.1.0' ) ) ) )->maybe_run();

		$this->assertSame( array( '1.1.0' ), $this->ran );
	}

	public function test_failed_step_keeps_progress_releases_lock_and_rethrows(): void {
		$options = OptionsStub::install( array( Migrator::VERSION_OPTION => '1.0.0' ) );

		$steps          = $this->steps( array( '1.0.1', '1.2.0' ) );
		$steps['1.1.0'] = static function () {
			throw new \RuntimeException( 'boom' );
		};

		try {
			( new Migrator( '1.2.0', $steps ) )->maybe_run();
			$this->fail( 'Exception should propagate.' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'boom', $e->getMessage() );
		}

		$this->assertSame( array( '1.0.1' ), $this->ran );
		$this->assertSame( '1.0.1', $options->options[ Migrator::VERSION_OPTION ], 'Next request resumes after the last successful step.' );
		$this->assertArrayNotHasKey( Migrator::LOCK_OPTION, $options->options );
	}
}
