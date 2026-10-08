<?php
/**
 * Canonical and robots output on real requests.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Tests\Integration;

use DumpSEO\Admin\SettingsPage;
use DumpSEO\Admin\TermFields;
use DumpSEO\Frontend\HeadModule;
use DumpSEO\Meta\Keys;
use DumpSEO\Meta\MetaModule;
use DumpSEO\Plugin;
use DumpSEO\Settings\Settings;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Drives WordPress to pages and inspects the full wp_head() output.
 *
 * @covers \DumpSEO\Meta\Canonical
 * @covers \DumpSEO\Meta\Robots
 * @covers \DumpSEO\Frontend\HeadModule
 */
final class CanonicalRobotsTest extends WP_UnitTestCase {

	/**
	 * Head module booted by the plugin.
	 *
	 * @var HeadModule
	 */
	private $head;

	public function set_up(): void {
		parent::set_up();
		update_option( 'posts_per_page', 1 );
		update_option( 'blog_public', '1' );
		delete_option( Settings::OPTION );
		$this->set_permalink_structure( '/%postname%/' );

		$head = Plugin::instance()->module( 'head' );
		$this->assertInstanceOf( HeadModule::class, $head );
		$this->head = $head;

		$meta = Plugin::instance()->module( 'meta' );
		$this->assertInstanceOf( MetaModule::class, $meta );
		$meta->register_meta(); // The WP test suite unregisters meta keys between tests.
	}

	/**
	 * Visits a URL and returns the complete wp_head() output.
	 *
	 * @param string $url URL.
	 */
	private function head_html( string $url ): string {
		$this->go_to( $url );
		$this->head->reset();
		add_action( 'wp_head', 'rel_canonical' ); // Restore core's canonical each visit; DumpSEO must remove it.
		return get_echo( 'wp_head' );
	}

	/**
	 * Canonical hrefs in the HTML.
	 *
	 * @param string $html HTML.
	 * @return string[]
	 */
	private function canonicals( string $html ): array {
		preg_match_all( '/<link rel=[\'"]canonical[\'"] href=[\'"]([^\'"]+)[\'"]/', $html, $m );
		// Attribute values are entity-encoded by esc_url(); decode before comparing.
		return array_map( 'html_entity_decode', $m[1] );
	}

	/**
	 * Robots directives in the HTML.
	 *
	 * @param string $html HTML.
	 * @return string[]
	 */
	private function robots( string $html ): array {
		if ( ! preg_match( '/<meta name=[\'"]robots[\'"] content=[\'"]([^\'"]*)[\'"]/', $html, $m ) ) {
			return array();
		}
		return array_map( 'trim', explode( ',', $m[1] ) );
	}

	public function test_single_post_has_exactly_one_clean_canonical(): void {
		$id = self::factory()->post->create( array( 'post_title' => 'Boots' ) );

		$html = $this->head_html( add_query_arg( 'utm_source', 'news"><script>', get_permalink( $id ) ) );

		$this->assertSame( array( get_permalink( $id ) ), $this->canonicals( $html ), 'One canonical, no tracking params.' );
		$this->assertNotContains( 'noindex', $this->robots( $html ) );
	}

	public function test_nothing_is_noindexed_by_default(): void {
		$cat  = self::factory()->category->create();
		$post = self::factory()->post->create( array( 'post_category' => array( $cat ) ) );
		$page = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$user = self::factory()->user->create( array( 'role' => 'author' ) );
		self::factory()->post->create( array( 'post_author' => $user ) );

		foreach ( array( home_url( '/' ), get_permalink( $post ), get_permalink( $page ), get_category_link( $cat ), get_author_posts_url( $user ) ) as $url ) {
			$html = $this->head_html( $url );
			$this->assertNotContains( 'noindex', $this->robots( $html ), $url );
			$this->assertCount( 1, $this->canonicals( $html ), $url );
		}
	}

