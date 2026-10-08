<?php
/**
 * Transition words.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Readability\Rules;

use DumpSEO\Analysis\Input;
use DumpSEO\Analysis\Result;
use DumpSEO\Analysis\Rules\BaseRule;
use DumpSEO\Readability\English;

defined( 'ABSPATH' ) || exit;

/**
 * Share of sentences with a transition word or phrase (English only), which
 * help readers follow the argument. At least 30% → pass, otherwise warning.
 * Needs at least 5 sentences.
 */
final class TransitionWords extends BaseRule {

	public const MIN_PERCENT = 30;

	/**
	 * Rule ID.
	 */
	public function id(): string {
		return 'transition_words';
	}

	/**
	 * English texts with at least 5 sentences.
	 *
	 * @param Input $input Analysis input.
	 */
	public function applies( Input $input ): bool {
		return $input->is_english() && count( $input->sentences() ) >= 5;
	}

	/**
	 * Runs the check.
	 *
	 * @param Input $input Analysis input.
	 */
	public function check( Input $input ): Result {
		$sentences = $input->sentences();
		$with      = count( array_filter( $sentences, array( English::class, 'has_transition' ) ) );
		$percent   = round( $with / count( $sentences ) * 100, 1 );
		$meta      = array(
			'with_transition' => $with,
			'sentences'       => count( $sentences ),
			'percent'         => $percent,
		);
		/* translators: %s: percentage of sentences. */
		$found = sprintf( __( '%s%% of sentences contain a transition word (such as “however” or “for example”).', 'dumpseo' ), (string) $percent );

		if ( $percent >= self::MIN_PERCENT ) {
			return $this->result( Result::PASS, Result::LOW, $found, '', $meta );
		}
		return $this->result(
			Result::WARNING,
			Result::LOW,
			$found,
			__( 'Link sentences with words like “because”, “however” or “as a result” so the text flows.', 'dumpseo' ),
			$meta
		);
	}
}
