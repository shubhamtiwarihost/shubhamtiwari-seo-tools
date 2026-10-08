<?php
/**
 * The page's main image.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Schema\Pieces;

use DumpSEO\Schema\Piece;
use DumpSEO\Schema\SchemaContext;

defined( 'ABSPATH' ) || exit;

/**
 * ImageObject for the featured image, referenced by WebPage and Article.
 */
final class PrimaryImage implements Piece {

	/**
	 * Needed when the page has a featured image and a canonical URL.
	 *
	 * @param SchemaContext $context Schema context.
	 */
	public function is_needed( SchemaContext $context ): bool {
		return null !== $context->image && '' !== $context->url;
	}

	/**
	 * ImageObject node.
	 *
	 * @param SchemaContext $context Schema context.
	 * @return array<int, array<string, mixed>>
	 */
	public function generate( SchemaContext $context ): array {
		if ( null === $context->image ) {
			return array();
		}
		return array(
			array(
				'@type'      => 'ImageObject',
				'@id'        => $context->image_id(),
				'url'        => $context->image['url'],
				'contentUrl' => $context->image['url'],
				'width'      => $context->image['width'] > 0 ? $context->image['width'] : null,
				'height'     => $context->image['height'] > 0 ? $context->image['height'] : null,
				'caption'    => $context->image['caption'],
				'inLanguage' => $context->language,
			),
		);
	}
}
