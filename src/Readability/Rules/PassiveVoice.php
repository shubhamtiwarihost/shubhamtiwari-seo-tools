<?php
/**
 * Passive voice indicators.
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
 * Share of sentences with a passive-voice pattern (English only). Up to 10%
 * → pass, otherwise warning. Pattern-based, so treat it as a hint.
 */
final class PassiveVoice extends BaseRule {

	public const MAX_PERCENT = 10;

	/**
	 * Rule ID.
	 */
	public function id(): string {
		return 'passive_voice';
	}

	/**
	 * English texts with sentences.
	 *
	 * @param Input $input Analysis input.
	 */
	public function applies( Input $input ): bool {
		return $input->is_english() && array() !== $input->sentences();
	}

	/**
	 * Runs the check.
	 *
	 * @param Input $input Analysis input.
	 */
	public function check( Input $input ): Result {
		$sentences = $input->sentences();
		$passive   = count( array_filter( $sentences, array( English::class, 'is_passive' ) ) );
		$percent   = round( $passive / count( $sentences ) * 100, 1 );
		$meta      = array(
			'passive'   => $passive,
			'sentences' => count( $sentences ),
			'percent'   => $percent,
		);
		/* translators: %s: percentage of sentences. */
		$found = sprintf( __( '%s%% of sentences look like passive voice.', 'dumpseo' ), (string) $percent );

		if ( $percent <= self::MAX_PERCENT ) {
			return $this->result( Result::PASS, Result::LOW, $found, '', $meta );
		}
		return $this->result(
			Result::WARNING,
			Result::LOW,
			$found,
			__( 'Where it reads better, say who does what: “We tested the shoes” instead of “The shoes were tested”.', 'dumpseo' ),
			$meta
		);
	}
}
