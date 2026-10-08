<?php
/**
 * Builds Open Graph and X (Twitter) Card tags.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Social;

use DumpSEO\Helpers\Text;
use DumpSEO\Meta\Keys;
use DumpSEO\Meta\PageContext;
use DumpSEO\Meta\Resolver;
use DumpSEO\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Turns the current page's SEO data into a list of social meta tags.
 *
 * Title:       custom social title → SEO title → object name → site name.
 * Description: custom social description → meta description.
 * Image:       custom social image → featured image → site default image.
 * URL:         the canonical URL (omitted when the page has none).
 *
 * Returns plain values; SocialModule escapes them when printing.
 */
class SocialTags {

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Resolver (renders %%variables%% in custom values).
	 *
	 * @var Resolver
	 */
	private $resolver;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings.
	 * @param Resolver $resolver Resolver.
	 */
	public function __construct( Settings $settings, Resolver $resolver ) {
		$this->settings = $settings;
		$this->resolver = $resolver;
	}

	/**
	 * Tags for a page.
	 *
	 * @param array{context: PageContext, title: string, description: string, canonical: string, robots: array<string, true>} $data Current page data.
	 * @return array<int, array{attr: string, key: string, value: string, url: bool}>
	 */
	public function build( array $data ): array {
		$context = $data['context'];
		if ( in_array( $context->type, array( PageContext::SEARCH, PageContext::NOT_FOUND, PageContext::OTHER ), true ) ) {
			return array();
		}

		$og      = false !== $this->settings->get( 'social_og_enabled' );
		$twitter = false !== $this->settings->get( 'social_twitter_enabled' );
		if ( ! $og && ! $twitter ) {
			return array();
		}

		$title       = $this->resolver->render( $this->custom( Keys::SOCIAL_TITLE, $context ), $context );
		$title       = '' !== $title ? $title : ( '' !== $data['title'] ? $data['title'] : $this->fallback_title( $context ) );
		$description = $this->resolver->render( $this->custom( Keys::SOCIAL_DESCRIPTION, $context ), $context );
		$description = '' !== $description ? $description : $data['description'];
		$image       = $this->image( $context );
		$article     = $this->is_article( $context );

		$tags = array();
		if ( $og ) {
			$this->add( $tags, 'property', 'og:locale', (string) get_locale() );
			$this->add( $tags, 'property', 'og:type', $article ? 'article' : 'website' );
			$this->add( $tags, 'property', 'og:title', $title );
			$this->add( $tags, 'property', 'og:description', $description );
			$this->add( $tags, 'property', 'og:url', $data['canonical'], true );
			$this->add( $tags, 'property', 'og:site_name', $this->plain( (string) get_bloginfo( 'name' ) ) );

			if ( null !== $image ) {
				$this->add( $tags, 'property', 'og:image', $image['url'], true );
				$this->add( $tags, 'property', 'og:image:width', $image['width'] > 0 ? (string) $image['width'] : '' );
				$this->add( $tags, 'property', 'og:image:height', $image['height'] > 0 ? (string) $image['height'] : '' );
				$this->add( $tags, 'property', 'og:image:type', $image['type'] );
				$this->add( $tags, 'property', 'og:image:alt', $image['alt'] );
			}

			if ( $article && $context->object instanceof \WP_Post ) {
				$this->add( $tags, 'property', 'article:published_time', $this->w3c( $context->object->post_date_gmt ) );
				$this->add( $tags, 'property', 'article:modified_time', $this->w3c( $context->object->post_modified_gmt ) );
			}
		}

		if ( $twitter ) {
			$handle = (string) $this->settings->get( 'twitter_site' );
			$this->add( $tags, 'name', 'twitter:card', $this->large_card( $image ) ? 'summary_large_image' : 'summary' );
			$this->add( $tags, 'name', 'twitter:site', '' !== $handle ? '@' . $handle : '' );
			$this->add( $tags, 'name', 'twitter:title', $title );
			$this->add( $tags, 'name', 'twitter:description', $description );
			if ( null !== $image ) {
				$this->add( $tags, 'name', 'twitter:image', $image['url'], true );
				$this->add( $tags, 'name', 'twitter:image:alt', $image['alt'] );
			}
		}

		/**
		 * Filters the social tags before printing.
		 *
		 * @param array<int, array{attr: string, key: string, value: string, url: bool}> $tags    Tags.
		 * @param PageContext                                                            $context Page context.
		 */
		$filtered = apply_filters( 'dumpseo_social_tags', $tags, $context );

		return $this->validate( $filtered );
	}

