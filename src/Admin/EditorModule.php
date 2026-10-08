<?php
/**
 * Block editor sidebar.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Admin;

use DumpSEO\Helpers\Assets;
use DumpSEO\Meta\Robots;
use DumpSEO\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Loads the DumpSEO sidebar (build/editor) in the block editor for supported
 * post types, and makes sure those post types expose SEO meta over REST.
 *
 * Core only includes registered post meta in REST responses for post types
 * that support "custom-fields", so that support is added to supported types.
 * The keys are protected (leading underscore), so they never appear in the
 * Custom Fields panel.
 */
final class EditorModule implements Module {

	/**
	 * Loads on every request: REST requests (where meta support matters) are not admin requests.
	 */
	public function should_load(): bool {
		return true;
	}

	/**
	 * Registers hooks.
	 */
	public function register(): void {
		// After post types from themes/plugins are registered (usually priority 10).
		add_action( 'init', array( $this, 'add_meta_support' ), 99 );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue' ) );
	}

	/**
	 * Adds "custom-fields" support to supported post types.
	 */
	public function add_meta_support(): void {
		foreach ( PostTypes::supported() as $post_type ) {
			if ( ! post_type_supports( $post_type, 'custom-fields' ) ) {
				add_post_type_support( $post_type, 'custom-fields' );
			}
		}
	}

	/**
	 * Enqueues the sidebar script when editing a supported post type.
	 */
	public function enqueue(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen instanceof \WP_Screen || 'post' !== $screen->base || ! PostTypes::is_supported( (string) $screen->post_type ) ) {
			return;
		}

		$asset = Assets::manifest( 'editor' );
		if ( null === $asset ) {
			return; // Development checkout without `npm run build`.
		}

		wp_enqueue_script( 'dumpseo-editor', $asset['url'] . 'index.js', $asset['dependencies'], $asset['version'], true );
		if ( is_readable( DUMPSEO_DIR . 'build/editor/index.css' ) ) {
			wp_enqueue_style( 'dumpseo-editor', $asset['url'] . 'index.css', array( 'wp-components' ), $asset['version'] );
		}
		wp_set_script_translations( 'dumpseo-editor', 'dumpseo', DUMPSEO_DIR . 'languages' );

		wp_add_inline_script(
			'dumpseo-editor',
			'window.dumpseoEditor = ' . wp_json_encode(
				array(
					'robotsTokens' => Robots::TOKENS,
					'blogPublic'   => '0' !== (string) get_option( 'blog_public' ),
				)
			) . ';',
			'before'
		);
	}
}
