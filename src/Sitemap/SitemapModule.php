<?php
/**
 * XML sitemap integration.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Sitemap;

use DumpSEO\Module;
use DumpSEO\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Extends WordPress core sitemaps (/wp-sitemap.xml) instead of replacing them.
 *
 * - Honours DumpSEO indexing rules: noindex post types, taxonomies, posts and
 *   terms are left out; items explicitly set to "index" are kept even when
 *   their type is noindex; items whose canonical points elsewhere are left out.
 * - Leaves out password-protected posts (core lists them).
 * - Adds lastmod where core does not (WordPress < 6.5) and image entries.
 * - Settings: sitemap on/off, images on/off, author sitemap on/off.
 *
 * Core still decides everything else (pagination, 2,000 URLs per page,
 * robots.txt "Sitemap:" line, and no sitemap at all when the site
 * discourages search engines).
 */
final class SitemapModule implements Module {

	/**
	 * Query var marking plugin-adjusted sitemap queries, so caches are primed only for them.
	 */
	private const QUERY_FLAG = 'dumpseo_sitemap';

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Exclusion rules.
	 *
	 * @var Exclusions
	 */
	private $exclusions;

	/**
	 * Image collector.
	 *
	 * @var Images
	 */
	private $images;

	/**
	 * Constructor.
	 *
	 * @param Settings   $settings   Settings.
	 * @param Exclusions $exclusions Exclusion rules.
	 * @param Images     $images     Image collector.
	 */
	public function __construct( Settings $settings, Exclusions $exclusions, Images $images ) {
		$this->settings   = $settings;
		$this->exclusions = $exclusions;
		$this->images     = $images;
	}

	/**
	 * Needed on every request: sitemap filters (frontend) and the settings help (admin). Hooks only.
	 */
	public function should_load(): bool {
		return true;
	}

	/**
	 * Registers hooks.
	 */
	public function register(): void {
		add_filter( 'wp_sitemaps_enabled', array( $this, 'enabled' ) );
		add_action( 'wp_sitemaps_init', array( $this, 'use_image_renderer' ) );
		add_filter( 'wp_sitemaps_add_provider', array( $this, 'filter_provider' ), 10, 2 );
		add_filter( 'wp_sitemaps_post_types', array( $this, 'filter_post_types' ) );
		add_filter( 'wp_sitemaps_posts_query_args', array( $this, 'filter_posts_query' ), 10, 2 );
		add_filter( 'the_posts', array( $this, 'prime_images' ), 10, 2 );
		add_filter( 'wp_sitemaps_posts_entry', array( $this, 'filter_post_entry' ), 10, 2 );
		add_filter( 'wp_sitemaps_taxonomies', array( $this, 'filter_taxonomies' ) );
		add_filter( 'wp_sitemaps_taxonomies_query_args', array( $this, 'filter_terms_query' ), 10, 2 );
		add_action( 'dumpseo_settings_section_sitemap', array( $this, 'render_help' ) );
	}

	/**
	 * Turns core sitemaps off when the owner disabled them. Never turns them on.
	 *
	 * @param mixed $enabled Core's decision (false when the site discourages search engines).
	 */
	public function enabled( $enabled ): bool {
		return (bool) $enabled && false !== $this->settings->get( 'sitemap_enabled' );
	}

	/**
	 * Swaps in the image-aware renderer.
	 *
	 * @param mixed $sitemaps Core sitemaps server.
	 */
	public function use_image_renderer( $sitemaps ): void {
		if ( $sitemaps instanceof \WP_Sitemaps && true === $this->settings->get( 'sitemap_images' ) ) {
			$sitemaps->renderer = new ImageRenderer();
		}
	}

	/**
	 * Drops the users (author) sitemap when author archives are hidden or the owner turned it off.
	 *
	 * @param mixed  $provider Provider instance.
	 * @param string $name     Provider name.
	 * @return mixed
	 */
	public function filter_provider( $provider, $name ) {
		if ( 'users' === $name && ( false === $this->settings->get( 'sitemap_users' ) || true === $this->settings->get( 'noindex_author' ) ) ) {
			return false;
		}
		return $provider;
	}

	/**
	 * Removes noindex post types, unless some of their posts are explicitly "index".
	 *
	 * @param mixed $post_types Post type objects keyed by name.
	 * @return mixed
	 */
	public function filter_post_types( $post_types ) {
		if ( ! is_array( $post_types ) ) {
			return $post_types;
		}
		foreach ( array_keys( $post_types ) as $name ) {
			if ( $this->exclusions->type_is_noindex( 'pt', (string) $name ) && array() === $this->exclusions->posts_with_token( (string) $name, 'index' ) ) {
				unset( $post_types[ $name ] );
			}
		}
		return $post_types;
	}

