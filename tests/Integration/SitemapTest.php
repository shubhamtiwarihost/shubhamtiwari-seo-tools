<?php
/**
 * XML sitemap behaviour, using WordPress core's sitemap provider and renderer.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Tests\Integration;

use DumpSEO\Meta\Keys;
use DumpSEO\Plugin;
use DumpSEO\Settings\Settings;
use DumpSEO\Sitemap\ImageRenderer;
use DumpSEO\Sitemap\SitemapModule;
use WP_UnitTestCase;

/**
 * Checks what search engines would receive at /wp-sitemap-*.xml.
 *
 * @covers \DumpSEO\Sitemap\SitemapModule
 * @covers \DumpSEO\Sitemap\Exclusions
 * @covers \DumpSEO\Sitemap\Images
 * @covers \DumpSEO\Sitemap\ImageRenderer
 */
final class SitemapTest extends WP_UnitTestCase {

	/**
	 * Sitemap module booted by the plugin.
	 *
	 * @var SitemapModule
	 */
	private $module;

	public function set_up(): void {
		parent::set_up();
		update_option( 'blog_public', '1' );
		delete_option( Settings::OPTION );
		$this->set_permalink_structure( '/%postname%/' );

		$module = Plugin::instance()->module( 'sitemap' );
		$this->assertInstanceOf( SitemapModule::class, $module );
		$this->module = $module;
		$this->fresh_exclusions();
	}

	/**
	 * The exclusion cache is per request; each test is a new "request".
	 */
	private function fresh_exclusions(): void {
		$reflection = new \ReflectionProperty( SitemapModule::class, 'exclusions' );
		$reflection->setAccessible( true );
		$reflection->getValue( $this->module )->reset();
	}

	/**
	 * Entries of the first page of a post type sitemap.
	 *
	 * @param string $post_type Post type.
	 * @return array<int, array<string, mixed>>
	 */
	private function post_entries( string $post_type = 'post' ): array {
		$this->fresh_exclusions();
		return wp_sitemaps_get_server()->registry->get_provider( 'posts' )->get_url_list( 1, $post_type );
	}

	/**
	 * Locations in a list of entries.
	 *
	 * @param array<int, array<string, mixed>> $entries Entries.
	 * @return string[]
	 */
	private function locs( array $entries ): array {
		return array_column( $entries, 'loc' );
	}

	/**
	 * Post type subtypes offered by the posts provider.
	 *
	 * @return string[]
	 */
	private function post_subtypes(): array {
		$this->fresh_exclusions();
		return array_keys( wp_sitemaps_get_server()->registry->get_provider( 'posts' )->get_object_subtypes() );
	}

	public function test_noindex_and_password_posts_are_left_out_and_lastmod_is_set(): void {
		$normal   = self::factory()->post->create();
		$nofollow = self::factory()->post->create();
		$hidden   = self::factory()->post->create();
		$secret   = self::factory()->post->create( array( 'post_password' => 'pw' ) );
		update_post_meta( $hidden, Keys::ROBOTS, 'noindex,nofollow' );
		update_post_meta( $nofollow, Keys::ROBOTS, 'nofollow' );

		$entries = $this->post_entries();
		$locs    = $this->locs( $entries );

		$this->assertContains( get_permalink( $normal ), $locs );
		$this->assertContains( get_permalink( $nofollow ), $locs, 'nofollow alone does not remove a post.' );
		$this->assertNotContains( get_permalink( $hidden ), $locs );
		$this->assertNotContains( get_permalink( $secret ), $locs );
		foreach ( $entries as $entry ) {
			$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', $entry['lastmod'] );
		}
	}

	public function test_noindex_post_type_is_removed_unless_items_are_explicitly_indexed(): void {
		$a = self::factory()->post->create();
		$b = self::factory()->post->create();
		update_option( Settings::OPTION, array( 'noindex_pt_post' => true ) );

		$this->assertNotContains( 'post', $this->post_subtypes() );
		$this->assertContains( 'page', $this->post_subtypes(), 'Other types unaffected.' );

		update_post_meta( $b, Keys::ROBOTS, 'index' );
		$this->assertContains( 'post', $this->post_subtypes() );
		$this->assertSame( array( get_permalink( $b ) ), $this->locs( $this->post_entries() ) );
		$this->assertNotContains( get_permalink( $a ), $this->locs( $this->post_entries() ) );
	}

