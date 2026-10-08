<?php
/**
 * SEO form fields shared by the term screen and the Classic Editor metabox.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Admin;

use DumpSEO\Helpers\Text;
use DumpSEO\Meta\Keys;
use DumpSEO\Meta\Robots;

defined( 'ABSPATH' ) || exit;

/**
 * Field names and the rules that turn a submitted form into meta values.
 * Callers verify the nonce and capability first.
 */
final class SeoForm {

	public const TITLE_FIELD        = 'dumpseo_title';
	public const DESC_FIELD         = 'dumpseo_description';
	public const CANON_FIELD        = 'dumpseo_canonical';
	public const INDEX_FIELD        = 'dumpseo_robots_index';
	public const ROBOT_FIELD        = 'dumpseo_robots';
	public const SOCIAL_TITLE_FIELD = 'dumpseo_social_title';
	public const SOCIAL_DESC_FIELD  = 'dumpseo_social_description';
	public const SOCIAL_IMAGE_FIELD = 'dumpseo_social_image';
	public const KEYPHRASE_FIELD    = 'dumpseo_focus_keyphrase';

	/**
	 * Robots directives offered as checkboxes (index/noindex is a separate choice).
	 */
	public const EXTRA_DIRECTIVES = array( 'nofollow', 'noarchive', 'nosnippet', 'noimageindex' );

	/**
	 * Meta values from a submitted form.
	 *
	 * Returns meta key => value, where '' means "delete" and null means "keep
	 * the stored value" (an invalid URL keeps the previous one instead of
	 * silently clearing it). Fields missing from the form are cleared, like
	 * any HTML form.
	 *
	 * @param array<string, mixed> $input          Unslashed form data.
	 * @param bool                 $with_keyphrase Whether the form has a focus keyphrase field.
	 * @return array<string, string|null>
	 */
	public static function values( array $input, bool $with_keyphrase ): array {
		$lines = array(
			self::TITLE_FIELD        => Keys::TITLE,
			self::DESC_FIELD         => Keys::DESCRIPTION,
			self::SOCIAL_TITLE_FIELD => Keys::SOCIAL_TITLE,
			self::SOCIAL_DESC_FIELD  => Keys::SOCIAL_DESCRIPTION,
		);
		if ( $with_keyphrase ) {
			$lines[ self::KEYPHRASE_FIELD ] = Keys::FOCUS_KEYPHRASE;
		}

		$values = array();
		foreach ( $lines as $field => $meta_key ) {
			$values[ $meta_key ] = isset( $input[ $field ] ) && is_string( $input[ $field ] ) ? Text::sanitize_line( $input[ $field ] ) : '';
		}
		if ( $with_keyphrase ) {
			$values[ Keys::FOCUS_KEYPHRASE ] = sanitize_text_field( (string) $values[ Keys::FOCUS_KEYPHRASE ] );
		}

		$urls = array(
			self::CANON_FIELD        => Keys::CANONICAL,
			self::SOCIAL_IMAGE_FIELD => Keys::SOCIAL_IMAGE,
		);
		foreach ( $urls as $field => $meta_key ) {
			$values[ $meta_key ] = isset( $input[ $field ] ) ? Text::http_url( $input[ $field ] ) : '';
		}

		$tokens = array();
		if ( isset( $input[ self::INDEX_FIELD ] ) && is_string( $input[ self::INDEX_FIELD ] ) ) {
			$tokens[] = sanitize_key( $input[ self::INDEX_FIELD ] );
		}
		if ( isset( $input[ self::ROBOT_FIELD ] ) && is_array( $input[ self::ROBOT_FIELD ] ) ) {
			$extra  = array_map( 'sanitize_key', array_filter( $input[ self::ROBOT_FIELD ], 'is_string' ) );
			$tokens = array_merge( $tokens, array_intersect( $extra, self::EXTRA_DIRECTIVES ) );
		}
		$values[ Keys::ROBOTS ] = Robots::sanitize( $tokens );

		return $values;
	}

	/**
	 * Stores values from values() with the given meta functions.
	 *
	 * @param array<string, string|null> $values    Meta key => value ('' delete, null keep).
	 * @param callable                   $update_fn Called with (meta key, value).
	 * @param callable                   $delete_fn Called with (meta key).
	 */
	public static function apply( array $values, callable $update_fn, callable $delete_fn ): void {
		foreach ( $values as $meta_key => $value ) {
			if ( '' === $value ) {
				$delete_fn( $meta_key );
			} elseif ( null !== $value ) {
				$update_fn( $meta_key, $value );
			}
		}
	}

	/**
	 * Labels for the extra robots directives.
	 *
	 * @return array<string, string>
	 */
	public static function directive_labels(): array {
		return array(
			'nofollow'     => __( 'Do not follow links (nofollow)', 'dumpseo' ),
			'noarchive'    => __( 'Do not show a cached copy (noarchive)', 'dumpseo' ),
			'nosnippet'    => __( 'Do not show a text snippet (nosnippet)', 'dumpseo' ),
			'noimageindex' => __( 'Do not index images (noimageindex)', 'dumpseo' ),
		);
	}
}
