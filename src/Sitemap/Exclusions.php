<?php
/**
 * Which content must stay out of (or be kept in) the sitemap.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Sitemap;

use DumpSEO\Helpers\Text;
use DumpSEO\Meta\Keys;
use DumpSEO\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Finds posts and terms that are noindex, explicitly "index", or canonicalised
 * to another URL, using the same rules as the robots meta tag.
 *
 * Queries return IDs only and run only on sitemap requests. Results are cached
 * for the rest of the request.
 */
class Exclusions {

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Per-request cache.
	 *
	 * @var array<string, int[]>
	 */
	private $cache = array();

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Whether a post type ("pt") or taxonomy ("tax") is set to noindex as a whole.
	 *
	 * @param string $kind "pt" or "tax".
	 * @param string $name Post type or taxonomy name.
	 */
	public function type_is_noindex( string $kind, string $name ): bool {
		return true === $this->settings->get( 'noindex_' . $kind . '_' . Keys::slug( $name ) );
	}

	/**
	 * Published post IDs of a type whose robots value contains a token.
	 *
	 * @param string $post_type Post type.
	 * @param string $token     "noindex" or "index".
	 * @return int[]
	 */
	public function posts_with_token( string $post_type, string $token ): array {
		$key = 'post:' . $post_type . ':' . $token;
		if ( ! isset( $this->cache[ $key ] ) ) {
			$this->cache[ $key ] = array_map(
				'intval',
				get_posts(
					array(
						'post_type'      => $post_type,
						'post_status'    => 'publish',
						'fields'         => 'ids',
						'posts_per_page' => -1,
						'no_found_rows'  => true,
						// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Sitemap requests only; returns IDs; only posts that have a DumpSEO robots value are scanned.
						'meta_query'     => array( $this->token_clause( $token ) ),
					)
				)
			);
		}
		return $this->cache[ $key ];
	}

	/**
	 * Published post IDs whose custom canonical points to a different URL.
	 *
	 * @param string $post_type Post type.
	 * @return int[]
	 */
	public function posts_canonicalised_elsewhere( string $post_type ): array {
		$key = 'post:' . $post_type . ':canonical';
		if ( isset( $this->cache[ $key ] ) ) {
			return $this->cache[ $key ];
		}

		$ids = get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Sitemap requests only; returns IDs of posts with a custom canonical.
				'meta_query'     => array( $this->non_empty_clause( Keys::CANONICAL ) ),
			)
		);
		update_meta_cache( 'post', $ids );

		$elsewhere = array();
		foreach ( $ids as $id ) {
			$canonical = (string) Text::http_url( get_post_meta( (int) $id, Keys::CANONICAL, true ) );
			$permalink = get_permalink( (int) $id );
			if ( '' !== $canonical && is_string( $permalink ) && ! $this->same_url( $canonical, $permalink ) ) {
				$elsewhere[] = (int) $id;
			}
		}
		$this->cache[ $key ] = $elsewhere;
		return $elsewhere;
	}

	/**
	 * Term IDs of a taxonomy whose robots value contains a token.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @param string $token    "noindex" or "index".
	 * @return int[]
	 */
	public function terms_with_token( string $taxonomy, string $token ): array {
		$key = 'term:' . $taxonomy . ':' . $token;
		if ( ! isset( $this->cache[ $key ] ) ) {
			$this->cache[ $key ] = $this->term_ids(
				$taxonomy,
				array( $this->token_clause( $token ) )
			);
		}
		return $this->cache[ $key ];
	}

	/**
	 * Term IDs whose custom canonical points to a different URL.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @return int[]
	 */
	public function terms_canonicalised_elsewhere( string $taxonomy ): array {
		$key = 'term:' . $taxonomy . ':canonical';
		if ( isset( $this->cache[ $key ] ) ) {
			return $this->cache[ $key ];
		}

		$elsewhere = array();
		foreach ( $this->term_ids( $taxonomy, array( $this->non_empty_clause( Keys::CANONICAL ) ) ) as $id ) {
			$canonical = (string) Text::http_url( get_term_meta( $id, Keys::CANONICAL, true ) );
			$link      = get_term_link( $id, $taxonomy );
			if ( '' !== $canonical && is_string( $link ) && ! $this->same_url( $canonical, $link ) ) {
				$elsewhere[] = $id;
			}
		}
		$this->cache[ $key ] = $elsewhere;
		return $elsewhere;
	}

	/**
	 * Term IDs matching a meta query.
	 *
	 * @param string                           $taxonomy   Taxonomy.
	 * @param array<int, array<string, mixed>> $meta_query Meta query clauses.
	 * @return int[]
	 */
	private function term_ids( string $taxonomy, array $meta_query ): array {
		$ids = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'fields'     => 'ids',
				'hide_empty' => false,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Sitemap requests only; returns term IDs.
				'meta_query' => $meta_query,
			)
		);
		return is_array( $ids ) ? array_map( 'intval', $ids ) : array();
	}

	/**
	 * Meta query clause: robots value contains a whole token.
	 *
	 * `noindex` is matched as a whole comma-separated token, so "index" never
	 * matches "noindex" and vice versa.
	 *
	 * @param string $token Token.
	 * @return array<string, string>
	 */
	private function token_clause( string $token ): array {
		return array(
			'key'     => Keys::ROBOTS,
			'value'   => '(^|,)' . preg_quote( $token, '/' ) . '(,|$)',
			'compare' => 'REGEXP',
		);
	}

	/**
	 * Meta query clause: key exists with a non-empty value.
	 *
	 * @param string $key Meta key.
	 * @return array<string, string>
	 */
	private function non_empty_clause( string $key ): array {
		return array(
			'key'     => $key,
			'value'   => '',
			'compare' => '!=',
		);
	}

	/**
	 * URL equality ignoring a trailing slash.
	 *
	 * @param string $a First URL.
	 * @param string $b Second URL.
	 */
	private function same_url( string $a, string $b ): bool {
		return untrailingslashit( $a ) === untrailingslashit( $b );
	}

	/**
	 * Clears the per-request cache. For tests.
	 *
	 * @internal
	 */
	public function reset(): void {
		$this->cache = array();
	}
}
