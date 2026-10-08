<?php
/**
 * Keyphrase in the URL slug.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Analysis\Rules;

use DumpSEO\Analysis\Input;
use DumpSEO\Analysis\Result;

defined( 'ABSPATH' ) || exit;

/**
 * Every word of the keyphrase should appear in the slug.
 */
final class KeyphraseInSlug extends BaseRule {

	/**
	 * Rule ID.
	 */
	public function id(): string {
		return 'keyphrase_in_slug';
	}

	/**
	 * Only when the post has a slug.
	 *
	 * @param Input $input Analysis input.
	 */
	public function applies( Input $input ): bool {
		return $input->has_keyphrase() && '' !== $input->slug;
	}

	/**
	 * Runs the check.
	 *
	 * @param Input $input Analysis input.
	 */
	public function check( Input $input ): Result {
		if ( $input->keyphrase->in_slug( $input->slug ) ) {
			return $this->result( Result::PASS, Result::LOW, __( 'The URL slug contains the words of the focus keyphrase.', 'dumpseo' ) );
		}
		return $this->result(
			Result::WARNING,
			Result::LOW,
			__( 'The URL slug does not contain all words of the focus keyphrase.', 'dumpseo' ),
			__( 'Before publishing, edit the slug to include the keyphrase. Changing the slug of a published post changes its address.', 'dumpseo' )
		);
	}
}
