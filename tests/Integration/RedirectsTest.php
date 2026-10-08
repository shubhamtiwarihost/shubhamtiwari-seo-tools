<?php
/**
 * Redirects: validation on save, permissions, and frontend behaviour.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Tests\Integration;

use DumpSEO\Context;
use DumpSEO\Plugin;
use DumpSEO\Redirects\AdminScreen;
use DumpSEO\Redirects\RedirectsModule;
use DumpSEO\Redirects\Store;
use WP_UnitTestCase;

/**
 * @covers \DumpSEO\Redirects\RedirectsModule
 * @covers \DumpSEO\Redirects\AdminScreen
 * @covers \DumpSEO\Redirects\Store
 */
final class RedirectsTest extends WP_UnitTestCase {

	/**
	 * Admin screen hooks (normally admin-only).
	 *
	 * @var AdminScreen
	 */
	private $admin;

	/**
	 * Store.
	 *
	 * @var Store
	 */
	private $store;

	/**
	 * Captured redirect: [location, status].
	 *
	 * @var array{0: string, 1: int}|null
	 */
	private $sent = null;

	public function set_up(): void {
		parent::set_up();
		Store::register_post_type();
		$this->store = new Store();
		$this->admin = new AdminScreen( new Context(), $this->store );
		$this->admin->register();
		foreach ( array( 'save_post', 'trashed_post', 'untrashed_post', 'deleted_post' ) as $hook ) {
			add_action( $hook, array( $this->store, 'changed' ), 20 );
		}
		$this->store->rebuild();

		// Capture the redirect and stop before core sends headers (not possible under PHPUnit) or exits.
		add_filter(
			'wp_redirect',
			function ( $location, $status ) {
				$this->sent = array( (string) $location, (int) $status );
				throw new \RuntimeException( 'dumpseo-test-redirect' );
			},
			10,
			2
		);
	}

	public function tear_down(): void {
		$_POST                     = array();
		$_SERVER['REQUEST_METHOD'] = 'GET';
		delete_transient( 'dumpseo_redirect_errors_' . get_current_user_id() );
		parent::tear_down();
	}

	/**
	 * Saves a redirect through the edit form as an administrator.
	 *
	 * @param string $source Old address as typed.
	 * @param string $target New address as typed.
	 * @param int    $type   Type.
	 * @param int    $id     Existing post ID to update, or 0.
	 * @return int Post ID.
	 */
	private function save( string $source, string $target, int $type = 301, int $id = 0 ): int {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		if ( 0 === $id ) {
			$id = (int) wp_insert_post(
				array(
					'post_type'   => Store::POST_TYPE,
					'post_status' => 'auto-draft',
					'post_title'  => '',
				)
			);
		}
		$_POST = array(
			AdminScreen::NONCE_FIELD  => wp_create_nonce( 'dumpseo_redirect_' . $id ),
			AdminScreen::TARGET_FIELD => wp_slash( $target ),
			AdminScreen::TYPE_FIELD   => (string) $type,
		);
		wp_update_post(
			array(
				'ID'          => $id,
				'post_title'  => wp_slash( $source ),
				'post_status' => 'publish',
			)
		);
		$_POST = array();
		return $id;
	}

