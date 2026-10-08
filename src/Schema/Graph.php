<?php
/**
 * Builds the JSON-LD graph.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Schema;

use DumpSEO\Breadcrumbs\Trail;
use DumpSEO\Helpers\Text;
use DumpSEO\Meta\PageContext;
use DumpSEO\Schema\Pieces\Article;
use DumpSEO\Schema\Pieces\BreadcrumbList;
use DumpSEO\Schema\Pieces\PrimaryImage;
use DumpSEO\Schema\Pieces\Publisher;
use DumpSEO\Schema\Pieces\WebPage;
use DumpSEO\Schema\Pieces\WebSite;
use DumpSEO\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Collects nodes from every registered piece into one `@graph`.
 *
 * Pieces: publisher, website, webpage, primary_image, breadcrumb, article.
 * Extensions add, replace or remove pieces with `dumpseo_schema_pieces`
 * and change the final graph with `dumpseo_schema_graph`.
 */
class Graph {

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Breadcrumb trail builder.
	 *
	 * @var Trail
	 */
	private $trail;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings.
	 * @param Trail    $trail    Breadcrumb trail builder.
	 */
	public function __construct( Settings $settings, Trail $trail ) {
		$this->settings = $settings;
		$this->trail    = $trail;
	}

	/**
	 * The graph nodes for a page (plain PHP values, empty values removed).
	 *
	 * @param array{context: PageContext, title: string, description: string, canonical: string, robots: array<string, true>} $data Current page data.
	 * @return array<int, array<string, mixed>>
	 */
	public function build( array $data ): array {
		if ( PageContext::OTHER === $data['context']->type ) {
			return array();
		}
		$context = $this->context( $data );

		$pieces = array(
			'publisher'     => new Publisher(),
			'website'       => new WebSite(),
			'webpage'       => new WebPage(),
			'primary_image' => new PrimaryImage(),
			'breadcrumb'    => new BreadcrumbList(),
			'article'       => new Article(),
		);

		/**
		 * Filters the schema pieces. Entries that are not Piece instances are ignored.
		 *
		 * @param array<string, mixed> $pieces  Pieces keyed by ID.
		 * @param SchemaContext        $context Schema context.
		 */
		$pieces = apply_filters( 'dumpseo_schema_pieces', $pieces, $context );

		$graph = array();
		foreach ( (array) $pieces as $piece ) {
			if ( ! $piece instanceof Piece || ! $piece->is_needed( $context ) ) {
				continue;
			}
			foreach ( $piece->generate( $context ) as $node ) {
				$graph[] = $node;
			}
		}

		/**
		 * Filters the graph nodes before output.
		 *
		 * @param array<int, mixed> $graph   Nodes.
		 * @param SchemaContext     $context Schema context.
		 */
		$graph = apply_filters( 'dumpseo_schema_graph', $graph, $context );

		$nodes = array();
		$ids   = array();
		foreach ( (array) $graph as $node ) {
			if ( is_array( $node ) && isset( $node['@type'] ) ) {
				$nodes[] = $node;
				self::collect_ids( $node, $ids );
			}
		}

		$clean = array();
		foreach ( $nodes as $node ) {
			$clean[] = self::strip_empty( $node, $ids );
		}
		return $clean;
	}

	/**
	 * Adds every `@id` defined in a node (including nested nodes) to $ids.
	 *
	 * @param array<mixed>        $node Node.
	 * @param array<string, true> $ids  Known IDs (by reference).
	 */
	private static function collect_ids( array $node, array &$ids ): void {
		if ( isset( $node['@id'] ) && is_string( $node['@id'] ) && ! self::is_ref( $node ) ) {
			$ids[ $node['@id'] ] = true;
		}
		foreach ( $node as $value ) {
			if ( is_array( $value ) ) {
				self::collect_ids( $value, $ids );
			}
		}
	}

	/**
	 * Whether a value is a bare {"@id": ...} reference.
	 *
	 * @param array<mixed> $value Value.
	 */
	private static function is_ref( array $value ): bool {
		return 1 === count( $value ) && isset( $value['@id'] );
	}

	/**
	 * Resolves the values pieces share.
	 *
	 * @param array{context: PageContext, title: string, description: string, canonical: string, robots: array<string, true>} $data Current page data.
	 */
	public function context( array $data ): SchemaContext {
		$page      = $data['context'];
		$site_name = Text::plain( (string) get_bloginfo( 'name' ) );
		$publisher = Text::plain( (string) $this->settings->get( 'organization_name' ) );
		$url       = $data['canonical'];

		return new SchemaContext(
			array(
				'page'           => $page,
				'url'            => $url,
				'title'          => $data['title'],
				'description'    => $data['description'],
				'home'           => home_url( '/' ),
				'site_name'      => $site_name,
				'language'       => (string) get_bloginfo( 'language' ),
				'represents'     => 'person' === $this->settings->get( 'site_represents' ) ? 'person' : 'organization',
				'publisher_name' => '' !== $publisher ? $publisher : $site_name,
				'publisher_logo' => (string) Text::http_url( $this->settings->get( 'organization_logo' ) ),
				'image'          => $this->image( $page ),
				'trail'          => '' !== $url ? $this->trail->items( $page, $url ) : array(),
			)
		);
	}

	/**
	 * Featured image of a singular page (never for password-protected posts).
	 *
	 * @param PageContext $page Page context.
	 * @return array{url: string, width: int, height: int, caption: string}|null
	 */
	private function image( PageContext $page ): ?array {
		$post = $page->object;
		if ( ! $post instanceof \WP_Post || ! in_array( $page->type, array( PageContext::SINGULAR, PageContext::FRONT ), true ) ) {
			return null;
		}
		if ( '' !== $post->post_password || ! has_post_thumbnail( $post ) ) {
			return null;
		}
		$id  = (int) get_post_thumbnail_id( $post );
		$src = wp_get_attachment_image_src( $id, 'full' );
		if ( ! is_array( $src ) || '' === (string) Text::http_url( $src[0] ) ) {
			return null;
		}
		return array(
			'url'     => (string) Text::http_url( $src[0] ),
			'width'   => (int) $src[1],
			'height'  => (int) $src[2],
			'caption' => Text::plain( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) ),
		);
	}

	/**
	 * Removes null, '' and empty-array values, and references to nodes that
	 * are not in the graph (e.g. a piece an extension removed), recursively.
	 *
	 * @param array<mixed>        $values Values.
	 * @param array<string, true> $ids    IDs defined in the graph.
	 * @return array<mixed>
	 */
	private static function strip_empty( array $values, array $ids ): array {
		$list = array_values( $values ) === $values;
		foreach ( $values as $key => $value ) {
			if ( is_array( $value ) ) {
				$value          = self::is_ref( $value ) && ! isset( $ids[ (string) $value['@id'] ] ) ? null : self::strip_empty( $value, $ids );
				$values[ $key ] = $value;
			}
			if ( null === $value || '' === $value || array() === $value ) {
				unset( $values[ $key ] );
			}
		}
		return $list ? array_values( $values ) : $values;
	}
}