	public function test_paginated_archive_is_canonical_to_itself(): void {
		$cat = self::factory()->category->create( array( 'slug' => 'news' ) );
		self::factory()->post->create_many( 3, array( 'post_category' => array( $cat ) ) );

		$canonical = $this->canonicals( $this->head_html( add_query_arg( 'paged', 2, get_category_link( $cat ) ) ) );

		$this->assertCount( 1, $canonical );
		$this->assertMatchesRegularExpression( '#(/page/2/?|[?&]paged=2)$#', $canonical[0] );
		$this->assertStringStartsWith( get_category_link( $cat ), $canonical[0] );
	}

	public function test_multipage_post_page_two(): void {
		$id = self::factory()->post->create( array( 'post_content' => 'One<!--nextpage-->Two' ) );

		$canonical = $this->canonicals( $this->head_html( add_query_arg( 'page', 2, get_permalink( $id ) ) ) );

		$this->assertCount( 1, $canonical );
		$this->assertMatchesRegularExpression( '#(/2/?|[?&]page=2)$#', $canonical[0] );
	}

	public function test_search_and_404_are_noindex_without_canonical(): void {
		self::factory()->post->create();

		foreach ( array( home_url( '/?s=boots' ), home_url( '/no-such-page-here/' ) ) as $url ) {
			$html = $this->head_html( $url );
			$this->assertContains( 'noindex', $this->robots( $html ), $url );
			$this->assertSame( array(), $this->canonicals( $html ), $url );
		}
	}

	public function test_type_noindex_setting_and_per_post_override(): void {
		update_option( Settings::OPTION, array( 'noindex_pt_post' => true ) );
		$hidden  = self::factory()->post->create();
		$visible = self::factory()->post->create();
		update_post_meta( $visible, Keys::ROBOTS, 'index' );

		$html = $this->head_html( get_permalink( $hidden ) );
		$this->assertContains( 'noindex', $this->robots( $html ) );
		$this->assertSame( array(), $this->canonicals( $html ), 'No canonical on noindex pages.' );

		$html = $this->head_html( get_permalink( $visible ) );
		$this->assertNotContains( 'noindex', $this->robots( $html ) );
		$this->assertCount( 1, $this->canonicals( $html ) );

		$page = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$this->assertNotContains( 'noindex', $this->robots( $this->head_html( get_permalink( $page ) ) ), 'Other post types unaffected.' );
	}

	public function test_per_post_extra_directives(): void {
		$id = self::factory()->post->create();
		update_post_meta( $id, Keys::ROBOTS, 'nofollow,noarchive,nosnippet,noimageindex' );

		$robots = $this->robots( $this->head_html( get_permalink( $id ) ) );

		foreach ( array( 'nofollow', 'noarchive', 'nosnippet', 'noimageindex' ) as $directive ) {
			$this->assertContains( $directive, $robots );
		}
		$this->assertNotContains( 'noindex', $robots );
	}

	public function test_core_discourage_search_engines_is_never_overridden(): void {
		update_option( 'blog_public', '0' );
		$id = self::factory()->post->create();
		update_post_meta( $id, Keys::ROBOTS, 'index' );

		$this->assertContains( 'noindex', $this->robots( $this->head_html( get_permalink( $id ) ) ) );
	}

	public function test_custom_canonical_and_invalid_values(): void {
		$id = self::factory()->post->create();
		update_post_meta( $id, Keys::CANONICAL, 'https://example.com/original/' );
		$this->assertSame( array( 'https://example.com/original/' ), $this->canonicals( $this->head_html( get_permalink( $id ) ) ) );

		// A bad value stored directly in the database is ignored, falling back to the permalink.
		global $wpdb;
		// phpcs:disable WordPress.DB.SlowDBQuery -- Deliberately bypasses the meta sanitize callback to simulate a direct database edit.
		$wpdb->update(
			$wpdb->postmeta,
			array( 'meta_value' => 'javascript:alert(1)' ),
			array(
				'post_id'  => $id,
				'meta_key' => Keys::CANONICAL,
			)
		);
		// phpcs:enable WordPress.DB.SlowDBQuery
		wp_cache_delete( $id, 'post_meta' );
		$this->assertSame( array( get_permalink( $id ) ), $this->canonicals( $this->head_html( get_permalink( $id ) ) ) );
	}

