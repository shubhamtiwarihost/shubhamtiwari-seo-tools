<?php
/**
 * WooCommerce integration, against the real WooCommerce plugin.
 *
 * Run with `npm run test:php:woo`; skipped in the regular runs (WooCommerce not loaded).
 *
 * @package DumpSEO
 */

namespace DumpSEO\Tests\Integration;

use DumpSEO\Frontend\CurrentPage;
use DumpSEO\Plugin;
use DumpSEO\Settings\Settings;
use DumpSEO\Sitemap\SitemapModule;
use DumpSEO\WooCommerce\WooModule;
use WP_UnitTestCase;

/**
 * @group woocommerce
 * @covers \DumpSEO\WooCommerce\WooModule
 */
final class WooCommerceTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is not loaded. Run: npm run test:php:woo' );
		}
		update_option( 'blogname', 'Acme' );
		update_option( 'blog_public', '1' );
		update_option( Settings::OPTION, array() );
		$this->set_permalink_structure( '/%postname%/' );
		// The test suite deletes all posts before tests run, so recreate WooCommerce's shop/cart/checkout/account pages.
		\WC_Install::create_pages();
		flush_rewrite_rules();
	}

	/**
	 * Visits a URL and resets per-request caches.
	 *
	 * @param string $url URL.
	 */
	private function visit( string $url ): void {
		$this->go_to( $url );
		$page = Plugin::instance()->container()->get( CurrentPage::class );
		$this->assertInstanceOf( CurrentPage::class, $page );
		$page->reset();
	}

	/**
	 * Creates a simple product in a nested category.
	 *
	 * @return array{0: int, 1: int, 2: int} Product ID, parent category ID, child category ID.
	 */
	private function product(): array {
		$parent  = self::factory()->term->create(
			array(
				'taxonomy' => 'product_cat',
				'name'     => 'Footwear',
			)
		);
		$child   = self::factory()->term->create(
			array(
				'taxonomy' => 'product_cat',
				'name'     => 'Boots',
				'parent'   => $parent,
			)
		);
		$product = new \WC_Product_Simple();
		$product->set_name( 'Trail boot' );
		$product->set_status( 'publish' );
		$product->set_regular_price( '19.5' );
		$product->set_stock_status( 'instock' );
		$product->set_category_ids( array( $child ) );
		return array( (int) $product->save(), $parent, $child );
	}

	public function test_module_loads_only_with_woocommerce(): void {
		$this->assertInstanceOf( WooModule::class, Plugin::instance()->module( 'woocommerce' ) );
	}

	public function test_cart_checkout_and_account_are_hidden(): void {
		$woo = new WooModule();
		$ids = $woo->private_pages();
		$this->assertCount( 3, $ids, 'WooCommerce created its cart, checkout and account pages.' );

		$this->visit( get_permalink( (int) wc_get_page_id( 'cart' ) ) );
		$data = Plugin::instance()->container()->get( CurrentPage::class )->data();
		$this->assertArrayHasKey( 'noindex', $data['robots'] );
		$this->assertSame( '', $data['canonical'], 'No canonical on noindex pages.' );

		$sitemap = Plugin::instance()->module( 'sitemap' );
		$this->assertInstanceOf( SitemapModule::class, $sitemap );
		$args = $sitemap->filter_posts_query( array(), 'page' );
		foreach ( $ids as $id ) {
			$this->assertContains( $id, $args['post__not_in'] );
		}
		$this->assertArrayNotHasKey( 'post__not_in', $sitemap->filter_posts_query( array(), 'product' ) );
	}

	public function test_product_social_tags(): void {
		list( $product ) = $this->product();
		$this->visit( get_permalink( $product ) );

		$html = get_echo( array( Plugin::instance()->module( 'social' ), 'print_tags' ) );
		$this->assertStringContainsString( '<meta property="og:type" content="product" />', $html );
		$this->assertStringContainsString( '<meta property="product:price:amount" content="19.50" />', $html );
		$this->assertStringContainsString( '<meta property="product:price:currency" content="' . get_woocommerce_currency() . '" />', $html );
		$this->assertStringContainsString( '<meta property="product:availability" content="in stock" />', $html );
		$this->assertStringNotContainsString( 'article:published_time', $html );
	}

	public function test_product_schema_leaves_product_markup_to_woocommerce(): void {
		list( $product ) = $this->product();
		$this->visit( get_permalink( $product ) );

		$json = get_echo( array( Plugin::instance()->module( 'schema' ), 'print_graph' ) );
		preg_match( '#>(.*)</script>#s', $json, $m );
		$types = array_column( json_decode( $m[1], true )['@graph'], '@type' );

		$this->assertContains( 'ItemPage', $types );
		$this->assertNotContains( 'Article', $types );
		$this->assertNotContains( 'BlogPosting', $types );
		$this->assertNotContains( 'Product', $types, 'WooCommerce prints Product data itself.' );

		$woo = Plugin::instance()->module( 'woocommerce' );
		$this->assertInstanceOf( WooModule::class, $woo );
		$crumbs = array(
			'@type'           => 'BreadcrumbList',
			'itemListElement' => array(),
		);
		$this->assertSame( array(), $woo->drop_duplicate_schema( $crumbs ), 'Duplicate of the DumpSEO graph.' );

		add_filter( 'dumpseo_schema_output_enabled', '__return_false' );
		$this->assertSame( $crumbs, $woo->drop_duplicate_schema( $crumbs ), 'Kept when DumpSEO prints no graph.' );
		remove_filter( 'dumpseo_schema_output_enabled', '__return_false' );
	}

	public function test_breadcrumbs(): void {
		list( $product, $parent, $child ) = $this->product();
		$shop                             = (int) wc_get_page_id( 'shop' );
		$names                            = static function ( string $html ): array {
			preg_match_all( '#<li class="dumpseo-breadcrumbs__item">(?:<a href="[^"]*">|<span[^>]*>)([^<]*)<#', $html, $m );
			return array_map( 'html_entity_decode', $m[1] );
		};

		$this->visit( get_permalink( $product ) );
		$this->assertSame( array( 'Home', get_the_title( $shop ), 'Footwear', 'Boots', 'Trail boot' ), $names( dumpseo_get_breadcrumbs() ) );

		$this->visit( get_term_link( $child ) );
		$this->assertSame( array( 'Home', get_the_title( $shop ), 'Footwear', 'Boots' ), $names( dumpseo_get_breadcrumbs() ) );

		$this->visit( get_permalink( $shop ) );
		$this->assertSame( array( 'Home', get_the_title( $shop ) ), $names( dumpseo_get_breadcrumbs() ) );
		unset( $parent );
	}
}