	/**
	 * Applies per-post exclusions to core's sitemap query (also used for page counts).
	 *
	 * @param mixed  $args      WP_Query arguments.
	 * @param string $post_type Post type.
	 * @return mixed
	 */
	public function filter_posts_query( $args, $post_type ) {
		if ( ! is_array( $args ) ) {
			return $args;
		}
		$post_type = (string) $post_type;

		/**
		 * Filters extra post IDs to leave out of a post type's sitemap (e.g. shop cart and checkout pages).
		 *
		 * @param mixed  $ids       Post IDs. Non-numeric entries are ignored.
		 * @param string $post_type Post type.
		 */
		$extra                    = array_filter( array_map( 'intval', (array) apply_filters( 'dumpseo_sitemap_excluded_posts', array(), $post_type ) ) );
		$elsewhere                = array_merge( $this->exclusions->posts_canonicalised_elsewhere( $post_type ), $extra );
		$args[ self::QUERY_FLAG ] = true;

		// Core lists password-protected posts; their content is hidden, so leave them out.
		$args['has_password'] = false;

		if ( $this->exclusions->type_is_noindex( 'pt', $post_type ) ) {
			$included         = array_diff( $this->exclusions->posts_with_token( $post_type, 'index' ), $elsewhere );
			$args['post__in'] = array() === $included ? array( 0 ) : array_values( $included );
			return $args;
		}

		$excluded = array_merge( $this->exclusions->posts_with_token( $post_type, 'noindex' ), $elsewhere );
		if ( array() !== $excluded ) {
			$existing = isset( $args['post__not_in'] ) ? (array) $args['post__not_in'] : array();
			// phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- Short list (noindex/canonicalised-away items only); cheaper than a NOT EXISTS meta query on every sitemap page.
			$args['post__not_in'] = array_values( array_unique( array_merge( $existing, $excluded ) ) );
		}
		return $args;
	}

	/**
	 * Primes featured-image caches for a sitemap page in bulk (core disables meta priming).
	 *
	 * @param mixed $posts Posts from WP_Query.
	 * @param mixed $query The query.
	 * @return mixed
	 */
	public function prime_images( $posts, $query ) {
		if ( is_array( $posts ) && $query instanceof \WP_Query && $query->get( self::QUERY_FLAG ) && true === $this->settings->get( 'sitemap_images' ) ) {
			$this->images->prime( $posts );
		}
		return $posts;
	}

	/**
	 * Adds lastmod (if core did not) and images to a post entry.
	 *
	 * @param mixed $entry Entry: ['loc' => …, 'lastmod' => …].
	 * @param mixed $post  Post.
	 * @return mixed
	 */
	public function filter_post_entry( $entry, $post ) {
		if ( ! is_array( $entry ) || ! $post instanceof \WP_Post ) {
			return $entry;
		}

		if ( ! isset( $entry['lastmod'] ) && '0000-00-00 00:00:00' !== $post->post_modified_gmt ) {
			$entry['lastmod'] = (string) wp_date( DATE_W3C, (int) strtotime( $post->post_modified_gmt ) );
		}

		if ( true === $this->settings->get( 'sitemap_images' ) ) {
			$images = $this->images->for_post( $post );
			if ( array() !== $images ) {
				$entry['images'] = $images;
			}
		}
		return $entry;
	}

	/**
	 * Removes noindex taxonomies, unless some of their terms are explicitly "index".
	 *
	 * @param mixed $taxonomies Taxonomy objects keyed by name.
	 * @return mixed
	 */
	public function filter_taxonomies( $taxonomies ) {
		if ( ! is_array( $taxonomies ) ) {
			return $taxonomies;
		}
		foreach ( array_keys( $taxonomies ) as $name ) {
			if ( $this->exclusions->type_is_noindex( 'tax', (string) $name ) && array() === $this->exclusions->terms_with_token( (string) $name, 'index' ) ) {
				unset( $taxonomies[ $name ] );
			}
		}
		return $taxonomies;
	}

	/**
	 * Applies per-term exclusions to core's term sitemap query.
	 *
	 * @param mixed  $args     get_terms() arguments.
	 * @param string $taxonomy Taxonomy.
	 * @return mixed
	 */
	public function filter_terms_query( $args, $taxonomy ) {
		if ( ! is_array( $args ) ) {
			return $args;
		}
		$taxonomy  = (string) $taxonomy;
		$elsewhere = $this->exclusions->terms_canonicalised_elsewhere( $taxonomy );

		if ( $this->exclusions->type_is_noindex( 'tax', $taxonomy ) ) {
			$included        = array_diff( $this->exclusions->terms_with_token( $taxonomy, 'index' ), $elsewhere );
			$args['include'] = array() === $included ? array( 0 ) : array_values( $included );
			return $args;
		}

		$excluded = array_merge( $this->exclusions->terms_with_token( $taxonomy, 'noindex' ), $elsewhere );
		if ( array() !== $excluded ) {
			$existing = isset( $args['exclude'] ) ? wp_parse_id_list( $args['exclude'] ) : array();
			// phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- Short list (noindex/canonicalised-away terms only); cheaper than a NOT EXISTS meta query.
			$args['exclude'] = array_values( array_unique( array_merge( $existing, $excluded ) ) );
		}
		return $args;
	}

	/**
	 * Help text with a link to the live sitemap, above the sitemap settings.
	 */
	public function render_help(): void {
		echo '<p>' . esc_html__( 'DumpSEO adds to the sitemap built into WordPress. Content hidden from search engines (noindex) and content whose canonical URL points elsewhere is left out automatically.', 'dumpseo' ) . '</p>';

		if ( '0' === (string) get_option( 'blog_public' ) ) {
			echo '<p><strong>' . esc_html__( 'The sitemap is unavailable because the site is set to discourage search engines (Settings → Reading).', 'dumpseo' ) . '</strong></p>';
			return;
		}
		if ( function_exists( 'get_sitemap_url' ) && false !== $this->settings->get( 'sitemap_enabled' ) ) {
			$url = get_sitemap_url( 'index' );
			if ( is_string( $url ) ) {
				printf(
					'<p><a href="%1$s" target="_blank" rel="noopener">%2$s<span class="screen-reader-text"> %3$s</span></a></p>',
					esc_url( $url ),
					esc_html__( 'View your sitemap', 'dumpseo' ),
					/* translators: Accessibility text for links that open in a new tab. */
					esc_html__( '(opens in a new tab)', 'dumpseo' )
				);
			}
		}
	}
}
