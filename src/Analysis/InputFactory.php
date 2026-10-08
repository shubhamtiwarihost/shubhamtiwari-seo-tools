<?php
/**
 * Builds analysis input for a post.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Analysis;

use DumpSEO\Meta\Keys;
use DumpSEO\Meta\PageContext;
use DumpSEO\Meta\Resolver;
use DumpSEO\Meta\Robots;

defined( 'ABSPATH' ) || exit;

/**
 * Turns a post, plus unsaved values from the editor, into an Input: renders
 * the SEO title and description exactly as the frontend would, and looks up
 * other posts that use the same focus keyphrase.
 */
class InputFactory {

	/**
	 * Editor values that may override what is saved.
	 */
	public const OVERRIDES = array( 'keyphrase', 'title', 'excerpt', 'content', 'slug', 'seo_title', 'seo_description' );

	/**
	 * Title/description resolver.
	 *
	 * @var Resolver
	 */
	private $resolver;

	/**
	 * Robots directives.
	 *
	 * @var Robots
	 */
	private $robots;

	/**
	 * Constructor.
	 *
	 * @param Resolver $resolver Title/description resolver.
	 * @param Robots   $robots   Robots directives.
	 */
	public function __construct( Resolver $resolver, Robots $robots ) {
		$this->resolver = $resolver;
		$this->robots   = $robots;
	}

	/**
	 * Input for a post.
	 *
	 * @param \WP_Post              $post      Saved post.
	 * @param array<string, string> $overrides Unsaved editor values keyed by OVERRIDES names.
	 */
	public function for_post( \WP_Post $post, array $overrides = array() ): Input {
		$fields = array(
			'title'   => 'post_title',
			'excerpt' => 'post_excerpt',
			'content' => 'post_content',
			'slug'    => 'post_name',
		);
		$data   = $post->to_array();
		foreach ( $fields as $name => $property ) {
			if ( isset( $overrides[ $name ] ) ) {
				$data[ $property ] = $overrides[ $name ];
			}
		}
		$draft = new \WP_Post( (object) $data );

		$is_front = 'page' === get_option( 'show_on_front' ) && (int) get_option( 'page_on_front' ) === $post->ID;
		$context  = new PageContext( $is_front ? PageContext::FRONT : PageContext::SINGULAR, $draft );

		$seo_title       = $overrides['seo_title'] ?? (string) get_post_meta( $post->ID, Keys::TITLE, true );
		$seo_description = $overrides['seo_description'] ?? (string) get_post_meta( $post->ID, Keys::DESCRIPTION, true );
		$keyphrase       = trim( $overrides['keyphrase'] ?? (string) get_post_meta( $post->ID, Keys::FOCUS_KEYPHRASE, true ) );

		$title = $this->resolver->resolve_custom( 'title', $seo_title, $context );

		return new Input(
			array(
				'keyphrase'         => $keyphrase,
				// With no template, WordPress prints the post title; analyse that.
				'title'             => '' !== $title ? $title : wp_strip_all_tags( $draft->post_title ),
				'description'       => $this->resolver->resolve_custom( 'desc', $seo_description, $context ),
				'slug'              => $draft->post_name,
				'content'           => $draft->post_content,
				'host'              => (string) wp_parse_url( home_url(), PHP_URL_HOST ),
				'post_type'         => $post->post_type,
				'noindex'           => $this->robots->is_noindex( $context ) || '0' === (string) get_option( 'blog_public' ),
				'keyphrase_used_by' => $this->used_by( $keyphrase, $post->ID ),
				/**
				 * Filters the language of a post's content (locale, e.g. "en_US"), for multilingual plugins.
				 *
				 * @param string   $locale Site locale.
				 * @param \WP_Post $post   Post.
				 */
				'language'          => (string) apply_filters( 'dumpseo_content_locale', get_locale(), $post ),
			)
		);
	}

	/**
	 * IDs of up to 5 other published posts with the same focus keyphrase (case-insensitive).
	 *
	 * @param string $keyphrase Keyphrase.
	 * @param int    $post_id   Post to leave out.
	 * @return int[]
	 */
	private function used_by( string $keyphrase, int $post_id ): array {
		if ( '' === $keyphrase ) {
			return array();
		}
		$query = new \WP_Query(
			array(
				'post_type'              => 'any',
				'post_status'            => 'publish',
				'posts_per_page'         => 6, // One extra, in case the post itself is among them.
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Editor-only request, limited to 6 IDs.
				'meta_query'             => array(
					array(
						'key'   => Keys::FOCUS_KEYPHRASE,
						'value' => $keyphrase,
					),
				),
			)
		);
		$ids   = array_values( array_diff( array_map( 'intval', $query->posts ), array( $post_id ) ) );
		return array_slice( $ids, 0, 5 );
	}
}
