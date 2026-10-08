<?php
/**
 * Settings screen: registration, permissions, saving and rendering.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Tests\Integration;

use DumpSEO\Admin\SettingsPage;
use DumpSEO\Plugin;
use DumpSEO\Settings\Settings;
use WP_UnitTestCase;
use WPDieException;

/**
 * Exercises the settings page against real WordPress.
 *
 * @covers \DumpSEO\Admin\SettingsPage
 */
final class SettingsPageTest extends WP_UnitTestCase {

	/**
	 * Page under test.
	 *
	 * @var SettingsPage
	 */
	private $page;

	public function set_up(): void {
		parent::set_up();
		$page = Plugin::build_container()->get( SettingsPage::class );
		$this->assertInstanceOf( SettingsPage::class, $page );
		$this->page = $page;
		$this->page->register_settings();
		delete_option( Settings::OPTION );
		$GLOBALS['wp_settings_errors'] = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Resets core's settings-error list between tests.
	}

	/**
	 * Roles and whether they may manage DumpSEO settings.
	 *
	 * @return array<string, array{string, bool}>
	 */
	public function role_provider(): array {
		return array(
			'subscriber'    => array( 'subscriber', false ),
			'contributor'   => array( 'contributor', false ),
			'author'        => array( 'author', false ),
			'editor'        => array( 'editor', false ),
			'administrator' => array( 'administrator', true ),
		);
	}

	/**
	 * @dataProvider role_provider
	 *
	 * @param string $role    Role.
	 * @param bool   $allowed Whether the role may manage settings.
	 */
	public function test_only_administrators_can_open_or_save_settings( string $role, bool $allowed ): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => $role ) ) );

		// options.php checks this capability before saving the group.
		$capability = apply_filters( 'option_page_capability_' . SettingsPage::GROUP, 'manage_options' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Invokes the core hook exactly as options.php does.
		$this->assertSame( 'manage_options', $capability );
		$this->assertSame( $allowed, current_user_can( $capability ) );

		if ( $allowed ) {
			ob_start();
			$this->page->render_page();
			$this->assertStringContainsString( '<form', (string) ob_get_clean() );
			return;
		}

		$this->expectException( WPDieException::class );
		ob_start();
		try {
			$this->page->render_page();
		} finally {
			ob_end_clean();
		}
	}

	public function test_logged_out_visitor_cannot_render_page(): void {
		wp_set_current_user( 0 );
		$this->expectException( WPDieException::class );
		$this->page->render_page();
	}

	public function test_saving_runs_sanitizer_and_reports_errors(): void {
		update_option(
			Settings::OPTION,
			array(
				'organization_name' => '<img src=x onerror=alert(1)>Acme',
				'organization_logo' => 'javascript:alert(1)',
				'separator'         => 'pipe',
				'evil_key'          => 'x',
			)
		);

		$stored = get_option( Settings::OPTION );
		$this->assertIsArray( $stored );
		$this->assertSame( 'Acme', $stored['organization_name'] );
		$this->assertSame( '', $stored['organization_logo'] );
		$this->assertSame( 'pipe', $stored['separator'] );
		$this->assertArrayNotHasKey( 'evil_key', $stored );

		$codes = wp_list_pluck( get_settings_errors( Settings::OPTION ), 'code' );
		$this->assertContains( 'dumpseo_invalid_organization_logo', $codes );
	}

	public function test_settings_option_is_autoloaded(): void {
		update_option( Settings::OPTION, array( 'separator' => 'pipe' ) );

		global $wpdb;
		$autoload = $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", Settings::OPTION ) );
		$this->assertContains( $autoload, array( 'yes', 'on', 'auto', 'auto-on' ) );
	}

	public function test_page_renders_accessible_escaped_form_with_nonce(): void {
		global $wp_settings_sections, $wp_settings_fields;
		$this->assertNotEmpty( $wp_settings_sections[ SettingsPage::PAGE ] ?? null );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		// Bypass sanitization to prove output escaping holds even for bad stored data.
		remove_all_filters( 'sanitize_option_' . Settings::OPTION );
		update_option( Settings::OPTION, array( 'organization_name' => '"><script>alert(1)</script>' ) );

		ob_start();
		$this->page->render_page();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'name="_wpnonce"', $html );
		$this->assertMatchesRegularExpression( '/name=[\'"]option_page[\'"] value=[\'"]' . SettingsPage::GROUP . '[\'"]/', $html );
		$this->assertStringNotContainsString( '<script>alert(1)</script>', $html );
		$this->assertStringContainsString( '&quot;&gt;&lt;script&gt;', $html );

		// Every text/url/select control has a <label for>, and a description (when present) is linked.
		foreach ( $wp_settings_fields[ SettingsPage::PAGE ] as $fields ) {
			foreach ( $fields as $field ) {
				$id = $field['args']['label_for'] ?? null;
				if ( null === $id ) {
					continue; // Checkboxes are wrapped in their own <label>.
				}
				$this->assertStringContainsString( 'for="' . $id . '"', $html, $id );
				if ( '' !== $field['args']['field']->description ) {
					$this->assertStringContainsString( 'aria-describedby="' . $id . '-description"', $html, $id );
				}
			}
		}
		$this->assertMatchesRegularExpression( '/<label for="dumpseo-remove-data-on-uninstall"><input type="checkbox"/', $html );
	}

	public function test_plugins_screen_settings_link_only_for_admins(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->assertSame( array(), $this->page->action_links( array() ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$links = $this->page->action_links( array() );
		$this->assertCount( 1, $links );
		$this->assertStringContainsString( 'page=dumpseo', $links[0] );
	}
}