	/**
	 * Runs the frontend handler for a request.
	 *
	 * @param string $uri    Request URI.
	 * @param string $method HTTP method.
	 * @return array{0: string, 1: int}|null Location and status, or null when not redirected.
	 */
	private function request( string $uri, string $method = 'GET' ): ?array {
		$_SERVER['REQUEST_URI']    = $uri;
		$_SERVER['REQUEST_METHOD'] = $method;
		$this->sent                = null;
		$module                    = new RedirectsModule( $this->store );
		try {
			$module->maybe_redirect();
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'dumpseo-test-redirect', $e->getMessage() );
			return $this->sent;
		}
		return null;
	}

	/**
	 * Validation errors stored for the current user.
	 *
	 * @return string[]
	 */
	private function errors(): array {
		$errors = get_transient( 'dumpseo_redirect_errors_' . get_current_user_id() );
		return is_array( $errors ) ? $errors : array();
	}

	public function test_valid_redirect_is_normalised_indexed_and_followed(): void {
		$id = $this->save( 'https://' . wp_parse_url( home_url(), PHP_URL_HOST ) . '/Old-Page/?x=1', '/new-page/' );

		$this->assertSame( 'publish', get_post_status( $id ) );
		$this->assertSame( '/old-page', get_post_field( 'post_title', $id ) );
		$this->assertSame( array( '/old-page' ), array_keys( $this->store->index() ) );

		$this->assertSame( array( home_url( '/new-page/' ), 301 ), $this->request( '/old-page' ) );
		$this->assertSame( array( home_url( '/new-page/?utm_source=x' ), 301 ), $this->request( '/OLD-PAGE/?utm_source=x' ), 'Case/trailing slash ignored; query string passed on.' );
		$this->assertNull( $this->request( '/old-page', 'POST' ), 'Form submissions are never redirected.' );
		$this->assertNull( $this->request( '/other' ) );
	}

	public function test_types_and_external_targets(): void {
		$this->save( '/temp', 'https://example.com/landing?ref=site', 307 );
		$this->assertSame( array( 'https://example.com/landing?ref=site', 307 ), $this->request( '/temp?a=b' ), 'Target with its own query string keeps it.' );

		$this->save( '/removed', '', 410 );
		$this->assertNull( $this->request( '/removed' ), 'No Location for 410.' );

		$module                 = new RedirectsModule( $this->store );
		$_SERVER['REQUEST_URI'] = '/removed';
		$module->maybe_redirect();
		$status = 0;
		$filter = static function ( $header, $code ) use ( &$status ) {
			$status = (int) $code;
			return $header;
		};
		add_filter( 'status_header', $filter, 10, 2 );
		$module->maybe_gone();
		remove_filter( 'status_header', $filter, 10 );
		$this->assertSame( 410, $status );
		$this->assertTrue( $GLOBALS['wp_query']->is_404(), 'Theme 404 template is used.' );
	}

	public function test_invalid_redirects_are_saved_inactive_with_reasons(): void {
		$cases = array(
			'/wp-admin/options.php' => array( '/x', 'dashboard' ),
			'/'                     => array( '/x', 'homepage' ),
			'https://other.com/a'   => array( '/x', 'path on this site' ),
			'/bad-target'           => array( 'javascript:alert(1)', 'new address must be' ),
		);
		foreach ( $cases as $source => $case ) {
			$id = $this->save( $source, $case[0] );
			$this->assertSame( 'draft', get_post_status( $id ), $source );
			$this->assertStringContainsString( $case[1], implode( ' ', $this->errors() ), $source );
			delete_transient( 'dumpseo_redirect_errors_' . get_current_user_id() );
		}
		$this->assertSame( array(), $this->store->index(), 'Nothing invalid is active.' );
		$this->assertNull( $this->request( '/wp-admin/options.php' ) );
	}

	public function test_loops_and_duplicates_are_rejected(): void {
		$this->save( '/a', '/b' );
		$this->save( '/b', '/c' );

		$loop = $this->save( '/c', home_url( '/a/' ) );
		$this->assertSame( 'draft', get_post_status( $loop ) );
		$this->assertStringContainsString( 'loop', implode( ' ', $this->errors() ) );

		$self = $this->save( '/d', '/D/' );
		$this->assertSame( 'draft', get_post_status( $self ), 'Redirect to itself.' );

		$dupe = $this->save( '/a', '/elsewhere' );
		$this->assertSame( 'draft', get_post_status( $dupe ) );
		$this->assertStringContainsString( 'already uses', implode( ' ', $this->errors() ) );

		$this->assertSame( array( '/a', '/b' ), array_keys( $this->store->index() ) );
	}

	public function test_editing_an_existing_redirect_does_not_conflict_with_itself(): void {
		$id = $this->save( '/promo', '/sale/' );
		$this->save( '/promo', '/sale-2026/', 302, $id );
		$this->assertSame( 'publish', get_post_status( $id ) );
		$this->assertSame( array( home_url( '/sale-2026/' ), 302 ), $this->request( '/promo' ) );
	}

	public function test_trash_and_drafts_deactivate(): void {
		$id = $this->save( '/old', '/new/' );
		wp_trash_post( $id );
		$this->assertNotFalse( has_action( 'shutdown', array( $this->store, 'rebuild' ) ), 'Rebuild scheduled once for the end of the request.' );
		$this->assertNull( $this->request( '/old' ) );

		wp_untrash_post( $id );
		wp_update_post(
			array(
				'ID'          => $id,
				'post_status' => 'publish',
			)
		);
		$this->assertNotNull( $this->request( '/old' ) );
	}

	public function test_changes_outside_the_admin_screen_update_the_index(): void {
		$id = $this->save( '/cli-old', '/cli-new/' );
		$this->assertNotNull( $this->request( '/cli-old' ) );

		wp_delete_post( $id, true ); // As WP-CLI or code would.
		$this->assertNull( $this->request( '/cli-old' ), 'Deleted redirects stop working in the same request.' );

		// Created by code, bypassing the form (the admin screen is not loaded outside the dashboard): invalid data is never used.
		remove_filter( 'wp_insert_post_data', array( $this->admin, 'validate' ), 10 );
		$bad = self::factory()->post->create(
			array(
				'post_type'   => Store::POST_TYPE,
				'post_title'  => '/wp-login.php',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $bad, Store::META_TYPE, 301 );
		update_post_meta( $bad, Store::META_TARGET, 'javascript:alert(1)' );
		$good = self::factory()->post->create(
			array(
				'post_type'   => Store::POST_TYPE,
				'post_title'  => 'Imported-Page/',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $good, Store::META_TYPE, 302 );
		update_post_meta( $good, Store::META_TARGET, '/landing/' );
		$this->store->changed( $good );

		$this->assertSame( array( '/imported-page' ), array_keys( $this->store->index() ), 'Re-validated on rebuild.' );
	}

	public function test_only_administrators_manage_redirects(): void {
		$id = $this->save( '/admin-only', '/x/' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->assertFalse( current_user_can( 'edit_post', $id ) );
		$this->assertFalse( current_user_can( 'delete_post', $id ) );
		$this->assertFalse( current_user_can( get_post_type_object( Store::POST_TYPE )->cap->create_posts ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertTrue( current_user_can( 'edit_post', $id ) );

		$this->assertFalse( get_post_type_object( Store::POST_TYPE )->public );
		$this->assertFalse( get_post_type_object( Store::POST_TYPE )->show_in_rest );
	}

	public function test_index_is_autoloaded_and_costs_no_query(): void {
		$this->save( '/fast', '/x/' );
		$this->store->index(); // Settles the rebuild the save scheduled; a visitor's request starts with a fresh index.
		global $wpdb;
		$autoload = $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", Store::INDEX_OPTION ) );
		$this->assertContains( $autoload, array( 'yes', 'on' ) );

		wp_cache_delete( 'alloptions', 'options' );
		wp_load_alloptions();
		$before = $wpdb->num_queries;
		$this->request( '/not-redirected' );
		$this->request( '/fast' );
		$this->assertSame( $before, $wpdb->num_queries );
	}

	public function test_plugin_registers_frontend_module(): void {
		$this->assertInstanceOf( RedirectsModule::class, Plugin::instance()->module( 'redirects' ) );
	}
}
