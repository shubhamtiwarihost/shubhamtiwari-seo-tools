<?php
/**
 * Breadcrumb HTML.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Breadcrumbs;

use DumpSEO\Meta\PageContext;
use DumpSEO\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Turns a trail into accessible HTML: a labelled <nav> with an ordered list,
 * links for every item but the last, aria-current="page" on the last, and
 * separators hidden from screen readers.
 */
class Renderer {

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Trail builder.
	 *
	 * @var Trail
	 */
	private $trail;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings.
	 * @param Trail    $trail    Trail builder.
	 */
	public function __construct( Settings $settings, Trail $trail ) {
		$this->settings = $settings;
		$this->trail    = $trail;
	}

	/**
	 * Breadcrumb HTML for a page, or '' on the homepage (nothing to show).
	 *
	 * @param PageContext $context       Page context.
	 * @param string      $url           Current page URL ('' is fine; the last item is never linked).
	 * @param string      $wrapper_attrs Attributes for the <nav>, already escaped (e.g. from get_block_wrapper_attributes()).
	 */
	public function html( PageContext $context, string $url, string $wrapper_attrs = 'class="dumpseo-breadcrumbs"' ): string {
		$items = $this->trail->items( $context, $url );
		if ( array() === $items ) {
			return '';
		}

		$home = trim( (string) $this->settings->get( 'breadcrumbs_home' ) );
		if ( '' !== $home ) {
			$items[0]['name'] = $home;
		}
		$separator = (string) $this->settings->get( 'breadcrumbs_separator' );

		$last = count( $items ) - 1;
		$html = '';
		foreach ( $items as $index => $item ) {
			if ( $index === $last || '' === $item['url'] ) {
				$label = $index === $last
					? '<span aria-current="page">' . esc_html( $item['name'] ) . '</span>'
					: '<span>' . esc_html( $item['name'] ) . '</span>';
			} else {
				$label = '<a href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['name'] ) . '</a>';
			}
			$sep   = $index < $last && '' !== $separator
				? '<span class="dumpseo-breadcrumbs__separator" aria-hidden="true">' . esc_html( $separator ) . '</span>'
				: '';
			$html .= '<li class="dumpseo-breadcrumbs__item">' . $label . $sep . '</li>';
		}

		return sprintf(
			'<nav %1$s aria-label="%2$s"><ol class="dumpseo-breadcrumbs__list">%3$s</ol></nav>',
			$wrapper_attrs,
			esc_attr__( 'Breadcrumbs', 'dumpseo' ),
			$html
		);
	}

	/**
	 * Minimal layout CSS; colors and fonts come from the theme.
	 */
	public static function css(): string {
		return '.dumpseo-breadcrumbs__list{display:flex;flex-wrap:wrap;gap:.25em .5em;list-style:none;margin:0;padding:0}'
			. '.dumpseo-breadcrumbs__item{display:inline-flex;gap:.5em;margin:0}';
	}
}
