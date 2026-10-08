<?php
/**
 * %%variable%% template engine.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Meta;

defined( 'ABSPATH' ) || exit;

/**
 * Renders title/description templates such as "%%title%% %%separator%% %%site_name%%".
 *
 * Rules:
 * - Variables are replaced in ONE pass over the template. Values are never
 *   scanned again, so a post titled "%%site_name%%" stays literal.
 * - Unknown variables render as nothing.
 * - Values are plain text: entities decoded, tags stripped, whitespace collapsed.
 * - Separators are tracked with a control character during rendering so that
 *   repeated separators (caused by empty variables) collapse to one and
 *   separators at the start or end are removed — without touching separator
 *   characters that are genuinely part of a value.
 * - The result is plain text. Callers must escape it for their output context.
 */
final class TemplateEngine {

	/**
	 * Placeholder for %%separator%% while rendering (ASCII unit separator).
	 */
	private const MARK = "\x1F";

	/**
	 * Renders a template.
	 *
	 * @param string                                   $template  Template text.
	 * @param array<string, string|\Closure(): string> $values Values or lazy closures keyed by variable name.
	 * @param string                                   $separator Separator character(s).
	 */
	public function render( string $template, array $values, string $separator ): string {
		$output = preg_replace_callback(
			'/%%([a-z_]+)%%/',
			function ( array $found ) use ( $values ): string {
				$name = $found[1];
				if ( 'separator' === $name ) {
					return self::MARK;
				}
				if ( ! array_key_exists( $name, $values ) ) {
					return '';
				}
				$value = $values[ $name ];
				if ( $value instanceof \Closure ) {
					$value = $value();
				}
				return $this->clean( (string) $value );
			},
			$template
		);

		$output = (string) preg_replace( '/\s+/u', ' ', (string) $output );
		$output = (string) preg_replace( '/(?:\s*' . self::MARK . '\s*)+/u', ' ' . self::MARK . ' ', $output );
		$output = trim( $output, ' ' . self::MARK );

		return trim( str_replace( self::MARK, $separator, $output ) );
	}

	/**
	 * Turns a raw value into single-line plain text.
	 *
	 * @param string $value Raw value (may contain HTML or entities).
	 */
	private function clean( string $value ): string {
		$value = wp_strip_all_tags( html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		$value = str_replace( self::MARK, '', $value );
		return trim( (string) preg_replace( '/\s+/u', ' ', $value ) );
	}
}
