<?php
/**
 * Visible breadcrumbs.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Breadcrumbs;

use DumpSEO\Frontend\CurrentPage;
use DumpSEO\Helpers\Assets;
use DumpSEO\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Breadcrumbs are opt-in: nothing is printed until the site owner places the
 * "Breadcrumbs" block, the [dumpseo_breadcrumbs] shortcode, or a
 * dumpseo_breadcrumbs() call in the theme.
 */
final class BreadcrumbsModule implements Module {

	public const SHORTCODE = 'dumpseo_breadcrumbs';
	public const BLOCK     = 'dumpseo/breadcrumbs';
	public const STYLE     = 'dumpseo-breadcrumbs';

	/**
	 * Current page SEO data.
	 *
	 * @var CurrentPage
	 */
	private $page;

	/**
	 * HTML renderer.
	 *
	 * @var Renderer
	 */
	private $renderer;

	/**
	 * Constructor.
	 *
	 * @param CurrentPage $page     Current page SEO data.
	 * @param Renderer    $renderer HTML renderer.
	 */
	public function __construct( CurrentPage $page, Renderer $renderer ) {
		$this->page     = $page;
		$this->renderer = $renderer;
	}

	/**
	 * The block must be registered on every request (editor, REST, frontend).
	 */
	public function should_load(): bool {
		return true;
	}

	/**
	 * Registers hooks.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_block_and_shortcode' ) );
	}

	/**
	 * Registers the shortcode, the style and the dynamic block.
	 */
	public function register_block_and_shortcode(): void {
		add_shortcode( self::SHORTCODE, array( $this, 'shortcode' ) );

		wp_register_style( self::STYLE, false, array(), DUMPSEO_VERSION );
		wp_add_inline_style( self::STYLE, Renderer::css() );

		$args = array(
			'api_version'     => '3',
			'title'           => __( 'Breadcrumbs', 'dumpseo' ),
			'category'        => 'theme',
			'render_callback' => array( $this, 'render_block' ),
			'style_handles'   => array( self::STYLE ),
			'supports'        => array(
				'html'       => false,
				'align'      => array( 'wide', 'full' ),
				'color'      => array(
					'text'       => true,
					'link'       => true,
					'background' => true,
				),
				'typography' => array( 'fontSize' => true ),
				'spacing'    => array(
					'margin'  => true,
					'padding' => true,
				),
			),
		);

		$asset = Assets::manifest( 'blocks/breadcrumbs' );
		if ( null !== $asset ) {
			wp_register_script( 'dumpseo-breadcrumbs-block', $asset['url'] . 'index.js', $asset['dependencies'], $asset['version'], true );
			wp_set_script_translations( 'dumpseo-breadcrumbs-block', 'dumpseo', DUMPSEO_DIR . 'languages' );
			$args['editor_script_handles'] = array( 'dumpseo-breadcrumbs-block' );
		}

		register_block_type( self::BLOCK, $args );
	}

	/**
	 * Breadcrumb HTML for the current page, or ''.
	 *
	 * @param string $wrapper_attrs Already-escaped attributes for the <nav>.
	 */
	public function html( string $wrapper_attrs = 'class="dumpseo-breadcrumbs"' ): string {
		$data = $this->page->data();
		if ( null === $data ) {
			return '';
		}
		$html = $this->renderer->html( $data['context'], $data['canonical'], $wrapper_attrs );
		if ( '' !== $html ) {
			wp_enqueue_style( self::STYLE );
		}
		return $html;
	}

	/**
	 * Shortcode callback.
	 *
	 * @return string
	 */
	public function shortcode(): string {
		return $this->html();
	}

	/**
	 * Block render callback.
	 *
	 * @return string
	 */
	public function render_block(): string {
		return $this->html( get_block_wrapper_attributes( array( 'class' => 'dumpseo-breadcrumbs' ) ) );
	}
}
