<?php
/**
 * JSON-LD output on real requests.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Tests\Integration;

use DumpSEO\Frontend\CurrentPage;
use DumpSEO\Meta\Keys;
use DumpSEO\Plugin;
use DumpSEO\Schema\Piece;
use DumpSEO\Schema\SchemaContext;
use DumpSEO\Schema\SchemaModule;
use DumpSEO\Settings\Settings;
use WP_UnitTestCase;

/**
 * Checks the structured data printed in wp_head.
 *
 * @covers \DumpSEO\Schema\SchemaModule
 * @covers \DumpSEO\Schema\Graph
 * @covers \DumpSEO\Schema\SchemaContext
 * @covers \DumpSEO\Schema\Pieces\Publisher
 * @covers \DumpSEO\Schema\Pieces\WebSite
 * @covers \DumpSEO\Schema\Pieces\WebPage
 * @covers \DumpSEO\Schema\Pieces\PrimaryImage
 * @covers \DumpSEO\Schema\Pieces\BreadcrumbList
 * @covers \DumpSEO\Schema\Pieces\Article
 * @covers \DumpSEO\Breadcrumbs\Trail
 * @covers \DumpSEO\Compatibility\Conflicts
 */
final class SchemaTest extends WP_UnitTestCase {

	/**
	 * Schema module booted by the plugin.
	 *
	 * @var SchemaModule
	 */
	private $schema;

	public function set_up(): void {
		parent::set_up();
		update_option( 'blogname', 'Acme' );
		update_option( 'blog_public', '1' );
		update_option( Settings::OPTION, array( 'organization_logo' => 'https://example.org/logo.png' ) );
		$this->set_permalink_structure( '/%postname%/' );

		$schema = Plugin::instance()->module( 'schema' );
		$this->assertInstanceOf( SchemaModule::class, $schema );
		$this->schema = $schema;
	}

	/**
	 * Printed HTML for a URL.
	 *
	 * @param string $url URL.
	 */
	private function html( string $url ): string {
		$this->go_to( $url );
		Plugin::instance()->container()->get( CurrentPage::class )->reset();
		return get_echo( array( $this->schema, 'print_graph' ) );
	}

	/**
	 * Visits a URL and returns the graph nodes keyed by @type.
	 *
	 * @param string $url URL.
	 * @return array<string, array<string, mixed>>
	 */
	private function graph( string $url ): array {
		$html = $this->html( $url );
		if ( '' === $html ) {
			return array();
		}
		$this->assertSame( 1, substr_count( $html, '<script' ), 'Exactly one JSON-LD block.' );
		$this->assertSame( 1, preg_match( '#^<script type="application/ld\+json" class="dumpseo-schema">(.*)</script>\n$#s', $html, $m ) );
		$data = json_decode( $m[1], true );
		$this->assertIsArray( $data, 'Valid JSON.' );
		$this->assertSame( 'https://schema.org', $data['@context'] );

		$nodes = array();
		foreach ( $data['@graph'] as $node ) {
			$this->assertArrayNotHasKey( $node['@type'], $nodes, "One {$node['@type']} node." );
			$nodes[ $node['@type'] ] = $node;
		}
		$this->assert_references_resolve( $data['@graph'] );
		return $nodes;
	}

	/**
	 * Every {"@id": x} reference points at a node in the graph.
	 *
	 * @param array<int, array<string, mixed>> $graph Nodes.
	 */
	private function assert_references_resolve( array $graph ): void {
		$ids  = array();
		$refs = array();
		$walk = static function ( $value ) use ( &$walk, &$ids, &$refs ): void {
			if ( ! is_array( $value ) ) {
				return;
			}
			if ( isset( $value['@id'] ) && 1 === count( $value ) ) {
				$refs[] = $value['@id'];
				return;
			}
			if ( isset( $value['@id'] ) ) {
				$ids[] = $value['@id'];
			}
			foreach ( $value as $child ) {
				$walk( $child );
			}
		};
		$walk( $graph );
		foreach ( $refs as $ref ) {
			$this->assertContains( $ref, $ids, "Reference {$ref} resolves." );
		}
	}

