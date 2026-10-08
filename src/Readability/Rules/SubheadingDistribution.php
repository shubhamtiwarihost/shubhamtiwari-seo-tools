<?php
/**
 * Subheadings spread through the text.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Readability\Rules;

use DumpSEO\Analysis\Input;
use DumpSEO\Analysis\Result;
use DumpSEO\Analysis\Rules\BaseRule;

defined( 'ABSPATH' ) || exit;

/**
 * Texts over 300 words need subheadings, and no stretch between them should
 * run past 300 words, so readers can scan. Works for any language.
 */
final class SubheadingDistribution extends BaseRule {

	public const MAX_WORDS = 300;

	/**
	 * Rule ID.
	 */
	public function id(): string {
		return 'subheading_distribution';
	}

	/**
	 * Only for texts over 300 words.
	 *
	 * @param Input $input Analysis input.
	 */
	public function applies( Input $input ): bool {
		return $input->content->word_count > self::MAX_WORDS;
	}

	/**
	 * Runs the check.
	 *
	 * @param Input $input Analysis input.
	 */
	public function check( Input $input ): Result {
		$sections = $input->content->section_words;
		$long     = count(
			array_filter(
				$sections,
				static function ( int $words ): bool {
					return $words > self::MAX_WORDS;
				}
			)
		);
		$meta     = array(
			'subheadings' => count( $sections ) - 1,
			'long'        => $long,
			'longest'     => (int) max( $sections ),
		);

		if ( 1 === count( $sections ) ) {
			return $this->result(
				Result::WARNING,
				Result::MEDIUM,
				__( 'The text has no subheadings.', 'dumpseo' ),
				__( 'Add subheadings so readers can scan the text and find the part they need.', 'dumpseo' ),
				$meta
			);
		}
		if ( $long > 0 ) {
			return $this->result(
				Result::WARNING,
				Result::MEDIUM,
				/* translators: 1: number of sections, 2: word limit. */
				sprintf( _n( '%1$d section runs longer than %2$d words without a subheading.', '%1$d sections run longer than %2$d words without a subheading.', $long, 'dumpseo' ), $long, self::MAX_WORDS ),
				__( 'Add a subheading where the topic shifts.', 'dumpseo' ),
				$meta
			);
		}
		return $this->result( Result::PASS, Result::MEDIUM, __( 'Subheadings are spread well through the text.', 'dumpseo' ), '', $meta );
	}
}
