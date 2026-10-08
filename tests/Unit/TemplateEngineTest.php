<?php
/**
 * Tests for TemplateEngine and text truncation.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Tests\Unit;

use Brain\Monkey\Functions;
use DumpSEO\Meta\TemplateEngine;
use DumpSEO\Meta\VariableValues;

/**
 * Covers variable replacement, separator cleanup and injection resistance.
 *
 * @covers \DumpSEO\Meta\TemplateEngine
 * @covers \DumpSEO\Meta\VariableValues::truncate
 */
final class TemplateEngineTest extends TestCase {

	/**
	 * Engine under test.
	 *
	 * @var TemplateEngine
	 */
	private $engine;

	protected function set_up() {
		parent::set_up();
		Functions\when( 'wp_strip_all_tags' )->alias(
			static function ( $text ) {
				// Same behaviour as core for these inputs: drop script/style blocks, then tags.
				$text = (string) preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $text );
				return trim( strip_tags( $text ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Test double for wp_strip_all_tags().
			}
		);
		$this->engine = new TemplateEngine();
	}

	/**
	 * Templates and expected output.
	 *
	 * @return array<string, array{string, array<string, string>, string}>
	 */
	public function render_provider(): array {
		$site = array(
			'title'     => 'Hello World',
			'site_name' => 'Acme',
			'page'      => '',
		);
		return array(
			'basic'                       => array( '%%title%% %%separator%% %%site_name%%', $site, 'Hello World – Acme' ),
			'empty page collapses seps'   => array( '%%title%% %%separator%% %%page%% %%separator%% %%site_name%%', $site, 'Hello World – Acme' ),
			'page shown when set'         => array( '%%title%% %%separator%% %%page%% %%separator%% %%site_name%%', array_merge( $site, array( 'page' => 'Page 2 of 3' ) ), 'Hello World – Page 2 of 3 – Acme' ),
			'leading separator removed'   => array( '%%separator%% %%title%%', $site, 'Hello World' ),
			'trailing separator removed'  => array( '%%title%% %%separator%% %%missing%%', $site, 'Hello World' ),
			'unknown variable removed'    => array( '%%nope%% %%title%%', $site, 'Hello World' ),
			'literal text kept'           => array( 'Read: %%title%%!', $site, 'Read: Hello World!' ),
			'uppercase is not a variable' => array( '%%TITLE%%', $site, '%%TITLE%%' ),
			'whitespace collapsed'        => array( "  %%title%% \n\t %%site_name%%  ", $site, 'Hello World Acme' ),
			'only separators'             => array( '%%separator%% %%separator%%', $site, '' ),
			'empty template'              => array( '', $site, '' ),
		);
	}

	/**
	 * @dataProvider render_provider
	 *
	 * @param string                $template Template.
	 * @param array<string, string> $values   Values.
	 * @param string                $expected Expected output.
	 */
	public function test_render( string $template, array $values, string $expected ): void {
		$this->assertSame( $expected, $this->engine->render( $template, $values, '–' ) );
	}

	public function test_values_are_not_expanded_again(): void {
		$out = $this->engine->render(
			'%%title%% %%separator%% %%site_name%%',
			array(
				'title'     => 'All about %%site_name%% and %%separator%%',
				'site_name' => 'Acme',
			),
			'|'
		);

		$this->assertSame( 'All about %%site_name%% and %%separator%% | Acme', $out );
	}

	public function test_separator_character_inside_a_value_is_preserved(): void {
		$out = $this->engine->render(
			'%%separator%% %%title%% %%separator%%',
			array( 'title' => '| Pipes | in | title |' ),
			'|'
		);

		$this->assertSame( '| Pipes | in | title |', $out );
	}

	public function test_values_become_plain_text(): void {
		$out = $this->engine->render(
			'%%title%%',
			array( 'title' => 'Fish &amp; Chips <script>alert(1)</script><b>now</b>' ),
			'-'
		);

		$this->assertSame( 'Fish & Chips now', $out, 'Entities decoded once (caller escapes), tags stripped.' );
	}

	public function test_closures_are_lazy(): void {
		$called = false;
		$values = array(
			'title'   => 'T',
			'excerpt' => static function () use ( &$called ): string {
				$called = true;
				return 'E';
			},
		);

		$this->assertSame( 'T', $this->engine->render( '%%title%%', $values, '-' ) );
		$this->assertFalse( $called, 'Unused closures must not run.' );
		$this->assertSame( 'E', $this->engine->render( '%%excerpt%%', $values, '-' ) );
		$this->assertTrue( $called );
	}

	public function test_string_value_naming_a_php_function_is_not_called(): void {
		// A value like "phpinfo" or "date" must be treated as text, never as a callable.
		$this->assertSame( 'phpinfo', $this->engine->render( '%%title%%', array( 'title' => 'phpinfo' ), '-' ) );
	}

	/**
	 * Truncation cases.
	 *
	 * @return array<string, array{string, int, string}>
	 */
	public function truncate_provider(): array {
		return array(
			'short unchanged'     => array( 'Short text.', 20, 'Short text.' ),
			'word boundary'       => array( 'The quick brown fox jumps over', 20, 'The quick brown fox…' ),
			'trailing punct gone' => array( 'Hello, world, again and again', 14, 'Hello, world…' ),
			'multibyte safe'      => array( 'Ünïcödé wörds änd möre wörds', 16, 'Ünïcödé wörds…' ),
			'whitespace'          => array( "  a \n\n b  ", 10, 'a b' ),
		);
	}

	/**
	 * @dataProvider truncate_provider
	 *
	 * @param string $text     Input.
	 * @param int    $length   Max length.
	 * @param string $expected Expected.
	 */
	public function test_truncate( string $text, int $length, string $expected ): void {
		$result = VariableValues::truncate( $text, $length );
		$this->assertSame( $expected, $result );
		$this->assertLessThanOrEqual( $length, mb_strlen( $result ) );
	}
}
