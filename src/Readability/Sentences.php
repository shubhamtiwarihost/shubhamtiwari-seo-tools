<?php
/**
 * Sentence and syllable helpers.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Readability;

use DumpSEO\Analysis\Document;

defined( 'ABSPATH' ) || exit;

/**
 * Splits plain text into sentences and estimates English syllables.
 * Pure PHP, no WordPress, so it can be unit-tested and ported to JavaScript.
 */
final class Sentences {

	/**
	 * Abbreviations whose trailing period does not end a sentence (lowercase, without the period).
	 */
	private const ABBREVIATIONS = array( 'mr', 'mrs', 'ms', 'dr', 'prof', 'sr', 'jr', 'st', 'vs', 'etc', 'e.g', 'i.e', 'approx', 'no', 'fig', 'inc', 'ltd', 'co', 'mt', 'jan', 'feb', 'mar', 'apr', 'jun', 'jul', 'aug', 'sep', 'sept', 'oct', 'nov', 'dec' );

	/**
	 * Sentences of a plain-text block.
	 *
	 * A sentence ends at ".", "!", "?" or "…" (optionally followed by closing
	 * quotes or brackets) when whitespace and then an uppercase letter, digit
	 * or opening quote follow, or at the end of the block. Periods after known
	 * abbreviations, initials ("J. R. Smith") and inside numbers ("3.5") do not
	 * end sentences.
	 *
	 * @param string $text Plain text.
	 * @return string[]
	 */
	public static function split( string $text ): array {
		$text = trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
		if ( '' === $text ) {
			return array();
		}

		$parts = preg_split( '/(?<=[.!?…])(["\'”’)\]]*)\s+(?=["\'“‘(\[]?[\p{Lu}\p{N}])/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE );
		$parts = is_array( $parts ) ? $parts : array( $text );

		$sentences = array();
		$current   = '';
		$count     = count( $parts );
		for ( $i = 0; $i < $count; $i += 2 ) {
			$current .= $parts[ $i ] . ( $parts[ $i + 1 ] ?? '' );
			if ( $i + 2 < $count && self::is_abbreviation_end( $parts[ $i ] ) ) {
				$current .= ' ';
				continue;
			}
			$current = trim( $current );
			if ( Document::count_words( $current ) > 0 ) {
				$sentences[] = $current;
			}
			$current = '';
		}
		return $sentences;
	}

	/**
	 * Whether a chunk ends with an abbreviation or an initial, not a sentence end.
	 *
	 * @param string $chunk Text before a candidate boundary.
	 */
	private static function is_abbreviation_end( string $chunk ): bool {
		if ( ! preg_match( '/(\S+)\.$/u', $chunk, $m ) ) {
			return false;
		}
		$word = mb_strtolower( ltrim( $m[1], '("\'“‘[' ), 'UTF-8' );
		return in_array( $word, self::ABBREVIATIONS, true ) || (bool) preg_match( '/^\p{Lu}$/u', ltrim( $m[1], '("\'“‘[' ) );
	}

	/**
	 * Estimated number of syllables in an English word (at least 1).
	 *
	 * Counts vowel groups (y counts as a vowel except at the start), then
	 * corrects common silent endings: a final "e" ("make"), "-es"/"-ed"
	 * after most consonants ("makes", "jumped"), while keeping "-le" after
	 * a consonant ("table") and "-ted"/"-ded" ("wanted") as syllables.
	 *
	 * @param string $word Word.
	 */
	public static function syllables( string $word ): int {
		$word = (string) preg_replace( '/[^a-z]/', '', strtolower( $word ) );
		if ( '' === $word ) {
			return 0;
		}
		if ( strlen( $word ) <= 3 ) {
			return 1;
		}

		$scan  = 'y' === $word[0] ? 'x' . substr( $word, 1 ) : $word; // A leading y is a consonant: "yellow".
		$count = (int) preg_match_all( '/[aeiouy]+/', $scan );

		if ( preg_match( '/[^aeiouy]e$/', $word ) && ! preg_match( '/[^aeiouy]le$/', $word ) ) {
			--$count; // Silent final e: make, love.
		} elseif ( preg_match( '/[^aeiouydt]ed$/', $word ) || preg_match( '/[^aeiouyszxhgc]es$/', $word ) ) {
			--$count; // jumped, makes (but wanted, boxes, pages stay).
		}

		return max( 1, $count );
	}
}
