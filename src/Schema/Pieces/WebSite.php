<?php
/**
 * The site itself.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Schema\Pieces;

use DumpSEO\Helpers\Text;
use DumpSEO\Schema\Piece;
use DumpSEO\Schema\SchemaContext;

defined( 'ABSPATH' ) || exit;

/**
 * WebSite node with the site search action, printed on every page.
 */
final class WebSite implements Piece {

	/**
	 * Always needed.
	 *
	 * @param SchemaContext $context Schema context.
	 */
	public function is_needed( SchemaContext $context ): bool {
		return true;
	}

	/**
	 * WebSite node.
	 *
	 * @param SchemaContext $context Schema context.
	 * @return array<int, array<string, mixed>>
	 */
	public function generate( SchemaContext $context ): array {
		$node = array(
			'@type'       => 'WebSite',
			'@id'         => $context->website_id(),
			'url'         => $context->home,
			'name'        => $context->site_name,
			'description' => Text::plain( (string) get_bloginfo( 'description' ) ),
			'publisher'   => '' !== $context->publisher_name ? SchemaContext::ref( $context->publisher_id() ) : null,
			'inLanguage'  => $context->language,
		);

		/**
		 * Filters whether the WebSite node describes the site search (SearchAction).
		 *
		 * @param bool $search Default true.
		 */
		if ( apply_filters( 'dumpseo_schema_search_action', true ) ) {
			$node['potentialAction'] = array(
				array(
					'@type'       => 'SearchAction',
					'target'      => array(
						'@type'       => 'EntryPoint',
						// Braces must stay literal (add_query_arg would encode them), so they are added afterwards.
						'urlTemplate' => str_replace( 'search_term_string', '{search_term_string}', add_query_arg( 's', 'search_term_string', $context->home ) ),
					),
					'query-input' => 'required name=search_term_string',
				),
			);
		}

		return array( $node );
	}
}