	public function test_posts_canonicalised_elsewhere_are_left_out(): void {
		$elsewhere = self::factory()->post->create();
		$self      = self::factory()->post->create();
		update_post_meta( $elsewhere, Keys::CANONICAL, 'https://example.com/original/' );
		update_post_meta( $self, Keys::CANONICAL, untrailingslashit( get_permalink( $self ) ) );

		$locs = $this->locs( $this->post_entries() );

		$this->assertNotContains( get_permalink( $elsewhere ), $locs );
		$this->assertContains( get_permalink( $self ), $locs, 'A canonical pointing to itself keeps the post.' );
	}

	public function test_page_count_matches_exclusions(): void {
		self::factory()->post->create_many( 3 );
		$hidden = self::factory()->post->create();
		update_post_meta( $hidden, Keys::ROBOTS, 'noindex' );
		add_filter(
			'wp_sitemaps_max_urls',
			static function () {
				return 1;
			}
		);
		$this->fresh_exclusions();

		$pages = wp_sitemaps_get_server()->registry->get_provider( 'posts' )->get_max_num_pages( 'post' );

		$this->assertSame( 3, (int) $pages, 'Core counts pages with the same (filtered) query (a float on WordPress 6.4).' );
	}

	public function test_terms_follow_the_same_rules(): void {
		$shown  = self::factory()->category->create();
		$hidden = self::factory()->category->create();
		self::factory()->post->create( array( 'post_category' => array( $shown, $hidden ) ) );
		update_term_meta( $hidden, Keys::ROBOTS, 'noindex' );
		$provider = wp_sitemaps_get_server()->registry->get_provider( 'taxonomies' );

		$this->fresh_exclusions();
		$locs = $this->locs( $provider->get_url_list( 1, 'category' ) );
		$this->assertContains( get_category_link( $shown ), $locs );
		$this->assertNotContains( get_category_link( $hidden ), $locs );

		update_option( Settings::OPTION, array( 'noindex_tax_category' => true ) );
		$this->fresh_exclusions();
		$this->assertArrayNotHasKey( 'category', $provider->get_object_subtypes() );
	}

	public function test_images_featured_local_relative_and_external(): void {
		$thumb = self::factory()->attachment->create_object( 'featured.jpg', 0, array( 'post_mime_type' => 'image/jpeg' ) );
		$post  = self::factory()->post->create(
			array(
				'post_content' => '<img src="' . home_url( '/wp-content/uploads/a.png' ) . '">'
					. '<img class="x" src=\'/wp-content/uploads/b%20c.png?x=1&amp;y=2\' alt="">'
					. '<img src="https://cdn.elsewhere.example/c.png">'
					. '<img src="javascript:alert(1)">'
					. '<img src="' . home_url( '/wp-content/uploads/a.png' ) . '">',
			)
		);
		set_post_thumbnail( $post, $thumb );

		$entries = $this->post_entries();
		$images  = $entries[0]['images'] ?? array();

		$this->assertCount( 3, $images, 'Featured + 2 unique local images; external and javascript: dropped.' );
		$this->assertStringEndsWith( 'featured.jpg', $images[0], 'Featured image comes first.' );
		$this->assertSame( home_url( '/wp-content/uploads/a.png' ), $images[1] );
		$this->assertStringStartsWith( home_url( '/wp-content/uploads/b' ), $images[2], 'Root-relative URL made absolute.' );
	}

	public function test_images_are_capped_and_password_posts_only_list_featured(): void {
		$content = '';
		for ( $i = 0; $i < 15; $i++ ) {
			$content .= '<img src="' . home_url( "/wp-content/uploads/{$i}.png" ) . '">';
		}
		self::factory()->post->create( array( 'post_content' => $content ) );
		$this->assertCount( 10, $this->post_entries()[0]['images'] );

		wp_delete_post( (int) get_posts( array( 'fields' => 'ids' ) )[0], true );
		self::factory()->post->create(
			array(
				'post_content'  => $content,
				'post_password' => 'pw',
			)
		);
		// The sitemap leaves password-protected posts out entirely; check the collector on its own too.
		$images = ( new \DumpSEO\Sitemap\Images() )->for_post( get_post( (int) get_posts( array( 'fields' => 'ids' ) )[0] ) );
		$this->assertSame( array(), $images );
	}

