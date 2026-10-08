<?php
/**
 * Sitemap renderer with image support.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Sitemap;

defined( 'ABSPATH' ) || exit;

/**
 * Core's renderer, extended to output `<image:image>` elements.
 *
 * Identical to WP_Sitemaps_Renderer::get_sitemap_xml() (same escaping: esc_url
 * for locations, esc_xml for other values) except that it declares the image
 * namespace and renders an `images` list on each entry.
 */
class ImageRenderer extends \WP_Sitemaps_Renderer {

	public const IMAGE_NS = 'http://www.google.com/schemas/sitemap-image/1.1';

	/**
	 * Gets XML for a sitemap page.
	 *
	 * @param array<int, array<string, mixed>> $url_list Entries.
	 * @return string|false Well-formed XML, or false on error.
	 */
	public function get_sitemap_xml( $url_list ) {
		$urlset = new \SimpleXMLElement(
			sprintf(
				'%1$s%2$s%3$s',
				'<?xml version="1.0" encoding="UTF-8" ?>',
				$this->stylesheet,
				'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="' . self::IMAGE_NS . '" />'
			)
		);

		foreach ( $url_list as $url_item ) {
			$url = $urlset->addChild( 'url' );

			foreach ( $url_item as $name => $value ) {
				if ( 'loc' === $name ) {
					$url->addChild( 'loc', esc_url( (string) $value ) );
				} elseif ( in_array( $name, array( 'lastmod', 'changefreq', 'priority' ), true ) ) {
					$url->addChild( $name, esc_xml( (string) $value ) );
				} elseif ( 'images' === $name && is_array( $value ) ) {
					foreach ( $value as $image_url ) {
						if ( is_string( $image_url ) && '' !== $image_url ) {
							$image = $url->addChild( 'image:image', null, self::IMAGE_NS );
							$image->addChild( 'image:loc', esc_url( $image_url ), self::IMAGE_NS );
						}
					}
				} else {
					_doing_it_wrong(
						__METHOD__,
						esc_html__( 'Sitemap entries support only loc, lastmod, changefreq, priority and images.', 'dumpseo' ),
						'1.0.0'
					);
				}
			}
		}

		return $urlset->asXML();
	}
}