	/**
	 * The image for a page, or null.
	 *
	 * @param PageContext $context Page context.
	 * @return array{url: string, width: int, height: int, type: string, alt: string}|null
	 */
	public function image( PageContext $context ): ?array {
		$image  = null;
		$custom = (string) Text::http_url( $this->custom( Keys::SOCIAL_IMAGE, $context ) );

		if ( '' !== $custom ) {
			$image = $this->url_only( $custom );
		} elseif ( $context->object instanceof \WP_Post && has_post_thumbnail( $context->object ) && '' === $context->object->post_password ) {
			$image = $this->attachment( (int) get_post_thumbnail_id( $context->object ) );
		}

		if ( null === $image ) {
			$default = (string) Text::http_url( $this->settings->get( 'default_social_image' ) );
			$image   = '' !== $default ? $this->url_only( $default ) : null;
		}

		/**
		 * Filters the social image. Return null for none.
		 *
		 * @param mixed       $image   Image array {url, width, height, type, alt}, or null. Validated after filtering.
		 * @param PageContext $context Page context.
		 */
		$filtered = apply_filters( 'dumpseo_social_image', $image, $context );

		if ( ! is_array( $filtered ) || ! isset( $filtered['url'] ) || '' === (string) Text::http_url( $filtered['url'] ) ) {
			return null;
		}
		return array(
			'url'    => (string) Text::http_url( $filtered['url'] ),
			'width'  => isset( $filtered['width'] ) ? (int) $filtered['width'] : 0,
			'height' => isset( $filtered['height'] ) ? (int) $filtered['height'] : 0,
			'type'   => isset( $filtered['type'] ) && is_string( $filtered['type'] ) ? $filtered['type'] : '',
			'alt'    => isset( $filtered['alt'] ) && is_string( $filtered['alt'] ) ? $this->plain( $filtered['alt'] ) : '',
		);
	}

