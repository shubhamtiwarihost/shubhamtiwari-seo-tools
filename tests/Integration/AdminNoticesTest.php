<?php
/**
 * Admin notices stay on the plugin's own screens (directory guideline 11).
 *
 * @package DumpSEO
 */

namespace DumpSEO\Tests\Integration;

use DumpSEO\Plugin;
use DumpSEO\Redirects\AdminScreen;
use DumpSEO\Redirects\Store;
use DumpSEO\Requirements;
use WP_UnitTestCase;

/**
 * @covers \DumpSEO\Requirements
 * @covers \DumpSEO\Redirects\AdminScreen
 */
final class AdminNoticesTest extends WP_UnitTestCase {

	/**
	 * Hooks WordPress prints notices on.
	 */
	private const NOTICE_HOOKS = array( 'admin_notices', 'all_admin_notices', 'network_admin_notices', 'user_admin_notices' );

	/**
	 * Redirect screen hooks (normally registered on admin requests only).
	 *
	 * @var AdminScreen
	 */
	private $redirects;

	public function set_up(): void {
		parent::set_up();
		$redirects = Plugin::build_container()->get( AdminScreen::class );
		$this->assertInstanceOf( AdminScreen::class, $redirects );
		$this->redirects = $redirects;
		Store::register_post_type();
	}

	public function tear_down(): void {
		set_current_screen( 'front' );
		parent::tear_down();
	}

	/**
	 * Screens outside the plugin.
	 *
	 * @return array<string, array{string}>
	 */
	public function foreign_screen_provider(): array {
		return array(
			'dashboard'         => array( 'dashboard' ),
			'posts'             => array( 'edit-post' ),
			'post editor'       => array( 'post' ),
			'pages'             => array( 'edit-page' ),
			'media'             => array( 'upload' ),
			'plugins'           => array( 'plugins' ),
			'updates'           => array( 'update-core' ),
			'users'             => array( 'users' ),
			'general settings'  => array( 'options-general' ),
			'woocommerce home'  => array( 'woocommerce_page_wc-admin' ),
			'woocommerce order' => array( 'edit-shop_order' ),
			'products'          => array( 'edit-product' ),
			'network dashboard' => array( 'dashboard-network' ),
			'network plugins'   => array( 'plugins-network' ),
			'network sites'     => array( 'sites-network' ),
		);
	}

	/**
	 * Callbacks the plugin has on the notice hooks, as "hook: callable".
	 *
	 * @return string[]
	 */
	private function plugin_notice_callbacks(): array {
		global $wp_filter;
		$found = array();
		foreach ( self::NOTICE_HOOKS as $hook ) {
			if ( ! isset( $wp_filter[ $hook ] ) ) {
				continue;
			}
			foreach ( $wp_filter[ $hook ]->callbacks as $callbacks ) {
				foreach ( $callbacks as $callback ) {
					$function = $callback['function'];
					$class    = is_array( $function ) ? ( is_object( $function[0] ) ? get_class( $function[0] ) : (string) $function[0] ) : '';
					if ( 0 === strpos( $class, 'DumpSEO\\' ) ) {
						$found[] = $hook . ': ' . $class . '::' . $function[1];
					}
				}
			}
		}
		return $found;
	}

	/**
	 * Output of every notice hook on a screen.
	 *
	 * @param string $screen_id Screen ID.
	 */
	private function notices_on( string $screen_id ): string {
		set_current_screen( $screen_id );
		ob_start();
		foreach ( self::NOTICE_HOOKS as $hook ) {
			do_action( $hook ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Core hooks, fired to observe the plugin's output.
		}
		return (string) ob_get_clean();
	}

	public function test_a_running_plugin_registers_no_global_notice(): void {
		$this->assertSame( array(), $this->plugin_notice_callbacks(), 'A booted plugin (requirements met, no admin request) hooks nothing on the notice hooks.' );
	}

	public function test_only_the_redirect_errors_callback_is_added_in_admin(): void {
		$this->redirects->register();
		$this->assertSame(
			array( 'admin_notices: ' . AdminScreen::class . '::render_errors' ),
			$this->plugin_notice_callbacks()
		);
	}

	/**
	 * @dataProvider foreign_screen_provider
	 *
	 * @param string $screen_id Screen ID.
	 */
	public function test_redirect_errors_are_not_printed_outside_the_redirect_screens( string $screen_id ): void {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );
		if ( is_multisite() ) {
			grant_super_admin( $admin );
		}
		$this->redirects->register();
		set_transient( 'dumpseo_redirect_errors_' . $admin, array( 'Pending problem' ), 60 );

		$this->assertSame( '', $this->notices_on( $screen_id ) );
		$this->assertSame( array( 'Pending problem' ), get_transient( 'dumpseo_redirect_errors_' . $admin ), 'Kept for the redirect screen.' );
	}

	public function test_redirect_errors_are_printed_once_on_the_redirect_screen(): void {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );
		$this->redirects->register();
		set_transient( 'dumpseo_redirect_errors_' . $admin, array( 'Pending <b>problem</b>' ), 60 );

		$first = $this->notices_on( Store::POST_TYPE );
		$this->assertStringContainsString( 'notice-error', $first );
		$this->assertStringContainsString( 'Pending &lt;b&gt;problem&lt;/b&gt;', $first );
		$this->assertSame( '', $this->notices_on( Store::POST_TYPE ), 'Shown once, not on every page load.' );
	}

	public function test_redirect_errors_are_private_to_the_user_and_need_the_capability(): void {
		$admin  = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$other  = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$editor = self::factory()->user->create( array( 'role' => 'editor' ) );
		$this->redirects->register();
		set_transient( 'dumpseo_redirect_errors_' . $admin, array( 'Pending problem' ), 60 );
		set_transient( 'dumpseo_redirect_errors_' . $editor, array( 'Pending problem' ), 60 );

		wp_set_current_user( $other );
		$this->assertSame( '', $this->notices_on( Store::POST_TYPE ), 'Another administrator does not see it.' );
		wp_set_current_user( $editor );
		$this->assertSame( '', $this->notices_on( Store::POST_TYPE ), 'A user who cannot manage redirects sees nothing.' );
	}

	/**
	 * @dataProvider foreign_screen_provider
	 *
	 * @param string $screen_id Screen ID.
	 */
	public function test_requirements_notice_is_limited_to_the_plugins_screens( string $screen_id ): void {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );
		if ( is_multisite() ) {
			grant_super_admin( $admin );
		}
		$requirements = new Requirements( '7.0', '5.0' );
		add_action( 'admin_notices', array( $requirements, 'render_notice' ) );
		add_action( 'network_admin_notices', array( $requirements, 'render_notice' ) );

		$output = $this->notices_on( $screen_id );
		if ( in_array( $screen_id, array( 'plugins', 'plugins-network' ), true ) ) {
			$this->assertStringContainsString( 'notice-error', $output );
		} else {
			$this->assertSame( '', $output );
		}
	}

	public function test_requirements_notice_is_hidden_from_users_who_cannot_manage_plugins(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$requirements = new Requirements( '7.0', '5.0' );
		add_action( 'admin_notices', array( $requirements, 'render_notice' ) );

		$this->assertSame( '', $this->notices_on( 'plugins' ) );
	}
}
