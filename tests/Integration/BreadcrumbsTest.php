<?php
/**
 * Visible breadcrumbs: function, shortcode and block.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Tests\Integration;

use DumpSEO\Breadcrumbs\BreadcrumbsModule;
use DumpSEO\Frontend\CurrentPage;
use DumpSEO\Plugin;
use DumpSEO\Settings\Settings;
use WP_UnitTestCase;

/**
 * @covers \DumpSEO\Breadcrumbs\BreadcrumbsModule
 * @covers \DumpSEO\Breadcrumbs\Renderer
 * @covers \DumpSEO\Breadcrumbs\Trail
 */
final class BreadcrumbsTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		update_option( Settings::OPTION, array() );
		$this->set_permalink_structure( '/%postname%/' );

		$module = Plugin::instance()->module( 'breadcrumbs' );
		$this->assertInstanceOf( BreadcrumbsModule::class, $module );
		if ( ! \WP_Block_Type_Registry::get_instance()->is_registered( BreadcrumbsModule::BLOCK ) ) {
			$module->register_block_and_shortcode();
		}
	}

	/**
	 * Visits a URL and returns the breadcrumb HTML from the template function.
	 *
	 * @param string $url URL.
	 */
	private function crumbs( string $url ): string {
		$this->go_to( $url );
		Plugin::instance()->container()->get( CurrentPage::class )->reset();
		return dumpseo_get_breadcrumbs();
	}

	/**
	 * Item names and links from breadcrumb HTML.
	 *
	 * @param string $html HTML.
	 * @return array<int, array{0: string, 1: string}> [name, href or '' for the current page].
	 */
	private function items( string $html ): array {
		preg_match_all( '#<li class="dumpseo-breadcrumbs__item">(?:<a href="([^"]*)">([^<]*)</a>|<span[^>]*>([^<]*)</span>)#', $html, $m, PREG_SET_ORDER );
		return array_map(
			static function ( array $found ): array {
				return '' !== $found[1] ? array( html_entity_decode( $found[2] ), $found[1] ) : array( html_entity_decode( $found[3] ), '' );
			},
			$m
		);
	}

	public function test_post_trail_markup(): void {
		$parent = self::factory()->category->create( array( 'name' => 'Guides' ) );
		$child  = self::factory()->category->create(
			array(
				'name'   => 'Boots & Shoes',
				'parent' => $parent,
			)
		);
		$post   = self::factory()->post->create(
			array(
				'post_title'    => 'Choosing <em>boots</em>',
				'post_category' => array( $child ),
			)
		);

		$html = $this->crumbs( get_permalink( $post ) );

		$this->assertStringStartsWith( '<nav class="dumpseo-breadcrumbs" aria-label="Breadcrumbs"><ol', $html );
		$this->assertSame(
			array(
				array( 'Home', home_url( '/' ) ),
				array( 'Guides', get_category_link( $parent ) ),
				array( 'Boots & Shoes', get_category_link( $child ) ),
				array( 'Choosing boots', '' ),
			),
			$this->items( $html )
		);
		$this->assertStringContainsString( '<span aria-current="page">Choosing boots</span>', $html );
		$this->assertSame( 3, substr_count( $html, 'aria-hidden="true">›</span>' ), 'Separators between items only, hidden from screen readers.' );
		$this->assertTrue( wp_style_is( BreadcrumbsModule::STYLE, 'enqueued' ) );
	}

	public function test_settings_search_404_and_home(): void {
		update_option(
			Settings::OPTION,
			array(
				'breadcrumbs_home'      => 'Start',
				'breadcrumbs_separator' => '/',
			)
		);

		$html = $this->crumbs( home_url( '/?s=<b>boots</b>' ) );
		$this->assertSame( array( array( 'Start', home_url( '/' ) ), array( 'Search results for “boots”', '' ) ), $this->items( $html ) );
		$this->assertStringNotContainsString( '<b>', $html, 'Search phrase is plain text.' );
		$this->assertStringNotContainsString( '<script', $this->crumbs( home_url( '/?s=<script>alert(1)</script>' ) ) );
		$this->assertStringContainsString( 'aria-hidden="true">/</span>', $html );

		$this->assertSame( 'Page not found', $this->items( $this->crumbs( home_url( '/?p=999999' ) ) )[1][0] );
		$this->assertSame( '', $this->crumbs( home_url( '/' ) ), 'Nothing on the homepage.' );
	}

	public function test_shortcode_and_block(): void {
		$page = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Team',
			)
		);
		$this->go_to( get_permalink( $page ) );
		Plugin::instance()->container()->get( CurrentPage::class )->reset();

		$shortcode = do_shortcode( '[dumpseo_breadcrumbs]' );
		$this->assertSame( array( array( 'Home', home_url( '/' ) ), array( 'Team', '' ) ), $this->items( $shortcode ) );

		$block = do_blocks( '<!-- wp:dumpseo/breadcrumbs {"style":{"spacing":{"margin":{"top":"2rem"}}}} /-->' );
		$this->assertStringStartsWith( '<nav ', $block );
		$this->assertStringContainsString( 'class="dumpseo-breadcrumbs wp-block-dumpseo-breadcrumbs"', $block );
		$this->assertMatchesRegularExpression( '/style="margin-top:2rem;?"/', $block, 'Block supports (spacing) apply (WordPress 6.4 adds a trailing semicolon).' );
		$this->assertSame( $this->items( $shortcode ), $this->items( $block ) );
	}

	public function test_matches_schema_trail(): void {
		$cat  = self::factory()->category->create( array( 'name' => 'News' ) );
		$post = self::factory()->post->create( array( 'post_category' => array( $cat ) ) );

		$html   = $this->crumbs( get_permalink( $post ) );
		$schema = Plugin::instance()->module( 'schema' );
		$this->assertNotNull( $schema );
		$json = get_echo( array( $schema, 'print_graph' ) );
		preg_match( '#>(.*)</script>#s', $json, $m );
		$graph = json_decode( $m[1], true )['@graph'];
		$list  = array_values(
			array_filter(
				$graph,
				static function ( array $node ): bool {
					return 'BreadcrumbList' === $node['@type'];
				}
			)
		)[0];

		$this->assertSame( array_column( $this->items( $html ), 0 ), array_column( $list['itemListElement'], 'name' ), 'Visible trail and structured data agree.' );
	}
}