	/**
	 * Image data from a media library attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array{url: string, width: int, height: int, type: string, alt: string}|null
	 */
	private function attachment( int $attachment_id ): ?array {
		$src = wp_get_attachment_image_src( $attachment_id, 'full' );
		if ( ! is_array( $src ) ) {
			return null;
		}
		return array(
			'url'    => $src[0],
			'width'  => (int) $src[1],
			'height' => (int) $src[2],
			'type'   => (string) get_post_mime_type( $attachment_id ),
			'alt'    => (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
		);
	}

	/**
	 * Image data when only the URL is known.
	 *
	 * @param string $url Image URL.
	 * @return array{url: string, width: int, height: int, type: string, alt: string}
	 */
	private function url_only( string $url ): array {
		return array(
			'url'    => $url,
			'width'  => 0,
			'height' => 0,
			'type'   => '',
			'alt'    => '',
		);
	}

	/**
	 * Whether the image suits X's large card (at least 300 × 157 px). Unknown sizes are allowed; X decides.
	 *
	 * @param array{url: string, width: int, height: int, type: string, alt: string}|null $image Image.
	 */
	private function large_card( ?array $image ): bool {
		if ( null === $image ) {
			return false;
		}
		if ( 0 === $image['width'] || 0 === $image['height'] ) {
			return true;
		}
		return $image['width'] >= 300 && $image['height'] >= 157;
	}

	/**
	 * Whether the page is an article (single post of any type except pages and media).
	 *
	 * @param PageContext $context Page context.
	 */
	private function is_article( PageContext $context ): bool {
		$article = PageContext::SINGULAR === $context->type
			&& $context->object instanceof \WP_Post
			&& ! in_array( $context->object->post_type, array( 'page', 'attachment' ), true );

		/**
		 * Filters whether og:type is "article" (true) or "website" (false).
		 *
		 * @param bool        $article Default decision.
		 * @param PageContext $context Page context.
		 */
		return (bool) apply_filters( 'dumpseo_og_is_article', $article, $context );
	}

	/**
	 * Custom value saved on the post or term, or ''.
	 *
	 * @param string      $key     Meta key.
	 * @param PageContext $context Page context.
	 */
	private function custom( string $key, PageContext $context ): string {
		$object = $context->object;
		if ( $object instanceof \WP_Post && in_array( $context->type, array( PageContext::SINGULAR, PageContext::FRONT, PageContext::BLOG ), true ) ) {
			return trim( (string) get_post_meta( $object->ID, $key, true ) );
		}
		if ( $object instanceof \WP_Term && PageContext::TERM === $context->type ) {
			return trim( (string) get_term_meta( $object->term_id, $key, true ) );
		}
		return '';
	}

	/**
	 * Title when nothing else is available.
	 *
	 * @param PageContext $context Page context.
	 */
	private function fallback_title( PageContext $context ): string {
		$object = $context->object;
		if ( $object instanceof \WP_Post ) {
			return $this->plain( $object->post_title );
		}
		if ( $object instanceof \WP_Term ) {
			return $this->plain( $object->name );
		}
		return $this->plain( (string) get_bloginfo( 'name' ) );
	}

	/**
	 * Adds a tag when the value is not empty.
	 *
	 * @param array<int, array{attr: string, key: string, value: string, url: bool}> $tags  Tags (by reference).
	 * @param string                                                                 $attr  "property" or "name".
	 * @param string                                                                 $key   Tag key.
	 * @param string                                                                 $value Plain value.
	 * @param bool                                                                   $url   Whether the value is a URL.
	 */
	private function add( array &$tags, string $attr, string $key, string $value, bool $url = false ): void {
		if ( '' !== $value ) {
			$tags[] = array(
				'attr'  => $attr,
				'key'   => $key,
				'value' => $value,
				'url'   => $url,
			);
		}
	}

	/**
	 * Keeps only well-formed tags (after the filter).
	 *
	 * @param mixed $tags Tags.
	 * @return array<int, array{attr: string, key: string, value: string, url: bool}>
	 */
	private function validate( $tags ): array {
		$clean = array();
		foreach ( (array) $tags as $tag ) {
			if ( is_array( $tag ) && in_array( $tag['attr'] ?? '', array( 'property', 'name' ), true ) && is_string( $tag['key'] ?? null ) && is_string( $tag['value'] ?? null ) && '' !== $tag['value'] ) {
				$clean[] = array(
					'attr'  => (string) $tag['attr'],
					'key'   => (string) $tag['key'],
					'value' => (string) $tag['value'],
					'url'   => ! empty( $tag['url'] ),
				);
			}
		}
		return $clean;
	}

	/**
	 * GMT MySQL date to W3C format, or '' for empty dates.
	 *
	 * @param string $gmt GMT date.
	 */
	private function w3c( string $gmt ): string {
		return '0000-00-00 00:00:00' === $gmt ? '' : (string) gmdate( DATE_W3C, (int) strtotime( $gmt . ' UTC' ) );
	}

	/**
	 * Plain text: entities decoded, tags stripped.
	 *
	 * @param string $text Text.
	 */
	private function plain( string $text ): string {
		return Text::plain( $text );
	}
}
