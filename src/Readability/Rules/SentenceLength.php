<?php
/**
 * Long sentences.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Readability\Rules;

use DumpSEO\Analysis\Input;
use DumpSEO\Analysis\Result;
use DumpSEO\Analysis\Rules\BaseRule;

defined( 'ABSPATH' ) || exit;

/**
 * Share of sentences longer than 20 words. Up to 25% → pass; up to 40% →
 * warning; more → error. Needs at least 50 words. Works for any language.
 */
final class SentenceLength extends BaseRule {

	public const MAX_WORDS = 20;

	/**
	 * Rule ID.
	 */
	public function id(): string {
		return 'sentence_length';
	}

	/**
	 * Only for texts of at least 50 words.
	 *
	 * @param Input $input Analysis input.
	 */
	public function applies( Input $input ): bool {
		return $input->content->word_count >= 50 && array() !== $input->sentences();
	}

	/**
	 * Runs the check.
	 *
	 * @param Input $input Analysis input.
	 */
	public function check( Input $input ): Result {
		$sentences = $input->sentences();
		$long      = 0;
		foreach ( $sentences as $sentence ) {
			if ( \DumpSEO\Analysis\Document::count_words( $sentence ) > self::MAX_WORDS ) {
				++$long;
			}
		}
		$percent = round( $long / count( $sentences ) * 100, 1 );
		$meta    = array(
			'long'      => $long,
			'sentences' => count( $sentences ),
			'percent'   => $percent,
		);
		/* translators: 1: percentage of sentences, 2: word limit. */
		$found = sprintf( __( '%1$s%% of sentences are longer than %2$d words.', 'dumpseo' ), (string) $percent, self::MAX_WORDS );

		if ( $percent <= 25 ) {
			return $this->result( Result::PASS, Result::MEDIUM, $found, '', $meta );
		}
		$advice = __( 'Split long sentences, or cut words that do not add meaning.', 'dumpseo' );
		return $this->result( $percent > 40 ? Result::ERROR : Result::WARNING, Result::MEDIUM, $found, $advice, $meta );
	}
}
