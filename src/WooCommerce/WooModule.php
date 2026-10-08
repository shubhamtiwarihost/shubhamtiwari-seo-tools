<?php
/**
 * WooCommerce integration.
 *
 * @package DumpSEO
 */

namespace DumpSEO\WooCommerce;

use DumpSEO\Helpers\Text;
use DumpSEO\Meta\PageContext;
use DumpSEO\Module;
use DumpSEO\Schema\SchemaContext;
use DumpSEO\Schema\SchemaModule;

defined( 'ABSPATH' ) || exit;

/**
 * Loaded only when WooCommerce is active. Works purely through DumpSEO'
 * own filters; it never changes WooCommerce data or output.
 *
 * - Cart, checkout and account pages: noindex and out of the sitemap.
 * - Products: og:type "product" with price and availability; no Article
 *   schema (WooCommerce prints its own Product structured data), WebPage
 *   type "ItemPage".
 * - Breadcrumbs: Home › Shop › product categories › product.
 * - While DumpSEO prints structured data, WooCommerce's own BreadcrumbList
 *   and WebSite blocks (duplicates of nodes in DumpSEO' graph) are turned
 *   off through WooCommerce's filters; its Product data is kept.
 */
final class WooModule implements Module {

	/**
	 * Structured data module (whether DumpSEO prints a graph on this request).
	 *
	 * @var SchemaModule|null
	 */
	private $schema;

	/**
	 * Constructor.
	 *
	 * @param SchemaModule|null $schema Structured data module.
	 */
	public function __construct( ?SchemaModule $schema = null ) {
		$this->schema = $schema;
	}

	/**
	 * Only when WooCommerce is active (checked after all plugins loaded).
	 */
	public function should_load(): bool {
		return class_exists( 'WooCommerce' ) && function_exists( 'wc_get_page_id' );
	}

	/**
	 * Registers hooks.
	 */
	public function register(): void {
		add_filter( 'dumpseo_robots_directives', array( $this, 'robots' ), 10, 2 );
		add_filter( 'dumpseo_sitemap_excluded_posts', array( $this, 'sitemap_exclusions' ), 10, 2 );
		add_filter( 'dumpseo_og_is_article', array( $this, 'og_is_article' ), 10, 2 );
		add_filter( 'dumpseo_social_tags', array( $this, 'social_tags' ), 10, 2 );
		add_filter( 'dumpseo_schema_article_type', array( $this, 'article_type' ), 10, 2 );
		add_filter( 'dumpseo_schema_webpage_type', array( $this, 'webpage_type' ), 10, 2 );
		add_filter( 'dumpseo_breadcrumb_trail', array( $this, 'breadcrumbs' ), 10, 2 );
		add_filter( 'woocommerce_structured_data_breadcrumblist', array( $this, 'drop_duplicate_schema' ) );
		add_filter( 'woocommerce_structured_data_website', array( $this, 'drop_duplicate_schema' ) );
	}

	/**
	 * Drops a WooCommerce structured-data block that DumpSEO' graph already
	 * contains (an empty array makes WooCommerce skip it).
	 *
	 * @param mixed $markup WooCommerce's markup.
	 * @return mixed
	 */
	public function drop_duplicate_schema( $markup ) {
		return null !== $this->schema && $this->schema->active() ? array() : $markup;
	}

	/**
	 * IDs of the cart, checkout and account pages.
	 *
	 * @return int[]
	 */
	public function private_pages(): array {
		$ids = array();
		foreach ( array( 'cart', 'checkout', 'myaccount' ) as $page ) {
			$id = (int) wc_get_page_id( $page );
			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}
		return $ids;
	}

	/**
	 * Noindex for cart, checkout and account pages.
	 *
	 * @param mixed $directives Directives.
	 * @param mixed $context    Page context.
	 * @return mixed
	 */
	public function robots( $directives, $context ) {
		if ( is_array( $directives ) && $context instanceof PageContext && $context->object instanceof \WP_Post
			&& in_array( $context->object->ID, $this->private_pages(), true ) ) {
			$directives['noindex'] = true;
		}
		return $directives;
	}

	/**
	 * Leaves cart, checkout and account pages out of the sitemap.
	 *
	 * @param mixed $ids       Excluded IDs.
	 * @param mixed $post_type Post type.
	 * @return mixed
	 */
	public function sitemap_exclusions( $ids, $post_type ) {
		return 'page' === $post_type ? array_merge( (array) $ids, $this->private_pages() ) : $ids;
	}

	/**
	 * Products are not articles.
	 *
	 * @param mixed $article Decision.
	 * @param mixed $context Page context.
	 * @return mixed
	 */
	public function og_is_article( $article, $context ) {
		return $this->product_of( $context ) ? false : $article;
	}

