<?php
/**
 * Flesch reading ease.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Readability\Rules;

use DumpSEO\Analysis\Input;
use DumpSEO\Analysis\Result;
use DumpSEO\Analysis\Rules\BaseRule;
use DumpSEO\Analysis\Document;
use DumpSEO\Readability\Sentences;

defined( 'ABSPATH' ) || exit;

/**
 * Flesch reading ease (Rudolf Flesch, 1948), English only:
 * 206.835 − 1.015 × (words ÷ sentences) − 84.6 × (syllables ÷ words).
 * Higher is easier. 60 or more → pass, otherwise warning. Needs 100+ words.
 * Syllables are estimated (see Sentences::syllables), so the score is approximate.
 */
final class ReadingEase extends BaseRule {

	public const MIN_SCORE = 60;

	/**
	 * Rule ID.
	 */
	public function id(): string {
		return 'reading_ease';
	}

	/**
	 * English texts of at least 100 words.
	 *
	 * @param Input $input Analysis input.
	 */
	public function applies( Input $input ): bool {
		return $input->is_english() && $input->content->word_count >= 100 && array() !== $input->sentences();
	}

	/**
	 * Flesch reading ease of a list of sentences, or null without words.
	 *
	 * @param string[] $sentences Sentences.
	 */
	public static function score( array $sentences ): ?float {
		$words     = 0;
		$syllables = 0;
		foreach ( $sentences as $sentence ) {
			foreach ( Document::words( $sentence ) as $word ) {
				++$words;
				$syllables += max( 1, Sentences::syllables( $word ) );
			}
		}
		if ( 0 === $words || array() === $sentences ) {
			return null;
		}
		return round( 206.835 - 1.015 * ( $words / count( $sentences ) ) - 84.6 * ( $syllables / $words ), 1 );
	}

	/**
	 * Runs the check.
	 *
	 * @param Input $input Analysis input.
	 */
	public function check( Input $input ): Result {
		$score = (float) self::score( $input->sentences() );
		$meta  = array(
			'score' => $score,
			'label' => $this->label( $score ),
		);
		/* translators: 1: score, 2: difficulty label such as "fairly difficult". */
		$found = sprintf( __( 'Flesch reading ease is %1$s (%2$s).', 'dumpseo' ), (string) $score, $this->label( $score ) );

		if ( $score >= self::MIN_SCORE ) {
			return $this->result( Result::PASS, Result::MEDIUM, $found, '', $meta );
		}
		return $this->result(
			Result::WARNING,
			Result::MEDIUM,
			$found,
			__( 'Use shorter sentences and more everyday words, unless your readers expect technical language.', 'dumpseo' ),
			$meta
		);
	}

	/**
	 * Plain-language label for a score.
	 *
	 * @param float $score Score.
	 */
	private function label( float $score ): string {
		if ( $score >= 80 ) {
			return __( 'easy', 'dumpseo' );
		}
		if ( $score >= 70 ) {
			return __( 'fairly easy', 'dumpseo' );
		}
		if ( $score >= 60 ) {
			return __( 'plain', 'dumpseo' );
		}
		if ( $score >= 50 ) {
			return __( 'fairly difficult', 'dumpseo' );
		}
		if ( $score >= 30 ) {
			return __( 'difficult', 'dumpseo' );
		}
		return __( 'very difficult', 'dumpseo' );
	}
}
