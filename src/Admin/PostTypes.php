<?php
/**
 * Post types that get SEO editing controls.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Public post types with an admin UI, except media.
 */
final class PostTypes {

	/**
	 * Post type names.
	 *
	 * @return string[]
	 */
	public static function supported(): array {
		$types = array_values(
			array_diff(
				get_post_types(
					array(
						'public'  => true,
						'show_ui' => true,
					)
				),
				array( 'attachment' )
			)
		);

		/**
		 * Filters the post types that get the DumpSEO sidebar and metabox.
		 *
		 * @param mixed $types Post type names. Non-strings are ignored.
		 */
		$filtered = apply_filters( 'dumpseo_editor_post_types', $types );
		return array_values( array_filter( (array) $filtered, 'is_string' ) );
	}

	/**
	 * Whether a post type is supported.
	 *
	 * @param string $post_type Post type name.
	 */
	public static function is_supported( string $post_type ): bool {
		return in_array( $post_type, self::supported(), true );
	}
}