	/**
	 * Sets og:type "product" and adds price and availability.
	 *
	 * @param mixed $tags    Tags.
	 * @param mixed $context Page context.
	 * @return mixed
	 */
	public function social_tags( $tags, $context ) {
		$product = $this->product_of( $context );
		if ( null === $product || ! is_array( $tags ) ) {
			return $tags;
		}

		$has_og = false;
		foreach ( $tags as $i => $tag ) {
			if ( is_array( $tag ) && 'og:type' === ( $tag['key'] ?? '' ) ) {
				$tags[ $i ]['value'] = 'product';
				$has_og              = true;
			}
		}
		if ( ! $has_og ) {
			return $tags;
		}

		$price = (string) $product->get_price();
		if ( '' !== $price && is_numeric( $price ) ) {
			$tags[] = $this->tag( 'product:price:amount', wc_format_decimal( $price, wc_get_price_decimals() ) );
			$tags[] = $this->tag( 'product:price:currency', (string) get_woocommerce_currency() );
		}
		$availability = array(
			'instock'     => 'in stock',
			'outofstock'  => 'out of stock',
			'onbackorder' => 'available for order',
		);
		$status       = (string) $product->get_stock_status();
		if ( isset( $availability[ $status ] ) ) {
			$tags[] = $this->tag( 'product:availability', $availability[ $status ] );
		}
		return $tags;
	}

	/**
	 * No Article node for products (WooCommerce prints Product structured data itself).
	 *
	 * @param mixed $type Article type.
	 * @param mixed $post Post.
	 * @return mixed
	 */
	public function article_type( $type, $post ) {
		return $post instanceof \WP_Post && 'product' === $post->post_type ? '' : $type;
	}

	/**
	 * "ItemPage" for single products.
	 *
	 * @param mixed $type    WebPage type.
	 * @param mixed $context Schema context.
	 * @return mixed
	 */
	public function webpage_type( $type, $context ) {
		return $context instanceof SchemaContext && null !== $this->product_of( $context->page ) ? 'ItemPage' : $type;
	}

	/**
	 * Home › Shop › categories › product; Home › Shop › category; Home › Shop.
	 *
	 * @param mixed $items   Trail items.
	 * @param mixed $context Page context.
	 * @return mixed
	 */
	public function breadcrumbs( $items, $context ) {
		if ( ! is_array( $items ) || array() === $items || ! $context instanceof PageContext ) {
			return $items;
		}
		$shop   = $this->shop_item();
		$object = $context->object;

		if ( PageContext::SINGULAR === $context->type && $object instanceof \WP_Post && 'product' === $object->post_type ) {
			$last  = end( $items );
			$trail = array( $items[0] );
			if ( null !== $shop ) {
				$trail[] = $shop;
			}
			$terms = get_the_terms( $object, 'product_cat' );
			if ( is_array( $terms ) && array() !== $terms ) {
				usort(
					$terms,
					static function ( \WP_Term $a, \WP_Term $b ): int {
						return $a->term_id <=> $b->term_id;
					}
				);
				$trail = array_merge( $trail, $this->term_chain( $terms[0] ) );
			}
			$trail[] = $last;
			return $trail;
		}

		if ( PageContext::TERM === $context->type && $object instanceof \WP_Term && in_array( $object->taxonomy, array( 'product_cat', 'product_tag' ), true ) && null !== $shop ) {
			array_splice( $items, 1, 0, array( $shop ) );
			return $items;
		}

		if ( PageContext::PT_ARCHIVE === $context->type && $object instanceof \WP_Post_Type && 'product' === $object->name && null !== $shop && count( $items ) > 1 ) {
			$items[ count( $items ) - 1 ]['name'] = $shop['name'];
		}
		return $items;
	}

	/**
	 * The product shown on a page, or null.
	 *
	 * @param mixed $context Page context.
	 * @return \WC_Product|null
	 */
	private function product_of( $context ) {
		if ( ! $context instanceof PageContext || PageContext::SINGULAR !== $context->type || ! $context->object instanceof \WP_Post || 'product' !== $context->object->post_type ) {
			return null;
		}
		$product = wc_get_product( $context->object );
		return $product instanceof \WC_Product ? $product : null;
	}

	/**
	 * Breadcrumb item for the shop page, or null when there is none.
	 *
	 * @return array{name: string, url: string}|null
	 */
	private function shop_item(): ?array {
		$id = (int) wc_get_page_id( 'shop' );
		if ( $id <= 0 || 'publish' !== get_post_status( $id ) ) {
			return null;
		}
		return array(
			'name' => Text::plain( get_the_title( $id ) ),
			'url'  => (string) get_permalink( $id ),
		);
	}

	/**
	 * A product category and its parents, top first.
	 *
	 * @param \WP_Term $term Category.
	 * @return array<int, array{name: string, url: string}>
	 */
	private function term_chain( \WP_Term $term ): array {
		$chain = array();
		foreach ( array_merge( array_reverse( get_ancestors( $term->term_id, 'product_cat', 'taxonomy' ) ), array( $term->term_id ) ) as $id ) {
			$item = get_term( (int) $id, 'product_cat' );
			$link = $item instanceof \WP_Term ? get_term_link( $item ) : '';
			if ( $item instanceof \WP_Term && is_string( $link ) ) {
				$chain[] = array(
					'name' => Text::plain( $item->name ),
					'url'  => $link,
				);
			}
		}
		return $chain;
	}

	/**
	 * A property tag.
	 *
	 * @param string $key   Property.
	 * @param string $value Value.
	 * @return array{attr: string, key: string, value: string, url: bool}
	 */
	private function tag( string $key, string $value ): array {
		return array(
			'attr'  => 'property',
			'key'   => $key,
			'value' => $value,
			'url'   => false,
		);
	}
}
