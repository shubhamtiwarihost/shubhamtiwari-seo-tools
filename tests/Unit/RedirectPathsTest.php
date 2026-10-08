<?php
/**
 * Tests for redirect path handling and loop detection.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Tests\Unit;

use DumpSEO\Redirects\Paths;

/**
 * @covers \DumpSEO\Redirects\Paths
 */
final class RedirectPathsTest extends TestCase {

	public function test_source_normalisation(): void {
		$cases = array(
			'/Old-Page/'                          => '/old-page',
			'old-page'                            => '/old-page',
			'  /a//b/  '                          => '/a/b',
			'https://www.example.org/x?utm=1#top' => '/x',
			'/caf%C3%A9'                          => '/café',
			'/wp-admin-guide'                     => '/wp-admin-guide',
		);
		foreach ( $cases as $input => $expected ) {
			$this->assertSame( $expected, Paths::source( $input, 'example.org' ), $input );
		}
	}

	public function test_rejected_sources(): void {
		foreach ( array( '', '/', 'https://other.com/x', '//other.com/x', '/wp-admin', '/wp-admin/options.php', '/WP-LOGIN.PHP', '/wp-json/wp/v2/posts', '/xmlrpc.php', "/a\x00b", '/wp-content/uploads/x.jpg' ) as $input ) {
			$this->assertNull( Paths::source( $input, 'example.org' ), $input );
		}
		$this->assertNull( Paths::source( '/blog/wp-login.php', 'example.org', '/blog' ), 'Protected paths under a sub-directory install.' );
		$this->assertSame( '/blog/old', Paths::source( '/blog/old', 'example.org', '/blog' ) );
	}

	public function test_request_paths(): void {
		$this->assertSame( '/old-page', Paths::request( '/Old-Page/?utm_source=x' ) );
		$this->assertSame( '/', Paths::request( '/' ) );
		$this->assertSame( '/a/b', Paths::request( '//a///b/' ) );
	}

	public function test_targets(): void {
		$this->assertSame( '/new-page/', Paths::target( ' /new-page/ ' ) );
		$this->assertSame( 'https://other.com/x?y=1', Paths::target( 'https://other.com/x?y=1' ) );
		foreach ( array( '', 'javascript:alert(1)', 'data:text/html,x', '//evil.com/x', 'ftp://x.org/', 'new-page', "https://a.com/\nLocation: x", 'https://a.com/"><script>' ) as $bad ) {
			$this->assertNull( Paths::target( $bad ), $bad );
		}
	}

	public function test_loop_detection(): void {
		$index = array(
			'/b'    => array(
				'target' => '/c',
				'type'   => 301,
			),
			'/c'    => array(
				'target' => 'https://example.org/a/',
				'type'   => 302,
			),
			'/gone' => array(
				'target' => '',
				'type'   => 410,
			),
		);

		$this->assertTrue( Paths::loops( '/a', '/a/', array(), 'example.org' ), 'Self-redirect.' );
		$this->assertTrue( Paths::loops( '/a', '/b', $index, 'example.org' ), 'a → b → c → a.' );
		$this->assertFalse( Paths::loops( '/a', '/d', $index, 'example.org' ), 'Ends on a real page.' );
		$this->assertFalse( Paths::loops( '/a', 'https://other.com/a', $index, 'example.org' ), 'Leaves the site.' );
		$this->assertFalse( Paths::loops( '/a', '/gone', $index, 'example.org' ) );

		$long = array();
		for ( $i = 0; $i < 12; $i++ ) {
			$long[ '/p' . $i ] = array(
				'target' => '/p' . ( $i + 1 ),
				'type'   => 301,
			);
		}
		$this->assertTrue( Paths::loops( '/start', '/p0', $long, 'example.org' ), 'Chains over 10 steps are rejected.' );
	}
}
