<?php
/**
 * Amount of text.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Analysis\Rules;

use DumpSEO\Analysis\Input;
use DumpSEO\Analysis\Result;

defined( 'ABSPATH' ) || exit;

/**
 * Very short pages rarely answer a search well. Bands: under 150 words →
 * error, under 300 → warning, otherwise pass. Pages (post type "page") get
 * info instead of error/warning, because contact or landing pages are short
 * on purpose.
 */
final class ContentLength extends BaseRule {

	/**
	 * Rule ID.
	 */
	public function id(): string {
		return 'content_length';
	}

	/**
	 * Always applies.
	 *
	 * @param Input $input Analysis input.
	 */
	public function applies( Input $input ): bool {
		return true;
	}

	/**
	 * Runs the check.
	 *
	 * @param Input $input Analysis input.
	 */
	public function check( Input $input ): Result {
		$words = $input->content->word_count;
		$meta  = array( 'words' => $words );
		/* translators: %d: number of words. */
		$found = sprintf( _n( 'The text contains %d word.', 'The text contains %d words.', $words, 'dumpseo' ), $words );

		if ( $words >= 300 ) {
			return $this->result( Result::PASS, Result::MEDIUM, $found, '', $meta );
		}
		$advice = __( 'Consider covering the topic in more depth, if readers would benefit.', 'dumpseo' );
		if ( 'page' === $input->post_type ) {
			return $this->result( Result::INFO, Result::LOW, $found, $advice, $meta );
		}
		return $this->result( $words < 150 ? Result::ERROR : Result::WARNING, Result::MEDIUM, $found, $advice, $meta );
	}
}
