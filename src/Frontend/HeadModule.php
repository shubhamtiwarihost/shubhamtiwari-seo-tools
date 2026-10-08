<?php
/**
 * Prints the SEO title, meta description, canonical and robots directives.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Frontend;

use DumpSEO\Context;
use DumpSEO\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Frontend <head> output.
 *
 * - Title: `pre_get_document_title` (title-tag themes) and `wp_title` (legacy
 *   themes; skipped in feeds, where core reuses that filter).
 * - Description and canonical: `wp_head`, priority 1.
 * - Robots: core's `wp_robots` filter, so there is only ever one robots tag.
 * - Core's own `rel_canonical` (singular pages only) is removed to avoid a
 *   second canonical tag.
 *
 * Everything is resolved once per request, after the main query has run.
 */
final class HeadModule implements Module {

	/**
	 * Request context.
	 *
	 * @var Context
	 */
	private $context;

	/**
	 * Current page SEO data.
	 *
	 * @var CurrentPage
	 */
	private $page;

	/**
	 * Constructor.
	 *
	 * @param Context     $context Request context.
	 * @param CurrentPage $page    Current page SEO data.
	 */
	public function __construct( Context $context, CurrentPage $page ) {
		$this->context = $context;
		$this->page    = $page;
	}

	/**
	 * Frontend requests only.
	 */
	public function should_load(): bool {
		return $this->context->is_frontend();
	}

	/**
	 * Registers hooks.
	 */
	public function register(): void {
		add_filter( 'pre_get_document_title', array( $this, 'document_title' ), 15 );
		add_filter( 'wp_title', array( $this, 'wp_title' ), 15 );
		add_filter( 'wp_robots', array( $this, 'filter_robots' ), 20 );
		add_action( 'wp_head', array( $this, 'take_over_canonical' ), 0 );
		add_action( 'wp_head', array( $this, 'print_tags' ), 1 );
	}

	/**
	 * Title for themes that support title-tag. Returned value is echoed by core unescaped.
	 *
	 * @param mixed $title Title from earlier filters.
	 * @return mixed
	 */
	public function document_title( $title ) {
		if ( ! $this->enabled() ) {
			return $title;
		}
		$ours = $this->resolved()['title'];
		return '' === $ours ? $title : esc_html( $ours );
	}

	/**
	 * Title for legacy themes that call wp_title().
	 *
	 * @param mixed $title Title from core.
	 * @return mixed
	 */
	public function wp_title( $title ) {
		if ( is_feed() ) {
			return $title;
		}
		return $this->document_title( $title );
	}

	/**
	 * Adds DumpSEO directives to core's robots meta tag. Never removes core directives.
	 *
	 * @param mixed $robots Directives from core and other plugins.
	 * @return mixed
	 */
	public function filter_robots( $robots ) {
		if ( ! is_array( $robots ) || ! $this->enabled() ) {
			return $robots;
		}
		foreach ( $this->resolved()['robots'] as $directive => $on ) {
			$robots[ $directive ] = $on;
		}
		return $robots;
	}

	/**
	 * Removes core's rel_canonical so only one canonical tag is printed.
	 */
	public function take_over_canonical(): void {
		if ( $this->enabled() ) {
			remove_action( 'wp_head', 'rel_canonical' );
		}
	}

	/**
	 * Prints the meta description and canonical tags.
	 */
	public function print_tags(): void {
		if ( ! $this->enabled() ) {
			return;
		}
		$resolved = $this->resolved();

		if ( '' !== $resolved['description'] ) {
			printf( '<meta name="description" content="%s" />' . "\n", esc_attr( $resolved['description'] ) );
		}
		if ( '' !== $resolved['canonical'] ) {
			printf( '<link rel="canonical" href="%s" />' . "\n", esc_url( $resolved['canonical'] ) );
		}
	}

	/**
	 * Resolved values for this request (empty strings before the main query exists).
	 *
	 * @return array{title: string, description: string, canonical: string, robots: array<string, true>}
	 */
	public function resolved(): array {
		$data = $this->page->data();
		if ( null === $data ) {
			return array(
				'title'       => '',
				'description' => '',
				'canonical'   => '',
				'robots'      => array(),
			);
		}
		return array(
			'title'       => $data['title'],
			'description' => $data['description'],
			'canonical'   => $data['canonical'],
			'robots'      => $data['robots'],
		);
	}

	/**
	 * Clears the per-request cache. For tests that simulate several requests.
	 *
	 * @internal
	 */
	public function reset(): void {
		$this->page->reset();
	}

	/**
	 * Whether DumpSEO should print its head tags.
	 */
	private function enabled(): bool {
		/**
		 * Filters whether DumpSEO outputs title, description, canonical and robots.
		 *
		 * @param bool $enabled Default true.
		 */
		return (bool) apply_filters( 'dumpseo_head_output_enabled', true );
	}
}
