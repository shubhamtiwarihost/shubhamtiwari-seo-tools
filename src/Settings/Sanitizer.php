<?php
/**
 * Settings sanitizer.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Settings;

use DumpSEO\Helpers\Text;

defined( 'ABSPATH' ) || exit;

/**
 * Turns untrusted settings input into a clean settings array.
 *
 * - Unknown keys are dropped.
 * - An invalid value keeps the previously saved value and produces an error
 *   message, so a typo never silently wipes a setting.
 * - Checkboxes: a form only submits checked boxes, so a missing boolean means
 *   "off" — but only for sections that were actually on the submitted form
 *   (listed in the hidden `_sections` field). Programmatic updates without
 *   `_sections` only change the keys they contain.
 */
class Sanitizer {

	public const SECTIONS_KEY = '_sections';

	/**
	 * Settings schema.
	 *
	 * @var Schema
	 */
	private $schema;

	/**
	 * Error messages from the last sanitize() call, keyed by field key.
	 *
	 * @var array<string, string>
	 */
	private $errors = array();

	/**
	 * Constructor.
	 *
	 * @param Schema $schema Settings schema.
	 */
	public function __construct( Schema $schema ) {
		$this->schema = $schema;
	}

	/**
	 * Sanitizes input against the schema.
	 *
	 * @param mixed                $input    Raw input (normally $_POST data via the Settings API).
	 * @param array<string, mixed> $previous Currently saved settings with defaults applied.
	 * @return array<string, bool|string> Clean settings.
	 */
	public function sanitize( $input, array $previous ): array {
		$this->errors = array();
		$input        = is_array( $input ) ? $input : array();

		$submitted_sections = array();
		if ( isset( $input[ self::SECTIONS_KEY ] ) && is_string( $input[ self::SECTIONS_KEY ] ) ) {
			$submitted_sections = array_filter( explode( ',', $input[ self::SECTIONS_KEY ] ) );
		}

		$clean = array();
		foreach ( $this->schema->fields() as $key => $field ) {
			$old = array_key_exists( $key, $previous ) ? $previous[ $key ] : $field->default;

			if ( ! array_key_exists( $key, $input ) ) {
				$on_form       = in_array( $field->section, $submitted_sections, true );
				$clean[ $key ] = ( Field::TYPE_BOOL === $field->type && $on_form ) ? false : $old;
				continue;
			}

			$value = $this->sanitize_value( $field, $input[ $key ] );
			if ( null === $value ) {
				$clean[ $key ] = $old;
				/* translators: %s: settings field label. */
				$this->errors[ $key ] = sprintf( __( '“%s” was not saved because the value is not valid.', 'dumpseo' ), $field->label );
				continue;
			}
			$clean[ $key ] = $value;
		}

		return $clean;
	}

	/**
	 * Errors from the last sanitize() call.
	 *
	 * @return array<string, string>
	 */
	public function errors(): array {
		return $this->errors;
	}

	/**
	 * Sanitizes a single value. Returns null when the value is invalid.
	 *
	 * @param Field $field Field definition.
	 * @param mixed $value Raw value.
	 * @return bool|string|null
	 */
	private function sanitize_value( Field $field, $value ) {
		if ( Field::TYPE_BOOL === $field->type ) {
			return in_array( $value, array( true, 1, '1', 'on', 'yes', 'true' ), true );
		}

		if ( ! is_scalar( $value ) ) {
			return null;
		}
		$value = trim( (string) $value );

		switch ( $field->type ) {
			case Field::TYPE_ENUM:
				return array_key_exists( $value, $field->choices ) ? $value : null;

			case Field::TYPE_URL:
			case Field::TYPE_IMAGE_URL:
				return Text::http_url( $value );

			case Field::TYPE_TWITTER_HANDLE:
				$value = ltrim( $value, '@' );
				if ( '' === $value ) {
					return '';
				}
				return preg_match( '/^[A-Za-z0-9_]{1,15}$/', $value ) ? $value : null;

			case Field::TYPE_TEMPLATE:
			case Field::TYPE_TEXT:
			default:
				return Text::sanitize_line( $value );
		}
	}
}
