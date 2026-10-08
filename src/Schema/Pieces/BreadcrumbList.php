<?php
/**
 * Breadcrumb trail as structured data.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Schema\Pieces;

use DumpSEO\Schema\Piece;
use DumpSEO\Schema\SchemaContext;

defined( 'ABSPATH' ) || exit;

/**
 * BreadcrumbList node built from Breadcrumbs\Trail.
 */
final class BreadcrumbList implements Piece {

	/**
	 * Needed when there is a trail and a canonical URL.
	 *
	 * @param SchemaContext $context Schema context.
	 */
	public function is_needed( SchemaContext $context ): bool {
		return array() !== $context->trail && '' !== $context->url;
	}

	/**
	 * BreadcrumbList node. The last item has no `item` URL (it is the page itself).
	 *
	 * @param SchemaContext $context Schema context.
	 * @return array<int, array<string, mixed>>
	 */
	public function generate( SchemaContext $context ): array {
		$elements = array();
		$last     = count( $context->trail ) - 1;
		foreach ( $context->trail as $index => $item ) {
			$elements[] = array(
				'@type'    => 'ListItem',
				'position' => $index + 1,
				'name'     => $item['name'],
				'item'     => $index < $last ? $item['url'] : null,
			);
		}

		return array(
			array(
				'@type'           => 'BreadcrumbList',
				'@id'             => $context->breadcrumb_id(),
				'itemListElement' => $elements,
			),
		);
	}
}
