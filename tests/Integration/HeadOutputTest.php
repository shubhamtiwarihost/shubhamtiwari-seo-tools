<?php
/**
 * Title and meta description output on real requests.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Tests\Integration;

use DumpSEO\Frontend\HeadModule;
use DumpSEO\Meta\Keys;
use DumpSEO\Plugin;
use DumpSEO\Settings\Settings;
use WP_UnitTestCase;

/**
 * Drives WordPress to each kind of page and checks <title> and description.
 *
 * @covers \DumpSEO\Frontend\HeadModule
 * @covers \DumpSEO\Meta\Resolver
 * @covers \DumpSEO\Meta\PageContext
 * @covers \DumpSEO\Meta\VariableValues
 */
final class HeadOutputTest extends WP_UnitTestCase {

	/**
	 * Head module booted by the plugin.
	 *
	 * @var HeadModule
	 */
	private $head;

	public function set_up(): void {
		parent::set_up();
		update_option( 'blogname', 'Acme' );
		update_option( 'blogdescription', 'Tools for builders' );
		update_option( 'posts_per_page', 1 );
		delete_option( Settings::OPTION );
		$this->set_permalink_structure( '/%postname%/' );

		$head = Plugin::instance()->module( 'head' );
		$this->assertInstanceOf( HeadModule::class, $head );
		$this->head = $head;
	}

	/**
	 * Navigates and returns [title, description tag HTML].
	 *
	 * @param string $url URL to visit.
	 * @return array{string, string}
	 */
	private function visit( string $url ): array {
		$this->go_to( $url );
		$this->head->reset();

		ob_start();
		$this->head->print_tags();
		$html = (string) ob_get_clean();

		$description = preg_match( '/<meta name="description"[^>]*>/', $html, $m ) ? $m[0] : '';
		return array( wp_get_document_title(), $description );
	}

	public function test_single_post_uses_default_template_and_excerpt(): void {
		$id = self::factory()->post->create(
			array(
				'post_title'   => 'Hello Post',
				'post_excerpt' => '',
				'post_content' => '<!-- wp:paragraph --><p>First <strong>bold</strong> paragraph [gallery] of the post.</p><!-- /wp:paragraph -->',
			)
		);

		list( $title, $description ) = $this->visit( get_permalink( $id ) );

		$this->assertSame( 'Hello Post – Acme', $title );
		$this->assertSame( '<meta name="description" content="First bold paragraph of the post." />', $description );
	}

	public function test_custom_title_and_description_win_and_may_use_variables(): void {
		$id = self::factory()->post->create( array( 'post_title' => 'Plain' ) );
		update_post_meta( $id, Keys::TITLE, 'Custom %%separator%% %%site_name%%' );
		update_post_meta( $id, Keys::DESCRIPTION, 'Hand written.' );

		list( $title, $description ) = $this->visit( get_permalink( $id ) );

		$this->assertSame( 'Custom – Acme', $title );
		$this->assertStringContainsString( 'content="Hand written."', $description );
	}

	public function test_variables_inside_post_title_are_not_expanded(): void {
		$id = self::factory()->post->create( array( 'post_title' => 'About %%site_name%%' ) );

		list( $title ) = $this->visit( get_permalink( $id ) );

		$this->assertSame( 'About %%site_name%% – Acme', $title );
	}

	public function test_malicious_title_and_description_are_escaped(): void {
		$id = self::factory()->post->create( array( 'post_title' => '</title><script>alert(1)</script>Evil & "Co"' ) );
		update_post_meta( $id, Keys::DESCRIPTION, '"><script>alert(2)</script>' );

		list( $title, $description ) = $this->visit( get_permalink( $id ) );

		$this->assertStringNotContainsString( '<script', $title );
		$this->assertStringNotContainsString( '</title>', $title );
		$this->assertStringContainsString( 'Evil &amp; &quot;Co&quot;', $title );
		$this->assertStringNotContainsString( '<script', $description );
		$this->assertMatchesRegularExpression( '/^<meta name="description" content="[^"<>]*" \/>$/', $description );
	}

	public function test_long_content_description_is_truncated(): void {
		$id = self::factory()->post->create(
			array(
				'post_excerpt' => '',
				'post_content' => str_repeat( 'word ', 100 ),
			)
		);

		list( , $description ) = $this->visit( get_permalink( $id ) );

		$this->assertMatchesRegularExpression( '/content="(.+)…"/u', $description );
		preg_match( '/content="(.+)"/u', $description, $m );
		$this->assertLessThanOrEqual( 155, mb_strlen( html_entity_decode( $m[1] ) ) );
	}

	public function test_password_protected_post_leaks_nothing(): void {
		$id = self::factory()->post->create(
			array(
				'post_content'  => 'Secret launch plans',
				'post_excerpt'  => 'Secret summary',
				'post_password' => 'pw',
			)
		);

		list( , $description ) = $this->visit( get_permalink( $id ) );

		$this->assertSame( '', $description );
	}

