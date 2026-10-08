<?php
/**
 * Parsed post content.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Analysis;

defined( 'ABSPATH' ) || exit;

/**
 * The parts of post HTML that rules look at: plain text, words, first
 * paragraph, headings, links and images.
 *
 * Plain PHP (regular expressions, no DOM extension, no WordPress functions)
 * so it behaves the same on every host and in unit tests. Built for the HTML
 * WordPress editors produce, not for arbitrary documents.
 */
final class Document {

	/**
	 * Plain text of the whole content.
	 *
	 * @var string
	 */
	public $text;

	/**
	 * Number of words.
	 *
	 * @var int
	 */
	public $word_count;

	/**
	 * Plain text of the first paragraph (or first 100 words when there are no paragraphs).
	 *
	 * @var string
	 */
	public $intro;

	/**
	 * Plain text of each non-empty paragraph. Without <p> tags (classic editor
	 * text before wpautop), blank lines separate paragraphs.
	 *
	 * @var string[]
	 */
	public $paragraphs;

	/**
	 * Plain text of each non-empty list item.
	 *
	 * @var string[]
	 */
	public $list_items;

	/**
	 * Word counts of the text before the first subheading and after each one.
	 *
	 * @var int[]
	 */
	public $section_words;

	/**
	 * Plain text of each h2–h6 heading.
	 *
	 * @var string[]
	 */
	public $subheadings;

	/**
	 * Number of h1 elements.
	 *
	 * @var int
	 */
	public $h1_count;

	/**
	 * Link targets (href values), excluding in-page anchors, mailto: and tel:.
	 *
	 * @var string[]
	 */
	public $links;

	/**
	 * One entry per <img>: its alt text ('' when missing or empty).
	 *
	 * @var string[]
	 */
	public $image_alts;

	/**
	 * Constructor.
	 *
	 * @param string $html Post content HTML.
	 */
	public function __construct( string $html ) {
		$html = (string) preg_replace( '/<!--.*?-->/s', '', $html );
		$html = (string) preg_replace( '@<(script|style|noscript)\b[^>]*>.*?</\1>@is', '', $html );

		$this->text       = self::plain( $html );
		$this->word_count = self::count_words( $this->text );

		$has_p            = (bool) preg_match_all( '@<p\b[^>]*>(.*?)</p>@is', $html, $paragraphs );
		$raw              = $has_p ? $paragraphs[1] : (array) preg_split( '/\n\s*\n/', $html );
		$this->paragraphs = array_values( array_filter( array_map( array( self::class, 'plain' ), array_map( 'strval', $raw ) ) ) );

		preg_match_all( '@<li\b[^>]*>(.*?)</li>@is', $html, $items );
		$this->list_items = array_values( array_filter( array_map( array( self::class, 'plain' ), $items[1] ) ) );

		$this->section_words = array();
		foreach ( (array) preg_split( '@<h[2-6]\b[^>]*>.*?</h[2-6]>@is', $html ) as $section ) {
			$this->section_words[] = self::count_words( self::plain( (string) $section ) );
		}

		// Without <p> tags the first "paragraph" may be the whole text, so only its first 100 words count as the intro.
		$this->intro = $this->paragraphs[0] ?? '';
		if ( ! $has_p ) {
			$this->intro = implode( ' ', array_slice( self::words( $this->intro ), 0, 100 ) );
		}

		preg_match_all( '@<h([2-6])\b[^>]*>(.*?)</h\1>@is', $html, $headings );
		$this->subheadings = array_values( array_filter( array_map( array( self::class, 'plain' ), $headings[2] ) ) );
		$this->h1_count    = (int) preg_match_all( '@<h1\b@i', $html );

		$this->links = array();
		preg_match_all( '@<a\b[^>]*?\bhref\s*=\s*(["\'])(.*?)\1@is', $html, $anchors );
		foreach ( $anchors[2] as $href ) {
			$href = trim( html_entity_decode( $href, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
			if ( '' !== $href && '#' !== $href[0] && ! preg_match( '/^(mailto|tel|javascript):/i', $href ) ) {
				$this->links[] = $href;
			}
		}

		$this->image_alts = array();
		preg_match_all( '@<img\b[^>]*>@i', $html, $images );
		foreach ( $images[0] as $tag ) {
			$alt                = preg_match( '@\balt\s*=\s*(["\'])(.*?)\1@is', $tag, $m ) ? trim( html_entity_decode( $m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) : '';
			$this->image_alts[] = $alt;
		}
	}

	/**
	 * Whether a link points to this site.
	 *
	 * @param string $href Link target.
	 * @param string $host This site's host, e.g. "example.org".
	 */
	public static function is_internal( string $href, string $host ): bool {
		if ( '/' === $href[0] && ( ! isset( $href[1] ) || '/' !== $href[1] ) ) {
			return true; // Root-relative.
		}
		$link_host = (string) parse_url( $href, PHP_URL_HOST ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Pure PHP on purpose (unit-testable, no WordPress).
		if ( '' === $link_host ) {
			return ! preg_match( '@^[a-z][a-z0-9+.-]*:@i', $href ); // Relative path, not another scheme.
		}
		return self::bare_host( $link_host ) === self::bare_host( $host );
	}

	/**
	 * Lowercase host without a leading "www.".
	 *
	 * @param string $host Host.
	 */
	private static function bare_host( string $host ): string {
		$host = strtolower( $host );
		return 0 === strpos( $host, 'www.' ) ? substr( $host, 4 ) : $host;
	}

	/**
	 * HTML fragment to plain text with single spaces.
	 *
	 * @param string $html HTML.
	 */
	public static function plain( string $html ): string {
		// Block-level tags end words, so "</p><p>" must not glue words together.
		$html = (string) preg_replace( '@<(br|/p|/h[1-6]|/li|/div|/td|/th|/blockquote|/figcaption)\b[^>]*>@i', '$0 ', $html );
		$text = html_entity_decode( strip_tags( $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Script/style already removed; pure PHP on purpose.
		return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
	}

	/**
	 * Words in plain text (tokens containing a letter or digit).
	 *
	 * @param string $text Plain text.
	 * @return string[]
	 */
	public static function words( string $text ): array {
		$tokens = preg_split( '/\s+/u', trim( $text ), -1, PREG_SPLIT_NO_EMPTY );
		return array_values(
			array_filter(
				(array) $tokens,
				static function ( $token ): bool {
					return (bool) preg_match( '/[\p{L}\p{N}]/u', (string) $token );
				}
			)
		);
	}

	/**
	 * Number of words in plain text.
	 *
	 * @param string $text Plain text.
	 */
	public static function count_words( string $text ): int {
		return count( self::words( $text ) );
	}
}
