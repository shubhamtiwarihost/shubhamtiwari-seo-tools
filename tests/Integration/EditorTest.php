<?php
/**
 * Block editor sidebar loading and the Classic Editor metabox.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Tests\Integration;

use DumpSEO\Admin\EditorModule;
use DumpSEO\Admin\Metabox;
use DumpSEO\Admin\SeoForm;
use DumpSEO\Context;
use DumpSEO\Meta\Keys;
use DumpSEO\Meta\MetaModule;
use DumpSEO\Plugin;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * @covers \DumpSEO\Admin\EditorModule
 * @covers \DumpSEO\Admin\Metabox
 * @covers \DumpSEO\Admin\SeoForm
 * @covers \DumpSEO\Admin\PostTypes
 */
final class EditorTest extends WP_UnitTestCase {

	/**
	 * Metabox under test.
	 *
	 * @var Metabox
	 */
	private $metabox;

	public function set_up(): void {
		parent::set_up();
		$meta = Plugin::instance()->module( 'meta' );
		$this->assertInstanceOf( MetaModule::class, $meta );
		$meta->register_meta();
		$this->metabox = new Metabox( new Context() );
	}

	public function tear_down(): void {
		$_POST = array();
		unregister_post_type( 'book' );
		parent::tear_down();
	}

	/**
	 * Submits the metabox form for a post as the current user.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $fields  Form fields.
	 * @param string|null          $nonce   Nonce (default: valid).
	 */
	private function submit( int $post_id, array $fields, ?string $nonce = null ): void {
		$_POST = wp_slash( $fields ) + array( Metabox::NONCE_FIELD => $nonce ?? wp_create_nonce( 'dumpseo_post_' . $post_id ) );
		$this->metabox->save( $post_id, get_post( $post_id ) );
		$_POST = array();
	}

	public function test_metabox_saves_sanitized_values(): void {
		$author = self::factory()->user->create( array( 'role' => 'author' ) );
		$post   = self::factory()->post->create( array( 'post_author' => $author ) );
		wp_set_current_user( $author );

		$this->submit(
			$post,
			array(
				SeoForm::KEYPHRASE_FIELD    => ' <b>Trail</b> shoes ',
				SeoForm::TITLE_FIELD        => "%%title%% %%separator%% C:\\Guides\n<script>x</script>",
				SeoForm::DESC_FIELD         => 'A description',
				SeoForm::CANON_FIELD        => 'https://example.org/original/',
				SeoForm::INDEX_FIELD        => 'noindex',
				SeoForm::ROBOT_FIELD        => array( 'nofollow', 'evil', array( 'nested' ) ),
				SeoForm::SOCIAL_IMAGE_FIELD => 'https://cdn.example.org/card.png',
			)
		);

		$this->assertSame( 'Trail shoes', get_post_meta( $post, Keys::FOCUS_KEYPHRASE, true ) );
		$this->assertSame( '%%title%% %%separator%% C:\\Guides', get_post_meta( $post, Keys::TITLE, true ), 'Variables and backslashes survive.' );
		$this->assertSame( 'https://example.org/original/', get_post_meta( $post, Keys::CANONICAL, true ) );
		$this->assertSame( 'noindex,nofollow', get_post_meta( $post, Keys::ROBOTS, true ) );

		// Invalid URL keeps the stored one; emptied fields are deleted.
		$this->submit(
			$post,
			array(
				SeoForm::CANON_FIELD        => 'javascript:alert(1)',
				SeoForm::SOCIAL_IMAGE_FIELD => '',
			)
		);
		$this->assertSame( 'https://example.org/original/', get_post_meta( $post, Keys::CANONICAL, true ) );
		$this->assertSame( '', get_post_meta( $post, Keys::SOCIAL_IMAGE, true ) );
		$this->assertFalse( metadata_exists( 'post', $post, Keys::TITLE ), 'Deleted, not stored as empty.' );
		$this->assertSame( '', get_post_meta( $post, Keys::ROBOTS, true ) );
	}

	public function test_metabox_rejects_bad_nonce_and_other_users_posts(): void {
		$owner = self::factory()->user->create( array( 'role' => 'author' ) );
		$post  = self::factory()->post->create( array( 'post_author' => $owner ) );
		update_post_meta( $post, Keys::TITLE, 'Original' );

		wp_set_current_user( $owner );
		$this->submit( $post, array( SeoForm::TITLE_FIELD => 'Forged' ), 'bad-nonce' );
		$this->assertSame( 'Original', get_post_meta( $post, Keys::TITLE, true ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'author' ) ) );
		$this->submit( $post, array( SeoForm::TITLE_FIELD => 'Hijacked' ) );
		$this->assertSame( 'Original', get_post_meta( $post, Keys::TITLE, true ) );

		// Without our nonce field (block editor, quick edit, wp_update_post) nothing is touched.
		wp_set_current_user( $owner );
		$this->metabox->save( $post, get_post( $post ) );
		$this->assertSame( 'Original', get_post_meta( $post, Keys::TITLE, true ) );
	}

	public function test_metabox_render_escapes_values(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$post = self::factory()->post->create();
		update_post_meta( $post, Keys::TITLE, '"><script>alert(1)</script>' );

		$html = get_echo( array( $this->metabox, 'render' ), array( get_post( $post ) ) );
		$this->assertStringNotContainsString( '<script>alert', $html );
		$this->assertStringContainsString( 'name="' . Metabox::NONCE_FIELD . '"', $html );
	}

	public function test_custom_post_types_expose_meta_over_rest(): void {
		register_post_type(
			'book',
			array(
				'public'       => true,
				'show_in_rest' => true,
				'supports'     => array( 'title', 'editor' ),
			)
		);
		( new EditorModule() )->add_meta_support();
		$this->assertTrue( post_type_supports( 'book', 'custom-fields' ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$book    = self::factory()->post->create( array( 'post_type' => 'book' ) );
		$request = new WP_REST_Request( 'POST', '/wp/v2/book/' . $book );
		$request->set_body_params( array( 'meta' => array( Keys::FOCUS_KEYPHRASE => 'rare books' ) ) );

		do_action( 'rest_api_init' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook, fired to register routes in tests.
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'rare books', $response->get_data()['meta'][ Keys::FOCUS_KEYPHRASE ] );
	}

	public function test_sidebar_script_only_on_supported_post_screens(): void {
		if ( ! is_readable( DUMPSEO_DIR . 'build/editor/index.asset.php' ) ) {
			$this->markTestSkipped( 'Run `npm run build` first.' );
		}
		$module = new EditorModule();

		set_current_screen( 'edit.php' );
		$module->enqueue();
		$this->assertFalse( wp_script_is( 'dumpseo-editor', 'enqueued' ), 'Not on list screens.' );

		set_current_screen( 'post' );
		$module->enqueue();
		$this->assertTrue( wp_script_is( 'dumpseo-editor', 'enqueued' ) );
		$this->assertStringContainsString( 'dumpseoEditor', (string) wp_scripts()->get_data( 'dumpseo-editor', 'before' )[1] );

		wp_dequeue_script( 'dumpseo-editor' );
		set_current_screen( 'attachment' );
		$module->enqueue();
		$this->assertFalse( wp_script_is( 'dumpseo-editor', 'enqueued' ), 'Not for media.' );
		set_current_screen( 'front' );
	}
}
