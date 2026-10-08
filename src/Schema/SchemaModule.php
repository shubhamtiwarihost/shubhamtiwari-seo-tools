<?php
/**
 * Prints schema.org structured data.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Schema;

use DumpSEO\Compatibility\Conflicts;
use DumpSEO\Frontend\CurrentPage;
use DumpSEO\Module;
use DumpSEO\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * One JSON-LD `<script>` with a single `@graph` in the page head (wp_head,
 * priority 20). Steps aside when another plugin that prints a graph is active.
 */
final class SchemaModule implements Module {

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Current page SEO data.
	 *
	 * @var CurrentPage
	 */
	private $page;

	/**
	 * Graph builder.
	 *
	 * @var Graph
	 */
	private $graph;

	/**
	 * Conflict detection.
	 *
	 * @var Conflicts
	 */
	private $conflicts;

	/**
	 * Constructor.
	 *
	 * @param Settings    $settings  Settings.
	 * @param CurrentPage $page      Current page SEO data.
	 * @param Graph       $graph     Graph builder.
	 * @param Conflicts   $conflicts Conflict detection.
	 */
	public function __construct( Settings $settings, CurrentPage $page, Graph $graph, Conflicts $conflicts ) {
		$this->settings  = $settings;
		$this->page      = $page;
		$this->graph     = $graph;
		$this->conflicts = $conflicts;
	}

	/**
	 * Frontend output plus a settings-screen notice in admin. Hooks only.
	 */
	public function should_load(): bool {
		return true;
	}

	/**
	 * Registers hooks.
	 */
	public function register(): void {
		add_action( 'wp_head', array( $this, 'print_graph' ), 20 );
		add_action( 'dumpseo_settings_section_schema', array( $this, 'render_notice' ) );
	}

	/**
	 * Whether DumpSEO prints structured data on this request.
	 */
	public function active(): bool {
		if ( '' !== $this->conflicts->schema_plugin() ) {
			return false;
		}

		/**
		 * Filters whether DumpSEO outputs schema.org JSON-LD.
		 *
		 * @param bool $enabled Whether structured data is enabled in settings.
		 */
		return (bool) apply_filters( 'dumpseo_schema_output_enabled', false !== $this->settings->get( 'schema_enabled' ) );
	}

	/**
	 * Prints the graph.
	 */
	public function print_graph(): void {
		if ( ! $this->active() ) {
			return;
		}
		$data = $this->page->data();
		if ( null === $data ) {
			return;
		}
		$graph = $this->graph->build( $data );
		if ( array() === $graph ) {
			return;
		}

		// JSON_HEX_* turn <, >, & and quotes into \u escapes, so the data cannot close the script element.
		$json = wp_json_encode(
			array(
				'@context' => 'https://schema.org',
				'@graph'   => $graph,
			),
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
		);
		if ( ! is_string( $json ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoded with JSON_HEX_TAG/AMP/APOS/QUOT above; HTML escaping would corrupt JSON.
		echo '<script type="application/ld+json" class="dumpseo-schema">' . $json . "</script>\n";
	}

	/**
	 * Notice on the DumpSEO settings screen when another plugin handles structured data.
	 */
	public function render_notice(): void {
		$plugin = $this->conflicts->schema_plugin();
		if ( '' === $plugin ) {
			return;
		}
		printf(
			'<div class="notice notice-info inline"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %s: name of another SEO plugin. */
					__( '%s is active and already prints structured data, so DumpSEO does not print its own. The setting below takes effect once it is deactivated.', 'dumpseo' ),
					$plugin
				)
			)
		);
	}
}
