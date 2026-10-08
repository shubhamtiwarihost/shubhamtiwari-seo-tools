<?php
/**
 * Image URLs for sitemap entries.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Sitemap;

use DumpSEO\Helpers\Text;

defined( 'ABSPATH' ) || exit;

/**
 * Collects the images of a post: the featured image first, then images in the
 * content. Only images hosted on this site are listed, at most MAX per post.
 * Password-protected posts only contribute their featured image.
 */
class Images {

	public const MAX = 10;

	/**
	 * Hosts considered "this site", built once.
	 *
	 * @var string[]|null
	 */
	private $hosts = null;

	/**
	 * Image URLs for a post.
	 *
	 * @param \WP_Post $post Post.
	 * @return string[]
	 */
	public function for_post( \WP_Post $post ): array {
		$urls = array();

		$thumbnail = (int) get_post_thumbnail_id( $post );
		if ( $thumbnail ) {
			$url = wp_get_attachment_image_url( $thumbnail, 'full' );
			if ( is_string( $url ) ) {
				$urls[] = $url;
			}
		}

		if ( '' === $post->post_password && false !== stripos( $post->post_content, '<img' ) ) {
			preg_match_all( '/<img\s[^>]*?\bsrc\s*=\s*(["\'])(.+?)\1/i', $post->post_content, $matches );
			foreach ( $matches[2] as $src ) {
				$urls[] = html_entity_decode( $src, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			}
		}

		$clean = array();
		foreach ( $urls as $url ) {
			$url = $this->absolute( $url );
			if ( null !== $url && $this->is_local( $url ) && ! in_array( $url, $clean, true ) ) {
				$clean[] = $url;
			}
			if ( count( $clean ) >= self::MAX ) {
				break;
			}
		}

		/**
		 * Filters the image URLs listed for a post in the sitemap.
		 *
		 * @param string[] $clean Image URLs.
		 * @param \WP_Post $post  Post.
		 */
		$filtered = apply_filters( 'dumpseo_sitemap_images', $clean, $post );

		return array_values( array_filter( (array) $filtered, 'is_string' ) );
	}

	/**
	 * Primes caches for the featured images of many posts: two queries in
	 * total instead of several per post.
	 *
	 * @param array<int, mixed> $posts Posts (from a core filter; non-posts are skipped).
	 */
	public function prime( array $posts ): void {
		$ids = array();
		foreach ( $posts as $post ) {
			if ( $post instanceof \WP_Post ) {
				$ids[] = $post->ID;
			}
		}
		if ( array() === $ids ) {
			return;
		}

		update_meta_cache( 'post', $ids );

		$thumbnails = array();
		foreach ( $ids as $id ) {
			$thumbnail = (int) get_post_meta( $id, '_thumbnail_id', true );
			if ( $thumbnail ) {
				$thumbnails[] = $thumbnail;
			}
		}
		if ( array() !== $thumbnails ) {
			_prime_post_caches( array_unique( $thumbnails ), false, true );
		}
	}

	/**
	 * Makes a root-relative URL absolute; validates http(s). Null when unusable.
	 *
	 * @param string $url URL from content or attachment.
	 */
	private function absolute( string $url ): ?string {
		$url = trim( $url );
		if ( '' !== $url && '/' === $url[0] && ( ! isset( $url[1] ) || '/' !== $url[1] ) ) {
			$url = home_url( $url );
		}
		$valid = Text::http_url( $url );
		return null === $valid || '' === $valid ? null : $valid;
	}

	/**
	 * Whether a URL is hosted on this site (home or uploads host).
	 *
	 * @param string $url Absolute URL.
	 */
	private function is_local( string $url ): bool {
		if ( null === $this->hosts ) {
			$uploads = wp_get_upload_dir();
			$hosts   = array(
				wp_parse_url( home_url(), PHP_URL_HOST ),
				wp_parse_url( (string) $uploads['baseurl'], PHP_URL_HOST ),
			);

			/**
			 * Filters which hosts count as this site's images (e.g. add a CDN host).
			 *
			 * @param mixed $hosts Host names.
			 */
			$hosts       = apply_filters( 'dumpseo_sitemap_image_hosts', $hosts );
			$this->hosts = array_values( array_unique( array_map( 'strtolower', array_filter( (array) $hosts, 'is_string' ) ) ) );
		}

		$host = wp_parse_url( $url, PHP_URL_HOST );
		return is_string( $host ) && in_array( strtolower( $host ), $this->hosts, true );
	}
}