	public function test_blog_post_graph(): void {
		$parent = self::factory()->category->create( array( 'name' => 'Guides' ) );
		$child  = self::factory()->category->create(
			array(
				'name'   => 'Boots',
				'parent' => $parent,
			)
		);
		$author = self::factory()->user->create( array( 'display_name' => 'Jo Writer' ) );
		$post   = self::factory()->post->create(
			array(
				'post_title'    => 'Choosing boots',
				'post_excerpt'  => 'How to choose.',
				'post_content'  => 'One two three four five.',
				'post_author'   => $author,
				'post_category' => array( $child ),
				'tags_input'    => array( 'leather' ),
				'post_date_gmt' => '2026-03-04 10:00:00',
				'post_date'     => '2026-03-04 10:00:00',
			)
		);
		$image  = self::factory()->attachment->create_object( 'boot.jpg', 0, array( 'post_mime_type' => 'image/jpeg' ) );
		wp_update_attachment_metadata(
			$image,
			array(
				'width'  => 1200,
				'height' => 630,
				'file'   => 'boot.jpg',
			)
		);
		set_post_thumbnail( $post, $image );

		$url   = get_permalink( $post );
		$nodes = $this->graph( $url );
		$home  = home_url( '/' );

		$this->assertSame( array( 'Organization', 'WebSite', 'WebPage', 'ImageObject', 'BreadcrumbList', 'BlogPosting', 'Person' ), array_keys( $nodes ) );

		$this->assertSame( $home . '#organization', $nodes['Organization']['@id'] );
		$this->assertSame( 'Acme', $nodes['Organization']['name'] );
		$this->assertSame( 'https://example.org/logo.png', $nodes['Organization']['logo']['url'] );

		$this->assertSame( $home . '?s={search_term_string}', $nodes['WebSite']['potentialAction'][0]['target']['urlTemplate'] );

		$this->assertSame( $url, $nodes['WebPage']['url'] );
		$this->assertSame( 'Choosing boots – Acme', $nodes['WebPage']['name'] );
		$this->assertSame( 'How to choose.', $nodes['WebPage']['description'] );
		$this->assertSame( '2026-03-04T10:00:00+00:00', $nodes['WebPage']['datePublished'] );

		$this->assertSame( 1200, $nodes['ImageObject']['width'] );

		$crumbs = array_column( $nodes['BreadcrumbList']['itemListElement'], 'name' );
		$this->assertSame( array( 'Home', 'Guides', 'Boots', 'Choosing boots' ), $crumbs );
		$this->assertArrayNotHasKey( 'item', $nodes['BreadcrumbList']['itemListElement'][3], 'Current page has no link.' );
		$this->assertSame( get_category_link( $parent ), $nodes['BreadcrumbList']['itemListElement'][1]['item'] );

		$article = $nodes['BlogPosting'];
		$this->assertSame( 'Choosing boots', $article['headline'] );
		$this->assertSame( 5, $article['wordCount'] );
		$this->assertSame( array( 'Boots' ), $article['articleSection'] );
		$this->assertSame( array( 'leather' ), $article['keywords'] );
		$this->assertSame( $nodes['Person']['@id'], $article['author']['@id'] );
		$this->assertSame( 'Jo Writer', $nodes['Person']['name'] );
	}

