<?php
/**
 * Template functions for themes.
 *
 * @package DumpSEO
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'dumpseo_get_breadcrumbs' ) ) {
	/**
	 * Breadcrumb HTML for the current page ('' on the homepage, or when DumpSEO is not running).
	 *
	 * Usage in a theme: `if ( function_exists( 'dumpseo_breadcrumbs' ) ) { dumpseo_breadcrumbs(); }`
	 */
	function dumpseo_get_breadcrumbs(): string {
		$module = \DumpSEO\Plugin::instance()->module( 'breadcrumbs' );
		return $module instanceof \DumpSEO\Breadcrumbs\BreadcrumbsModule ? $module->html() : '';
	}
}

if ( ! function_exists( 'dumpseo_breadcrumbs' ) ) {
	/**
	 * Prints the breadcrumbs for the current page.
	 */
	function dumpseo_breadcrumbs(): void {
		echo dumpseo_get_breadcrumbs(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built by Breadcrumbs\Renderer, which escapes every value.
	}
}
