<?php
/**
 * Tests for Container.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Tests\Unit;

use DumpSEO\Container;

/**
 * Covers the DI container.
 *
 * @covers \DumpSEO\Container
 */
final class ContainerTest extends TestCase {

	public function test_services_are_built_lazily_and_shared(): void {
		$container = new Container();
		$builds    = 0;
		$container->set(
			'svc',
			static function () use ( &$builds ) {
				++$builds;
				return new \stdClass();
			}
		);

		$this->assertSame( 0, $builds, 'Factory must not run before first get().' );
		$first = $container->get( 'svc' );
		$this->assertSame( $first, $container->get( 'svc' ) );
		$this->assertSame( 1, $builds );
	}

	public function test_factory_receives_container_for_dependencies(): void {
		$container = new Container();
		$container->set(
			'dep',
			static function () {
				return 'value';
			}
		);
		$container->set(
			'svc',
			static function ( Container $c ) {
				return 'uses ' . $c->get( 'dep' );
			}
		);

		$this->assertSame( 'uses value', $container->get( 'svc' ) );
	}

	public function test_unknown_service_throws(): void {
		$this->expectException( \OutOfBoundsException::class );
		( new Container() )->get( 'missing' );
	}

	public function test_service_can_be_replaced_before_it_is_built(): void {
		$container = new Container();
		$container->set(
			'svc',
			static function () {
				return 'core';
			}
		);
		$container->set(
			'svc',
			static function () {
				return 'extension';
			}
		);

		$this->assertSame( 'extension', $container->get( 'svc' ) );
	}

	public function test_built_service_cannot_be_replaced(): void {
		$container = new Container();
		$container->set(
			'svc',
			static function () {
				return 'core';
			}
		);
		$container->get( 'svc' );

		$this->expectException( \LogicException::class );
		$container->set(
			'svc',
			static function () {
				return 'late';
			}
		);
	}
}
