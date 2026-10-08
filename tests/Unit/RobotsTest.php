<?php
/**
 * Tests for robots token parsing.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Tests\Unit;

use DumpSEO\Meta\Robots;

/**
 * Covers the allowlist and conflict rules for stored robots values.
 *
 * @covers \DumpSEO\Meta\Robots::sanitize
 * @covers \DumpSEO\Meta\Robots::parse
 */
final class RobotsTest extends TestCase {

	/**
	 * Raw values and their normalised form.
	 *
	 * @return array<string, array{mixed, string}>
	 */
	public function sanitize_provider(): array {
		return array(
			'empty'               => array( '', '' ),
			'single'              => array( 'noindex', 'noindex' ),
			'fixed order'         => array( 'nofollow,noindex', 'noindex,nofollow' ),
			'case and spaces'     => array( ' NoIndex , NOFOLLOW ', 'noindex,nofollow' ),
			'duplicates'          => array( 'nofollow,nofollow', 'nofollow' ),
			'noindex beats index' => array( 'index,noindex', 'noindex' ),
			'index alone kept'    => array( 'index', 'index' ),
			'unknown dropped'     => array( 'noindex,evil,<script>,max-snippet:-1,none', 'noindex' ),
			'array input'         => array( array( 'noarchive', 'nosnippet', 5, array( 'x' ) ), 'noarchive,nosnippet' ),
			'non-string input'    => array( 42, '' ),
			'null input'          => array( null, '' ),
			'all extras'          => array( 'noimageindex,nosnippet,noarchive,nofollow', 'nofollow,noarchive,nosnippet,noimageindex' ),
		);
	}

	/**
	 * @dataProvider sanitize_provider
	 *
	 * @param mixed  $raw      Raw value.
	 * @param string $expected Normalised value.
	 */
	public function test_sanitize( $raw, string $expected ): void {
		$this->assertSame( $expected, Robots::sanitize( $raw ) );
	}

	public function test_sanitize_is_idempotent(): void {
		$once = Robots::sanitize( 'nosnippet, index, NOINDEX, nofollow' );
		$this->assertSame( $once, Robots::sanitize( $once ) );
	}
}
