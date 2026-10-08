<?php
/**
 * Links to other pages of this site.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Analysis\Rules;

use DumpSEO\Analysis\Input;
use DumpSEO\Analysis\Result;

defined( 'ABSPATH' ) || exit;

/**
 * Internal links help readers and search engines find related content.
 */
final class InternalLinks extends BaseRule {

	/**
	 * Rule ID.
	 */
	public function id(): string {
		return 'internal_links';
	}

	/**
	 * Only when there is text.
	 *
	 * @param Input $input Analysis input.
	 */
	public function applies( Input $input ): bool {
		return $input->content->word_count > 0;
	}

	/**
	 * Runs the check.
	 *
	 * @param Input $input Analysis input.
	 */
	public function check( Input $input ): Result {
		$count = 0;
		foreach ( $input->content->links as $href ) {
			if ( \DumpSEO\Analysis\Document::is_internal( $href, $input->host ) ) {
				++$count;
			}
		}
		$meta = array( 'count' => $count );
		if ( 0 === $count ) {
			return $this->result(
				Result::WARNING,
				Result::MEDIUM,
				__( 'The text has no links to other pages on this site.', 'dumpseo' ),
				__( 'Link to related posts or pages where it helps the reader.', 'dumpseo' ),
				$meta
			);
		}
		/* translators: %d: number of links. */
		return $this->result( Result::PASS, Result::MEDIUM, sprintf( _n( 'The text has %d link to this site.', 'The text has %d links to this site.', $count, 'dumpseo' ), $count ), '', $meta );
	}
}