	public function test_images_can_be_turned_off(): void {
		self::factory()->post->create( array( 'post_content' => '<img src="' . home_url( '/x.png' ) . '">' ) );
		update_option( Settings::OPTION, array( 'sitemap_images' => false ) );

		$this->assertArrayNotHasKey( 'images', $this->post_entries()[0] );
	}

	public function test_renderer_outputs_valid_xml_with_image_namespace(): void {
		$renderer = new ImageRenderer();
		$xml      = $renderer->get_sitemap_xml(
			array(
				array(
					'loc'     => 'https://example.org/a-b/?x=1&y=2',
					'lastmod' => '2026-01-02T03:04:05+00:00',
					'images'  => array( 'https://example.org/i.png?w=1&h=2', '' ),
				),
				array( 'loc' => 'https://example.org/c/' ),
			)
		);

		$this->assertIsString( $xml );
		$doc = new \DOMDocument();
		$this->assertTrue( $doc->loadXML( $xml ), 'Well-formed XML.' );

		$xpath = new \DOMXPath( $doc );
		$xpath->registerNamespace( 's', 'http://www.sitemaps.org/schemas/sitemap/0.9' );
		$xpath->registerNamespace( 'image', ImageRenderer::IMAGE_NS );
		$this->assertSame( 2, $xpath->query( '//s:url' )->length );
		$this->assertSame( 1, $xpath->query( '//s:url[1]/image:image/image:loc' )->length, 'Empty image URL skipped.' );
		$this->assertSame( 'https://example.org/i.png?w=1&h=2', $xpath->query( '//image:loc' )->item( 0 )->textContent );
		$this->assertSame( 'https://example.org/a-b/?x=1&y=2', $xpath->query( '//s:loc' )->item( 0 )->textContent );
	}

	public function test_image_renderer_is_installed_only_when_images_enabled(): void {
		$server           = wp_sitemaps_get_server();
		$server->renderer = new \WP_Sitemaps_Renderer();

		update_option( Settings::OPTION, array( 'sitemap_images' => false ) );
		$this->module->use_image_renderer( $server );
		$this->assertNotInstanceOf( ImageRenderer::class, $server->renderer );

		delete_option( Settings::OPTION );
		$this->module->use_image_renderer( $server );
		$this->assertInstanceOf( ImageRenderer::class, $server->renderer );
	}

	public function test_enable_switch_and_core_discourage_setting(): void {
		$this->assertTrue( wp_sitemaps_get_server()->sitemaps_enabled() );

		update_option( Settings::OPTION, array( 'sitemap_enabled' => false ) );
		$this->assertFalse( wp_sitemaps_get_server()->sitemaps_enabled() );

		delete_option( Settings::OPTION );
		update_option( 'blog_public', '0' );
		$this->assertFalse( wp_sitemaps_get_server()->sitemaps_enabled(), 'DumpSEO never re-enables a sitemap core turned off.' );
	}

	public function test_author_sitemap_switch_and_noindex(): void {
		$provider = new \stdClass();

		$this->assertSame( $provider, $this->module->filter_provider( $provider, 'users' ) );
		$this->assertSame( $provider, $this->module->filter_provider( $provider, 'posts' ) );

		update_option( Settings::OPTION, array( 'sitemap_users' => false ) );
		$this->assertFalse( $this->module->filter_provider( $provider, 'users' ) );

		update_option( Settings::OPTION, array( 'noindex_author' => true ) );
		$this->assertFalse( $this->module->filter_provider( $provider, 'users' ) );
	}

	public function test_featured_images_do_not_cost_queries_per_post(): void {
		$count = function ( int $posts ): int {
			global $wpdb;
			foreach ( get_posts(
				array(
					'fields'         => 'ids',
					'posts_per_page' => -1,
				)
			) as $id ) {
				wp_delete_post( $id, true );
			}
			for ( $i = 0; $i < $posts; $i++ ) {
				$post = self::factory()->post->create();
				set_post_thumbnail( $post, self::factory()->attachment->create_object( "img{$i}.jpg", 0, array( 'post_mime_type' => 'image/jpeg' ) ) );
			}
			wp_cache_flush();
			$before = $wpdb->num_queries;
			$this->post_entries();
			return $wpdb->num_queries - $before;
		};

		$ten    = $count( 10 );
		$thirty = $count( 30 );

		$this->assertSame( $ten, $thirty, "Query count must not grow with posts (10 posts: {$ten}, 30 posts: {$thirty})." );
	}
}
