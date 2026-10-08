<?php
/**
 * Who may read and write SEO meta, via REST and the term screen.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Tests\Integration;

use DumpSEO\Admin\TermFields;
use DumpSEO\Meta\Keys;
use DumpSEO\Meta\MetaModule;
use DumpSEO\Plugin;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Capability boundaries for per-post and per-term SEO fields.
 *
 * @covers \DumpSEO\Meta\MetaModule
 * @covers \DumpSEO\Admin\TermFields
 */
final class MetaPermissionsTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		// The WP test suite unregisters all meta keys after each test; re-run what `init` does on a real request.
		$meta = Plugin::instance()->module( 'meta' );
		$this->assertInstanceOf( MetaModule::class, $meta );
		$meta->register_meta();
	}

	/**
	 * Updates a post's SEO meta through REST as a given user.
	 *
	 * @param int                  $user_id User ID (0 = logged out).
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $meta    Meta to send.
	 */
	private function rest_update( int $user_id, int $post_id, array $meta ): int {
		wp_set_current_user( $user_id );
		$request = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post_id );
		$request->set_body_params( array( 'meta' => $meta ) );
		return rest_get_server()->dispatch( $request )->get_status();
	}

	public function test_meta_is_registered_for_posts_and_terms(): void {
		foreach ( Keys::ALL as $key ) {
			$this->assertTrue( registered_meta_key_exists( 'post', $key ), $key );
			$this->assertTrue( registered_meta_key_exists( 'term', $key ), $key );
		}
	}

	public function test_author_can_edit_own_post_meta_and_value_is_sanitized(): void {
		$author = self::factory()->user->create( array( 'role' => 'author' ) );
		$post   = self::factory()->post->create( array( 'post_author' => $author ) );

		$status = $this->rest_update( $author, $post, array( Keys::TITLE => "<b>Bold</b> title\nsecond line" ) );

		$this->assertSame( 200, $status );
		$this->assertSame( 'Bold title second line', get_post_meta( $post, Keys::TITLE, true ) );
	}

	/**
	 * Users who must not change another author's SEO meta.
	 *
	 * @return array<string, array{string|null}>
	 */
	public function forbidden_provider(): array {
		return array(
			'logged out'   => array( null ),
			'subscriber'   => array( 'subscriber' ),
			'contributor'  => array( 'contributor' ),
			'other author' => array( 'author' ),
		);
	}

	/**
	 * @dataProvider forbidden_provider
	 *
	 * @param string|null $role Role, or null for logged out.
	 */
	public function test_others_cannot_edit_post_meta( ?string $role ): void {
		$owner = self::factory()->user->create( array( 'role' => 'author' ) );
		$post  = self::factory()->post->create( array( 'post_author' => $owner ) );
		update_post_meta( $post, Keys::TITLE, 'Original' );
		$user = null === $role ? 0 : self::factory()->user->create( array( 'role' => $role ) );

		$status = $this->rest_update( $user, $post, array( Keys::TITLE => 'Hijacked' ) );

		$this->assertContains( $status, array( 401, 403 ) );
		$this->assertSame( 'Original', get_post_meta( $post, Keys::TITLE, true ) );
	}

	public function test_variables_survive_meta_sanitizing(): void {
		$author = self::factory()->user->create( array( 'role' => 'author' ) );
		$post   = self::factory()->post->create( array( 'post_author' => $author ) );

		$this->rest_update( $author, $post, array( Keys::TITLE => '%%title%% %%separator%% %%date%% %%description%% 100%' ) );

		$this->assertSame( '%%title%% %%separator%% %%date%% %%description%% 100%', get_post_meta( $post, Keys::TITLE, true ) );
	}

	public function test_editor_can_edit_any_post_meta(): void {
		$post   = self::factory()->post->create();
		$editor = self::factory()->user->create( array( 'role' => 'editor' ) );

		$this->assertSame( 200, $this->rest_update( $editor, $post, array( Keys::DESCRIPTION => 'By editor' ) ) );
		$this->assertSame( 'By editor', get_post_meta( $post, Keys::DESCRIPTION, true ) );
	}

	public function test_seo_meta_of_draft_is_not_readable_by_public(): void {
		$post = self::factory()->post->create( array( 'post_status' => 'draft' ) );
		update_post_meta( $post, Keys::DESCRIPTION, 'Unreleased' );
		wp_set_current_user( 0 );

		$response = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post ) );

		$this->assertContains( $response->get_status(), array( 401, 403, 404 ) );
	}

	/**
	 * Term fields module from the container.
	 */
	private function term_fields(): TermFields {
		$module = Plugin::build_container()->get( TermFields::class );
		$this->assertInstanceOf( TermFields::class, $module );
		return $module;
	}

	/**
	 * Simulates submitting the term edit form.
	 *
	 * @param int         $term_id Term ID.
	 * @param string|null $nonce   Nonce, or null to omit.
	 * @param string      $title   Title to submit.
	 */
	private function submit_term( int $term_id, ?string $nonce, string $title ): void {
		$_POST = array( TermFields::TITLE_FIELD => wp_slash( $title ) );
		if ( null !== $nonce ) {
			$_POST[ TermFields::NONCE_FIELD ] = $nonce;
		}
		$this->term_fields()->save( $term_id, 0, 'category' );
		$_POST = array();
	}

	public function test_term_form_saves_with_valid_nonce_and_capability(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$cat = self::factory()->category->create();

		$this->submit_term( $cat, wp_create_nonce( 'dumpseo_term_' . $cat ), 'Great <em>news</em> "quoted"' );

		$this->assertSame( 'Great news "quoted"', get_term_meta( $cat, Keys::TITLE, true ) );
	}

	public function test_term_form_rejects_bad_nonce_wrong_term_nonce_and_low_roles(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$cat   = self::factory()->category->create();
		$other = self::factory()->category->create();

		$this->submit_term( $cat, null, 'No nonce' );
		$this->submit_term( $cat, 'forged', 'Forged' );
		$this->submit_term( $cat, wp_create_nonce( 'dumpseo_term_' . $other ), 'Nonce for another term' );
		$this->assertSame( '', get_term_meta( $cat, Keys::TITLE, true ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'author' ) ) );
		$this->submit_term( $cat, wp_create_nonce( 'dumpseo_term_' . $cat ), 'Author attempt' );
		$this->assertSame( '', get_term_meta( $cat, Keys::TITLE, true ), 'Authors cannot manage categories.' );
	}

	public function test_term_form_clearing_field_deletes_meta(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$cat = self::factory()->category->create();
		update_term_meta( $cat, Keys::TITLE, 'Old' );

		$this->submit_term( $cat, wp_create_nonce( 'dumpseo_term_' . $cat ), '' );

		$this->assertFalse( metadata_exists( 'term', $cat, Keys::TITLE ) );
	}

	public function test_term_form_renders_escaped_values(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$cat = self::factory()->category->create();
		update_term_meta( $cat, Keys::TITLE, '"><script>alert(1)</script>' );

		ob_start();
		$this->term_fields()->render( get_term( $cat ) );
		$html = (string) ob_get_clean();

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( 'name="' . TermFields::NONCE_FIELD . '"', $html );
		$this->assertStringContainsString( '<label for="dumpseo-term-title">', $html );
	}
}
