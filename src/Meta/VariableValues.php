<?php
/**
 * Values for template variables.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Meta;

defined( 'ABSPATH' ) || exit;

/**
 * Supplies the value of every %%variable%% for a page context.
 *
 * Values that cost queries (excerpt, category, author) are closures, evaluated
 * only if the template actually uses them.
 *
 * Variables:
 *   site_name, sitedesc, separator (handled by the engine), page, currentyear,
 *   title, excerpt, description, category, author, date, searchphrase,
 *   pt_singular, pt_plural
 */
class VariableValues {

	/**
	 * Maximum length of an auto-generated excerpt, in characters.
	 */
	public const EXCERPT_LENGTH = 155;

	/**
	 * Values for a context.
	 *
	 * @param PageContext $context Page context.
	 * @return array<string, string|\Closure(): string>
	 */
	public function for_context( PageContext $context ): array {
		$values = array(
			'site_name'    => static function (): string {
				return (string) get_bloginfo( 'name' );
			},
			'sitedesc'     => static function (): string {
				return (string) get_bloginfo( 'description' );
			},
			'currentyear'  => static function (): string {
				return (string) wp_date( 'Y' );
			},
			'page'         => $this->page_label( $context ),
			'searchphrase' => $context->search,
		);

		$object = $context->object;

		if ( $object instanceof \WP_Post ) {
			$values = array_merge( $values, $this->post_values( $object ) );
		} elseif ( $object instanceof \WP_Term ) {
			$values['title']       = $object->name;
			$values['description'] = $object->description;
			$values['category']    = $object->name;
		} elseif ( $object instanceof \WP_User ) {
			$values['title']       = $object->display_name;
			$values['author']      = $object->display_name;
			$values['description'] = static function () use ( $object ): string {
				return (string) get_user_meta( $object->ID, 'description', true );
			};
		} elseif ( $object instanceof \WP_Post_Type ) {
			$values['title']       = (string) $object->labels->name;
			$values['pt_plural']   = (string) $object->labels->name;
			$values['pt_singular'] = (string) $object->labels->singular_name;
			$values['description'] = (string) $object->description;
		}

		if ( PageContext::DATE === $context->type ) {
			$values['title'] = $context->date_label;
			$values['date']  = $context->date_label;
		}
		if ( PageContext::SEARCH === $context->type ) {
			$values['title'] = $context->search;
		}

		/**
		 * Filters template variable values.
		 *
		 * Values may be strings or closures returning strings. Returned values
		 * are treated as plain text: tags are stripped.
		 *
		 * @param array<string, mixed> $values  Values keyed by variable name (without %%).
		 * @param PageContext          $context Page context.
		 */
		$filtered = apply_filters( 'dumpseo_template_variables', $values, $context );

		$clean = array();
		foreach ( (array) $filtered as $name => $value ) {
			if ( is_string( $value ) || $value instanceof \Closure ) {
				$clean[ (string) $name ] = $value;
			}
		}
		return $clean;
	}

	/**
	 * Values derived from a post.
	 *
	 * @param \WP_Post $post Post.
	 * @return array<string, string|\Closure(): string>
	 */
	private function post_values( \WP_Post $post ): array {
		$type = get_post_type_object( $post->post_type );

		return array(
			'title'       => $post->post_title,
			'excerpt'     => static function () use ( $post ): string {
				return self::excerpt( $post );
			},
			'category'    => static function () use ( $post ): string {
				return self::first_category( $post );
			},
			'author'      => static function () use ( $post ): string {
				return (string) get_the_author_meta( 'display_name', (int) $post->post_author );
			},
			'date'        => static function () use ( $post ): string {
				return (string) get_the_date( '', $post );
			},
			'pt_singular' => $type ? (string) $type->labels->singular_name : '',
			'pt_plural'   => $type ? (string) $type->labels->name : '',
		);
	}

	/**
	 * The manual excerpt, or the start of the content, as plain text.
	 *
	 * Password-protected posts never expose their content.
	 *
	 * @param \WP_Post $post Post.
	 */
	public static function excerpt( \WP_Post $post ): string {
		if ( '' !== $post->post_password ) {
			return '';
		}

		$text = '' !== trim( $post->post_excerpt ) ? $post->post_excerpt : strip_shortcodes( $post->post_content );
		$text = html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		return self::truncate( $text, self::EXCERPT_LENGTH );
	}

	/**
	 * Truncates at a word boundary and appends an ellipsis when shortened.
	 *
	 * @param string $text   Plain text.
	 * @param int    $length Maximum length in characters, including the ellipsis.
	 */
	public static function truncate( string $text, int $length ): string {
		$text = trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
		if ( mb_strlen( $text ) <= $length ) {
			return $text;
		}

		$cut = mb_substr( $text, 0, $length - 1 );

		// If the cut lands mid-word, back up to the previous space (unless that would drop over half the text).
		if ( ' ' !== mb_substr( $text, $length - 1, 1 ) ) {
			$space = mb_strrpos( $cut, ' ' );
			if ( false !== $space && $space > $length / 2 ) {
				$cut = mb_substr( $cut, 0, $space );
			}
		}
		return rtrim( $cut, " \t,;:.-–—" ) . '…';
	}

	/**
	 * Name of the post's first category (alphabetical), or '' when none.
	 *
	 * @param \WP_Post $post Post.
	 */
	private static function first_category( \WP_Post $post ): string {
		if ( ! is_object_in_taxonomy( $post->post_type, 'category' ) ) {
			return '';
		}
		$terms = get_the_terms( $post, 'category' );
		if ( ! is_array( $terms ) || array() === $terms ) {
			return '';
		}
		usort(
			$terms,
			static function ( \WP_Term $a, \WP_Term $b ): int {
				return strcasecmp( $a->name, $b->name );
			}
		);
		return $terms[0]->name;
	}

	/**
	 * "Page 2 of 5" on page 2+, otherwise empty.
	 *
	 * @param PageContext $context Page context.
	 */
	private function page_label( PageContext $context ): string {
		if ( $context->page < 2 ) {
			return '';
		}
		if ( $context->total_pages >= $context->page ) {
			/* translators: 1: current page number, 2: total number of pages. */
			return sprintf( __( 'Page %1$d of %2$d', 'dumpseo' ), $context->page, $context->total_pages );
		}
		/* translators: %d: current page number. */
		return sprintf( __( 'Page %d', 'dumpseo' ), $context->page );
	}
}
