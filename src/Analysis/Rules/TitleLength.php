<?php
/**
 * SEO title length.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Analysis\Rules;

use DumpSEO\Analysis\Input;
use DumpSEO\Analysis\Result;

defined( 'ABSPATH' ) || exit;

/**
 * Length of the SEO title in characters. Search engines cut titles by pixel
 * width (roughly 600px on desktop); 60 characters is a practical stand-in.
 */
final class TitleLength extends BaseRule {

	public const MIN = 30;
	public const MAX = 60;

	/**
	 * Rule ID.
	 */
	public function id(): string {
		return 'title_length';
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
		$length = mb_strlen( $input->title, 'UTF-8' );
		$meta   = array(
			'length' => $length,
			'min'    => self::MIN,
			'max'    => self::MAX,
		);
		if ( 0 === $length ) {
			return $this->result( Result::ERROR, Result::HIGH, __( 'The page has no SEO title.', 'dumpseo' ), __( 'Give the page a title.', 'dumpseo' ), $meta );
		}
		/* translators: %d: number of characters. */
		$found = sprintf( _n( 'The SEO title is %d character long.', 'The SEO title is %d characters long.', $length, 'dumpseo' ), $length );
		if ( $length < self::MIN ) {
			return $this->result( Result::WARNING, Result::MEDIUM, $found, __( 'Add a few descriptive words; there is room for more.', 'dumpseo' ), $meta );
		}
		if ( $length > self::MAX ) {
			return $this->result( Result::WARNING, Result::MEDIUM, $found, __( 'Shorten the title so search results are less likely to cut it off.', 'dumpseo' ), $meta );
		}
		return $this->result( Result::PASS, Result::MEDIUM, $found, '', $meta );
	}
}
