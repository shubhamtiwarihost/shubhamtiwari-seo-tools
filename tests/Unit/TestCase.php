<?php
/**
 * Base class for unit tests.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Tests\Unit;

use Brain\Monkey;
use Yoast\PHPUnitPolyfills\TestCases\TestCase as PolyfillTestCase;

/**
 * Sets up and tears down Brain Monkey around each test.
 */
abstract class TestCase extends PolyfillTestCase {

	/**
	 * Sets up Brain Monkey.
	 */
	protected function set_up() {
		parent::set_up();
		Monkey\setUp();
	}

	/**
	 * Tears down Brain Monkey.
	 */
	protected function tear_down() {
		Monkey\tearDown();
		parent::tear_down();
	}
}
