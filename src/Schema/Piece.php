<?php
/**
 * One part of the schema graph.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * A schema.org node type. Pieces are registered through the
 * `dumpseo_schema_pieces` filter and refer to each other by `@id`
 * (see SchemaContext), never by calling each other.
 */
interface Piece {

	/**
	 * Whether this piece adds anything on this page.
	 *
	 * @param SchemaContext $context Schema context.
	 */
	public function is_needed( SchemaContext $context ): bool;

	/**
	 * Nodes to add to the graph. Plain values; empty values are removed later.
	 *
	 * @param SchemaContext $context Schema context.
	 * @return array<int, array<string, mixed>>
	 */
	public function generate( SchemaContext $context ): array;
}