	public function test_rest_sanitizes_canonical_and_robots(): void {
		$author = self::factory()->user->create( array( 'role' => 'author' ) );
		$id     = self::factory()->post->create( array( 'post_author' => $author ) );
		wp_set_current_user( $author );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $id );
		$request->set_body_params(
			array(
				'meta' => array(
					Keys::CANONICAL => 'javascript:alert(1)',
					Keys::ROBOTS    => 'index,NOINDEX,evil,nofollow',
				),
			)
		);
		$this->assertSame( 200, rest_get_server()->dispatch( $request )->get_status() );

		$this->assertSame( '', get_post_meta( $id, Keys::CANONICAL, true ) );
		$this->assertSame( 'noindex,nofollow', get_post_meta( $id, Keys::ROBOTS, true ) );
	}

	public function test_term_noindex_setting_and_term_form(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$tag = self::factory()->tag->create();
		self::factory()->post->create( array( 'tags_input' => array( get_term( $tag )->name ) ) );

		update_option( Settings::OPTION, array( 'noindex_tax_post_tag' => true ) );
		$this->assertContains( 'noindex', $this->robots( $this->head_html( get_tag_link( $tag ) ) ) );

		$fields = Plugin::build_container()->get( TermFields::class );
		$this->assertInstanceOf( TermFields::class, $fields );

		$_POST = array(
			TermFields::NONCE_FIELD => wp_create_nonce( 'dumpseo_term_' . $tag ),
			TermFields::CANON_FIELD => 'https://example.com/tags/',
			TermFields::INDEX_FIELD => 'index',
			TermFields::ROBOT_FIELD => array( 'nofollow', 'evil', 'noindex' ),
		);
		$fields->save( $tag, 0, 'post_tag' );

		$this->assertSame( 'https://example.com/tags/', get_term_meta( $tag, Keys::CANONICAL, true ) );
		$this->assertSame( 'index,nofollow', get_term_meta( $tag, Keys::ROBOTS, true ), 'Checkboxes cannot smuggle in noindex; unknown values dropped.' );

		$html = $this->head_html( get_tag_link( $tag ) );
		$this->assertNotContains( 'noindex', $this->robots( $html ) );
		$this->assertContains( 'nofollow', $this->robots( $html ) );
		$this->assertSame( array( 'https://example.com/tags/' ), $this->canonicals( $html ) );

		// An invalid canonical keeps the previous value.
		$_POST[ TermFields::CANON_FIELD ] = 'javascript:alert(1)';
		$fields->save( $tag, 0, 'post_tag' );
		$this->assertSame( 'https://example.com/tags/', get_term_meta( $tag, Keys::CANONICAL, true ) );
		$_POST = array();
	}

	public function test_output_disabled_leaves_core_canonical(): void {
		$id = self::factory()->post->create();
		add_filter( 'dumpseo_head_output_enabled', '__return_false' );

		$html = $this->head_html( get_permalink( $id ) );
		remove_filter( 'dumpseo_head_output_enabled', '__return_false' );

		$this->assertSame( array( get_permalink( $id ) ), $this->canonicals( $html ), 'Core prints its own canonical.' );
	}

	public function test_settings_page_warns_when_site_discourages_search_engines(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$page = Plugin::build_container()->get( SettingsPage::class );
		$this->assertInstanceOf( SettingsPage::class, $page );
		$page->register_settings();

		update_option( 'blog_public', '0' );
		$this->assertStringContainsString( 'options-reading.php', get_echo( array( $page, 'render_page' ) ) );

		update_option( 'blog_public', '1' );
		$this->assertStringNotContainsString( 'options-reading.php', get_echo( array( $page, 'render_page' ) ) );
	}
}
