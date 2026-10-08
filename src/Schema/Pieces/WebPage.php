<?php
/**
 * The page being viewed.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Schema\Pieces;

use DumpSEO\Meta\PageContext;
use DumpSEO\Schema\Piece;
use DumpSEO\Schema\SchemaContext;

defined( 'ABSPATH' ) || exit;

/**
 * WebPage node, typed by page kind: CollectionPage for archives, ProfilePage
 * for author archives, WebPage otherwise. Only on pages with a canonical URL.
 */
final class WebPage implements Piece {

	/**
	 * Needed on pages that have a canonical URL.
	 *
	 * @param SchemaContext $context Schema context.
	 */
	public function is_needed( SchemaContext $context ): bool {
		return '' !== $context->url;
	}

	/**
	 * WebPage node.
	 *
	 * @param SchemaContext $context Schema context.
	 * @return array<int, array<string, mixed>>
	 */
	public function generate( SchemaContext $context ): array {
		$node = array(
			/**
			 * Filters the schema.org type of the WebPage node (e.g. "ItemPage" for products).
			 *
			 * @param string        $type    WebPage, CollectionPage, ProfilePage or SearchResultsPage.
			 * @param SchemaContext $context Schema context.
			 */
			'@type'       => (string) apply_filters( 'dumpseo_schema_webpage_type', $this->type( $context->page ), $context ),
			'@id'         => $context->webpage_id(),
			'url'         => $context->url,
			'name'        => $context->title,
			'description' => $context->description,
			'isPartOf'    => SchemaContext::ref( $context->website_id() ),
			'inLanguage'  => $context->language,
		);

		if ( PageContext::FRONT === $context->page->type && '' !== $context->publisher_name ) {
			$node['about'] = SchemaContext::ref( $context->publisher_id() );
		}

		$post = $context->page->object;
		if ( $post instanceof \WP_Post && in_array( $context->page->type, array( PageContext::SINGULAR, PageContext::FRONT ), true ) ) {
			$node['datePublished'] = self::date( $post->post_date_gmt );
			$node['dateModified']  = self::date( $post->post_modified_gmt );
		}

		if ( null !== $context->image ) {
			$node['primaryImageOfPage'] = SchemaContext::ref( $context->image_id() );
			$node['image']              = SchemaContext::ref( $context->image_id() );
		}
		if ( array() !== $context->trail ) {
			$node['breadcrumb'] = SchemaContext::ref( $context->breadcrumb_id() );
		}

		return array( $node );
	}

	/**
	 * The schema.org type for a page kind.
	 *
	 * @param PageContext $page Page context.
	 */
	private function type( PageContext $page ): string {
		switch ( $page->type ) {
			case PageContext::AUTHOR:
				return 'ProfilePage';
			case PageContext::BLOG:
			case PageContext::TERM:
			case PageContext::PT_ARCHIVE:
			case PageContext::DATE:
				return 'CollectionPage';
			case PageContext::SEARCH:
				return 'SearchResultsPage';
			default:
				return 'WebPage';
		}
	}

	/**
	 * GMT MySQL date to ISO 8601, or '' for empty dates.
	 *
	 * @param string $gmt GMT date.
	 */
	public static function date( string $gmt ): string {
		return '0000-00-00 00:00:00' === $gmt ? '' : (string) gmdate( DATE_W3C, (int) strtotime( $gmt . ' UTC' ) );
	}
}
