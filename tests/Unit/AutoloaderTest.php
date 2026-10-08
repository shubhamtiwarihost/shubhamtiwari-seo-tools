<?php
/**
 * Tests for the Autoloader.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Tests\Unit;

use DumpSEO\Autoloader;

/**
 * @covers \DumpSEO\Autoloader
 */
final class AutoloaderTest extends TestCase {

	public function test_ignores_foreign_namespaces_and_traversal(): void {
		Autoloader::register( dirname( __DIR__, 2 ) . '/src' );

		Autoloader::load( 'Other\\Thing' );
		Autoloader::load( 'DumpSEO\\..\\..\\etc\\passwd' );

		$this->assertFalse( class_exists( 'Other\\Thing', false ) );
	}
}
