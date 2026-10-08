<?php
/**
 * Robots directives.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Meta;

use DumpSEO\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Decides DumpSEO' robots directives for a page.
 *
 * Sources, in order:
 * 1. Search results and 404 pages: always noindex.
 * 2. Page-type setting ("hide Posts from search engines" → noindex_pt_post).
 * 3. Per-post / per-term value (`_dumpseo_robots`): "index" or "noindex"
 *    overrides step 2; nofollow, noarchive, nosnippet and noimageindex add to it.
 *
 * DumpSEO only ever ADDS restrictions to what WordPress core decides. It
 * never removes core's own noindex (e.g. "Discourage search engines").
 */
class Robots {

	/**
	 * Tokens accepted in `_dumpseo_robots`.
	 */
	public const TOKENS = array( 'index', 'noindex', 'nofollow', 'noarchive', 'nosnippet', 'noimageindex' );

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Directives to add, keyed by directive name (value true), e.g. ['noindex' => true].
	 *
	 * @param PageContext $context Page context.
	 * @return array<string, true>
	 */
	public function directives( PageContext $context ): array {
		$directives = array();

		if ( in_array( $context->type, array( PageContext::SEARCH, PageContext::NOT_FOUND ), true ) ) {
			$directives['noindex'] = true;
		}

		$type_key = self::setting_key( $context );
		if ( null !== $type_key && true === $this->settings->get( $type_key ) ) {
			$directives['noindex'] = true;
		}

		$tokens = self::parse( $this->object_value( $context ) );
		if ( in_array( 'index', $tokens, true ) ) {
			unset( $directives['noindex'] );
		}
		foreach ( $tokens as $token ) {
			if ( 'index' !== $token ) {
				$directives[ $token ] = true;
			}
		}

		/**
		 * Filters the robots directives DumpSEO applies to a page (e.g. an
		 * integration hiding shop utility pages). Only allowlisted directives
		 * are kept. The canonical URL, structured data and social tags all
		 * follow the result, so they stay consistent.
		 *
		 * @param mixed       $directives Directive => true. Unknown directives and non-true values are dropped.
		 * @param PageContext $context    Page context.
		 */
		$filtered = apply_filters( 'dumpseo_robots_directives', $directives, $context );

		$clean = array();
		foreach ( (array) $filtered as $token => $on ) {
			if ( true === $on && in_array( $token, self::TOKENS, true ) && 'index' !== $token ) {
				$clean[ (string) $token ] = true;
			}
		}
		return $clean;
	}

	/**
	 * Whether DumpSEO marks the page noindex.
	 *
	 * @param PageContext $context Page context.
	 */
	public function is_noindex( PageContext $context ): bool {
		return isset( $this->directives( $context )['noindex'] );
	}

	/**
	 * Settings key of the page-type noindex toggle, or null when none applies.
	 *
	 * The homepage and posts page never get a type-level toggle: hiding them by
	 * accident would remove the whole site from search results.
	 *
	 * @param PageContext $context Page context.
	 */
	public static function setting_key( PageContext $context ): ?string {
		$object = $context->object;

		switch ( $context->type ) {
			case PageContext::SINGULAR:
				return $object instanceof \WP_Post ? 'noindex_pt_' . Keys::slug( $object->post_type ) : null;
			case PageContext::TERM:
				return $object instanceof \WP_Term ? 'noindex_tax_' . Keys::slug( $object->taxonomy ) : null;
			case PageContext::AUTHOR:
				return 'noindex_author';
			case PageContext::DATE:
				return 'noindex_date';
			default:
				return null;
		}
	}

	/**
	 * Normalises a stored or submitted robots value to a comma-separated allowlisted string.
	 *
	 * Order is fixed, duplicates removed, and "noindex" wins over "index".
	 *
	 * @param mixed $value Raw value (string or array of tokens).
	 */
	public static function sanitize( $value ): string {
		return implode( ',', self::parse( $value ) );
	}

	/**
	 * Parses a value into allowlisted tokens.
	 *
	 * @param mixed $value Raw value.
	 * @return string[]
	 */
	public static function parse( $value ): array {
		if ( is_string( $value ) ) {
			$value = explode( ',', $value );
		}
		if ( ! is_array( $value ) ) {
			return array();
		}

		$given = array();
		foreach ( $value as $token ) {
			if ( is_string( $token ) ) {
				$given[] = strtolower( trim( $token ) );
			}
		}

		$tokens = array_values( array_intersect( self::TOKENS, $given ) );
		if ( in_array( 'noindex', $tokens, true ) ) {
			$tokens = array_values( array_diff( $tokens, array( 'index' ) ) );
		}
		return $tokens;
	}

	/**
	 * Raw per-object robots value.
	 *
	 * @param PageContext $context Page context.
	 */
	private function object_value( PageContext $context ): string {
		$object = $context->object;
		if ( $object instanceof \WP_Post && in_array( $context->type, array( PageContext::SINGULAR, PageContext::FRONT, PageContext::BLOG ), true ) ) {
			return (string) get_post_meta( $object->ID, Keys::ROBOTS, true );
		}
		if ( $object instanceof \WP_Term && PageContext::TERM === $context->type ) {
			return (string) get_term_meta( $object->term_id, Keys::ROBOTS, true );
		}
		return '';
	}
}
