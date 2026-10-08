<?php
/**
 * Tests for Context.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Tests\Unit;

use Brain\Monkey\Functions;
use DumpSEO\Context;

/**
 * Covers request detection.
 *
 * @covers \DumpSEO\Context
 */
final class ContextTest extends TestCase {

	/**
	 * Request types and whether each counts as frontend.
	 *
	 * @return array<string, array{bool, bool, bool}>
	 */
	public function request_provider(): array {
		return array(
			'theme request' => array( false, false, true ),
			'admin screen'  => array( true, false, false ),
			'cron'          => array( false, true, false ),
		);
	}

	/**
	 * @dataProvider request_provider
	 *
	 * @param bool $is_admin    is_admin().
	 * @param bool $doing_cron  wp_doing_cron().
	 * @param bool $is_frontend Expected is_frontend().
	 */
	public function test_is_frontend( bool $is_admin, bool $doing_cron, bool $is_frontend ): void {
		Functions\when( 'is_admin' )->justReturn( $is_admin );
		Functions\when( 'wp_doing_cron' )->justReturn( $doing_cron );

		$context = new Context();

		$this->assertSame( $is_admin, $context->is_admin() );
		$this->assertSame( $doing_cron, $context->is_cron() );
		$this->assertSame( $is_frontend, $context->is_frontend() );
	}

	public function test_is_ajax_delegates_to_wordpress(): void {
		Functions\when( 'wp_doing_ajax' )->justReturn( true );
		$this->assertTrue( ( new Context() )->is_ajax() );
	}
}
