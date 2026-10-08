<?php
/**
 * Canonical URLs.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Meta;

use DumpSEO\Helpers\Text;

defined( 'ABSPATH' ) || exit;

/**
 * Computes the canonical URL of a page.
 *
 * - Built from WordPress's own permalink functions, never from the request
 *   URL, so tracking parameters (?utm_source=…) never leak into it.
 * - Paginated archives are canonical to themselves (/page/2/), not to page 1.
 * - A per-post / per-term custom canonical (absolute http/https) wins.
 * - Search results, 404s and unknown page types get no canonical.
 *
 * Whether to print it at all (e.g. not on noindex pages) is the caller's decision.
 */
class Canonical {

	/**
	 * Canonical URL, or '' when there is none.
	 *
	 * @param PageContext $context Page context.
	 */
	public function url( PageContext $context ): string {
		$custom = $this->custom( $context );
		if ( '' !== $custom ) {
			return $custom;
		}

		$object = $context->object;

		switch ( $context->type ) {
			case PageContext::SINGULAR:
			case PageContext::FRONT:
				if ( $object instanceof \WP_Post ) {
					// Core handles <!--nextpage--> pages and returns false for unpublished posts.
					return (string) wp_get_canonical_url( $object );
				}
				return $this->paginate( home_url( '/' ), $context->page );

			case PageContext::BLOG:
				return $this->paginate( $object instanceof \WP_Post ? get_permalink( $object ) : home_url( '/' ), $context->page );

			case PageContext::TERM:
				$url = $object instanceof \WP_Term ? get_term_link( $object ) : '';
				return is_string( $url ) ? $this->paginate( $url, $context->page ) : '';

			case PageContext::PT_ARCHIVE:
				$url = $object instanceof \WP_Post_Type ? get_post_type_archive_link( $object->name ) : '';
				return is_string( $url ) ? $this->paginate( $url, $context->page ) : '';

			case PageContext::AUTHOR:
				return $object instanceof \WP_User ? $this->paginate( get_author_posts_url( $object->ID, $object->user_nicename ), $context->page ) : '';

			case PageContext::DATE:
				return $this->paginate( $this->date_url(), $context->page );

			default:
				return '';
		}
	}

	/**
	 * Adds a page number to an archive URL, respecting the permalink structure.
	 *
	 * @param string $url  Archive URL (page 1).
	 * @param int    $page Page number.
	 */
	public function paginate( string $url, int $page ): string {
		if ( '' === $url || $page < 2 ) {
			return $url;
		}

		global $wp_rewrite;
		if ( $wp_rewrite instanceof \WP_Rewrite && $wp_rewrite->using_permalinks() && false === strpos( $url, '?' ) ) {
			return user_trailingslashit( trailingslashit( $url ) . $wp_rewrite->pagination_base . '/' . $page, 'paged' );
		}
		return add_query_arg( 'paged', $page, $url );
	}

	/**
	 * URL of a date archive, from the date parts in the main query.
	 */
	private function date_url(): string {
		$year  = (int) get_query_var( 'year' );
		$month = (int) get_query_var( 'monthnum' );
		$day   = (int) get_query_var( 'day' );

		if ( $year && $month && $day ) {
			return (string) get_day_link( $year, $month, $day );
		}
		if ( $year && $month ) {
			return (string) get_month_link( $year, $month );
		}
		return $year ? (string) get_year_link( $year ) : '';
	}

	/**
	 * Custom canonical saved on the post or term, or ''.
	 *
	 * @param PageContext $context Page context.
	 */
	private function custom( PageContext $context ): string {
		$object = $context->object;
		$value  = '';

		if ( $object instanceof \WP_Post && in_array( $context->type, array( PageContext::SINGULAR, PageContext::FRONT, PageContext::BLOG ), true ) ) {
			$value = (string) get_post_meta( $object->ID, Keys::CANONICAL, true );
		} elseif ( $object instanceof \WP_Term && PageContext::TERM === $context->type ) {
			$value = (string) get_term_meta( $object->term_id, Keys::CANONICAL, true );
		}

		// Stored values are sanitized on save; re-validate in case of direct database edits.
		return '' === $value ? '' : (string) Text::http_url( $value );
	}
}
