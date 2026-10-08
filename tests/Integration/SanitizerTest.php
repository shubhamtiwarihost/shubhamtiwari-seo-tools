<?php
/**
 * Settings sanitization with real WordPress functions.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Tests\Integration;

use DumpSEO\Settings\Sanitizer;
use DumpSEO\Settings\Schema;
use WP_UnitTestCase;

/**
 * Malicious and malformed input must never reach the database unsanitized.
 *
 * @covers \DumpSEO\Settings\Sanitizer
 */
final class SanitizerTest extends WP_UnitTestCase {

	/**
	 * Sanitizer under test.
	 *
	 * @var Sanitizer
	 */
	private $sanitizer;

	/**
	 * Defaults used as "previously saved" values.
	 *
	 * @var array<string, bool|string>
	 */
	private $defaults;

	public function set_up(): void {
		parent::set_up();
		$schema          = new Schema();
		$this->sanitizer = new Sanitizer( $schema );
		$this->defaults  = $schema->defaults();
	}

	public function test_text_is_stripped_of_markup(): void {
		$clean = $this->sanitizer->sanitize( array( 'organization_name' => '  <script>alert(1)</script>Acme <b>Ltd</b>  ' ), $this->defaults );

		$this->assertSame( 'Acme Ltd', $clean['organization_name'] );
		$this->assertSame( array(), $this->sanitizer->errors() );
	}

	/**
	 * URLs that must be rejected.
	 *
	 * @return array<string, array{string}>
	 */
	public function bad_url_provider(): array {
		return array(
			'javascript'        => array( 'javascript:alert(1)' ),
			'javascript mixed'  => array( 'JaVaScRiPt:alert(1)' ),
			'data uri'          => array( 'data:text/html;base64,PHNjcmlwdD4=' ),
			'relative'          => array( '/wp-content/logo.png' ),
			'protocol relative' => array( '//evil.example/x.png' ),
			'ftp'               => array( 'ftp://example.com/logo.png' ),
			'no host'           => array( 'https://' ),
			'plain text'        => array( 'not a url' ),
		);
	}

	/**
	 * @dataProvider bad_url_provider
	 *
	 * @param string $url Malicious or malformed URL.
	 */
	public function test_bad_urls_are_rejected_and_old_value_kept( string $url ): void {
		$previous = array_merge( $this->defaults, array( 'organization_logo' => 'https://example.com/old.png' ) );

		$clean = $this->sanitizer->sanitize( array( 'organization_logo' => $url ), $previous );

		$this->assertSame( 'https://example.com/old.png', $clean['organization_logo'] );
		$this->assertArrayHasKey( 'organization_logo', $this->sanitizer->errors() );
	}

	public function test_good_url_is_kept_and_quotes_are_neutralised(): void {
		$clean = $this->sanitizer->sanitize( array( 'default_social_image' => 'https://example.com/a b.png?x="><script>' ), $this->defaults );

		$this->assertStringStartsWith( 'https://example.com/', $clean['default_social_image'] );
		$this->assertStringNotContainsString( '"', $clean['default_social_image'] );
		$this->assertStringNotContainsString( '<', $clean['default_social_image'] );
	}

	public function test_empty_url_clears_the_value(): void {
		$previous = array_merge( $this->defaults, array( 'organization_logo' => 'https://example.com/old.png' ) );

		$clean = $this->sanitizer->sanitize( array( 'organization_logo' => '' ), $previous );

		$this->assertSame( '', $clean['organization_logo'] );
	}

	public function test_enum_rejects_values_outside_choices(): void {
		$clean = $this->sanitizer->sanitize(
			array(
				'separator'       => '<script>',
				'site_represents' => 'person',
			),
			$this->defaults
		);

		$this->assertSame( 'ndash', $clean['separator'] );
		$this->assertSame( 'person', $clean['site_represents'] );
		$this->assertSame( array( 'separator' ), array_keys( $this->sanitizer->errors() ) );
	}

	/**
	 * X/Twitter handles.
	 *
	 * @return array<string, array{string, string|null}>
	 */
	public function handle_provider(): array {
		return array(
			'plain'      => array( 'seo_earth', 'seo_earth' ),
			'leading at' => array( '@seo_earth', 'seo_earth' ),
			'empty'      => array( '', '' ),
			'too long'   => array( 'abcdefghijklmnop', null ),
			'html'       => array( '<b>x</b>', null ),
			'url'        => array( 'https://x.com/someone', null ),
			'spaces'     => array( 'two words', null ),
		);
	}

