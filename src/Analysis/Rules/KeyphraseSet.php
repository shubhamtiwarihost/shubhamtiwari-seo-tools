<?php
/**
 * Whether a focus keyphrase is set.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Analysis\Rules;

use DumpSEO\Analysis\Input;
use DumpSEO\Analysis\Result;

defined( 'ABSPATH' ) || exit;

/**
 * Reminds the author to set a focus keyphrase; the keyphrase rules need one.
 */
final class KeyphraseSet extends BaseRule {

	/**
	 * Rule ID.
	 */
	public function id(): string {
		return 'keyphrase_set';
	}

	/**
	 * Only when no keyphrase is set.
	 *
	 * @param Input $input Analysis input.
	 */
	public function applies( Input $input ): bool {
		return ! $input->has_keyphrase();
	}

	/**
	 * Runs the check.
	 *
	 * @param Input $input Analysis input.
	 */
	public function check( Input $input ): Result {
		return $this->result(
			Result::INFO,
			Result::MEDIUM,
			__( 'No focus keyphrase is set, so keyphrase checks are skipped.', 'dumpseo' ),
			__( 'Enter the words people would search for to find this page.', 'dumpseo' )
		);
	}
}
