<?php
/**
 * Finding the focus keyphrase in text.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Analysis;

defined( 'ABSPATH' ) || exit;

/**
 * Case-insensitive, whole-word matching of a keyphrase. Words may be separated
 * by any whitespace or hyphens ("running shoes" matches "Running-Shoes"), and
 * curly apostrophes match straight ones. Word forms (plurals, tenses) are not
 * matched: that needs per-language rules and comes later.
 */
final class Keyphrase {

	/**
	 * Normalized words of the keyphrase.
	 *
	 * @var string[]
	 */
	private $words;

	/**
	 * Match pattern, or '' when the keyphrase is empty.
	 *
	 * @var string
	 */
	private $pattern;

	/**
	 * Constructor.
	 *
	 * @param string $phrase Keyphrase as typed.
	 */
	public function __construct( string $phrase ) {
		$this->words = self::split( $phrase );

		$quoted        = array_map(
			static function ( string $word ): string {
				return preg_quote( $word, '/' );
			},
			$this->words
		);
		$this->pattern = array() === $quoted ? '' : '/(?<![\p{L}\p{N}])' . implode( '[\s\-]+', $quoted ) . '(?![\p{L}\p{N}])/u';
	}

	/**
	 * Whether the keyphrase is empty.
	 */
	public function is_empty(): bool {
		return '' === $this->pattern;
	}

	/**
	 * Number of words in the keyphrase.
	 */
	public function word_count(): int {
		return count( $this->words );
	}

	/**
	 * Normalized keyphrase, for display and comparisons.
	 */
	public function normalized(): string {
		return implode( ' ', $this->words );
	}

	/**
	 * How many times the keyphrase occurs in a text.
	 *
	 * @param string $text Plain text.
	 */
	public function count_in( string $text ): int {
		return '' === $this->pattern ? 0 : (int) preg_match_all( $this->pattern, self::normalize( $text ) );
	}

	/**
	 * Whether the keyphrase occurs in a text.
	 *
	 * @param string $text Plain text.
	 */
	public function in( string $text ): bool {
		return $this->count_in( $text ) > 0;
	}

	/**
	 * Character offset of the first occurrence (0-based), or -1.
	 *
	 * @param string $text Plain text.
	 */
	public function position_in( string $text ): int {
		if ( '' === $this->pattern || ! preg_match( $this->pattern, self::normalize( $text ), $m, PREG_OFFSET_CAPTURE ) ) {
			return -1;
		}
		return mb_strlen( substr( self::normalize( $text ), 0, (int) $m[0][1] ), 'UTF-8' );
	}

	/**
	 * Whether every keyphrase word appears in a URL slug ("best-running-shoes").
	 *
	 * @param string $slug Post slug (may be percent-encoded).
	 */
	public function in_slug( string $slug ): bool {
		if ( '' === $this->pattern ) {
			return false;
		}
		$slug_words = self::split( str_replace( array( '-', '_' ), ' ', rawurldecode( $slug ) ) );
		foreach ( $this->words as $word ) {
			// Slugs keep only letters and digits ("don't" → "dont", "c++" → "c").
			if ( ! in_array( (string) preg_replace( '/[^\p{L}\p{N}]/u', '', $word ), $slug_words, true ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Lowercase, straight quotes, single spaces.
	 *
	 * @param string $text Text.
	 */
	public static function normalize( string $text ): string {
		$text = str_replace( array( "\u{2019}", "\u{2018}", "\u{201C}", "\u{201D}" ), array( "'", "'", '"', '"' ), $text );
		return trim( (string) preg_replace( '/\s+/u', ' ', mb_strtolower( $text, 'UTF-8' ) ) );
	}

	/**
	 * Normalized words, punctuation at word edges removed (except a trailing
	 * "+" or "#", as in "C++" and "C#").
	 *
	 * @param string $text Text.
	 * @return string[]
	 */
	private static function split( string $text ): array {
		$words = array();
		foreach ( (array) preg_split( '/[\s\-]+/u', self::normalize( $text ), -1, PREG_SPLIT_NO_EMPTY ) as $word ) {
			$word = (string) preg_replace( '/^[^\p{L}\p{N}]+|[^\p{L}\p{N}+#]+$/u', '', (string) $word );
			if ( '' !== $word ) {
				$words[] = $word;
			}
		}
		return $words;
	}
}