	/**
	 * @dataProvider handle_provider
	 *
	 * @param string      $input    Input.
	 * @param string|null $expected Stored value, or null when rejected.
	 */
	public function test_twitter_handle( string $input, ?string $expected ): void {
		$clean = $this->sanitizer->sanitize( array( 'twitter_site' => $input ), $this->defaults );

		if ( null === $expected ) {
			$this->assertSame( '', $clean['twitter_site'], 'Rejected value keeps previous (empty) value.' );
			$this->assertArrayHasKey( 'twitter_site', $this->sanitizer->errors() );
		} else {
			$this->assertSame( $expected, $clean['twitter_site'] );
		}
	}

	public function test_non_scalar_input_is_rejected(): void {
		$clean = $this->sanitizer->sanitize( array( 'organization_name' => array( 'a' => 'b' ) ), $this->defaults );

		$this->assertSame( '', $clean['organization_name'] );
		$this->assertArrayHasKey( 'organization_name', $this->sanitizer->errors() );
	}

	public function test_template_variables_are_not_mangled_as_url_octets(): void {
		// sanitize_text_field() alone turns "%%description%%" into "%scription%%".
		$template = '%%title%% %%separator%% %%date%% %%description%% %%excerpt%% %%category%% %%currentyear%% 50%';

		$clean = $this->sanitizer->sanitize( array( 'title_pt_post' => $template ), $this->defaults );

		$this->assertSame( $template, $clean['title_pt_post'] );
	}

	public function test_template_markup_is_still_stripped(): void {
		$clean = $this->sanitizer->sanitize( array( 'title_pt_post' => '<script>x</script>%%title%% <b>bold</b>' ), $this->defaults );

		$this->assertSame( '%%title%% bold', $clean['title_pt_post'] );
	}

	public function test_unknown_keys_and_non_array_input_are_dropped(): void {
		$this->assertSame( $this->defaults, $this->sanitizer->sanitize( array( 'evil' => 'x' ), $this->defaults ) );
		$this->assertSame( $this->defaults, $this->sanitizer->sanitize( 'a string', $this->defaults ) );
		$this->assertSame( $this->defaults, $this->sanitizer->sanitize( null, $this->defaults ) );
	}

	public function test_unchecked_checkbox_on_submitted_form_turns_off(): void {
		$previous = array_merge( $this->defaults, array( 'remove_data_on_uninstall' => true ) );

		$clean = $this->sanitizer->sanitize( array( Sanitizer::SECTIONS_KEY => 'general,social,advanced' ), $previous );

		$this->assertFalse( $clean['remove_data_on_uninstall'] );
	}

	public function test_checkbox_not_on_submitted_form_is_left_alone(): void {
		$previous = array_merge( $this->defaults, array( 'remove_data_on_uninstall' => true ) );

		$from_other_screen = $this->sanitizer->sanitize( array( Sanitizer::SECTIONS_KEY => 'general' ), $previous );
		$programmatic      = $this->sanitizer->sanitize( array( 'separator' => 'pipe' ), $previous );

		$this->assertTrue( $from_other_screen['remove_data_on_uninstall'] );
		$this->assertTrue( $programmatic['remove_data_on_uninstall'] );
	}

	public function test_checkbox_values(): void {
		foreach ( array( '1', 'on', true ) as $on ) {
			$this->assertTrue( $this->sanitizer->sanitize( array( 'remove_data_on_uninstall' => $on ), $this->defaults )['remove_data_on_uninstall'] );
		}
		foreach ( array( '0', '', 'off', array( '1' ) ) as $off ) {
			$this->assertFalse( $this->sanitizer->sanitize( array( 'remove_data_on_uninstall' => $off ), $this->defaults )['remove_data_on_uninstall'] );
		}
	}

	public function test_sanitizing_clean_output_again_is_stable(): void {
		// WordPress runs the sanitize callback twice when the option is first created.
		$once  = $this->sanitizer->sanitize(
			array(
				'organization_name'    => 'Acme',
				'default_social_image' => 'https://example.com/a.png',
				'twitter_site'         => '@acme',
			),
			$this->defaults
		);
		$twice = $this->sanitizer->sanitize( $once, $this->defaults );

		$this->assertSame( $once, $twice );
		$this->assertSame( array(), $this->sanitizer->errors() );
	}
}
