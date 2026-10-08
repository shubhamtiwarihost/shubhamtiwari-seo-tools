<?php
/**
 * How often the keyphrase is used.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Analysis\Rules;

use DumpSEO\Analysis\Input;
use DumpSEO\Analysis\Result;

defined( 'ABSPATH' ) || exit;

/**
 * Keyphrase uses per 100 words. Too few suggests the page is about something
 * else; too many reads as repetitive ("keyword stuffing").
 *
 * Bands: 0 → warning; below 0.5 → warning; 0.5–3 → pass; above 3 → warning;
 * above 4.5 → error. Skipped for texts under 100 words (too little to measure).
 */
final class KeyphraseDensity extends BaseRule {

	/**
	 * Rule ID.
	 */
	public function id(): string {
		return 'keyphrase_density';
	}

	/**
	 * Only with a keyphrase and at least 100 words.
	 *
	 * @param Input $input Analysis input.
	 */
	public function applies( Input $input ): bool {
		return $input->has_keyphrase() && $input->content->word_count >= 100;
	}

	/**
	 * Runs the check.
	 *
	 * @param Input $input Analysis input.
	 */
	public function check( Input $input ): Result {
		$count   = $input->keyphrase->count_in( $input->content->text );
		$density = round( $count / $input->content->word_count * 100, 2 );
		$meta    = array(
			'count'   => $count,
			'words'   => $input->content->word_count,
			'density' => $density,
		);

		if ( 0 === $count ) {
			return $this->result(
				Result::WARNING,
				Result::HIGH,
				__( 'The focus keyphrase does not appear in the text.', 'dumpseo' ),
				__( 'Use the keyphrase where it fits naturally in the text.', 'dumpseo' ),
				$meta
			);
		}
		/* translators: 1: number of times the keyphrase is used, 2: uses per 100 words. */
		$found = sprintf( _n( 'The focus keyphrase is used %1$d time (%2$s per 100 words).', 'The focus keyphrase is used %1$d times (%2$s per 100 words).', $count, 'dumpseo' ), $count, (string) $density );

		if ( $density < 0.5 ) {
			return $this->result( Result::WARNING, Result::MEDIUM, $found, __( 'Use the keyphrase a little more often.', 'dumpseo' ), $meta );
		}
		if ( $density > 4.5 ) {
			return $this->result( Result::ERROR, Result::HIGH, $found, __( 'This is far more than reads naturally. Replace some uses with synonyms or pronouns.', 'dumpseo' ), $meta );
		}
		if ( $density > 3 ) {
			return $this->result( Result::WARNING, Result::MEDIUM, $found, __( 'Use the keyphrase a little less often so the text does not feel repetitive.', 'dumpseo' ), $meta );
		}
		return $this->result( Result::PASS, Result::HIGH, $found, '', $meta );
	}
}