	public function test_page_front_page_and_archives(): void {
		$parent = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'About',
			)
		);
		$page   = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'Team',
				'post_parent' => $parent,
			)
		);

		$nodes = $this->graph( get_permalink( $page ) );
		$this->assertArrayNotHasKey( 'Article', $nodes );
		$this->assertArrayNotHasKey( 'BlogPosting', $nodes );
		$this->assertSame( array( 'Home', 'About', 'Team' ), array_column( $nodes['BreadcrumbList']['itemListElement'], 'name' ) );

		$home = $this->graph( home_url( '/' ) );
		$this->assertSame( 'WebPage', $home['WebPage']['@type'] );
		$this->assertSame( home_url( '/' ) . '#organization', $home['WebPage']['about']['@id'] );
		$this->assertArrayNotHasKey( 'BreadcrumbList', $home, 'No trail on the homepage.' );

		$cat = self::factory()->category->create( array( 'name' => 'News' ) );
		self::factory()->post->create( array( 'post_category' => array( $cat ) ) );
		$this->assertArrayHasKey( 'CollectionPage', $this->graph( get_category_link( $cat ) ) );

		$author = self::factory()->user->create( array( 'role' => 'author' ) );
		self::factory()->post->create( array( 'post_author' => $author ) );
		$this->assertArrayHasKey( 'ProfilePage', $this->graph( get_author_posts_url( $author ) ) );
	}

	public function test_person_publisher(): void {
		update_option(
			Settings::OPTION,
			array(
				'site_represents'   => 'person',
				'organization_name' => 'Sam Lee',
				'organization_logo' => 'https://example.org/sam.jpg',
			)
		);
		$nodes = $this->graph( home_url( '/' ) );

		$this->assertArrayNotHasKey( 'Organization', $nodes );
		$this->assertSame( home_url( '/' ) . '#person', $nodes['Person']['@id'] );
		$this->assertSame( 'Sam Lee', $nodes['Person']['name'] );
		$this->assertSame( 'https://example.org/sam.jpg', $nodes['Person']['image']['url'] );
	}

	public function test_noindex_404_and_search_keep_only_site_nodes(): void {
		$post = self::factory()->post->create();
		update_post_meta( $post, Keys::ROBOTS, 'noindex' );

		foreach ( array( get_permalink( $post ), home_url( '/?s=boots' ), home_url( '/?p=999999' ) ) as $url ) {
			$this->assertSame( array( 'Organization', 'WebSite' ), array_keys( $this->graph( $url ) ), $url );
		}
	}

	public function test_password_protected_post_hides_text_and_image(): void {
		$post  = self::factory()->post->create(
			array(
				'post_password' => 'secret',
				'post_content'  => 'Hidden words here.',
				'post_excerpt'  => 'Hidden summary.',
			)
		);
		$image = self::factory()->attachment->create_object( 'x.jpg', 0, array( 'post_mime_type' => 'image/jpeg' ) );
		set_post_thumbnail( $post, $image );

		$html = $this->html( get_permalink( $post ) );
		$this->assertStringNotContainsString( 'Hidden', $html );
		$this->assertStringNotContainsString( 'x.jpg', $html );
		$this->assertArrayNotHasKey( 'wordCount', $this->graph( get_permalink( $post ) )['BlogPosting'] );
	}

	public function test_output_cannot_break_out_of_the_script_element(): void {
		$post = self::factory()->post->create( array( 'post_title' => '</script><script>alert(1)</script> & "q"' ) );
		$html = $this->html( get_permalink( $post ) );

		$this->assertSame( 1, substr_count( $html, '<script' ) );
		$this->assertSame( 1, substr_count( $html, '</script>' ) );
		$this->assertSame( 'alert(1) & "q"', $this->graph( get_permalink( $post ) )['BlogPosting']['headline'] );
	}

	public function test_switch_conflicts_and_filters(): void {
		$post = self::factory()->post->create();
		$url  = get_permalink( $post );

		update_option( Settings::OPTION, array( 'schema_enabled' => false ) );
		$this->assertSame( '', $this->html( $url ) );
		update_option( Settings::OPTION, array() );

		$other = static function () {
			return 'Other SEO Plugin';
		};
		add_filter( 'dumpseo_schema_conflict', $other );
		$this->assertSame( '', $this->html( $url ) );
		$this->assertStringContainsString( 'Other SEO Plugin', get_echo( array( $this->schema, 'render_notice' ) ) );
		remove_filter( 'dumpseo_schema_conflict', $other );

		$faq = new class() implements Piece {
			public function is_needed( SchemaContext $context ): bool {
				return $context->is_singular();
			}
			public function generate( SchemaContext $context ): array {
				return array(
					array(
						'@type' => 'FAQPage',
						'@id'   => $context->url . '#faq',
						'empty' => '',
					),
				);
			}
		};
		$add = static function ( array $pieces ) use ( $faq ): array {
			unset( $pieces['breadcrumb'] );
			$pieces['faq'] = $faq;
			$pieces['bad'] = 'not a piece';
			return $pieces;
		};
		add_filter( 'dumpseo_schema_pieces', $add );
		$nodes = $this->graph( $url );
		remove_filter( 'dumpseo_schema_pieces', $add );

		$this->assertArrayHasKey( 'FAQPage', $nodes );
		$this->assertArrayNotHasKey( 'empty', $nodes['FAQPage'], 'Empty values removed.' );
		$this->assertArrayNotHasKey( 'BreadcrumbList', $nodes );
		$this->assertArrayNotHasKey( 'breadcrumb', $nodes['WebPage'], 'References to removed pieces are dropped.' );
	}
}
