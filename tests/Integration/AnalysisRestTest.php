<?php
/**
 * SEO analysis REST endpoint.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Tests\Integration;

use DumpSEO\Meta\Keys;
use DumpSEO\Meta\MetaModule;
use DumpSEO\Plugin;
use DumpSEO\Settings\Settings;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Checks permissions, input resolution and results of POST /dumpseo/v1/analysis.
 *
 * @covers \DumpSEO\Analysis\AnalysisModule
 * @covers \DumpSEO\Analysis\InputFactory
 * @covers \DumpSEO\Meta\Resolver::resolve_custom
 */
final class AnalysisRestTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		update_option( 'blogname', 'Acme' );
		update_option( 'blog_public', '1' );
		update_option( Settings::OPTION, array() );
		// The WP test suite unregisters all meta keys after each test; re-run what `init` does on a real request.
		$meta = Plugin::instance()->module( 'meta' );
		$this->assertInstanceOf( MetaModule::class, $meta );
		$meta->register_meta();
		do_action( 'rest_api_init' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook, fired to register routes in tests.
	}

	/**
	 * Sends an analysis request.
	 *
	 * @param array<string, mixed> $params Body parameters.
	 * @return \WP_REST_Response
	 */
	private function request( array $params ) {
		$request = new WP_REST_Request( 'POST', '/dumpseo/v1/analysis' );
		$request->set_body_params( $params );
		return rest_get_server()->dispatch( $request );
	}

	/**
	 * Results keyed by rule ID.
	 *
	 * @param array<string, mixed> $params Body parameters.
	 * @return array<string, array<string, mixed>>
	 */
	private function results( array $params ): array {
		$response = $this->request( $params );
		$this->assertSame( 200, $response->get_status(), (string) wp_json_encode( $response->get_data() ) );
		return array_column( $response->get_data()['seo']['results'], null, 'id' );
	}

	public function test_permissions(): void {
		$owner = self::factory()->user->create( array( 'role' => 'author' ) );
		$post  = self::factory()->post->create( array( 'post_author' => $owner ) );

		wp_set_current_user( 0 );
		$this->assertSame( 401, $this->request( array( 'post_id' => $post ) )->get_status() );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->assertSame( 403, $this->request( array( 'post_id' => $post ) )->get_status() );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'author' ) ) );
		$this->assertSame( 403, $this->request( array( 'post_id' => $post ) )->get_status(), "Authors cannot analyse others' posts." );

		wp_set_current_user( $owner );
		$this->assertSame( 200, $this->request( array( 'post_id' => $post ) )->get_status() );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertSame( 403, $this->request( array( 'post_id' => 999999 ) )->get_status(), 'Unknown posts are not editable.' );
		$this->assertSame( 400, $this->request( array() )->get_status() );
	}

	public function test_saved_values_rendered_like_the_frontend(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$post = self::factory()->post->create(
			array(
				'post_title'   => 'Winter boots',
				'post_name'    => 'winter-boots',
				'post_excerpt' => 'Warm winter boots for snow.',
				'post_content' => '<p>Winter boots keep feet warm.</p>',
			)
		);
		update_post_meta( $post, Keys::FOCUS_KEYPHRASE, 'Winter Boots' );

		$results = $this->results( array( 'post_id' => $post ) );

		$this->assertSame( 'pass', $results['keyphrase_in_title']['status'] );
		$this->assertSame( 'pass', $results['keyphrase_in_slug']['status'] );
		$this->assertSame( 'pass', $results['keyphrase_in_description']['status'], 'Description from the default %%excerpt%% template.' );
		$this->assertSame( strlen( 'Winter boots – Acme' ) - 2, $results['title_length']['metadata']['length'], 'Title rendered with the site separator (multibyte counted once).' );
	}

	public function test_unsaved_editor_values_override_saved_ones(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$post = self::factory()->post->create(
			array(
				'post_title'   => 'Old',
				'post_content' => '<p>Old text.</p>',
			)
		);
		update_post_meta( $post, Keys::TITLE, 'Saved title' );

		$results = $this->results(
			array(
				'post_id'         => $post,
				'keyphrase'       => 'trail shoes',
				'seo_title'       => 'Trail shoes %%separator%% %%site_name%%',
				'seo_description' => '%%title%% explained',
				'title'           => 'Trail shoes guide',
				'content'         => '<p>Trail shoes grip. <a href="/x">More</a></p>',
				'slug'            => 'trail-shoes',
			)
		);

		$this->assertSame( 'pass', $results['keyphrase_in_title']['status'] );
		$this->assertSame( 'pass', $results['keyphrase_in_description']['status'], '%%title%% uses the unsaved post title.' );
		$preview = $this->request(
			array(
				'post_id'   => $post,
				'seo_title' => 'Trail shoes %%separator%% %%site_name%%',
				'title'     => 'Trail shoes guide',
			)
		)->get_data()['preview'];
		$this->assertSame( 'Trail shoes – Acme', $preview['title'] );
		$this->assertSame( get_post( $post )->post_excerpt, $preview['description'], 'Default %%excerpt%% template uses the saved excerpt.' );
		$this->assertSame( 'pass', $results['keyphrase_in_intro']['status'] );
		$this->assertSame( 'pass', $results['internal_links']['status'] );
		$this->assertSame( 'Old', get_post( $post )->post_title, 'Nothing is saved.' );
		$this->assertSame( 'Saved title', get_post_meta( $post, Keys::TITLE, true ) );
	}

	public function test_duplicate_keyphrases_and_noindex(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$other = self::factory()->post->create();
		update_post_meta( $other, Keys::FOCUS_KEYPHRASE, 'Rain Jackets' );
		$draft = self::factory()->post->create( array( 'post_status' => 'draft' ) );
		update_post_meta( $draft, Keys::FOCUS_KEYPHRASE, 'rain jackets' );
		$post = self::factory()->post->create();
		update_post_meta( $post, Keys::FOCUS_KEYPHRASE, 'rain jackets' );
		update_post_meta( $post, Keys::ROBOTS, 'noindex' );

		$results = $this->results( array( 'post_id' => $post ) );

		$this->assertSame( 'warning', $results['keyphrase_unique']['status'] );
		$this->assertSame( (string) $other, $results['keyphrase_unique']['metadata']['post_ids'], 'Only other published posts, case-insensitive.' );
		$this->assertSame( 'info', $results['indexable']['status'] );
	}

	public function test_focus_keyphrase_meta_is_sanitized_and_permission_checked(): void {
		$owner = self::factory()->user->create( array( 'role' => 'author' ) );
		$post  = self::factory()->post->create( array( 'post_author' => $owner ) );

		wp_set_current_user( $owner );
		$request = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post );
		$request->set_body_params( array( 'meta' => array( Keys::FOCUS_KEYPHRASE => '  <b>Rain</b> jackets ' ) ) );
		$this->assertSame( 200, rest_get_server()->dispatch( $request )->get_status() );
		$this->assertSame( 'Rain jackets', get_post_meta( $post, Keys::FOCUS_KEYPHRASE, true ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'contributor' ) ) );
		$request = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post );
		$request->set_body_params( array( 'meta' => array( Keys::FOCUS_KEYPHRASE => 'hijack' ) ) );
		$this->assertContains( rest_get_server()->dispatch( $request )->get_status(), array( 401, 403 ) );
		$this->assertSame( 'Rain jackets', get_post_meta( $post, Keys::FOCUS_KEYPHRASE, true ) );
	}

	public function test_readability_report_and_content_language(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$post = self::factory()->post->create( array( 'post_content' => str_repeat( '<p>The cat sat on the mat. However, it was a big cat.</p>', 20 ) ) );

		$data = $this->request( array( 'post_id' => $post ) )->get_data();
		$this->assertSame( array( 'seo', 'readability', 'preview' ), array_keys( $data ) );
		$readability = array_column( $data['readability']['results'], null, 'id' );
		$this->assertSame( 'pass', $readability['reading_ease']['status'] );
		$this->assertSame( 'pass', $readability['transition_words']['status'] );

		$german = static function () {
			return 'de_DE';
		};
		add_filter( 'dumpseo_content_locale', $german );
		$data = $this->request( array( 'post_id' => $post ) )->get_data();
		remove_filter( 'dumpseo_content_locale', $german );

		$ids = array_column( $data['readability']['results'], 'id' );
		$this->assertContains( 'sentence_length', $ids );
		$this->assertNotContains( 'reading_ease', $ids, 'English-only checks skipped.' );
	}
}
