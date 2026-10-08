<?php
/**
 * Tests for Requirements.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Tests\Unit;

use Brain\Monkey\Functions;
use DumpSEO\Requirements;

/**
 * @covers \DumpSEO\Requirements
 */
final class RequirementsTest extends TestCase {

	/**
	 * Version combinations and whether they meet requirements.
	 *
	 * @return array<string, array{string, string, bool}>
	 */
	public function versions_provider(): array {
		return array(
			'exact minimums' => array( '7.4.0', '6.4', true ),
			'newer'          => array( '8.3.1', '6.8.1', true ),
			'old php'        => array( '7.3.33', '6.8', false ),
			'old wordpress'  => array( '8.2.0', '6.3.5', false ),
			'wp beta suffix' => array( '8.2.0', '6.9-beta1', true ),
		);
	}

	/**
	 * @dataProvider versions_provider
	 *
	 * @param string $php      PHP version.
	 * @param string $wp       WordPress version.
	 * @param bool   $expected Expected result.
	 */
	public function test_met( string $php, string $wp, bool $expected ): void {
		$this->assertSame( $expected, ( new Requirements( $php, $wp ) )->met() );
	}

	/**
	 * A screen with the given ID.
	 *
	 * @param string $id Screen ID.
	 */
	private function screen( string $id ): \WP_Screen {
		$screen     = new \WP_Screen();
		$screen->id = $id;
		return $screen;
	}

	/**
	 * Admin screens and whether the notice may print there.
	 *
	 * @return array<string, array{string, bool}>
	 */
	public function screen_provider(): array {
		return array(
			'plugins'         => array( 'plugins', true ),
			'network plugins' => array( 'plugins-network', true ),
			'dashboard'       => array( 'dashboard', false ),
			'posts'           => array( 'edit-post', false ),
			'pages'           => array( 'edit-page', false ),
			'updates'         => array( 'update-core', false ),
			'woocommerce'     => array( 'woocommerce_page_wc-admin', false ),
			'network home'    => array( 'dashboard-network', false ),
		);
	}

	/**
	 * @dataProvider screen_provider
	 *
	 * @param string $screen_id Screen ID.
	 * @param bool   $shown     Whether the notice prints.
	 */
	public function test_notice_only_on_plugins_screens( string $screen_id, bool $shown ): void {
		Functions\when( 'get_current_screen' )->justReturn( $this->screen( $screen_id ) );
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( '__' )->returnArg();
		Functions\when( 'esc_html' )->returnArg();

		ob_start();
		( new Requirements( '7.0', '5.0' ) )->render_notice();
		$output = (string) ob_get_clean();
		$this->assertSame( $shown, '' !== $output );
	}

	public function test_notice_hidden_without_a_screen(): void {
		Functions\when( 'get_current_screen' )->justReturn( null );
		Functions\when( 'current_user_can' )->justReturn( true );

		$this->expectOutputString( '' );
		( new Requirements( '7.0', '5.0' ) )->render_notice();
	}

	public function test_notice_hidden_from_users_who_cannot_activate_plugins(): void {
		Functions\when( 'get_current_screen' )->justReturn( $this->screen( 'plugins' ) );
		Functions\when( 'current_user_can' )->justReturn( false );

		$this->expectOutputString( '' );
		( new Requirements( '7.0', '5.0' ) )->render_notice();
	}

	public function test_notice_is_escaped_and_shown_to_admins(): void {
		Functions\when( 'get_current_screen' )->justReturn( $this->screen( 'plugins' ) );
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( '__' )->returnArg();
		Functions\when( 'esc_html' )->alias(
			static function ( $text ) {
				return htmlspecialchars( $text, ENT_QUOTES );
			}
		);

		$this->expectOutputRegex( '/notice-error.*PHP 7\.4 and WordPress 6\.4/' );
		( new Requirements( '7.0', '5.0' ) )->render_notice();
	}
}
