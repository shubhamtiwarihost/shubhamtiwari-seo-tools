<?php
/**
 * Breadcrumb trail for a page.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Breadcrumbs;

use DumpSEO\Helpers\Text;
use DumpSEO\Meta\PageContext;

defined( 'ABSPATH' ) || exit;

/**
 * The path from the homepage to the current page, as plain name/URL pairs.
 *
 * Home → (posts: first category and its parents | hierarchical types: parent
 * pages | other types: post type archive) → current page. Terms get their
 * parent terms. The last item carries the URL passed in (the canonical URL).
 *
 * Used by structured data (which skips pages without a canonical URL, such
 * as search and 404) and by visible breadcrumbs.
 */
class Trail {

	/**
	 * Trail items; an empty list when there is nothing between home and the page.
	 *
	 * @param PageContext $context Page context.
	 * @param string      $url     URL of the current page (canonical).
	 * @return array<int, array{name: string, url: string}>
	 */
	public function items( PageContext $context, string $url ): array {
		$items  = array( $this->item( __( 'Home', 'dumpseo' ), home_url( '/' ) ) );
		$object = $context->object;

		switch ( $context->type ) {
			case PageContext::SINGULAR:
				if ( $object instanceof \WP_Post ) {
					$items   = array_merge( $items, $this->post_parents( $object ) );
					$items[] = $this->item( $object->post_title, $url );
				}
				break;

			case PageContext::BLOG:
				if ( $object instanceof \WP_Post ) {
					$items[] = $this->item( $object->post_title, $url );
				}
				break;

			case PageContext::TERM:
				if ( $object instanceof \WP_Term ) {
					$items   = array_merge( $items, $this->term_parents( $object ) );
					$items[] = $this->item( $object->name, $url );
				}
				break;

			case PageContext::AUTHOR:
				if ( $object instanceof \WP_User ) {
					$items[] = $this->item( $object->display_name, $url );
				}
				break;

			case PageContext::PT_ARCHIVE:
				if ( $object instanceof \WP_Post_Type ) {
					$items[] = $this->item( $object->labels->name, $url );
				}
				break;

			case PageContext::DATE:
				$items[] = $this->item( $context->date_label, $url );
				break;

			case PageContext::SEARCH:
				/* translators: %s: search phrase. */
				$items[] = $this->item( sprintf( __( 'Search results for “%s”', 'dumpseo' ), $context->search ), $url );
				break;

			case PageContext::NOT_FOUND:
				$items[] = $this->item( __( 'Page not found', 'dumpseo' ), $url );
				break;
		}

		/**
		 * Filters the breadcrumb trail.
		 *
		 * @param array<int, mixed> $items   Items {name, url} from home to the current page. Malformed items are dropped.
		 * @param PageContext       $context Page context.
		 */
		$filtered = apply_filters( 'dumpseo_breadcrumb_trail', $items, $context );

		$clean = array();
		foreach ( (array) $filtered as $item ) {
			if ( is_array( $item ) && isset( $item['name'], $item['url'] ) && is_string( $item['name'] ) && '' !== Text::plain( $item['name'] ) ) {
				$clean[] = $this->item( $item['name'], (string) Text::http_url( $item['url'] ) );
			}
		}
		return count( $clean ) > 1 ? $clean : array();
	}

	/**
	 * Items between home and a post.
	 *
	 * @param \WP_Post $post Post.
	 * @return array<int, array{name: string, url: string}>
	 */
	private function post_parents( \WP_Post $post ): array {
		if ( is_post_type_hierarchical( $post->post_type ) ) {
			$items = array();
			foreach ( array_reverse( get_post_ancestors( $post ) ) as $ancestor_id ) {
				$ancestor = get_post( $ancestor_id );
				if ( $ancestor instanceof \WP_Post && 'publish' === $ancestor->post_status ) {
					$items[] = $this->item( $ancestor->post_title, (string) get_permalink( $ancestor ) );
				}
			}
			return $items;
		}

		if ( 'post' === $post->post_type ) {
			$categories = get_the_category( $post->ID );
			if ( ! $categories ) {
				return array();
			}
			usort(
				$categories,
				static function ( \WP_Term $a, \WP_Term $b ): int {
					return $a->term_id <=> $b->term_id;
				}
			);
			$primary = $categories[0];
			return array_merge( $this->term_parents( $primary ), array( $this->term_item( $primary ) ) );
		}

		$type = get_post_type_object( $post->post_type );
		if ( ! $type instanceof \WP_Post_Type || ! $type->has_archive ) {
			return array();
		}
		$link = get_post_type_archive_link( $post->post_type );
		return is_string( $link ) ? array( $this->item( $type->labels->name, $link ) ) : array();
	}

	/**
	 * Parent terms of a term, top first.
	 *
	 * @param \WP_Term $term Term.
	 * @return array<int, array{name: string, url: string}>
	 */
	private function term_parents( \WP_Term $term ): array {
		$items = array();
		foreach ( array_reverse( get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' ) ) as $ancestor_id ) {
			$ancestor = get_term( $ancestor_id, $term->taxonomy );
			if ( $ancestor instanceof \WP_Term ) {
				$items[] = $this->term_item( $ancestor );
			}
		}
		return $items;
	}

	/**
	 * Item for a term.
	 *
	 * @param \WP_Term $term Term.
	 * @return array{name: string, url: string}
	 */
	private function term_item( \WP_Term $term ): array {
		$link = get_term_link( $term );
		return $this->item( $term->name, is_string( $link ) ? $link : '' );
	}

	/**
	 * One item with a plain-text name.
	 *
	 * @param string $name Name.
	 * @param string $url  URL.
	 * @return array{name: string, url: string}
	 */
	private function item( string $name, string $url ): array {
		return array(
			'name' => Text::plain( $name ),
			'url'  => $url,
		);
	}
}