	public function test_category_archive_page_two_and_custom_term_title(): void {
		$cat = self::factory()->category->create( array( 'name' => 'News' ) );
		self::factory()->post->create_many( 3, array( 'post_category' => array( $cat ) ) );

		list( $title ) = $this->visit( add_query_arg( 'paged', 2, get_category_link( $cat ) ) );
		$this->assertSame( 'News – Page 2 of 3 – Acme', $title );

		update_term_meta( $cat, Keys::TITLE, 'Latest news %%separator%% %%site_name%%' );
		update_term_meta( $cat, Keys::DESCRIPTION, 'All our news.' );
		list( $title, $description ) = $this->visit( get_category_link( $cat ) );
		$this->assertSame( 'Latest news – Acme', $title );
		$this->assertStringContainsString( 'content="All our news."', $description );
	}

	public function test_homepage_showing_posts(): void {
		self::factory()->post->create();

		list( $title, $description ) = $this->visit( home_url( '/' ) );

		$this->assertSame( 'Acme – Tools for builders', $title );
		$this->assertStringContainsString( 'content="Tools for builders"', $description );
	}

	public function test_static_front_page_and_posts_page(): void {
		$front = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Welcome',
			)
		);
		$blog  = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Journal',
			)
		);
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $front );
		update_option( 'page_for_posts', $blog );

		list( $title ) = $this->visit( home_url( '/' ) );
		$this->assertSame( 'Acme – Tools for builders', $title, 'Front page uses the homepage template...' );

		update_post_meta( $front, Keys::TITLE, 'Welcome to Acme' );
		list( $title ) = $this->visit( home_url( '/' ) );
		$this->assertSame( 'Welcome to Acme', $title, '...unless the page has a custom title.' );

		list( $title ) = $this->visit( get_permalink( $blog ) );
		$this->assertSame( 'Journal – Acme', $title );
	}

	public function test_search_404_author_and_date(): void {
		$user = self::factory()->user->create(
			array(
				'display_name' => 'Ada Writer',
				'role'         => 'author',
			)
		);
		self::factory()->post->create(
			array(
				'post_author' => $user,
				'post_date'   => '2026-03-04 10:00:00',
			)
		);

		list( $title ) = $this->visit( home_url( '/?s=' . rawurlencode( '<script>x</script>boots' ) ) );
		$this->assertStringStartsWith( 'Search results for “', $title );
		$this->assertStringNotContainsString( '<script', $title );

		list( $title ) = $this->visit( home_url( '/this-does-not-exist-at-all/' ) );
		$this->assertSame( 'Page not found – Acme', $title );

		list( $title ) = $this->visit( get_author_posts_url( $user ) );
		$this->assertSame( 'Ada Writer – Acme', $title );

		list( $title ) = $this->visit( get_month_link( 2026, 3 ) );
		$this->assertSame( 'March 2026 – Acme', $title );
	}

	public function test_custom_post_type_and_its_archive(): void {
		register_post_type(
			'book-review',
			array(
				'public'      => true,
				'has_archive' => true,
				'label'       => 'Book Reviews',
				'labels'      => array( 'singular_name' => 'Book Review' ),
			)
		);
		flush_rewrite_rules();
		$id = self::factory()->post->create(
			array(
				'post_type'  => 'book-review',
				'post_title' => 'Dune',
			)
		);

		list( $title ) = $this->visit( get_permalink( $id ) );
		$this->assertSame( 'Dune – Acme', $title );

		list( $title ) = $this->visit( get_post_type_archive_link( 'book-review' ) );
		$this->assertSame( 'Book Reviews – Acme', $title );

		unregister_post_type( 'book-review' );
	}

	public function test_template_settings_are_used(): void {
		update_option(
			Settings::OPTION,
			array(
				'separator'     => 'pipe',
				'title_pt_post' => '%%title%% by %%author%% %%separator%% %%category%%',
				'desc_pt_post'  => '',
			)
		);
		$user = self::factory()->user->create( array( 'display_name' => 'Sam' ) );
		$cat  = self::factory()->category->create( array( 'name' => 'Guides' ) );
		$id   = self::factory()->post->create(
			array(
				'post_title'    => 'Setup',
				'post_author'   => $user,
				'post_category' => array( $cat ),
				'post_excerpt'  => 'Ignored because the description template is empty.',
			)
		);

		list( $title, $description ) = $this->visit( get_permalink( $id ) );

		$this->assertSame( 'Setup by Sam | Guides', $title );
		$this->assertSame( '', $description );
	}

	public function test_output_can_be_disabled_and_feeds_are_untouched(): void {
		$id = self::factory()->post->create( array( 'post_title' => 'Toggle' ) );

		add_filter( 'dumpseo_head_output_enabled', '__return_false' );
		list( $title, $description ) = $this->visit( get_permalink( $id ) );
		remove_filter( 'dumpseo_head_output_enabled', '__return_false' );

		$this->assertSame( 'Toggle &#8211; Acme', $title, 'Core title (core texturizes the dash).' );
		$this->assertSame( '', $description );

		$this->go_to( get_feed_link() );
		$this->head->reset();
		$this->assertSame( 'Core feed title', $this->head->wp_title( 'Core feed title' ) );
	}
}
