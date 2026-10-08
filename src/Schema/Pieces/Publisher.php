<?php
/**
 * Organization or Person behind the site.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Schema\Pieces;

use DumpSEO\Schema\Piece;
use DumpSEO\Schema\SchemaContext;

defined( 'ABSPATH' ) || exit;

/**
 * The site's publisher, from the "This website represents" settings.
 * Printed on every page so WebSite and Article can point at it.
 */
final class Publisher implements Piece {

	/**
	 * Needed whenever there is a name (the site title is the fallback).
	 *
	 * @param SchemaContext $context Schema context.
	 */
	public function is_needed( SchemaContext $context ): bool {
		return '' !== $context->publisher_name;
	}

	/**
	 * Organization (with logo) or Person (with image).
	 *
	 * @param SchemaContext $context Schema context.
	 * @return array<int, array<string, mixed>>
	 */
	public function generate( SchemaContext $context ): array {
		$person = 'person' === $context->represents;
		$node   = array(
			'@type' => $person ? 'Person' : 'Organization',
			'@id'   => $context->publisher_id(),
			'name'  => $context->publisher_name,
			'url'   => $context->home,
		);

		if ( '' !== $context->publisher_logo ) {
			$image = array(
				'@type'      => 'ImageObject',
				'@id'        => $context->home . '#logo',
				'url'        => $context->publisher_logo,
				'contentUrl' => $context->publisher_logo,
				'caption'    => $context->publisher_name,
			);
			if ( $person ) {
				$node['image'] = $image;
			} else {
				$node['logo']  = $image;
				$node['image'] = SchemaContext::ref( $image['@id'] );
			}
		}

		return array( $node );
	}
}
