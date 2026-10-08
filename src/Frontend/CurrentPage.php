<?php
/**
 * SEO data of the page being rendered.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Frontend;

use DumpSEO\Meta\Canonical;
use DumpSEO\Meta\PageContext;
use DumpSEO\Meta\Resolver;
use DumpSEO\Meta\Robots;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves the current page's context, title, description, canonical and
 * robots directives once per request, for every module that prints head tags.
 */
class CurrentPage {

	/**
	 * Title/description resolver.
	 *
	 * @var Resolver
	 */
	private $resolver;

	/**
	 * Canonical URL builder.
	 *
	 * @var Canonical
	 */
	private $canonical;

	/**
	 * Robots directives.
	 *
	 * @var Robots
	 */
	private $robots;

	/**
	 * Resolved data for this request.
	 *
	 * @var array{context: PageContext, title: string, description: string, canonical: string, robots: array<string, true>}|null
	 */
	private $data = null;

	/**
	 * Constructor.
	 *
	 * @param Resolver  $resolver  Title/description resolver.
	 * @param Canonical $canonical Canonical URL builder.
	 * @param Robots    $robots    Robots directives.
	 */
	public function __construct( Resolver $resolver, Canonical $canonical, Robots $robots ) {
		$this->resolver  = $resolver;
		$this->canonical = $canonical;
		$this->robots    = $robots;
	}

	/**
	 * Resolved data, or null before the main query exists.
	 *
	 * @return array{context: PageContext, title: string, description: string, canonical: string, robots: array<string, true>}|null
	 */
	public function data(): ?array {
		if ( null !== $this->data ) {
			return $this->data;
		}

		global $wp_query;
		if ( ! $wp_query instanceof \WP_Query ) {
			return null;
		}

		$context = PageContext::from_query( $wp_query );
		$robots  = $this->robots->directives( $context );

		/**
		 * Filters the canonical URL. Return '' to print none.
		 *
		 * @param mixed       $canonical Canonical URL string ('' on noindex pages). Non-strings are ignored.
		 * @param PageContext $context   Page context.
		 */
		$canonical = apply_filters( 'dumpseo_canonical', isset( $robots['noindex'] ) ? '' : $this->canonical->url( $context ), $context );

		$this->data = array(
			'context'     => $context,
			'title'       => $this->resolver->title( $context ),
			'description' => $this->resolver->description( $context ),
			'canonical'   => is_string( $canonical ) ? $canonical : '',
			'robots'      => $robots,
		);
		return $this->data;
	}

	/**
	 * Clears the per-request cache. For tests that simulate several requests.
	 *
	 * @internal
	 */
	public function reset(): void {
		$this->data = null;
	}
}
